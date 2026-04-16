import { Colis, ClientColis, PaginatedResponse, PaginationParams, CreateColisDto, UpdateColisDto } from '@types'
import { apiService } from './api.service'
import { calculerTotalLigneMarchandise } from '@utils/calculations'

/** Date SQL `YYYY-MM-DD` ou ISO → chaîne `YYYY-MM-DD` pour affichage (évite décalage fuseau). */
function normalizeDateEnvoi(raw: unknown): string | undefined {
  if (raw == null || raw === '') return undefined
  if (typeof raw === 'string') {
    const m = raw.match(/^(\d{4}-\d{2}-\d{2})/)
    if (m) return m[1]
    // Support legacy format "DD/MM/YYYY"
    const fr = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})$/)
    if (fr) return `${fr[3]}-${fr[2]}-${fr[1]}`
  }
  try {
    const d = new Date(raw as string | Date)
    if (Number.isNaN(d.getTime())) return undefined
    return d.toISOString().split('T')[0]
  } catch {
    return undefined
  }
}

/**
 * Adapter la réponse backend vers le format attendu par le frontend
 * Le backend renvoie: { client, marchandises[], nom_dest, ... }
 * Le frontend attend: { client_colis, nom_marchandise, poids_total, total_montant, nom_destinataire, ... }
 */
function adaptColisFromBackend(backendColis: any): Colis {
  // Calculer les totaux depuis les marchandises
  const marchandises = backendColis.marchandises || []
  const poidsTotal = marchandises.reduce((sum: number, m: any) => sum + Number(m.poids_total || 0), 0)
  const totalMontant = marchandises.reduce((sum: number, m: any) => {
    return sum + calculerTotalLigneMarchandise(
      Number(m.prix_unit || 0),
      Number(m.poids_total || 0),
      Number(m.prix_emballage || 0),
      Number(m.prix_assurance || 0),
      Number(m.prix_agence || 0)
    )
  }, 0)

  // Prendre la première marchandise pour les champs "aplatis" (compatibilité avec l'ancien format)
  const premiereMarchandise = marchandises[0] || {}

  return {
    ...backendColis, // Garder tous les champs originaux du backend (client, marchandises, etc.)
    id: backendColis.id,
    ref_colis: backendColis.ref_colis,
    mode_envoi: backendColis.mode_envoi || '',
    // Important: si date_envoi est absente, la laisser à undefined pour permettre
    // au rendu (table) de fallback sur created_at au lieu d'afficher "-"
    date_envoi: normalizeDateEnvoi(backendColis.date_envoi),
    nom_marchandise: premiereMarchandise.nom_marchandise || '',
    nbre_colis: premiereMarchandise.nbre_colis || 0,
    nbre_articles: premiereMarchandise.nbre_articles || 0,
    poids_total: poidsTotal,
    prix_unit: premiereMarchandise.prix_unit || 0,
    prix_emballage: premiereMarchandise.prix_emballage || 0,
    prix_assurance: premiereMarchandise.prix_assurance || 0,
    prix_agence: premiereMarchandise.prix_agence || 0,
    total_montant: totalMontant,
    client_colis: backendColis.client ? {
      id: backendColis.client.id,
      nom_exp: backendColis.client.nom_exp || '',
      type_piece_exp: backendColis.client.type_piece_exp || '',
      num_piece_exp: backendColis.client.num_piece_exp || '',
      tel_exp: backendColis.client.tel_exp || '',
      email_exp: backendColis.client.email_exp,
      date_enrg: backendColis.client.created_at ? new Date(backendColis.client.created_at).toISOString() : '',
    } : {} as ClientColis,
    nom_destinataire: backendColis.nom_dest || '',
    lieu_dest: backendColis.lieu_dest || '',
    tel_dest: backendColis.tel_dest || '',
    email_dest: backendColis.email_dest,
    adresse_recup: backendColis.adresse_recup,
    nom_recup: backendColis.nom_recup,
    tel_recup: backendColis.tel_recup,
    email_recup: backendColis.email_recup,
    forme_envoi: backendColis.forme_envoi as 'groupage' | 'autres_envoi',
    trafic_envoi: backendColis.trafic_envoi as any,
    code_user: backendColis.code_user || '',
    date_enrg: backendColis.created_at ? new Date(backendColis.created_at).toISOString() : '',
    marchandises: marchandises,
  }
}

