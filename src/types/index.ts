// Types utilisateurs et authentification
export interface User {
  id: number
  code_user: string
  username: string
  nom_complet: string
  full_name?: string // Ajouté pour compatibilité
  email?: string
  phone?: string
  role: Role
  agency?: Agency
  agency_id?: number // ID agence (pour filtrage CODEACCES 9)
  filter_mode?: 'individual' | 'agency' | 'all' // Mode de filtrage (CODEACCES 8, 9, 14)
  can_delete?: boolean // Protection suppression (CODEACCES 5)
  can_modify?: boolean // Protection modification (CODEACCES 6)
  actif: boolean
  last_login?: string
  created_at: string
  roleEntity?: any // Ajouté pour usePermission
  actionsSpeciales?: any[] // Ajouté pour usePermission
  peut_voir_toutes_agences?: boolean // Ajouté pour usePermission
  /** 2 = super admin (accès total), aligné backend */
  code_acces?: number
  // ✅ Nouveaux champs pour le flux de gestion
  must_change_password?: boolean
  agence_selected?: boolean
  password_plain?: string | null
}

export enum UserRole {
  ADMIN = 'ADMIN',
  DIRECTEUR = 'DIRECTEUR',
  MANAGER = 'MANAGER',
  SUPERVISEUR_REGIONAL = 'SUPERVISEUR_REGIONAL',
  AGENT_EXPLOITATION = 'AGENT_EXPLOITATION',
  AGENT_GROUPAGE = 'AGENT_GROUPAGE',
  CAISSIER = 'CAISSIER',
  CAISSIER_GROUPAGE = 'CAISSIER_GROUPAGE',
  AGENT_SUIVI = 'AGENT_SUIVI',
}


export interface Role {
  id: number
  code: string
  name: string
  description?: string
}

export interface Agency {
  id: number
  code: string
  name: string
  address?: string
  phone?: string
  email?: string
  currency?: string // Ajouté pour le support multi-devises
  latitude?: number | null
  longitude?: number | null
  place_id?: string | null
}

export interface AuthResponse {
  user: User
  token: string
  refresh_token?: string
  permissions: string[]
}

export interface LoginCredentials {
  username: string
  password: string
}


// Types permissions

export interface Permission {
  id: number
  code: string
  name: string
  module: string
  action: string
  description?: string
}

// Types Colis
export interface Colis {
  id: number
  ref_colis: string
  mode_envoi: string
  date_envoi: string
  nom_marchandise: string
  nbre_colis: number
  nbre_articles: number
  poids_total: number
  prix_unit: number
  prix_emballage: number
  prix_assurance: number
  prix_agence: number
  total_montant: number
  client_colis: ClientColis
  nom_destinataire: string
  lieu_dest: string
  tel_dest: string
  email_dest?: string
  adresse_recup?: string
  nom_recup?: string
  tel_recup?: string
  email_recup?: string
  forme_envoi: 'groupage' | 'autres_envoi'
  trafic_envoi: string
  code_user: string
  agence?: Agency
  date_enrg: string
  // Champs additionnels pour compatibilité avec les composants
  etat_validation?: number
  statut_suivi?: 'EMBALLE' | 'EXPEDIE' | 'REC_BOBIGNY' | 'EN_LIVRAISON' | 'LIVRE'
  client?: any
  nom_dest?: string
  marchandises?: any[]
  expedition?: Expedition // Relation avec Expedition
}

export interface ClientColis {
  id: number
  nom_exp: string
  type_piece_exp: string
  num_piece_exp: string
  tel_exp: string
  email_exp?: string
  date_enrg: string
}

// Types Factures
export interface FactureColis {
  id: number
  num_facture: string
  montant_ttc: number
  montant_paye: number  // montant déjà encaissé
  id_colis: number
  colis?: Colis
  ref_colis: string
  code_user: string
  etat: 0 | 1 | 2 // 0 = proforma, 1 = définitive, 2 = annulée
  date_facture: string
}

// Types Paiements
export interface Paiement {
  id: number
  montant: number
  date_paiement: string
  mode_paiement: string
  reference: string
  facture_id?: number
  colis_id?: number
  code_user: string
  etat_validation?: number
  created_at?: string
  reference_paiement?: string
}

// Types Dashboard
export interface DashboardStats {
  colis_aujourdhui: number
  colis_en_transit: number
  colis_livres: number
  revenus_jour: number
  revenus_mois: number
  clients_actifs: number
  factures_a_valider: number
  paiements_attente: number
}

export interface PointCaisse {
  entrees: number
  sorties: number
  solde: number
  date: string
}

// Types Caisse
export type TypeMouvementCaisse =
  | 'APPRO'
  | 'DECAISSEMENT'
  | 'ENTREE_CHEQUE'
  | 'ENTREE_ESPECE'
  | 'ENTREE_VIREMENT'

export type ModeReglement = 'ESPECE' | 'CHEQUE' | 'VIREMENT'

