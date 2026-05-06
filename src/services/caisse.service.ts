/**
 * Service API pour la gestion de la caisse
 */

import { apiService } from './api.service'
import type { MouvementCaisse, Caisse, RapportGrandesLignes, PointCaisse } from '@types'

const BASE_URL = '/caisse'

/**
 * Créer un approvisionnement
 */
export const createAppro = async (data: Partial<MouvementCaisse>): Promise<MouvementCaisse> => {
  return apiService.post<MouvementCaisse>(`${BASE_URL}/appro`, {
    ...data,
    type: 'APPRO',
  })
}

/**
 * Créer un décaissement
 */
export const createDecaissement = async (
  data: Partial<MouvementCaisse>,
): Promise<MouvementCaisse> => {
  return apiService.post<MouvementCaisse>(`${BASE_URL}/decaissement`, {
    ...data,
    type: 'DECAISSEMENT',
  })
}

/**
 * Créer une entrée de caisse (Chèque/Espèce/Virement)
 */
export const createEntreeCaisse = async (
  data: Partial<MouvementCaisse>,
): Promise<MouvementCaisse> => {
  return apiService.post<MouvementCaisse>(`${BASE_URL}/entree`, data)
}

/**
 * Récupérer la liste des mouvements de caisse
 */
export const getMouvementsCaisse = async (params?: {
  type?: string
  date_debut?: string
  date_fin?: string
  id_caisse?: number
}): Promise<MouvementCaisse[]> => {
  return apiService.get<MouvementCaisse[]>(`${BASE_URL}/mouvements`, { params })
}

/**
 * Récupérer un mouvement de caisse par ID
 */
export const getMouvementCaisseById = async (id: number): Promise<MouvementCaisse> => {
  return apiService.get<MouvementCaisse>(`${BASE_URL}/mouvements/${id}`)
}

/**
 * Mettre à jour un mouvement de caisse
 */
export const updateMouvementCaisse = async (
  id: number,
  data: Partial<MouvementCaisse>,
): Promise<MouvementCaisse> => {
  return apiService.put<MouvementCaisse>(`${BASE_URL}/mouvements/${id}`, data)
}

/**
 * Supprimer un mouvement de caisse
 */
export const deleteMouvementCaisse = async (id: number): Promise<void> => {
  await apiService.delete(`${BASE_URL}/mouvements/${id}`)
}

/**
 * Récupérer le rapport "Grandes Lignes"
 */
export const getRapportGrandesLignes = async (params: {
  date_debut: string
  date_fin: string
  id_caisse?: number
}): Promise<RapportGrandesLignes> => {
  return apiService.get<RapportGrandesLignes>(
    `${BASE_URL}/rapport-grandes-lignes`,
    { params },
  )
}

/**
 * Récupérer le solde actuel de la caisse
 */
export const getSoldeCaisse = async (id_caisse?: number): Promise<number> => {
  const response = await apiService.get<{ solde: number }>(`${BASE_URL}/solde`, {
    params: id_caisse ? { id_caisse } : {},
  })
  return response.solde
}

/**
 * Récupérer la liste des caisses
 */
export const getCaisses = async (): Promise<Caisse[]> => {
  return apiService.get<Caisse[]>(`${BASE_URL}/caisses`)
}

/**
 * Récupérer une caisse par ID
 */
export const getCaisseById = async (id: number): Promise<Caisse> => {
  return apiService.get<Caisse>(`${BASE_URL}/caisses/${id}`)
}

/**
 * Valider un numéro de dossier
 */
export const validateNumeroDossier = async (numero: string): Promise<{ valid: boolean; message?: string }> => {
  return apiService.post<{ valid: boolean; message?: string }>(`${BASE_URL}/valider-numero`, {
    numero,
  })
}

/**
 * Récupérer le point de caisse (entrées, sorties, solde)
 */
