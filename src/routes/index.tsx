/**
 * Configuration des routes avec lazy loading
 * Permet de réduire le bundle initial de 30-40%
 */

import React, { lazy } from 'react'
import { Routes, Route, Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '@hooks/useAuth'
import { ProtectedRoute } from '../components/common/ProtectedRoute'
import { ROUTE_ACCESS } from '@constants/routeAccess'
import { MainLayout } from '../components/layout/MainLayout'
import { OnboardingTourProvider } from '../components/onboarding/AppOnboardingTour'
import { PublicLayout } from '../components/layout/PublicLayout'
import { LazyPageLoader } from '../components/common/LazyPageLoader'

function getStoredPermissions(): string[] {
  try {
    const raw =
      sessionStorage.getItem('lbp_permissions') ?? localStorage.getItem('lbp_permissions')
    if (!raw) return []
    const parsed = JSON.parse(raw)
    return Array.isArray(parsed) ? parsed : []
  } catch {
    return []
  }
}

function pickLandingRoute(perms: string[], roleCode?: string): string {
  const has = (p: string) => perms.includes('*') || perms.includes(p)
  const hasAny = (ps: string[]) => perms.includes('*') || ps.some((p) => perms.includes(p))

  // Si permissions non encore chargées (ex: refresh, cache vidé),
  // on applique un fallback basé sur le rôle pour éviter `/dashboard` → 403.
  if (perms.length === 0 && roleCode) {
    const rc = roleCode.toUpperCase()
    if (rc === 'AGENT_GROUPAGE') return '/colis/groupage'
    if (rc === 'AGENT_EXPLOITATION' || rc === 'SUPERVISEUR_REGIONAL') return '/exploitation'
    if (rc === 'CALL_CENTER') return '/callcenter/inbox'
    if (rc === 'CAISSIER' || rc === 'CAISSIER_AGENCE') return '/caisse/suivi'
  }

  if (has(ROUTE_ACCESS.dashboard)) return '/dashboard'
  if (has(ROUTE_ACCESS.callcenterInbox)) return '/callcenter/inbox'
  if (has(ROUTE_ACCESS.colisGroupage)) return '/colis/groupage'
  if (has(ROUTE_ACCESS.colisAutresEnvois)) return '/colis/autres-envois'
  if (has(ROUTE_ACCESS.litiges)) return '/litiges'
  if (has(ROUTE_ACCESS.factures)) return '/factures'
  if (has(ROUTE_ACCESS.paiements)) return '/paiements'
  if (hasAny(Array.isArray(ROUTE_ACCESS.caisse) ? ROUTE_ACCESS.caisse : [ROUTE_ACCESS.caisse])) {
    return '/caisse/suivi'
  }
  return '/dashboard'
}

// Layouts - Chargés immédiatement (nécessaires partout)
// MainLayout et PublicLayout sont déjà importés statiquement

// Pages - Public (lazy loaded)
const LoginPage = lazy(() => import('../pages/public/LoginPage').then(m => ({ default: m.LoginPage })))
const ForbiddenPage = lazy(() => import('../pages/public/ForbiddenPage').then(m => ({ default: m.ForbiddenPage })))
const TrackPage = lazy(() => import('../pages/public/TrackPage').then(m => ({ default: m.TrackPage })))
const PublicPaymentPage = lazy(() => import('../pages/public/PublicPaymentPage').then(m => ({ default: m.PublicPaymentPage })))
const InvoicePublicPaymentPage = lazy(() => import('../pages/public/InvoicePublicPaymentPage').then(m => ({ default: m.InvoicePublicPaymentPage })))

// Pages - Admin (lazy loaded)
const DashboardPage = lazy(() => import('../pages/admin/DashboardPage').then(m => ({ default: m.DashboardPage })))

// Pages - Colis (lazy loaded)
const ColisGroupageListPage = lazy(() => import('../pages/admin/colis/GroupageListPage').then(m => ({ default: m.ColisGroupageListPage })))
const ColisAutresEnvoisListPage = lazy(() => import('../pages/admin/colis/AutresEnvoisListPage').then(m => ({ default: m.ColisAutresEnvoisListPage })))
const ColisRapportsPage = lazy(() => import('../pages/admin/colis/RapportsPage').then(m => ({ default: m.ColisRapportsPage })))
const ColisMapView = lazy(() => import('../pages/admin/colis/ColisMapView').then(m => ({ default: m.ColisMapView })))
const ExpeditionsPage = lazy(() => import('../pages/expeditions/ExpeditionsPage').then(m => ({ default: m.ExpeditionsPage })))

// Pages - Clients (lazy loaded)
const ClientsListPage = lazy(() => import('../pages/admin/clients/ClientsListPage').then(m => ({ default: m.ClientsListPage })))

// Pages - Litiges & Call center (lazy loaded)
const LitigesListPage = lazy(() => import('../pages/admin/litiges/LitigesListPage').then(m => ({ default: m.LitigesListPage })))
const LitigeDetailPage = lazy(() => import('../pages/admin/litiges/LitigeDetailPage').then(m => ({ default: m.LitigeDetailPage })))
const CallCenterInboxPage = lazy(() =>
  import('../pages/admin/callcenter/CallCenterInboxPage').then(m => ({ default: m.CallCenterInboxPage })),
)
const CallCenterConversationPage = lazy(() =>
  import('../pages/admin/callcenter/CallCenterConversationPage').then(m => ({ default: m.CallCenterConversationPage })),
)

const ExploitationDashboardPage = lazy(() =>
  import('../pages/admin/exploitation/ExploitationDashboardPage').then(m => ({ default: m.ExploitationDashboardPage })),
)
const ExploitationCreditsPage = lazy(() =>
  import('../pages/admin/exploitation/ExploitationCreditsPage').then(m => ({ default: m.ExploitationCreditsPage })),
)
const ExploitationPointsJournaliersPage = lazy(() =>
  import('../pages/admin/exploitation/ExploitationPointsJournaliersPage').then(m => ({
    default: m.ExploitationPointsJournaliersPage,
  })),
)
const AgencyCreditsRecapPage = lazy(() =>
  import('../pages/admin/exploitation/AgencyCreditsRecapPage').then(m => ({ default: m.AgencyCreditsRecapPage })),
)
const AgencyPointJournalierNouveauPage = lazy(() =>
  import('../pages/admin/exploitation/AgencyPointJournalierNouveauPage').then(m => ({
    default: m.AgencyPointJournalierNouveauPage,
  })),
)
const ExploitationFournituresPage = lazy(() =>
  import('../pages/admin/exploitation/ExploitationFournituresPage').then(m => ({
    default: m.ExploitationFournituresPage,
  })),
)
const PrestatairesFacturesPage = lazy(() =>
  import('../pages/admin/exploitation/PrestatairesFacturesPage').then(m => ({
    default: m.PrestatairesFacturesPage,
  })),
)
const PrestatairesRetraitsHubPage = lazy(() =>
  import('../pages/admin/exploitation/PrestatairesRetraitsHubPage').then(m => ({
    default: m.PrestatairesRetraitsHubPage,
  })),
)
const AgencyFournituresDemandePage = lazy(() =>
  import('../pages/admin/exploitation/AgencyFournituresDemandePage').then(m => ({
    default: m.AgencyFournituresDemandePage,
  })),
)

// Pages - Factures (lazy loaded)
const FacturesListPage = lazy(() => import('../pages/admin/factures/FacturesListPage').then(m => ({ default: m.FacturesListPage })))
const FacturePreviewPage = lazy(() => import('../pages/admin/factures/FacturePreviewPage').then(m => ({ default: m.FacturePreviewPage })))

// Pages - Paiements (lazy loaded)
const PaiementsListPage = lazy(() => import('../pages/admin/paiements/PaiementsListPage').then(m => ({ default: m.PaiementsListPage })))

// Pages - Settings (lazy loaded)
const SettingsPage = lazy(() => import('../pages/admin/settings/SettingsPage').then(m => ({ default: m.SettingsPage })))
const ProfilePage = lazy(() => import('../pages/admin/account/ProfilePage').then(m => ({ default: m.ProfilePage })))

// Pages - Users (lazy loaded)
const UsersListPage = lazy(() => import('../pages/admin/users/UsersListPage').then(m => ({ default: m.UsersListPage })))

// Pages - Caisse (lazy loaded)
const SuiviCaissePage = lazy(() => import('../pages/admin/caisse/SuiviCaissePage').then(m => ({ default: m.SuiviCaissePage })))
const WithdrawalTrackingPage = lazy(() => import('../pages/admin/caisse/WithdrawalTrackingPage'))

// Pages - Statistiques (lazy loaded)
const StatistiquesHistoriquesPage = lazy(() => import('../pages/admin/statistiques/StatistiquesHistoriquesPage').then(m => ({ default: m.StatistiquesHistoriquesPage })))
const RentabiliteTarifPage = lazy(() => import('../pages/admin/statistiques/RentabiliteTarifPage'))
const TarifManagementPage = lazy(() => import('../pages/admin/settings/TarifManagementPage'))
const CatalogueProduitsPage = lazy(() =>
  import('../pages/admin/settings/CatalogueProduitsPage').then((m) => ({
    default: m.CatalogueProduitsPage,
  })),
)
const HistoriqueProduitsPage = lazy(() =>
  import('../pages/admin/produits/HistoriqueProduitsPage').then((m) => ({
    default: m.HistoriqueProduitsPage,
  })),
)

// ✅ Flux Auth & Agences
import { ChangePasswordPage, SelectAgencyPage, AgencesManagementPage } from './lazyPages'

/**
 * Configuration des routes de l'application
 */
export const AppRoutes: React.FC = () => {
  const { isAuthenticated, user } = useAuth()
  const location = useLocation()

  const hasGlobalAgencyAccess = (() => {
    if (!user) return false
    const storedPermissions = getStoredPermissions()
    const allPermissions = Array.isArray(storedPermissions) && storedPermissions.includes('*')
    return Boolean(
      user.peut_voir_toutes_agences ||
      user.filter_mode === 'all' ||
      user.role?.code === 'DIRECTEUR' ||
      user.role?.code === 'ADMIN' ||
      allPermissions
    )
  })()

  // ✅ Redirection forcée pour le flux de première connexion
  if (isAuthenticated && user) {
    // 1. Forcer le changement de mot de passe si le flag est à true
    if (user.must_change_password && location.pathname !== '/auth/change-password') {
      return <Navigate to="/auth/change-password" replace />
    }
    // 2. Forcer la sélection d'agence si elle n'est pas encore faite (et mdp déjà changé)
    if (
      !user.must_change_password &&
      !user.agence_selected &&
      !hasGlobalAgencyAccess &&
      location.pathname !== '/auth/select-agency'
    ) {
      return <Navigate to="/auth/select-agency" replace />
    }
  }

  return (
    <Routes>
      {/* Routes publiques */}
      <Route path="/" element={<PublicLayout />}>
        <Route
          index
          element={
            <Navigate
              to={
                isAuthenticated
                  ? pickLandingRoute(getStoredPermissions(), user?.role?.code)
                  : '/login'
              }
              replace
            />
          }
        />
        <Route
          path="track/:ref?"
          element={
            <LazyPageLoader>
              <TrackPage />
            </LazyPageLoader>
          }
        />
        <Route
          path="pay/:token"
          element={
            <LazyPageLoader>
              <PublicPaymentPage />
            </LazyPageLoader>
          }
        />
        <Route
          path="invoice/:id/pay"
          element={
            <LazyPageLoader>
              <InvoicePublicPaymentPage />
            </LazyPageLoader>
          }
        />
        <Route
          path="login"
          element={
            <LazyPageLoader>
              <LoginPage />
            </LazyPageLoader>
          }
        />
        <Route
          path="forbidden"
          element={
            <LazyPageLoader>
              <ForbiddenPage />
            </LazyPageLoader>
          }
        />
        {/* Flux Auth (Accessibles même sans layout principal) */}
        <Route
          path="auth/change-password"
          element={
            <LazyPageLoader>
              <ChangePasswordPage />
            </LazyPageLoader>
          }
        />
        <Route
          path="auth/select-agency"
          element={
            <LazyPageLoader>
              <SelectAgencyPage />
            </LazyPageLoader>
          }
        />
      </Route>

      {/* Routes protégées - Admin */}
      <Route
        path="/"
        element={
          <ProtectedRoute>
            <OnboardingTourProvider>
              <MainLayout />
            </OnboardingTourProvider>
          </ProtectedRoute>
        }
      >
        {/* Dashboard */}
        <Route
          path="dashboard"
          element={
            <ProtectedRoute requiredPermission={ROUTE_ACCESS.dashboard}>
              <LazyPageLoader>
                <DashboardPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />

        {/* Profil utilisateur (tous rôles authentifiés) */}
        <Route
          path="profile"
          element={
            <ProtectedRoute>
              <LazyPageLoader>
                <ProfilePage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />

        {/* Colis */}
        <Route path="colis">
          <Route
            path="groupage"
            element={
              <ProtectedRoute requiredPermission={ROUTE_ACCESS.colisGroupage}>
                <LazyPageLoader>
                  <ColisGroupageListPage />
                </LazyPageLoader>
              </ProtectedRoute>
            }
          />
          <Route
            path="autres-envois"
            element={
              <ProtectedRoute requiredPermission={ROUTE_ACCESS.colisAutresEnvois}>
                <LazyPageLoader>
                  <ColisAutresEnvoisListPage />
                </LazyPageLoader>
              </ProtectedRoute>
            }
          />
          <Route
            path="rapports"
            element={
              <ProtectedRoute requiredPermission={ROUTE_ACCESS.colisRapports}>
                <LazyPageLoader>
                  <ColisRapportsPage />
                </LazyPageLoader>
              </ProtectedRoute>
            }
          />
          <Route
            path="map"
            element={
              <ProtectedRoute requiredPermission={[...ROUTE_ACCESS.colisMap]}>
                <LazyPageLoader>
                  <ColisMapView />
                </LazyPageLoader>
              </ProtectedRoute>
            }
          />
        </Route>

        {/* Expéditions / Manifestes */}
        <Route
          path="expeditions"
          element={
            <ProtectedRoute requiredPermission={[...ROUTE_ACCESS.expeditions]}>
              <LazyPageLoader>
                <ExpeditionsPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />

        {/* Clients */}
        <Route
          path="clients"
          element={
            <ProtectedRoute requiredPermission={ROUTE_ACCESS.clients}>
              <LazyPageLoader>
                <ClientsListPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />

        {/* Litiges */}
        <Route
          path="litiges/:id"
          element={
            <ProtectedRoute requiredPermission={ROUTE_ACCESS.litiges}>
              <LazyPageLoader>
                <LitigeDetailPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />
        <Route
          path="litiges"
          element={
            <ProtectedRoute requiredPermission={ROUTE_ACCESS.litiges}>
              <LazyPageLoader>
                <LitigesListPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />

        {/* Call center — boîte de réception */}
        <Route
          path="callcenter/inbox/:conversationId"
          element={
            <ProtectedRoute requiredPermission={ROUTE_ACCESS.callcenterInbox}>
              <LazyPageLoader>
                <CallCenterConversationPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />
        <Route
          path="callcenter/inbox"
          element={
            <ProtectedRoute requiredPermission={ROUTE_ACCESS.callcenterInbox}>
              <LazyPageLoader>
                <CallCenterInboxPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />

        {/* Agent exploitation — crédits & points journaliers */}
        <Route
          path="exploitation"
          element={
            <ProtectedRoute requiredPermission={[...ROUTE_ACCESS.exploitationDashboard]}>
              <LazyPageLoader>
                <ExploitationDashboardPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />
        <Route
          path="exploitation/credits"
          element={
            <ProtectedRoute requiredPermission={[...ROUTE_ACCESS.exploitationCredits]}>
              <LazyPageLoader>
                <ExploitationCreditsPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />
        <Route
          path="exploitation/points-journaliers"
          element={
            <ProtectedRoute
              requiredPermission={[...ROUTE_ACCESS.exploitationPointsJournaliers]}
            >
              <LazyPageLoader>
                <ExploitationPointsJournaliersPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />
        <Route
          path="exploitation/fournitures"
          element={
            <ProtectedRoute requiredPermission={[...ROUTE_ACCESS.exploitationFournitures]}>
              <LazyPageLoader>
                <ExploitationFournituresPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />
        <Route
          path="agence/credits-recap"
          element={
            <ProtectedRoute requiredPermission={ROUTE_ACCESS.agenceCreditsRecap}>
              <LazyPageLoader>
                <AgencyCreditsRecapPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />
        <Route
          path="agence/point-journalier/nouveau"
          element={
            <ProtectedRoute requiredPermission={[...ROUTE_ACCESS.agencePointJournalier]}>
              <LazyPageLoader>
                <AgencyPointJournalierNouveauPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />
        <Route
          path="agence/fournitures/demande"
          element={
            <ProtectedRoute requiredPermission={[...ROUTE_ACCESS.agenceFournituresDemande]}>
              <LazyPageLoader>
                <AgencyFournituresDemandePage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />

        {/* Prestataires — factures / règlements / retraits hub */}
        <Route
          path="prestataires/factures"
          element={
            <ProtectedRoute requiredPermission={[...ROUTE_ACCESS.exploitationPrestataires]}>
              <LazyPageLoader>
                <PrestatairesFacturesPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />
        <Route
          path="prestataires/retraits-hub"
          element={
            <ProtectedRoute requiredPermission={[...ROUTE_ACCESS.exploitationPrestataires]}>
              <LazyPageLoader>
                <PrestatairesRetraitsHubPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />

        {/* Factures */}
        <Route path="factures">
          <Route
            index
            element={
              <ProtectedRoute requiredPermission={ROUTE_ACCESS.factures}>
                <LazyPageLoader>
                  <FacturesListPage />
                </LazyPageLoader>
              </ProtectedRoute>
            }
          />
          <Route
            path=":id/preview"
            element={
              <ProtectedRoute requiredPermission={ROUTE_ACCESS.factures}>
                <LazyPageLoader>
                  <FacturePreviewPage />
                </LazyPageLoader>
              </ProtectedRoute>
            }
          />
        </Route>

        {/* Paiements */}
        <Route
          path="paiements"
          element={
            <ProtectedRoute requiredPermission={ROUTE_ACCESS.paiements}>
              <LazyPageLoader>
                <PaiementsListPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />

        {/* Caisse */}
        <Route
          path="caisse/suivi"
          element={
            <ProtectedRoute requiredPermission={ROUTE_ACCESS.caisse}>
              <LazyPageLoader>
                <SuiviCaissePage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />
        <Route
          path="caisse/retraits"
          element={
            <ProtectedRoute requiredPermission={ROUTE_ACCESS.caisse}>
              <LazyPageLoader>
                <WithdrawalTrackingPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />

        {/* Settings */}
        <Route
          path="settings"
          element={
            <ProtectedRoute requiredPermission={ROUTE_ACCESS.settings}>
              <LazyPageLoader>
                <SettingsPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />

        {/* Statistiques Historiques */}
        <Route
          path="statistiques/historiques"
          element={
            <ProtectedRoute requiredPermission={ROUTE_ACCESS.statistiques}>
              <LazyPageLoader>
                <StatistiquesHistoriquesPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />
        <Route
          path="statistiques/rentabilite"
          element={
            <ProtectedRoute requiredPermission={ROUTE_ACCESS.statistiques}>
              <LazyPageLoader>
                <RentabiliteTarifPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />
        <Route
          path="settings/tarifs"
          element={
            <ProtectedRoute requiredPermission={ROUTE_ACCESS.settingsTarifs}>
              <LazyPageLoader>
                <TarifManagementPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />
        <Route
          path="settings/agences"
          element={
            <ProtectedRoute requiredPermission={ROUTE_ACCESS.settingsAgences}>
              <LazyPageLoader>
                <AgencesManagementPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />
        <Route
          path="settings/catalogue-produits"
          element={
            <ProtectedRoute requiredPermission={ROUTE_ACCESS.settingsCatalogueProduits}>
              <LazyPageLoader>
                <CatalogueProduitsPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />
        <Route
          path="settings/produits-historique"
          element={
            <ProtectedRoute requiredPermission={ROUTE_ACCESS.settingsProduitsHistorique}>
              <LazyPageLoader>
                <HistoriqueProduitsPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />

        {/* Users */}
        <Route
          path="users"
          element={
            <ProtectedRoute requiredPermission={ROUTE_ACCESS.users}>
              <LazyPageLoader>
                <UsersListPage />
              </LazyPageLoader>
            </ProtectedRoute>
          }
        />

        {/* Redirect 404 */}
        <Route
          path="*"
          element={
            <Navigate to={pickLandingRoute(getStoredPermissions(), user?.role?.code)} replace />
          }
        />
      </Route>
    </Routes>
  )
}
