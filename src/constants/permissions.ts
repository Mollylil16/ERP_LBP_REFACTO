/**
 * CONSTANTES DE PERMISSIONS LBP
 * 
 * Ce fichier définit toutes les permissions disponibles dans le système LBP.
 * Les permissions sont organisées par module pour faciliter la gestion.
 */

export const PERMISSIONS = {
  // Module Dashboard
  DASHBOARD: {
    VIEW: 'dashboard.view',
    ADMIN: 'dashboard.admin',
    CAISSE: 'dashboard.caisse',
  },

  // Module Colis - Groupage
  COLIS_GROUPAGE: {
    READ: 'colis.groupage.read',
    CREATE: 'colis.groupage.create',
    UPDATE: 'colis.groupage.update',
    DELETE: 'colis.groupage.delete',
    VALIDATE: 'colis.groupage.validate',
  },

  // Module Colis - Autres Envois
  COLIS_AUTRES_ENVOIS: {
    READ: 'colis.autres-envois.read',
    CREATE: 'colis.autres-envois.create',
    UPDATE: 'colis.autres-envois.update',
    DELETE: 'colis.autres-envois.delete',
    VALIDATE: 'colis.autres-envois.validate',
  },

  // Module Clients
  CLIENTS: {
    READ: 'clients.read',
    CREATE: 'clients.create',
    UPDATE: 'clients.update',
    DELETE: 'clients.delete',
    VIEW_HISTORY: 'clients.view-history',
  },

  // Module Factures
  FACTURES: {
    READ: 'factures.read',
    CREATE: 'factures.create',
    UPDATE: 'factures.update',
    DELETE: 'factures.delete',
    VALIDATE: 'factures.validate',
    CANCEL: 'factures.cancel',
    PRINT: 'factures.print',
    EXPORT: 'factures.export',
  },

  // Module Paiements
  PAIEMENTS: {
    READ: 'paiements.read',
    CREATE: 'paiements.create',
    UPDATE: 'paiements.update',
    DELETE: 'paiements.delete',
    CANCEL: 'paiements.cancel',
    VALIDATE: 'paiements.validate',
  },

  // Module Rapports
  RAPPORTS: {
    VIEW: 'rapports.view',
    EXPORT: 'rapports.export',
    VIEW_ALL: 'rapports.view-all',
  },

  // Module Caisse
  CAISSE: {
    VIEW: 'caisse.view',
    OPERATIONS: 'caisse.operations',
    VIEW_ALL: 'caisse.view-all',
  },

  // Module Utilisateurs
  USERS: {
    READ: 'users.read',
    CREATE: 'users.create',
    UPDATE: 'users.update',
    DELETE: 'users.delete',
    PERMISSIONS: 'users.permissions',
  },

  // Module Configuration
  CONFIG: {
    VIEW: 'config.view',
    UPDATE: 'config.update',
    SYSTEM: 'config.system',
  },

  /** Liste / détail agences (sélecteurs, 1ère connexion) — sans accès écran paramètres société */
  AGENCES: {
    READ: 'agences.read',
  },

  // Module Litiges (aligné backend `litiges.*`)
  LITIGES: {
    VIEW: 'litiges.view',
    CREATE: 'litiges.create',
    MANAGE: 'litiges.manage',
    ADMIN: 'litiges.admin',
  },

  // Call center / messagerie (aligné backend `callcenter.inbox`)
  CALLCENTER: {
    INBOX: 'callcenter.inbox',
  },
} as const

/**
 * Tous les codes déclarés sous `PERMISSIONS` (pour tests / scripts de cohérence).
 */
export function getDeclaredAppPermissionCodes(): string[] {
  const out: string[] = []
  const walk = (node: unknown): void => {
    if (typeof node === 'string') {
      if (node !== '*' && node.includes('.')) out.push(node)
      return
    }
    if (node && typeof node === 'object' && !Array.isArray(node)) {
      Object.values(node).forEach(walk)
    }
  }
  walk(PERMISSIONS)
  return [...new Set(out)].sort((a, b) => a.localeCompare(b))
}

/**
 * Mapping des CODEACCES STTINTER vers Permissions LBP
 * 
 * CODEACCES 1-16: Droits fonctionnels STTINTER
 * Ces codes sont mappés vers des permissions LBP plus granulaires
 */
