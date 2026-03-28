import { UserRole } from '@types'

export type DashboardPersona =
  | 'direction'
  | 'manager'
  | 'caissier'
  | 'agent'
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
const AGENT = new Set<string>([
  UserRole.AGENT_EXPLOITATION,
  UserRole.AGENT_GROUPAGE,
])

/**
 * Persona d’accueil pour adapter titres, raccourcis et densité du tableau de bord (P3).
 */
export function resolveDashboardPersona(
  roleCode: string | undefined,
): DashboardPersona {
  if (!roleCode) return 'default'
  if (DIRECTION.has(roleCode)) return 'direction'
  if (MANAGER.has(roleCode)) return 'manager'
  if (CAISSIER.has(roleCode)) return 'caissier'
  if (AGENT.has(roleCode)) return 'agent'
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
  caissier: {
    title: 'Caisse du jour',
    subtitle: 'Sessions, encaissements et suivi rapide de votre point de vente',
  },
  agent: {
    title: 'Votre activité colis',
    subtitle: 'Accès rapide aux flux groupage, expéditions et traitements',
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
