import { apiService } from './api.service'

export interface PrestataireRow {
  id: number
  nom: string
  pays?: string | null
  actif: boolean
}

export interface FacturePrestataireRow {
  id: number
  agence?: { id: number; nom: string }
  pays?: string | null
  prestataire?: { id: number; nom: string }
  date_reception: string
  numero_lta?: string | null
  numero_envoi?: string | null
  numero_facture: string
  montant_total: number
  devise: string
  delai_reglement_jours?: number | null
  date_echeance: string
  statut: string
  montant_regle: number
  reliquat: number
  note?: string | null
  reglements?: ReglementPrestataireRow[]
}

export interface ReglementPrestataireRow {
  id: number
  facture?: { id: number; numero_facture?: string; devise?: string; pays?: string; agence?: { id: number; nom: string }; prestataire?: { id: number; nom: string } } | null
  date_reglement: string
  mode_reglement: 'ESPECE' | 'VIREMENT' | 'CHEQUE' | 'MOBILE' | 'AUTRE'
  montant: number
  reference?: string | null
  note?: string | null
  origine_fonds: 'CAISSE_PRINCIPALE' | 'AGENCE'
  hub_retrait_status: 'NA' | 'A_RETIRER' | 'RETIRE'
  hub_retrait_approval_status: 'NA' | 'PENDING' | 'APPROVED' | 'REJECTED'
  hub_retrait_marked_at?: string | null
  hub_retrait_marked_by?: string | null
}

export const prestatairesService = {
  listPrestataires: (params?: any) =>
    apiService.get<PrestataireRow[]>('/prestataires', { params }),
  createPrestataire: (body: Partial<PrestataireRow>) =>
    apiService.post<PrestataireRow>('/prestataires', body),

  listFactures: (params?: any) =>
    apiService.get<FacturePrestataireRow[]>('/prestataires/factures', { params }),
  getFacture: (id: number) =>
    apiService.get<FacturePrestataireRow>(`/prestataires/factures/${id}`),
  createFacture: (body: any) =>
    apiService.post<FacturePrestataireRow>('/prestataires/factures', body),

  addReglement: (factureId: number, body: any) =>
    apiService.post<{ reglement: ReglementPrestataireRow; facture: FacturePrestataireRow }>(
      `/prestataires/factures/${factureId}/reglements`,
      body,
    ),

  listRetraitsHub: (params?: any) =>
    apiService.get<ReglementPrestataireRow[]>('/prestataires/retraits-hub', { params }),
  markRetraitHubRetire: (reglementId: number) =>
    apiService.patch<ReglementPrestataireRow>(
      `/prestataires/retraits-hub/${reglementId}/mark-retire`,
      {},
    ),
  requestRetraitApproval: (reglementId: number) =>
    apiService.patch<ReglementPrestataireRow>(
      `/prestataires/retraits-hub/${reglementId}/request-approval`,
      {},
    ),
  decideRetraitApproval: (reglementId: number, body: { approve: boolean; reason?: string }) =>
    apiService.patch<ReglementPrestataireRow>(
      `/prestataires/retraits-hub/${reglementId}/decide-approval`,
      body,
    ),
}

