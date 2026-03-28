/**
 * Permissions requises par zone de l’app — source unique pour routes (ProtectedRoute) et menu.
 */
import { PERMISSIONS } from './permissions'

export const COLIS_READ_ANY = [
  PERMISSIONS.COLIS_GROUPAGE.READ,
  PERMISSIONS.COLIS_AUTRES_ENVOIS.READ,
] as const

export const ROUTE_ACCESS = {
  dashboard: PERMISSIONS.DASHBOARD.VIEW,
  colisGroupage: PERMISSIONS.COLIS_GROUPAGE.READ,
  colisAutresEnvois: PERMISSIONS.COLIS_AUTRES_ENVOIS.READ,
  colisMap: COLIS_READ_ANY,
  colisRapports: PERMISSIONS.RAPPORTS.VIEW,
  expeditions: COLIS_READ_ANY,
  clients: PERMISSIONS.CLIENTS.READ,
  factures: PERMISSIONS.FACTURES.READ,
  paiements: PERMISSIONS.PAIEMENTS.READ,
  caisse: PERMISSIONS.CAISSE.VIEW,
  statistiques: PERMISSIONS.RAPPORTS.VIEW,
  /** Paramètres > Général (société) */
  settings: PERMISSIONS.CONFIG.VIEW,
  /** Grilles tarifaires : nécessite droit de mise à jour config */
  settingsTarifs: PERMISSIONS.CONFIG.UPDATE,
  /** Gestion des agences (CRUD UI) */
  settingsAgences: PERMISSIONS.CONFIG.UPDATE,
  /** Liste agences (API) — souvent couvert par structures.agences.read → agences.read */
  agencesList: PERMISSIONS.AGENCES.READ,
  users: PERMISSIONS.USERS.READ,
  litiges: PERMISSIONS.LITIGES.VIEW,
  callcenterInbox: PERMISSIONS.CALLCENTER.INBOX,
} as const
