/**
 * Utilisateurs mock pour le développement (sans backend)
 * Ces utilisateurs permettent de tester l'interface sans connexion API
 */

import type { User, Role } from '@types'
import { PERMISSIONS, ROLES } from '@constants/permissions'

// Rôles mock
const mockRoles: Record<string, Role> = {
  SUPER_ADMIN: {
    id: 1,
    code: 'SUPER_ADMIN',
    name: 'Super Administrateur',
  },
  ADMIN: {
    id: 2,
    code: 'ADMIN',
    name: 'Administrateur',
  },
  OPERATEUR_COLIS: {
    id: 3,
    code: 'OPERATEUR_COLIS',
    name: 'Opérateur Colis',
  },
  VALIDATEUR: {
    id: 4,
    code: 'VALIDATEUR',
    name: 'Validateur',
  },
  CAISSIER: {
    id: 5,
    code: 'CAISSIER',
    name: 'Caissier',
  },
  AGENCE_MANAGER: {
    id: 6,
    code: 'AGENCE_MANAGER',
    name: 'Gestionnaire Agence',
  },
  LECTURE_SEULE: {
    id: 7,
    code: 'LECTURE_SEULE',
    name: 'Lecture Seule',
  },
}

/**
 * Utilisateurs mock pour le développement
 * Format: username / password
 */
export const MOCK_USERS: Record<string, User> = {
  // Super Admin - Toutes les permissions
  admin: {
    id: 1,
    code_user: 'ADMIN001',
    username: 'admin',
    nom_complet: 'Super Administrateur',
    full_name: 'Super Administrateur',
    email: 'admin@lbp.ci',
    phone: '+225 07 00 00 00 01',
    role: mockRoles.SUPER_ADMIN,
    actif: true,
    filter_mode: 'all',
    can_delete: false,
    can_modify: false,
    created_at: new Date().toISOString(),
  },

  // Administrateur - Gestion complète
  manager: {
    id: 2,
    code_user: 'MGR001',
    username: 'manager',
    nom_complet: 'Gestionnaire Principal',
    full_name: 'Gestionnaire Principal',
    email: 'manager@lbp.ci',
    phone: '+225 07 00 00 00 02',
    role: mockRoles.ADMIN,
    actif: true,
    filter_mode: 'all',
    can_delete: false,
    can_modify: false,
    created_at: new Date().toISOString(),
  },

  // Opérateur Colis - CRUD Colis
  operateur: {
    id: 3,
    code_user: 'OPR001',
    username: 'operateur',
    nom_complet: 'Opérateur Colis',
    full_name: 'Opérateur Colis',
    email: 'operateur@lbp.ci',
    phone: '+225 07 00 00 00 03',
    role: mockRoles.OPERATEUR_COLIS,
    actif: true,
    filter_mode: 'individual',
    can_delete: false,
    can_modify: true,
    created_at: new Date().toISOString(),
  },

  // Validateur - Validation uniquement
  validateur: {
    id: 4,
    code_user: 'VAL001',
    username: 'validateur',
    nom_complet: 'Validateur',
    full_name: 'Validateur',
    email: 'validateur@lbp.ci',
    phone: '+225 07 00 00 00 04',
    role: mockRoles.VALIDATEUR,
    actif: true,
    filter_mode: 'all',
    can_delete: false,
    can_modify: false,
    created_at: new Date().toISOString(),
  },

  // Caissier - Paiements + Caisse
  caissier: {
    id: 5,
    code_user: 'CAI001',
    username: 'caissier',
    nom_complet: 'Caissier Principal',
    full_name: 'Caissier Principal',
    email: 'caissier@lbp.ci',
    phone: '+225 07 00 00 00 05',
    role: mockRoles.CAISSIER,
    actif: true,
    filter_mode: 'individual',
    can_delete: false,
    can_modify: false,
    created_at: new Date().toISOString(),
  },

  // Gestionnaire Agence - Limité à agence
  agence: {
    id: 6,
    code_user: 'AGC001',
    username: 'agence',
    nom_complet: 'Gestionnaire Agence',
    full_name: 'Gestionnaire Agence',
    email: 'agence@lbp.ci',
    phone: '+225 07 00 00 00 06',
    role: mockRoles.AGENCE_MANAGER,
    agency_id: 1,
    actif: true,
    filter_mode: 'agency',
    can_delete: false,
    can_modify: true,
    created_at: new Date().toISOString(),
  },

  // Lecture Seule - Consultation uniquement
  lecteur: {
    id: 7,
    code_user: 'LEC001',
    username: 'lecteur',
    nom_complet: 'Utilisateur Lecture',
    full_name: 'Utilisateur Lecture',
    email: 'lecteur@lbp.ci',
    phone: '+225 07 00 00 00 07',
    role: mockRoles.LECTURE_SEULE,
    actif: true,
    filter_mode: 'all',
    can_delete: false,
    can_modify: false,
    created_at: new Date().toISOString(),
  },
}

/**
 * Mots de passe pour les utilisateurs mock
 * Format: username => password
 */
export const MOCK_PASSWORDS: Record<string, string> = {
  admin: 'adminpassword', // Corrigé pour correspondre au backend
  manager: 'manager123',
  operateur: 'operateur123',
  validateur: 'validateur123',
  caissier: 'caissier123',
  agence: 'agence123',
  lecteur: 'lecteur123',
}

/**
 * Permissions par rôle (pour le mock)
 */
export const MOCK_PERMISSIONS: Record<string, string[]> = {
  admin: ['*'], // Toutes les permissions
  manager: [...ROLES.ADMIN.permissions],
  operateur: [...ROLES.OPERATEUR_COLIS.permissions],
  validateur: [...ROLES.VALIDATEUR.permissions],
  caissier: [...ROLES.CAISSIER.permissions],
  agence: [...ROLES.AGENCE_MANAGER.permissions],
  lecteur: [...ROLES.LECTURE_SEULE.permissions],
}

/**
 * Vérifier si un utilisateur mock existe
 */
export const validateMockUser = (
  username: string,
  password: string,
): { valid: boolean; user?: User; permissions?: string[] } => {
  const user = MOCK_USERS[username.toLowerCase()]
  const expectedPassword = MOCK_PASSWORDS[username.toLowerCase()]

  if (!user || !expectedPassword) {
    return { valid: false }
  }

  if (password !== expectedPassword) {
    return { valid: false }
  }

  return {
    valid: true,
    user,
    permissions: MOCK_PERMISSIONS[username.toLowerCase()] || [],
  }
}
