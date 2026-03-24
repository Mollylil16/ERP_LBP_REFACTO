/**
 * Configuration des routes avec lazy loading
 * Permet de réduire le bundle initial de 30-40%
 */

import React, { lazy } from 'react'
import { Routes, Route, Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '@hooks/useAuth'
import { ProtectedRoute } from '../components/common/ProtectedRoute'
import { MainLayout } from '../components/layout/MainLayout'
import { PublicLayout } from '../components/layout/PublicLayout'
import { LazyPageLoader } from '../components/common/LazyPageLoader'

// Layouts - Chargés immédiatement (nécessaires partout)
// MainLayout et PublicLayout sont déjà importés statiquement

// Pages - Public (lazy loaded)
const LoginPage = lazy(() => import('../pages/public/LoginPage').then(m => ({ default: m.LoginPage })))
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

// Pages - Factures (lazy loaded)
const FacturesListPage = lazy(() => import('../pages/admin/factures/FacturesListPage').then(m => ({ default: m.FacturesListPage })))
const FacturePreviewPage = lazy(() => import('../pages/admin/factures/FacturePreviewPage').then(m => ({ default: m.FacturePreviewPage })))

// Pages - Paiements (lazy loaded)
const PaiementsListPage = lazy(() => import('../pages/admin/paiements/PaiementsListPage').then(m => ({ default: m.PaiementsListPage })))

// Pages - Settings (lazy loaded)
const SettingsPage = lazy(() => import('../pages/admin/settings/SettingsPage').then(m => ({ default: m.SettingsPage })))

// Pages - Users (lazy loaded)
const UsersListPage = lazy(() => import('../pages/admin/users/UsersListPage').then(m => ({ default: m.UsersListPage })))

// Pages - Caisse (lazy loaded)
const SuiviCaissePage = lazy(() => import('../pages/admin/caisse/SuiviCaissePage').then(m => ({ default: m.SuiviCaissePage })))
const WithdrawalTrackingPage = lazy(() => import('../pages/admin/caisse/WithdrawalTrackingPage'))

// Pages - Statistiques (lazy loaded)
const StatistiquesHistoriquesPage = lazy(() => import('../pages/admin/statistiques/StatistiquesHistoriquesPage').then(m => ({ default: m.StatistiquesHistoriquesPage })))
const RentabiliteTarifPage = lazy(() => import('../pages/admin/statistiques/RentabiliteTarifPage'))
const TarifManagementPage = lazy(() => import('../pages/admin/settings/TarifManagementPage'))

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
    const storedPermissions = JSON.parse(localStorage.getItem('lbp_permissions') || '[]') as string[]
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
          element={<Navigate to={isAuthenticated ? '/dashboard' : '/login'} replace />}
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
            <MainLayout />
          </ProtectedRoute>
        }
      >
        {/* Dashboard */}
        <Route
          path="dashboard"
          element={
            <LazyPageLoader>
              <DashboardPage />
            </LazyPageLoader>
          }
        />

        {/* Colis */}
        <Route path="colis">
          <Route
            path="groupage"
            element={
              <LazyPageLoader>
                <ColisGroupageListPage />
              </LazyPageLoader>
            }
          />
          <Route
            path="autres-envois"
            element={
              <LazyPageLoader>
                <ColisAutresEnvoisListPage />
              </LazyPageLoader>
            }
          />
          <Route
            path="rapports"
            element={
              <LazyPageLoader>
                <ColisRapportsPage />
              </LazyPageLoader>
            }
          />
          <Route
            path="map"
            element={
              <LazyPageLoader>
                <ColisMapView />
              </LazyPageLoader>
            }
          />
        </Route>

        {/* Expéditions / Manifestes */}
        <Route
          path="expeditions"
          element={
            <LazyPageLoader>
              <ExpeditionsPage />
            </LazyPageLoader>
          }
        />

        {/* Clients */}
        <Route
          path="clients"
          element={
            <LazyPageLoader>
              <ClientsListPage />
            </LazyPageLoader>
          }
        />

        {/* Factures */}
        <Route path="factures">
          <Route
            index
            element={
              <LazyPageLoader>
                <FacturesListPage />
              </LazyPageLoader>
            }
          />
          <Route
            path=":id/preview"
            element={
              <LazyPageLoader>
                <FacturePreviewPage />
              </LazyPageLoader>
            }
          />
        </Route>

        {/* Paiements */}
        <Route
          path="paiements"
          element={
            <LazyPageLoader>
              <PaiementsListPage />
            </LazyPageLoader>
          }
        />

        {/* Caisse */}
        <Route
          path="caisse/suivi"
          element={
            <LazyPageLoader>
              <SuiviCaissePage />
            </LazyPageLoader>
          }
        />
        <Route
          path="caisse/retraits"
          element={
            <LazyPageLoader>
              <WithdrawalTrackingPage />
            </LazyPageLoader>
          }
        />

        {/* Settings */}
        <Route
          path="settings"
          element={
            <LazyPageLoader>
              <SettingsPage />
            </LazyPageLoader>
          }
        />

        {/* Statistiques Historiques */}
        <Route
          path="statistiques/historiques"
          element={
            <LazyPageLoader>
              <StatistiquesHistoriquesPage />
            </LazyPageLoader>
          }
        />
        <Route
          path="statistiques/rentabilite"
          element={
            <LazyPageLoader>
              <RentabiliteTarifPage />
            </LazyPageLoader>
          }
        />
        <Route
          path="settings/tarifs"
          element={
            <LazyPageLoader>
              <TarifManagementPage />
            </LazyPageLoader>
          }
        />
        <Route
          path="settings/agences"
          element={
            <LazyPageLoader>
              <AgencesManagementPage />
            </LazyPageLoader>
          }
        />

        {/* Users */}
        <Route
          path="users"
          element={
            <LazyPageLoader>
              <UsersListPage />
            </LazyPageLoader>
          }
        />

        {/* Redirect 404 */}
        <Route path="*" element={<Navigate to="/dashboard" replace />} />
      </Route>
    </Routes>
  )
}