export interface MouvementCaisse {
  id?: number
  date: string
  type: TypeMouvementCaisse
  libelle: string
  montant: number
  solde?: number // Solde après l'opération
  mode_reglement?: ModeReglement
  numero_dossier?: string // RefColis
  numero_cheque?: string
  numero_virement?: string
  numero_recu?: string
  numero_fiche_recette?: string
  numero_bordereau_versement?: string
  numero_ordre_decaissement?: string
  nom_client?: string
  nom_demandeur?: string
  banque_remise?: string
  banque_creditee?: string
  reste_a_payer?: number
  id_colis?: number
  id_caisse: number
  code_user?: string
  etat?: number // 0 = Brouillon, 1 = Validé
  created_at?: string
  updated_at?: string
}

export interface Caisse {
  id: number
  code: string
  libelle: string
  montant_initial: number
  solde_actuel: number
  autorise: boolean
  id_agence?: number
  code_user?: string
  created_at?: string
  updated_at?: string
}

export interface RapportGrandesLignes {
  date_debut: string
  date_fin: string
  total_appro: number
  total_decaissement: number
  total_entrees_cheque: number
  total_entrees_espece: number
  total_entrees_virement: number
  total_entrees: number
  solde_initial: number
  solde_final: number
}

// Types généraux
export interface ApiResponse<T> {
  success: boolean
  data?: T
  message?: string
  error?: string
}

export interface PaginationParams {
  page: number
  limit: number
  search?: string
  sort_by?: string
  sort_order?: 'asc' | 'desc'
}

export interface PaginatedResponse<T> {
  data: T[]
  total: number
  page: number
  limit: number
  total_pages: number
}

// DTOs pour création et mise à jour de colis
export interface CreateColisDto {
  trafic_envoi: string
  date_envoi: string
  mode_envoi: string
  forme_envoi: 'groupage' | 'autres_envoi'

  // Informations expéditeur
  client_colis: {
    nom_exp: string
    type_piece_exp: string
    num_piece_exp: string
    tel_exp: string
    email_exp?: string
  }

  // Informations marchandise (tableau pour plusieurs colis)
  marchandise: Array<{
    nom_marchandise: string
    nbre_colis: number
    nbre_articles: number
    poids_total: number
    prix_unit: number
    id_tarif?: number
    prix_emballage?: number
    nbre_emballage?: number // Ajouté pour compatibilité
    prix_assurance?: number
    prix_agence?: number
  }>

  // Informations destinataire
  nom_destinataire: string
  lieu_dest: string
  tel_dest: string
  email_dest?: string

  // Informations récupérateur (optionnel)
  nom_recup?: string
  adresse_recup?: string
  tel_recup?: string
  email_recup?: string
}

export interface UpdateColisDto extends Partial<CreateColisDto> {
  id?: number
}

export interface Expedition {
  id: number;
  ref_expedition: string;
  date_depart: string;
  date_arrivee_prevue?: string;
  statut: 'EN_PREPARATION' | 'EN_TRANSIT' | 'ARRIVE' | 'DEDOUANE' | 'LIVRE';
  type: 'AERIEN' | 'MARITIME';
  agence_depart: Agency;
  agence_destination: Agency;
  colis?: Colis[];
  numero_container?: string;
  compagnie_transport?: string;
  created_at: string;
}

export interface CreateExpeditionDto {
  id_agence_destination: number;
  type: 'AERIEN' | 'MARITIME';
  numero_container?: string;
  compagnie_transport?: string;
  date_arrivee_prevue?: string;
}

/** Réponse paginée API litiges */
export interface LitigesListResponse {
  data: LitigeListItem[]
  total: number
  page: number
  limit: number
  totalPages: number
}

export interface LitigeListItem {
  id: number
  num_litige: string
  type: string
  statut: string
  priorite?: string
  objet: string
  created_at: string
  agence?: { id: number; nom?: string; code?: string }
  client?: { id: number; nom_exp?: string }
  colis?: { id: number; ref_colis?: string } | null
}

export interface LitigeUserRef {
  id: number
  username?: string
  nom_complet?: string
}

export interface LitigeMessageItem {
  id: number
  type: string
  contenu: string
  interne?: boolean
  created_at: string
  auteur?: LitigeUserRef | null
}

/** Détail litige (GET /litiges/:id) — inclut le fil de messages */
export interface LitigeDetail extends LitigeListItem {
  description?: string
  contact_nom?: string
  contact_email?: string
  contact_telephone?: string
  escalade?: boolean
  resolution?: string | null
  montant_compensation?: number | null
  createur?: LitigeUserRef | null
  assigne?: LitigeUserRef | null
  messages?: LitigeMessageItem[]
  facture?: { id: number } | null
  deleted_at?: string | null
}

/** Réponse paginée boîte call center */
export interface CallCenterConversationsResponse {
  data: CallCenterConversationRow[]
  total: number
  page: number
  limit: number
  totalPages: number
}

export interface CallCenterConversationRow {
  id: number
  channel: string
  customer_phone: string
  callcenter_phone: string | null
  last_message_at: string | null
  unread_count: number
  client_id: number | null
}

/** Messages d’une conversation (GET /callcenter/conversations/:id/messages) */
export interface CallCenterMessageRow {
  id: number
  conversation_id: number
  channel: 'sms' | 'whatsapp'
  direction: 'in' | 'out'
  from_phone: string
  to_phone: string
  message: string
  provider?: string | null
  created_at: string
}

export interface CallCenterMessagesResponse {
  data: CallCenterMessageRow[]
  total: number
  offset: number
  limit: number
}
