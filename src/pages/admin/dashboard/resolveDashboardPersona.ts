import { UserRole } from '@types'

export type DashboardPersona =
  | 'direction'
  | 'manager'
  | 'chef_agence'
  | 'caissier'
  | 'agent_groupage'
  | 'agent_exploitation'
  | 'callcenter'
  | 'suivi'
  | 'default'

const DIRECTION = new Set<string>([UserRole.DIRECTEUR, UserRole.ADMIN])
const MANAGER = new Set<string>([
  UserRole.MANAGER,
  UserRole.SUPERVISEUR_REGIONAL,
])
const CAISSIER = new Set<string>([
  UserRole.CAISSIER,
  UserRole.CAISSIER_GROUPAGE,
])
const CHEF_AGENCE = new Set<string>([UserRole.CHEF_AGENCE])

/**
 * Persona d’accueil pour adapter titres, raccourcis et densité du tableau de bord (P3).
 */
export function resolveDashboardPersona(
  roleCode: string | undefined,
): DashboardPersona {
  if (!roleCode) return 'default'
  if (DIRECTION.has(roleCode)) return 'direction'
  if (MANAGER.has(roleCode)) return 'manager'
  if (CHEF_AGENCE.has(roleCode)) return 'chef_agence'
  if (CAISSIER.has(roleCode)) return 'caissier'
  if (roleCode === UserRole.AGENT_GROUPAGE) return 'agent_groupage'
  if (roleCode === UserRole.AGENT_EXPLOITATION) return 'agent_exploitation'
  if (roleCode === UserRole.CALL_CENTER) return 'callcenter'
  if (roleCode === UserRole.AGENT_SUIVI) return 'suivi'
  return 'default'
}

export const DASHBOARD_PERSONA_COPY: Record<
  DashboardPersona,
  { title: string; subtitle: string }
> = {
  direction: {
    title: 'Tableau de bord',
    subtitle: 'Vue stratégique — volumes, agences et indicateurs globaux',
  },
  manager: {
    title: 'Espace manager',
    subtitle: 'Suivi opérationnel, validations et performance des équipes',
  },
  chef_agence: {
    title: "Espace chef d'agence",
    subtitle: 'Pilotage quotidien de votre agence : points, caisse et suivi',
  },
  caissier: {
    title: 'Caisse du jour',
    subtitle: 'Sessions, encaissements et suivi rapide de votre point de vente',
  },
  agent_groupage: {
    title: 'Espace groupage',
    subtitle: 'Création, traitement et suivi des colis groupage',
  },
  agent_exploitation: {
    title: 'Espace exploitation',
    subtitle: "Création, suivi des envois et opérations d'agence",
  },
  callcenter: {
    title: 'Call center',
    subtitle: 'Conversations SMS/WhatsApp et relation client',
  },
  suivi: {
    title: 'Suivi & relation client',
    subtitle: 'Litiges, messagerie et suivi des envois',
  },
  default: {
    title: 'Tableau de bord',
    subtitle: "Vue d'ensemble de votre activité",
  },
}
