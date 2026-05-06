import React, {
  createContext,
  useContext,
  useState,
  useEffect,
  useCallback,
  useRef,
} from "react";
import { useNavigate } from "react-router-dom";
import { User, LoginCredentials, AuthResponse } from "@types";
import { authService } from "@services/auth.service";
import toast from "react-hot-toast";
import { shouldSkipAgencySelection } from "@utils/agencyGate";
import { queryClient, idbPersister } from "@config/queryClient";

interface AuthContextType {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  permissions: string[];
  login: (credentials: LoginCredentials) => Promise<void>;
  logout: () => void;
  refreshUser: () => Promise<User | null>;
  getCurrency: () => string;
}

export const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({
  children,
}) => {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [permissions, setPermissions] = useState<string[]>([]);
  const navigate = useNavigate();
  const hasCheckedAuth = useRef(false);

  /**
   * Normalise la forme du user retourné par l’API.
   * Côté backend, `user.role` est souvent un enum string (ex: "ADMIN") alors que le front attend un objet `Role`.
   * On force donc `user.role` à devenir un objet { code, ... } pour éviter les crashs (ErrorBoundary/Oups).
   */
  const normalizeUser = useCallback((u: any): User => {
    if (!u) return u as User;

    const out: any = { ...u };

    // Normaliser l'agence: backend renvoie souvent `agence`, front attend `agency`
    if (!out.agency && out.agence) {
      out.agency = out.agence;
    }

    // Normaliser le rôle: front attend `role.code`
    const roleIsString = typeof out.role === "string";
    const roleEntityCode =
      out.roleEntity && typeof out.roleEntity === "object"
        ? out.roleEntity.code
        : undefined;

    if (roleIsString) {
      if (roleEntityCode) {
        // Le backend nous fournit déjà l'objet rôle dans roleEntity
        out.role = out.roleEntity;
      } else {
        // Fallback minimal
        out.role = {
          id: out.role_id ?? 0,
          code: out.role,
          name: out.role,
          description: "",
        };
      }
    }

    return out as User;
  }, []);

  /** Choisit une page d'atterrissage autorisée en fonction des permissions. */
  const pickLandingRoute = useCallback((perms: string[], roleCode?: string): string => {
    const has = (p: string) => perms.includes("*") || perms.includes(p);
    const hasAny = (ps: string[]) => perms.includes("*") || ps.some((p) => perms.includes(p));

    // Si permissions non encore disponibles, fallback par rôle (évite `/dashboard` → 403 sur certaines pages).
    if (perms.length === 0 && roleCode) {
      const rc = roleCode.toUpperCase()
      if (rc === "AGENT_GROUPAGE") return "/colis/groupage"
      if (rc === "AGENT_EXPLOITATION" || rc === "SUPERVISEUR_REGIONAL") return "/exploitation"
      if (rc === "CALL_CENTER") return "/callcenter/inbox"
      if (rc === "CAISSIER" || rc === "CAISSIER_AGENCE") return "/caisse/suivi"
      if (rc === "CHEF_AGENCE") return "/exploitation"
    }

    // Dashboard si autorisé
    if (has("dashboard.view") || has("dashboard.admin") || has("dashboard.caisse")) {
      return "/dashboard";
    }
    // Call center (relation client)
    if (has("callcenter.inbox")) return "/callcenter/inbox";
    // Colis
    if (has("colis.groupage.read") || has("colis.groupage.create") || has("colis.groupage.update")) {
      return "/colis/groupage";
    }
    if (has("colis.autres-envois.read") || has("colis.autres-envois.create") || has("colis.autres-envois.update")) {
      return "/colis/autres-envois";
    }
    // Litiges
    if (has("litiges.view") || has("litiges.create") || has("litiges.manage") || has("litiges.admin")) {
      return "/litiges";
    }
    // Factures / Paiements / Caisse
    if (has("factures.read")) return "/factures";
    if (has("paiements.read")) return "/paiements";
    if (hasAny(["caisse.view", "caisse.operations", "caisse.view-all"])) return "/caisse/suivi";

    // Par défaut, tenter dashboard (ProtectedRoute gérera)
    return "/dashboard";
  }, []);

  const hasGlobalAgencyAccess = useCallback((u: User | null, perms?: string[]) => {
    return shouldSkipAgencySelection(u, perms);
  }, []);

  // Vérifier si l'utilisateur est déjà connecté au chargement
  const checkAuth = useCallback(async () => {
    // Éviter de vérifier plusieurs fois
    if (hasCheckedAuth.current) {
      return;
    }

    try {
      const token =
        sessionStorage.getItem("lbp_token") ?? localStorage.getItem("lbp_token");
      if (token) {
        // Charger permissions immédiatement depuis le cache pour éviter tout délai post-login
        const cachedPerms =
          sessionStorage.getItem("lbp_permissions") ??
          localStorage.getItem("lbp_permissions");
        if (cachedPerms) {
          try {
            const parsed = JSON.parse(cachedPerms);
            if (Array.isArray(parsed)) setPermissions(parsed);
          } catch {
            // ignore
          }
        }

        // Vérifier la validité du token et récupérer l'utilisateur
        try {
          const userData = await authService.getCurrentUser();
          const normalized = normalizeUser(userData);
          // 🔒 Sécuriser le cache permissions : éviter les permissions "restées" d'un autre compte.
          const cachedUid =
            sessionStorage.getItem("lbp_permissions_user_id") ??
            localStorage.getItem("lbp_permissions_user_id");
          if (cachedUid && String(normalized?.id ?? "") !== String(cachedUid)) {
            sessionStorage.removeItem("lbp_permissions");
            localStorage.removeItem("lbp_permissions");
            sessionStorage.removeItem("lbp_permissions_user_id");
            localStorage.removeItem("lbp_permissions_user_id");
            setPermissions([]);
          }
          setUser(normalized);
        } catch (error: any) {
          // Si l'erreur est 401, le token est invalide
          if (error.response?.status === 401 || error.response?.status === 403) {
            console.warn("Token invalide, déconnexion...");
            localStorage.removeItem("lbp_token");
            localStorage.removeItem("lbp_refresh_token");
            localStorage.removeItem("lbp_mock_user");
            localStorage.removeItem("lbp_permissions");
            sessionStorage.removeItem("lbp_token");
            sessionStorage.removeItem("lbp_refresh_token");
            sessionStorage.removeItem("lbp_mock_user");
            sessionStorage.removeItem("lbp_permissions");
            setPermissions([]);
          } else {
            // Pour les autres erreurs (réseau, etc.), on garde le token et on réessayera plus tard
            console.warn("Erreur lors de la vérification du token:", error);
          }
        }
      }
    } catch (error) {
      console.error("Erreur lors de la vérification de l'authentification:", error);
    } finally {
      setIsLoading(false);
      hasCheckedAuth.current = true;
    }
  }, []);

  useEffect(() => {
    // Ne vérifier qu'une seule fois au chargement initial
    if (hasCheckedAuth.current) {
      return;
    }

    const token =
      sessionStorage.getItem("lbp_token") ?? localStorage.getItem("lbp_token");
    if (!token) {
      console.log('[AuthContext] No token, stopping loading');
      setIsLoading(false);
      hasCheckedAuth.current = true;
      return;
    }


    // Vérifier la validité du token et récupérer l'utilisateur
    console.log('[AuthContext] Checking auth with token');
    checkAuth();
  }, []); // Dépendances vides pour ne s'exécuter qu'une fois au montage

  const login = async (credentials: LoginCredentials) => {
    try {
      setIsLoading(true);
      const response: AuthResponse = await authService.login(credentials);

      // Sauvegarder le token
      sessionStorage.setItem("lbp_token", response.token);
      // Cleanup localStorage pour éviter le mélange entre onglets/comptes
      localStorage.removeItem("lbp_token");
      if (response.refresh_token) {
        sessionStorage.setItem("lbp_refresh_token", response.refresh_token);
        localStorage.removeItem("lbp_refresh_token");
      }

      // Sauvegarder les permissions
      sessionStorage.setItem(
        "lbp_permissions",
        JSON.stringify(response.permissions)
      );
      sessionStorage.setItem("lbp_permissions_user_id", String((response.user as any)?.id ?? ""));
      localStorage.removeItem("lbp_permissions");
      // Source unique en mémoire (instantané)
      setPermissions(Array.isArray(response.permissions) ? response.permissions : []);


      // Définir l'utilisateur directement depuis la réponse (pas besoin de vérifier à nouveau)
      console.log('[Auth] Login successful, setting user:', response.user);

      // Marquer comme vérifié AVANT de définir l'utilisateur pour éviter checkAuth
      hasCheckedAuth.current = true;

      // Définir l'utilisateur et arrêter le loading
      const normalizedUser = normalizeUser(response.user as any);
      setUser(normalizedUser ?? null);
      setIsLoading(false);

      if (normalizedUser) {
        toast.success(`Bienvenue ${normalizedUser.nom_complet || normalizedUser.username} !`);
      }

      // ✅ Logique de redirection selon les flags de 1ère connexion
      if (normalizedUser.must_change_password) {
        console.log('[Auth] Redirecting to change-password');
        navigate("/auth/change-password", { replace: true });
      } else if (
        !hasGlobalAgencyAccess(normalizedUser, response.permissions) &&
        !(normalizedUser.agency_id || normalizedUser.agency?.id)
      ) {
        console.log('[Auth] Redirecting to select-agency');
        navigate("/auth/select-agency", { replace: true });
      } else {
        const target = pickLandingRoute(
          Array.isArray(response.permissions) ? response.permissions : [],
          normalizedUser?.role?.code,
        );
        console.log('[Auth] Navigating to landing route:', target);
        navigate(target, { replace: true });
      }
    } catch (error: any) {
      const message =
        error.response?.data?.message || error.message || "Erreur de connexion";
      toast.error(message);
      throw error;
    } finally {
      setIsLoading(false);
    }
  };

  const logout = useCallback(() => {
    localStorage.removeItem("lbp_token");
    localStorage.removeItem("lbp_refresh_token");
    localStorage.removeItem("lbp_permissions");
    localStorage.removeItem("lbp_permissions_user_id");
    localStorage.removeItem("lbp_mock_user");
    sessionStorage.removeItem("lbp_token");
    sessionStorage.removeItem("lbp_refresh_token");
    sessionStorage.removeItem("lbp_permissions");
    sessionStorage.removeItem("lbp_permissions_user_id");
    sessionStorage.removeItem("lbp_mock_user");
    // Vider le cache React Query en mémoire ET en IndexedDB pour qu'un
    // nouvel utilisateur ne voie pas les données du compte précédent.
    queryClient.clear();
    void idbPersister.removeClient();
    setPermissions([]);
    setUser(null);
    hasCheckedAuth.current = false; // Réinitialiser pour permettre une nouvelle vérification
    navigate("/login");
    toast.success("Déconnexion réussie");
  }, [navigate]);

  const refreshUser = useCallback(async (): Promise<User | null> => {
    try {
      const userData = await authService.getCurrentUser();
      const u = normalizeUser(userData);
      setUser(u);
      return u;
    } catch (error) {
      console.error("Error refreshing user:", error);
      logout();
      return null;
    }
  }, [normalizeUser, logout]);

  const getCurrency = useCallback(() => {
    return user?.agency?.currency || "XOF";
  }, [user]);

  // Debug: log quand l'état change
  useEffect(() => {
    if (import.meta.env.DEV) {
      console.log('[AuthContext] State changed:', {
        hasUser: !!user,
        isAuthenticated: !!user,
        isLoading,
        username: user?.username
      })
    }
  }, [user, isLoading])

  const value: AuthContextType = {
    user,
    isAuthenticated: !!user,
    isLoading,
    permissions,
    login,
    logout,
    refreshUser,
    getCurrency,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};


