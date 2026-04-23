import { apiService } from './api.service'

export type Groupeur = {
  id: string
  user_id: number | null
  user?: { id: number; username: string } | null
  code: string
  raison_sociale: string
  nom_commercial: string | null
  type: string
  pays: string | null
  ville: string | null
  adresse: string | null
  telephone: string | null
  email_contact: string | null
  numero_registre: string | null
  corridors: string | null
  modes_transport: string | null
  statut: string
  created_at: string
  updated_at: string
}

class GroupeursService {
  // ── ADMIN ───────────────────────────────────────────────────────────────────
  listAdmin(filters?: Record<string, unknown>) {
    return apiService.get<Groupeur[]>('/groupeurs/admin', { params: filters })
  }

  getStats() {
    return apiService.get<{ groupeurs_actifs: number; groupeurs_total: number }>('/groupeurs/admin/stats')
  }

  getDetail(id: string) {
    return apiService.get<Groupeur>(`/groupeurs/admin/${id}`)
  }

  getCompte(id: string) {
    return apiService.get<{ user_id: number | null; username: string | null; password_changed: boolean | null }>(
      `/groupeurs/admin/${id}/compte`,
    )
  }

  getActivite(id: string) {
    return apiService.get<{
      devis: unknown[]
      expeditions: unknown[]
      factures: unknown[]
      documents: unknown[]
    }>(`/groupeurs/admin/${id}/activite`)
  }

  create(dto: Partial<Groupeur>) {
    return apiService.post<Groupeur>('/groupeurs/admin', dto)
  }

  update(id: string, dto: Partial<Groupeur>) {
    return apiService.put<Groupeur>(`/groupeurs/admin/${id}`, dto)
  }

  changeStatut(id: string, body: { statut: string; motif?: string }) {
    return apiService.put<Groupeur>(`/groupeurs/admin/${id}/statut`, body)
  }

  // ── ESPACE GROUPEUR ─────────────────────────────────────────────────────────
  getMyDashboard() {
    return apiService.get<{
      groupeur: Groupeur
      kpis: { devis_total: number; expeditions_actives: number; factures_impayees: number }
    }>('/groupeurs/espace/dashboard')
  }

  myDevis() {
    return apiService.get<unknown[]>('/groupeurs/espace/devis')
  }
  createDevis(dto: any) {
    return apiService.post<unknown>('/groupeurs/espace/devis', dto)
  }

  myExpeditions() {
    return apiService.get<unknown[]>('/groupeurs/espace/expeditions')
  }
  createExpedition(dto: any) {
    return apiService.post<unknown>('/groupeurs/espace/expeditions', dto)
  }
  updateExpeditionStatut(id: string, body: { statut: string; notes?: string }) {
    return apiService.put<unknown>(`/groupeurs/espace/expeditions/${id}/statut`, body)
  }

  myFactures() {
    return apiService.get<unknown[]>('/groupeurs/espace/factures')
  }
  createFacture(dto: any) {
    return apiService.post<unknown>('/groupeurs/espace/factures', dto)
  }
  updateFacture(id: string, dto: any) {
    return apiService.put<unknown>(`/groupeurs/espace/factures/${id}`, dto)
  }

  myDocuments() {
    return apiService.get<unknown[]>('/groupeurs/espace/documents')
  }
  uploadDocument(dto: any) {
    return apiService.post<unknown>('/groupeurs/espace/documents/upload', dto)
  }
  deleteDocument(id: string) {
    return apiService.delete<unknown>(`/groupeurs/espace/documents/${id}`)
  }

  getProfil() {
    return apiService.get<Groupeur>('/groupeurs/espace/profil')
  }
  updateProfil(dto: any) {
    return apiService.put<Groupeur>('/groupeurs/espace/profil', dto)
  }
}

export const groupeursService = new GroupeursService()

