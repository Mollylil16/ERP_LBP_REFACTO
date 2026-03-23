import { lazy } from 'react';

/**
 * Lazy loading des pages pour code splitting
 */

// Pages principales
export const ColisListPage = lazy(() => import('../pages/admin/colis/GroupageListPage').then(m => ({ default: m.ColisGroupageListPage })));
export const ColisFormPage = lazy(() => import('../pages/admin/colis/AutresEnvoisListPage').then(m => ({ default: m.ColisAutresEnvoisListPage })));
export const ColisDetailsPage = lazy(() => import('../pages/admin/colis/GroupageListPage').then(m => ({ default: m.ColisGroupageListPage }))); // Fallback

export const FacturesListPage = lazy(() => import('../pages/admin/factures/FacturesListPage').then(m => ({ default: m.FacturesListPage })));
export const FactureDetailsPage = lazy(() => import('../pages/admin/factures/FacturePreviewPage').then(m => ({ default: m.FacturePreviewPage })));

export const PaiementsListPage = lazy(() => import('../pages/admin/paiements/PaiementsListPage').then(m => ({ default: m.PaiementsListPage })));
export const PaiementFormPage = lazy(() => import('../pages/admin/paiements/PaiementsListPage').then(m => ({ default: m.PaiementsListPage }))); // Fallback

export const ClientsListPage = lazy(() => import('../pages/admin/clients/ClientsListPage').then(m => ({ default: m.ClientsListPage })));
export const ClientFormPage = lazy(() => import('../pages/admin/clients/ClientsListPage').then(m => ({ default: m.ClientsListPage }))); // Fallback

export const CaissePage = lazy(() => import('../pages/admin/caisse/SuiviCaissePage').then(m => ({ default: m.SuiviCaissePage })));
export const RapportsPage = lazy(() => import('../pages/admin/colis/RapportsPage').then(m => ({ default: m.ColisRapportsPage })));
export const DashboardPage = lazy(() => import('../pages/admin/DashboardPage').then(m => ({ default: m.DashboardPage })));

// Pages admin
export const UsersPage = lazy(() => import('../pages/admin/users/UsersListPage').then(m => ({ default: m.UsersListPage })));
export const SettingsPage = lazy(() => import('../pages/admin/settings/SettingsPage').then(m => ({ default: m.SettingsPage })));
export const RentabilitePage = lazy(() => import('../pages/admin/statistiques/RentabiliteTarifPage'));
export const TarifManagementPage = lazy(() => import('../pages/admin/settings/TarifManagementPage'));
export const ColisMapView = lazy(() => import('../pages/admin/colis/ColisMapView').then(m => ({ default: m.ColisMapView })));
export const PublicPaymentPage = lazy(() => import('../pages/public/PublicPaymentPage').then(m => ({ default: m.PublicPaymentPage })));
export const WithdrawalTrackingPage = lazy(() => import('../pages/admin/caisse/WithdrawalTrackingPage'));

// ✅ Pages de flux Auth (1ère connexion)
export const ChangePasswordPage = lazy(() => import('../pages/auth/ChangePasswordPage').then(m => ({ default: m.ChangePasswordPage })));
export const SelectAgencyPage = lazy(() => import('../pages/auth/SelectAgencyPage').then(m => ({ default: m.SelectAgencyPage })));
export const AgencesManagementPage = lazy(() => import('../pages/admin/agences/AgencesManagementPage').then(m => ({ default: m.AgencesManagementPage })));