class ColisService {
  /**
   * Récupérer la liste des colis (groupage ou autres envois)
   */
  async getColis(
    formeEnvoi: 'groupage' | 'autres_envoi',
    params?: PaginationParams
  ): Promise<PaginatedResponse<Colis>> {
    const queryParams = new URLSearchParams()
    if (params?.page) queryParams.append('page', params.page.toString())
    if (params?.limit) queryParams.append('limit', params.limit.toString())
    if (params?.search) queryParams.append('search', params.search)
    if (params?.sort_by) queryParams.append('sort_by', params.sort_by)
    if (params?.sort_order) queryParams.append('sort_order', params.sort_order)

    const response = await apiService.get<any>(
      `/colis?forme_envoi=${formeEnvoi}&${queryParams.toString()}`
    )

    // Le backend renvoie actuellement un simple tableau de colis (sans pagination).
    // On l'adapte ici au format PaginatedResponse attendu par le frontend.
    if (Array.isArray(response)) {
      const page = params?.page || 1
      const limit = params?.limit || response.length || 20
      const total = response.length

      return {
        data: response.map(adaptColisFromBackend),
        total,
        page,
        limit,
        total_pages: 1,
      }
    }

    // Sinon, on suppose que le backend renvoie déjà un PaginatedResponse<Colis>
    if (response.data && Array.isArray(response.data)) {
      return {
        ...response,
        data: response.data.map(adaptColisFromBackend),
      }
    }

    return response as PaginatedResponse<Colis>
  }

  /**
   * Récupérer un colis par son ID
   */
  async getColisById(id: number): Promise<Colis> {
    const backendColis = await apiService.get<any>(`/colis/${id}`)
    return adaptColisFromBackend(backendColis)
  }

  /**
   * Récupérer un colis par sa référence
   */
  async getColisByRef(refColis: string): Promise<Colis> {
    const backendColis = await apiService.get<any>(`/colis/ref/${refColis}`)
    return adaptColisFromBackend(backendColis)
  }

  /**
   * Récupérer tous les colis (pour compatibilité avec alerts.service)
   */
  async getAll(params?: {
    statut?: string,
    limit?: number,
    page?: number,
    sans_expedition?: boolean,
    agence_id?: number
  }): Promise<PaginatedResponse<Colis>> {
    const queryParams = new URLSearchParams()
    if (params?.statut) queryParams.append('statut', params.statut)
    if (params?.limit) queryParams.append('limit', params.limit.toString())
    if (params?.page) queryParams.append('page', params.page.toString())
    if (params?.sans_expedition) queryParams.append('sans_expedition', 'true')
    if (params?.agence_id) queryParams.append('agence_id', params.agence_id.toString())

    return apiService.get<PaginatedResponse<Colis>>(
      `/colis?${queryParams.toString()}`
    )
  }

  /**
   * Créer un nouveau colis (groupage)
   */
  async createGroupage(data: CreateColisDto): Promise<Colis> {
    const backendColis = await apiService.post<any>('/colis/groupage', data)
    return adaptColisFromBackend(backendColis)
  }

  /**
   * Créer un nouveau colis (autres envois)
   */
  async createAutresEnvois(data: CreateColisDto): Promise<Colis> {
    const backendColis = await apiService.post<any>('/colis/autres-envois', data)
    return adaptColisFromBackend(backendColis)
  }

  /**
   * Mettre à jour un colis
   */
  async updateColis(id: number, data: UpdateColisDto): Promise<Colis> {
    const backendColis = await apiService.put<any>(`/colis/${id}`, data)
    return adaptColisFromBackend(backendColis)
  }

  /**
   * Supprimer un colis
   */
  async deleteColis(id: number): Promise<void> {
    return apiService.delete<void>(`/colis/${id}`)
  }

  /**
   * Valider un colis
   */
  async validateColis(id: number): Promise<Colis> {
    const backendColis = await apiService.patch<any>(`/colis/${id}/validate`)
    return adaptColisFromBackend(backendColis)
  }

  /**
   * Rechercher des colis
   */
  async searchColis(searchTerm: string, formeEnvoi?: 'groupage' | 'autres_envoi'): Promise<Colis[]> {
    const query = formeEnvoi ? `?forme_envoi=${formeEnvoi}&search=${searchTerm}` : `?search=${searchTerm}`
    const backendColisList = await apiService.get<any[]>(`/colis/search${query}`)
    return backendColisList.map(adaptColisFromBackend)
  }

  /**
   * Marquer le colis comme reçu au Hub (Bobigny)
   */
  async receiveAtHub(id: number): Promise<Colis> {
    const backendColis = await apiService.patch<any>(`/colis/${id}/receive-at-hub`)
    return adaptColisFromBackend(backendColis)
  }

  /**
   * Suivre un colis (pour la page publique)
   */
  async trackColis(refColis: string): Promise<ColisTrackingInfo> {
    return apiService.get<ColisTrackingInfo>(`/colis/track/${refColis}`)
  }
}


export interface ColisTrackingInfo {
  ref_colis: string
  status: string
  payment_status?: string
  current_location?: string
  steps: Array<{
    title: string
    date: string
    location?: string
  }>
  client_colis: ClientColis
  colis: Colis
}

export const colisService = new ColisService()
