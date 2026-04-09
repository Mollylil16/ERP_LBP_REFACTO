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

interface AuthContextType {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (credentials: LoginCredentials) => Promise<void>;
  logout: () => void;
  refreshUser: () => Promise<void>;
  getCurrency: () => string;
}

export const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({
  children,
}) => {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const navigate = useNavigate();
  const hasCheckedAuth = useRef(false);

  const hasGlobalAgencyAccess = useCallback((u: User | null, perms?: string[]) => {
    if (!u) return false;
    const roleCode = u.role?.code;
    const allPermissions = Array.isArray(perms) ? perms.includes('*') : false;
    return Boolean(
      u.peut_voir_toutes_agences ||
        u.code_acces === 2 ||
        u.filter_mode === 'all' ||
        roleCode === 'DIRECTEUR' ||
        roleCode === 'ADMIN' ||
        allPermissions
    );
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

        // Vérifier la validité du token et récupérer l'utilisateur
        try {
          const userData = await authService.getCurrentUser();
          setUser(userData);
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
      localStorage.removeItem("lbp_permissions");


      // Définir l'utilisateur directement depuis la réponse (pas besoin de vérifier à nouveau)
      console.log('[Auth] Login successful, setting user:', response.user);

      // Marquer comme vérifié AVANT de définir l'utilisateur pour éviter checkAuth
      hasCheckedAuth.current = true;

      // Définir l'utilisateur et arrêter le loading
      setUser(response.user);
      setIsLoading(false);

      toast.success(`Bienvenue ${response.user.nom_complet || response.user.username} !`);

      // ✅ Logique de redirection selon les flags de 1ère connexion
      if (response.user.must_change_password) {
        console.log('[Auth] Redirecting to change-password');
        navigate("/auth/change-password", { replace: true });
      } else if (!response.user.agence_selected && !hasGlobalAgencyAccess(response.user, response.permissions)) {
        console.log('[Auth] Redirecting to select-agency');
        navigate("/auth/select-agency", { replace: true });
      } else {
        console.log('[Auth] Navigating to dashboard');
        navigate("/dashboard", { replace: true });
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

  const logout = () => {
    localStorage.removeItem("lbp_token");
    localStorage.removeItem("lbp_refresh_token");
    localStorage.removeItem("lbp_permissions");
    localStorage.removeItem("lbp_mock_user");
    sessionStorage.removeItem("lbp_token");
    sessionStorage.removeItem("lbp_refresh_token");
    sessionStorage.removeItem("lbp_permissions");
    sessionStorage.removeItem("lbp_mock_user");
    setUser(null);
    hasCheckedAuth.current = false; // Réinitialiser pour permettre une nouvelle vérification
    navigate("/login");
    toast.success("Déconnexion réussie");
  };

  const refreshUser = useCallback(async () => {
    try {
      const userData = await authService.getCurrentUser();
      setUser(userData);
    } catch (error) {
      console.error("Error refreshing user:", error);
      logout();
    }
  }, []);

  const getCurrency = useCallback(() => {
    return user?.agency?.currency || "XOF";
  }, [user]);

  // Debug: log quand l'état change
  useEffect(() => {
    if (process.env.NODE_ENV === 'development') {
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
    login,
    logout,
    refreshUser,
    getCurrency,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};


