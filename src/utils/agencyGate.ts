import type { User } from '@types'

/**
 * Profils autorisés à accéder à l’app sans passer par l’écran « choix d’agence »
 * (siège, multi-agences, super-admin).
 *
 * Important : ne pas se baser sur `filter_mode === 'all'` (souvent vrai pour tout le monde)
 * ni sur `code_acces === 1` (valeur par défaut à la création utilisateur).
 */
export function shouldSkipAgencySelection(user: User | null, permissions?: string[]): boolean {
  if (!user) return false
  const allPerms = Array.isArray(permissions) && permissions.includes('*')
  const rc = user.role?.code
  return Boolean(
    user.peut_voir_toutes_agences ||
      user.code_acces === 2 ||
      rc === 'DIRECTEUR' ||
      rc === 'ADMIN' ||
      rc === 'ASSISTANT_DG' ||
      rc === 'SUPERVISEURE_GENERALE' ||
      rc === 'GROUPEUR_GROSSISTE' ||
      allPerms
  )
}
