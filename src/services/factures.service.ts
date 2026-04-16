import { FactureColis, Colis, PaginatedResponse, PaginationParams } from '@types'
import { apiService } from './api.service'
import { normalizeCalendarDate } from '@utils/format'

function adaptFactureFromBackend(backendFacture: any): FactureColis {
  const dateFacture =
    normalizeCalendarDate(backendFacture.date_facture) ??
    normalizeCalendarDate(backendFacture.dateFacture) ??
    normalizeCalendarDate(backendFacture.created_at ?? backendFacture.createdAt)

  return {
    id: backendFacture.id,
    num_facture: backendFacture.num_facture,
    montant_ttc: Number(backendFacture.montant_ttc || 0),
    montant_paye: Number(backendFacture.montant_paye || 0),
    id_colis: backendFacture.colis?.id,
    colis: backendFacture.colis,
    ref_colis: backendFacture.colis?.ref_colis || '',
    code_user: backendFacture.code_user || '',
    etat: backendFacture.etat,
    date_facture: dateFacture ?? '',
  }
}

class FacturesService {
  /**
   * Récupérer la liste des factures
   */
  async getFactures(
    type?: 'proforma' | 'definitive',
    params?: PaginationParams
  ): Promise<PaginatedResponse<FactureColis>> {
    const queryParams = new URLSearchParams()
    if (type) queryParams.append('type', type)
    if (params?.page) queryParams.append('page', params.page.toString())
    if (params?.limit) queryParams.append('limit', params.limit.toString())
    if (params?.search) queryParams.append('search', params.search)

    const response = await apiService.get<any>(`/factures?${queryParams.toString()}`)

    // Si c'est un tableau simple (backend sans pagination)
    if (Array.isArray(response)) {
      return {
        data: response.map(adaptFactureFromBackend),
        total: response.length,
        page: params?.page || 1,
        limit: params?.limit || response.length,
        total_pages: 1
      }
    }

    // Si c'est une PaginatedResponse
    return {
      ...response,
      data: (response.data || []).map(adaptFactureFromBackend)
    }
  }

  /**
   * Récupérer une facture par son ID
   */
  async getFactureById(id: number): Promise<FactureColis> {
    const data = await apiService.get<any>(`/factures/${id}`)
    return adaptFactureFromBackend(data)
  }

  /**
   * Récupérer une facture par numéro
   */
  async getFactureByNum(numFacture: string): Promise<FactureColis> {
    const data = await apiService.get<any>(
      `/factures/num/${encodeURIComponent(numFacture.trim())}`,
    )
    return adaptFactureFromBackend(data)
  }

  /** Recherche encaissement : n° facture, ref colis ou téléphone client */
  async getEncaissementLookup(q: string): Promise<FactureColis | null> {
    const t = (q || '').trim()
    if (!t) return null
    const data = await apiService.get<any | null>(
      `/factures/encaissement-lookup?q=${encodeURIComponent(t)}`,
    )
    return data ? adaptFactureFromBackend(data) : null
  }

  /**
   * Récupérer la facture d'un colis
   */
  async getFactureByColis(refColis: string): Promise<FactureColis | null> {
    const data = await apiService.get<any | null>(`/factures/colis/${refColis}`)
    return data ? adaptFactureFromBackend(data) : null
  }

  /**
   * Créer une facture proforma pour un colis
   */
  async createFactureProforma(colisId: number): Promise<FactureColis> {
    const data = await apiService.post<any>(`/factures/generate/${colisId}`, {})
    return adaptFactureFromBackend(data)
  }

  /**
   * Valider une facture proforma (génère facture définitive)
   */
  async validateFacture(id: number): Promise<FactureColis> {
    const data = await apiService.patch<any>(`/factures/${id}/validate`)
    return adaptFactureFromBackend(data)
  }

  /**
   * Annuler une facture
   */
  async cancelFacture(id: number): Promise<void> {
    return apiService.patch<void>(`/factures/${id}/cancel`)
  }

  /**
   * Générer le PDF d'une facture
   */
  async generatePDF(id: number): Promise<Blob> {
    const response = await apiService.instance.get(`/factures/${id}/pdf`, {
      responseType: 'arraybuffer', // Plus robuste que 'blob' avec certains axios versions
    })
    return new Blob([response.data], { type: 'application/pdf' })
  }

  /**
   * Télécharger le PDF d'une facture
   */
  async downloadPDF(id: number, filename?: string): Promise<void> {
    try {
      const blob = await this.generatePDF(id)
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', filename || `facture-${id}.pdf`)

      // Indispensable pour certains navigateurs
      document.body.appendChild(link)
      link.click()

      // Nettoyage avec petit délai
      setTimeout(() => {
        document.body.removeChild(link)
        window.URL.revokeObjectURL(url)
      }, 100)
    } catch (error) {
      console.error('Erreur lors du téléchargement du PDF:', error)
      throw error
    }
  }

  /**
   * Imprimer une facture
   */
  async printFacture(id: number): Promise<void> {
    const blob = await this.generatePDF(id)
    const url = window.URL.createObjectURL(blob)
    const printWindow = window.open(url, '_blank')
    if (printWindow) {
      printWindow.onload = () => {
        printWindow.print()
      }
    }
  }
}

export const facturesService = new FacturesService()