export const getPointCaisse = async (
  date?: string,
  id_caisse?: number,
): Promise<PointCaisse> => {
  const params = new URLSearchParams()
  if (date) params.set('date', date)
  if (id_caisse != null) params.set('id_caisse', String(id_caisse))
  const qs = params.toString()
  const url = qs ? `${BASE_URL}/point?${qs}` : `${BASE_URL}/point`
  return apiService.get<PointCaisse>(url)
}

/** Journée consolidée (totaux + détail par caisse / agence) — caissier principal ou agence selon le rôle. */
export const getJourneeConsolidee = async (date?: string): Promise<{
  date_ref: string
  consolide: { entrees: number; sorties: number; mouvementsCount: number }
  par_caisse: Array<{
    id_caisse: number
    nom_caisse: string | null
    id_agence: number | null
    agence: { id: number; nom: string; code: string } | null
    solde_actuel: number
    point_du_jour: PointCaisse
  }>
}> => {
  return apiService.get(`${BASE_URL}/journee-consolidee`, {
    params: date ? { date } : {},
  })
}

export const getActiveSession = async (id_caisse: number) => {
  return apiService.get<any>(`${BASE_URL}/sessions/active`, { params: { id_caisse } })
}

export const getSessionHistory = async (id_caisse: number, limit = 20) => {
  return apiService.get<any[]>(`${BASE_URL}/sessions/history`, { params: { id_caisse, limit } })
}

export const openSession = async (payload: {
  id_caisse: number
  solde_ouverture_reel: number
  note?: string
}) => {
  return apiService.post<any>(`${BASE_URL}/sessions/open`, payload)
}

export const closeSession = async (
  sessionId: number,
  payload: { solde_fermeture_reel: number; note?: string },
) => {
  return apiService.post<any>(`${BASE_URL}/sessions/${sessionId}/close`, payload)
}

export const submitMouvement = async (mouvementId: number) => {
  return apiService.post<any>(`${BASE_URL}/mouvements/${mouvementId}/submit`)
}

export const validateMouvement = async (
  mouvementId: number,
  payload: { approve: boolean; reason?: string },
) => {
  return apiService.post<any>(`${BASE_URL}/mouvements/${mouvementId}/validate`, payload)
}

export const attachJustificatif = async (mouvementId: number, justificatif_url: string) => {
  return apiService.post<any>(`${BASE_URL}/mouvements/${mouvementId}/justificatif`, { justificatif_url })
}

export const getReconciliation = async (params?: { date?: string; id_caisse?: number }) => {
  return apiService.get<any>(`${BASE_URL}/reconciliation`, { params })
}

export const getCaisseAnomalies = async (params?: { date_debut?: string; date_fin?: string }) => {
  return apiService.get<any>(`${BASE_URL}/anomalies`, { params })
}

/**
 * Initier un transfert sécurisé
 */
export const initiateTransfer = async (payload: { id_caisse: number; montant: number; mode_transfert: string }) => {
  return apiService.post<any>(`${BASE_URL}/transfer/initiate`, payload)
}

/**
 * Récupérer les transferts en attente (En transit)
 */
export const getPendingTransfers = async () => {
  return apiService.get<any[]>(`${BASE_URL}/transfer/pending`)
}

/**
 * Confirmer la réception des fonds au siège
 */
export const confirmTransfer = async (mouvementId: number) => {
  return apiService.post<any>(`${BASE_URL}/transfer/confirm/${mouvementId}`)
}

/**
 * Service caisse exporté comme objet (pour compatibilité)
 */
export const caisseService = {
  createAppro,
  createDecaissement,
  createEntreeCaisse,
  getMouvementsCaisse,
  getMouvementCaisseById,
  updateMouvementCaisse,
  deleteMouvementCaisse,
  getRapportGrandesLignes,
  getSoldeCaisse,
  getCaisses,
  getCaisseById,
  validateNumeroDossier,
  getPointCaisse,
  getActiveSession,
  getSessionHistory,
  openSession,
  closeSession,
  submitMouvement,
  validateMouvement,
  attachJustificatif,
  getReconciliation,
  getCaisseAnomalies,
  initiateTransfer,
  getPendingTransfers,
  confirmTransfer,
}