export const CODEACCES_TO_PERMISSIONS: Record<number, string[]> = {
  // CODEACCES 1: Validation ouverture dossier (création colis)
  1: [PERMISSIONS.COLIS_GROUPAGE.VALIDATE, PERMISSIONS.COLIS_AUTRES_ENVOIS.VALIDATE],
  
  // CODEACCES 2: Accès total (toutes les permissions)
  2: ['*'],
  
  // CODEACCES 5: Protection suppression (ne peut pas supprimer)
  // Géré dans le code backend - pas de permission directe
  5: [], // Protection suppression (géré dans le code)
  
  // CODEACCES 6: Protection modification (ne peut pas modifier)
  // Géré dans le code backend - pas de permission directe
  6: [], // Protection modification (géré dans le code)
  
  // CODEACCES 7: Validation minute (documents, factures)
  7: [PERMISSIONS.FACTURES.VALIDATE],
  
  // CODEACCES 8: Page individuelle (voir uniquement ses propres données)
  // Géré dans le code backend - filtre automatique par CODE_USER
  8: [], // Page individuelle - Filtre par utilisateur (géré dans le code)
  
  // CODEACCES 9: Page agence (voir uniquement les données de son agence)
  // Géré dans le code backend - filtre automatique par id_agence
  9: [], // Page agence - Filtre par agence (géré dans le code)
  
  // CODEACCES 10: Validation proforma
  10: [PERMISSIONS.FACTURES.VALIDATE],
  
  // CODEACCES 11: Validation définitif
  11: [PERMISSIONS.FACTURES.VALIDATE],
  
  // CODEACCES 12: Uniquement groupage (accès limité au module groupage)
  12: [
    PERMISSIONS.COLIS_GROUPAGE.READ,
    PERMISSIONS.COLIS_GROUPAGE.CREATE,
    PERMISSIONS.COLIS_GROUPAGE.UPDATE,
  ],
  
  // CODEACCES 13: Ajout module utilisateur (gestion utilisateurs)
  13: [PERMISSIONS.USERS.CREATE, PERMISSIONS.USERS.UPDATE],
  
  // CODEACCES 14: Voir toutes agences (multi-agences)
  14: [PERMISSIONS.CAISSE.VIEW_ALL, PERMISSIONS.RAPPORTS.VIEW_ALL],
  
  // CODEACCES 15: Super action (toutes les permissions système)
  15: ['*'],
  
  // CODEACCES 16: Annuler encaissement groupage
  16: [PERMISSIONS.PAIEMENTS.CANCEL],
}

/**
 * Constantes pour les filtres automatiques (CODEACCES 8 & 9)
 */
export const FILTER_MODES = {
  // CODEACCES 8: Page individuelle - Voir uniquement ses propres données
  INDIVIDUAL: 'individual', // Filtre automatique: WHERE CODE_USER = current_user
  
  // CODEACCES 9: Page agence - Voir uniquement les données de son agence
  AGENCY: 'agency', // Filtre automatique: WHERE id_agence = user.agency_id
  
  // CODEACCES 14: Voir toutes agences - Pas de filtre
  ALL: 'all', // Pas de filtre - voir toutes les données
} as const

export type FilterMode = typeof FILTER_MODES[keyof typeof FILTER_MODES]

/**
 * Rôles prédéfinis avec leurs permissions
 */
