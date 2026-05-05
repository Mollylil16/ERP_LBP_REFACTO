import { apiService } from './api.service'

export interface SupervisionKpis {
  date: string
  colisCrees: number
  factures: number
  totalEncaissementsValides: number
  agences: number
}

export interface SupervisionAgenceRow {
  agence: { id: number; code: string; nom: string; ville?: string; actif: boolean }
  colis_aujourdhui: number
  id_caisse: number | null
  solde_caisse: number
  statut: string
}

export interface SupervisionRapportRow {
  id: number
  type: string
  periode: string
  id_agence: number | null
  date_debut: string | null
  date_fin: string | null
  commentaire: string | null
  statut_lecture: string
  created_at: string
  agence?: { id: number; code: string; nom: string } | null
}

export type SoumettreRapportPayload = {
  type: string
  periode: string
  agenceId?: number
  dateDebut: string
  dateFin: string
  commentaire?: string
}

export type SignalementPayload = {
  agenceId?: number
  type: string
  description: string
  gravite: string
}

export type DemanderJustificationPayload = {
  agenceId: number
  motif: string
  agentId?: number
  chefAgenceId?: number
  operationId?: string
}

export type AnnotationPayload = {
  cible: string
  cibleId: string
  contenu: string
}

/** Réponse structurée de `detectAnomalies` (caisse) */
export type SupervisionAnomaliesPayload = {
  range: { date_debut: string; date_fin: string }
  anomalies: {
    doublons_paiements: Array<{
      id_facture?: number | string
      montant?: string | number
      mode_paiement?: string
      date_paiement?: string
      occurrences?: string | number
    }>
    incoherences_montants_factures: Array<{
      id: number
      num_facture: string
      montant_ttc: number
      montant_paye: number
    }>
    trous_sequence_factures: Array<{ prefix: string; missing: number[] }>
  }
  summary: {
    doublons: number
    incoherences: number
    sequences_avec_trous: number
  }
}

export type SupervisionAnomaliesUnavailable = {
  message: string
  donnees: null
}

export interface SupervisionSignalementRow {
  id: number
  type: string
  description: string | null
  gravite: string
  statut: string
  created_at: string
  agence?: { id: number; code: string; nom: string } | null
  auteur?: { id: number; username: string; fullname?: string } | null
}

export interface SupervisionJustificationRow {
  id: number
  motif: string
  statut: string
  id_operation: string | null
  reponse: string | null
  created_at: string
  agence?: { id: number; code: string; nom: string } | null
  demandeur?: { id: number; username: string } | null
  destinataire?: { id: number; username: string; fullname?: string } | null
}

export interface SupervisionAnnotationRow {
  id: number
  cible: string
  cible_id: string
  contenu: string
  visibilite: string
  created_at: string
  auteur?: { id: number; username: string } | null
}

export interface SupervisionAgentRow {
  id: number
  username: string
  nom_complet: string | null
  role_code: string
  agence_nom: string | null
}

class SupervisionService {
  getKpis() {
    return apiService.get<SupervisionKpis>('/supervision/kpis')
  }

  getEtatAgences() {
    return apiService.get<SupervisionAgenceRow[]>('/supervision/agences')
  }

  getAnomalies(debut?: string, fin?: string) {
    const q = new URLSearchParams()
    if (debut) q.set('debut', debut)
    if (fin) q.set('fin', fin)
    const qs = q.toString()
    return apiService.get<SupervisionAnomaliesPayload | SupervisionAnomaliesUnavailable>(
      `/supervision/anomalies${qs ? `?${qs}` : ''}`,
    )
  }

  getRapports() {
    return apiService.get<SupervisionRapportRow[]>('/supervision/rapports')
  }

  soumettreRapport(data: SoumettreRapportPayload) {
    return apiService.post<SupervisionRapportRow>('/supervision/rapports', data)
  }

  signalerAnomalie(data: SignalementPayload) {
    return apiService.post<unknown>('/supervision/signalements', data)
  }

  demanderJustification(data: DemanderJustificationPayload) {
    return apiService.post<unknown>('/supervision/demandes-justification', data)
  }

  creerAnnotation(data: AnnotationPayload) {
    return apiService.post<unknown>('/supervision/annotations', data)
  }

  getInsightsKpis(debut: string, fin: string) {
    const q = new URLSearchParams({ debut, fin })
    return apiService.get<{
      periode: { debut: string; fin: string; label: string }
      colisCrees: number
      facturesEmises: number
      encaissementsValides: number
      nouveauxClients: number
      nbAgences: number
    }>(`/supervision/insights/kpis?${q.toString()}`)
  }

  getInsightsActivity(debut: string, fin: string, bucket: 'day' | 'month' = 'day') {
    const q = new URLSearchParams({ debut, fin, bucket })
    return apiService.get<{ point: string; colis: number; factures: number }[]>(
      `/supervision/insights/activity?${q.toString()}`,
    )
  }

  getRevenueYears(from: number, to: number) {
    return apiService.get<
      { annee: number; encaissements_valides: number; nb_paiements: number }[]
    >(`/supervision/insights/revenue-years?from=${from}&to=${to}`)
  }

  getCompareYears(a1: number, a2: number) {
    return apiService.get<{
      annees: number[]
      encaissements: Record<number, number>
      ecart_pourcent: number | null
    }>(`/supervision/insights/compare-years?a1=${a1}&a2=${a2}`)
  }

  getProjection() {
    return apiService.get<{
      methodologie: string
      base_moyenne_mensuelle: number
      encaissement_annee_reference_estime: number
      avertissement: string
    }>('/supervision/insights/projection')
  }

  getUserProductivity(debut: string, fin: string) {
    const q = new URLSearchParams({ debut, fin })
    return apiService.get<{
      periode: { debut: string; fin: string }
      utilisateurs: Array<{
        id: number
        username: string
        nom_complet: string
        role_code: string
        agence_nom: string | null
        colis_saisis: number
        factures_saisies: number
        operations_total: number
        indice_activite: number
        niveau_activite: string
      }>
    }>(`/supervision/insights/user-productivity?${q.toString()}`)
  }

  getCaisseReseau(debut: string, fin: string) {
    const q = new URLSearchParams({ debut, fin })
    return apiService.get<{
      periode: { debut: string; fin: string }
      id_caisse_principale: number | null
      caisses: Array<{
        agence: { id: number; code: string; nom: string }
        id_caisse: number
        nom_caisse: string
        solde_actuel: number
        volume_entrees_periode: number
        est_caisse_principale: boolean
      }>
    }>(`/supervision/insights/caisse-reseau?${q.toString()}`)
  }

  getPerformanceAgents() {
    return apiService.get<{ par_agence_role: unknown[] }>('/supervision/performance-agents')
  }

  getSignalements() {
    return apiService.get<SupervisionSignalementRow[]>('/supervision/signalements')
  }

  getJustifications() {
    return apiService.get<SupervisionJustificationRow[]>('/supervision/demandes-justification')
  }

  getAnnotations() {
    return apiService.get<SupervisionAnnotationRow[]>('/supervision/annotations')
  }

  getAgents() {
    return apiService.get<SupervisionAgentRow[]>('/supervision/agents')
  }
}

export const supervisionService = new SupervisionService()
