import { apiService } from './api.service'

export interface DonneesMensuelles {
  mois: string
  groupage: number
  autresEnvois: number
  total: number
  revenus?: number
}

export interface DonneesAnnuelles {
  annee: number
  mois: DonneesMensuelles[]
  totalColis: number
  totalRevenus: number
  moyenneMensuelle: number
}

export interface TendancesMensuelles {
  mois: string
  meilleureAnnee: number
  meilleureValeur: number
  pireAnnee: number
  pireValeur: number
  moyenne: number
  evolution: number // Pourcentage d'évolution
  tendance: 'hausse' | 'baisse' | 'stable'
}

class StatistiquesService {
  /**
   * Récupérer les données historiques pour plusieurs années
   */
  async getHistorique(annees: number[]): Promise<DonneesAnnuelles[]> {
    return apiService.get<DonneesAnnuelles[]>(`/rapports/historique?annees=${annees.join(',')}`);
  }

  /**
   * Récupérer l'analyse des tendances mensuelles
   */
  async getTendancesMensuelles(annees: number[]): Promise<TendancesMensuelles[]> {
    return apiService.get<TendancesMensuelles[]>(`/rapports/tendances?annees=${annees.join(',')}`);
  }

  /**
   * Récupérer les recommandations stratégiques dynamiques (IA)
   */
  async getAIRecommendations(): Promise<any[]> {
    return apiService.get<any[]>('/analytics/recommendations');
  }

  /**
   * Lot 3 - Rentabilité réelle:
   * marge unitaire, P&L agence/produit/destination, impayés et cohortes 30/60/90.
   */
  async getRealProfitability(params?: {
    date_debut?: string
    date_fin?: string
    agence_id?: number
  }): Promise<any> {
    const queryParams = new URLSearchParams()
    if (params?.date_debut) queryParams.append('date_debut', params.date_debut)
    if (params?.date_fin) queryParams.append('date_fin', params.date_fin)
    if (typeof params?.agence_id === 'number') queryParams.append('agence_id', String(params.agence_id))

    const qs = queryParams.toString()
    return apiService.get<any>(`/analytics/profitability/real${qs ? `?${qs}` : ''}`)
  }

  /**
   * Lot 3 - Simulateur tarifaire (scénarios).
   */
  async simulatePricingScenario(params: {
    price_change_pct?: number
    cost_change_pct?: number
    volume_change_pct?: number
    date_debut?: string
    date_fin?: string
    agence_id?: number
  }): Promise<any> {
    const queryParams = new URLSearchParams()
    if (typeof params.price_change_pct === 'number') queryParams.append('price_change_pct', String(params.price_change_pct))
    if (typeof params.cost_change_pct === 'number') queryParams.append('cost_change_pct', String(params.cost_change_pct))
    if (typeof params.volume_change_pct === 'number') queryParams.append('volume_change_pct', String(params.volume_change_pct))
    if (params.date_debut) queryParams.append('date_debut', params.date_debut)
    if (params.date_fin) queryParams.append('date_fin', params.date_fin)
    if (typeof params.agence_id === 'number') queryParams.append('agence_id', String(params.agence_id))

    return apiService.get<any>(`/analytics/profitability/scenario?${queryParams.toString()}`)
  }
}

export const statistiquesService = new StatistiquesService()