export const ROLES = {
  SUPER_ADMIN: {
    code: 'SUPER_ADMIN',
    name: 'Super Administrateur',
    permissions: ['*'], // Toutes les permissions
  },
  ADMIN: {
    code: 'ADMIN',
    name: 'Administrateur',
    permissions: [
      PERMISSIONS.DASHBOARD.ADMIN,
      PERMISSIONS.COLIS_GROUPAGE.READ,
      PERMISSIONS.COLIS_GROUPAGE.CREATE,
      PERMISSIONS.COLIS_GROUPAGE.UPDATE,
      PERMISSIONS.COLIS_GROUPAGE.DELETE,
      PERMISSIONS.COLIS_AUTRES_ENVOIS.READ,
      PERMISSIONS.COLIS_AUTRES_ENVOIS.CREATE,
      PERMISSIONS.COLIS_AUTRES_ENVOIS.UPDATE,
      PERMISSIONS.COLIS_AUTRES_ENVOIS.DELETE,
      PERMISSIONS.CLIENTS.READ,
      PERMISSIONS.CLIENTS.CREATE,
      PERMISSIONS.CLIENTS.UPDATE,
      PERMISSIONS.CLIENTS.DELETE,
      PERMISSIONS.FACTURES.READ,
      PERMISSIONS.FACTURES.CREATE,
      PERMISSIONS.FACTURES.VALIDATE,
      PERMISSIONS.FACTURES.PRINT,
      PERMISSIONS.PAIEMENTS.READ,
      PERMISSIONS.PAIEMENTS.CREATE,
      PERMISSIONS.RAPPORTS.VIEW,
      PERMISSIONS.RAPPORTS.EXPORT,
      PERMISSIONS.CAISSE.VIEW,
      PERMISSIONS.CAISSE.OPERATIONS,
      PERMISSIONS.LITIGES.VIEW,
      PERMISSIONS.LITIGES.CREATE,
      PERMISSIONS.LITIGES.MANAGE,
      PERMISSIONS.LITIGES.ADMIN,
      PERMISSIONS.CALLCENTER.INBOX,
    ],
  },
  OPERATEUR_COLIS: {
    code: 'OPERATEUR_COLIS',
    name: 'Opérateur Colis',
    permissions: [
      PERMISSIONS.DASHBOARD.VIEW,
      PERMISSIONS.COLIS_GROUPAGE.READ,
      PERMISSIONS.COLIS_GROUPAGE.CREATE,
      PERMISSIONS.COLIS_GROUPAGE.UPDATE,
      PERMISSIONS.COLIS_AUTRES_ENVOIS.READ,
      PERMISSIONS.COLIS_AUTRES_ENVOIS.CREATE,
      PERMISSIONS.COLIS_AUTRES_ENVOIS.UPDATE,
      PERMISSIONS.CLIENTS.READ,
      PERMISSIONS.CLIENTS.CREATE,
      PERMISSIONS.FACTURES.READ,
      PERMISSIONS.FACTURES.PRINT,
    ],
  },
  VALIDATEUR: {
    code: 'VALIDATEUR',
    name: 'Validateur',
    permissions: [
      PERMISSIONS.DASHBOARD.VIEW,
      PERMISSIONS.COLIS_GROUPAGE.READ,
      PERMISSIONS.COLIS_GROUPAGE.VALIDATE,
      PERMISSIONS.COLIS_AUTRES_ENVOIS.READ,
      PERMISSIONS.COLIS_AUTRES_ENVOIS.VALIDATE,
      PERMISSIONS.FACTURES.READ,
      PERMISSIONS.FACTURES.VALIDATE,
      PERMISSIONS.PAIEMENTS.READ,
      PERMISSIONS.PAIEMENTS.VALIDATE,
    ],
  },
  CAISSIER: {
    code: 'CAISSIER',
    name: 'Caissier',
    permissions: [
      PERMISSIONS.DASHBOARD.VIEW,
      PERMISSIONS.DASHBOARD.CAISSE,
      PERMISSIONS.COLIS_GROUPAGE.READ,
      PERMISSIONS.COLIS_AUTRES_ENVOIS.READ,
      PERMISSIONS.FACTURES.READ,
      PERMISSIONS.PAIEMENTS.READ,
      PERMISSIONS.PAIEMENTS.CREATE,
      PERMISSIONS.CAISSE.VIEW,
      PERMISSIONS.CAISSE.OPERATIONS,
    ],
  },
  AGENCE_MANAGER: {
    code: 'AGENCE_MANAGER',
    name: 'Gestionnaire Agence',
    permissions: [
      PERMISSIONS.DASHBOARD.VIEW,
      PERMISSIONS.COLIS_GROUPAGE.READ,
      PERMISSIONS.COLIS_GROUPAGE.CREATE,
      PERMISSIONS.COLIS_GROUPAGE.UPDATE,
      PERMISSIONS.COLIS_AUTRES_ENVOIS.READ,
      PERMISSIONS.COLIS_AUTRES_ENVOIS.CREATE,
      PERMISSIONS.COLIS_AUTRES_ENVOIS.UPDATE,
      PERMISSIONS.CLIENTS.READ,
      PERMISSIONS.CLIENTS.CREATE,
      PERMISSIONS.FACTURES.READ,
      PERMISSIONS.FACTURES.CREATE,
      PERMISSIONS.PAIEMENTS.READ,
      PERMISSIONS.PAIEMENTS.CREATE,
      PERMISSIONS.RAPPORTS.VIEW,
      PERMISSIONS.CAISSE.VIEW,
    ],
  },
  LECTURE_SEULE: {
    code: 'LECTURE_SEULE',
    name: 'Lecture Seule',
    permissions: [
      PERMISSIONS.DASHBOARD.VIEW,
      PERMISSIONS.COLIS_GROUPAGE.READ,
      PERMISSIONS.COLIS_AUTRES_ENVOIS.READ,
      PERMISSIONS.CLIENTS.READ,
      PERMISSIONS.FACTURES.READ,
      PERMISSIONS.PAIEMENTS.READ,
      PERMISSIONS.RAPPORTS.VIEW,
    ],
  },
} as const

/**
 * Vérifie si une permission est incluse dans la liste
 */
export function hasPermission(userPermissions: string[], permission: string): boolean {
  // Super admin a toutes les permissions
  if (userPermissions.includes('*')) {
    return true
  }
  
  return userPermissions.includes(permission)
}

/**
 * Vérifie si l'utilisateur a au moins une des permissions
 */
export function hasAnyPermission(userPermissions: string[], permissions: string[]): boolean {
  if (userPermissions.includes('*')) {
    return true
  }
  
  return permissions.some((p) => userPermissions.includes(p))
}

/**
 * Vérifie si l'utilisateur a toutes les permissions
 */
export function hasAllPermissions(userPermissions: string[], permissions: string[]): boolean {
  if (userPermissions.includes('*')) {
    return true
  }
  
  return permissions.every((p) => userPermissions.includes(p))
}
