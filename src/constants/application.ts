/**
 * CONSTANTES DE L'APPLICATION LBP
 * 
 * Configuration globale de l'application
 */

export const APP_CONFIG = {
  name: 'LBP',
  fullName: 'LA BELLE PORTE',
  description: 'Gestion de Colis',
  version: '1.0.0',

  // Informations entreprise (sera configurable via admin)
  company: {
    name: 'LA BELLE PORTE',
    shortName: 'LBP',
    /** Lignes service client / call center (CI) — aligné PDF facture & env `CALLCENTER_GLOBAL` */
    callCenterPhones: '0503497979 / 0509467979',
    address: '', // À configurer
    phone: '', // À configurer
    email: '', // À configurer
    website: '', // À configurer
    rccm: '', // À configurer
    nif: '', // À configurer
    accountNumber: '', // À configurer
  },

  // Options trafic d'envoi
  traficEnvoi: [
    'Groupage Aérien',
    'Groupage Maritime',
    'Colis Rapide CI vers FR',
    'Colis Rapide FR vers CI',
    'Colis Rapide SEN vers CI',
    'Colis Rapide CI vers SEN',
  ] as const,

  // Modes d'envoi
  modeEnvoi: [
    'DHL',
    'Colis Rapides Export',
    'Colis Rapides Import',
    'Autres',
    'groupage',
  ] as const,

  // Types de pièce d'identité
  typesPieceIdentite: [
    'Carte Nationale Identite',
    'Passeport',
    'Certificat de Nationalite',
    'Carte de Sejour',
    'Carte de Resident',
    'Permis de Conduire',
  ] as const,

  // Modes de paiement
  modesPaiement: [
    { value: 'especes', label: 'Espèces', color: 'green' },
    { value: 'wave', label: 'Wave', color: 'blue' },
    { value: 'om', label: 'Orange Money', color: 'orange' },
    { value: 'comptant', label: 'Comptant', color: 'cyan' },
    { value: 'cheque', label: 'Chèque', color: 'purple' },
    { value: 'virement', label: 'Virement', color: 'geekblue' },
    { value: '30j', label: 'Crédit 30 jours', color: 'gold' },
    { value: '45j', label: 'Crédit 45 jours', color: 'gold' },
    { value: '60j', label: 'Crédit 60 jours', color: 'gold' },
    { value: '90j', label: 'Crédit 90 jours', color: 'gold' },
  ],

  // Devise
  devise: 'FCFA',

  // Formats de date
  dateFormat: 'DD/MM/YYYY',
  dateTimeFormat: 'DD/MM/YYYY HH:mm',

  // Pagination par défaut
  pagination: {
    defaultPageSize: 20,
    pageSizeOptions: ['10', '20', '50', '100'],
  },

  // Refresh automatique (en millisecondes)
  refresh: {
    dashboard: 180000, // 3 minutes
    widgets: 30000, // 30 secondes
  },
} as const

export type TraficEnvoi = typeof APP_CONFIG.traficEnvoi[number]
export type ModeEnvoi = typeof APP_CONFIG.modeEnvoi[number]
export type TypePieceIdentite = typeof APP_CONFIG.typesPieceIdentite[number]
