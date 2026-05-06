import { DashboardStats, PointCaisse } from '@types'
import { apiService } from './api.service'

class DashboardService {
  /**
   * Récupérer les statistiques du dashboard
   */
  async getStats(): Promise<DashboardStats> {
    return apiService.get<DashboardStats>('/dashboard/stats')
  }

  /**
   * Récupérer le point caisse du jour
   */
  async getPointCaisse(date?: string): Promise<PointCaisse> {
    const url = date ? `/dashboard/caisse?date=${date}` : '/dashboard/caisse'
    return apiService.get<PointCaisse>(url)
  }

  /**
   * Récupérer les activités récentes
   */
  async getRecentActivities(limit: number = 10): Promise<any[]> {
    return apiService.get<any[]>(`/dashboard/activities?limit=${limit}`)
  }

  /**
   * Récupérer les données pour les graphiques (IA Real-time)
   */
  async getChartData(): Promise<any[]> {
    return apiService.get<any[]>('/analytics/chart-data')
  }

  /**
   * Récupérer la répartition du trafic
   */
  async getTrafficRepartition(): Promise<any[]> {
    return apiService.get<any[]>('/analytics/traffic-repartition')
  }

  /**
   * Récupérer la performance journalière par agence (Directeur)
   */
  async getAgenciesPerformances(date?: string): Promise<any[]> {
    const url = date ? `/dashboard/agencies-performances?date=${date}` : '/dashboard/agencies-performances'
    return apiService.get<any[]>(url)
  }

  /**
   * Récupérer les recommandations IA
   */
  async getAIRecommendations(): Promise<any[]> {
    return apiService.get<any[]>('/analytics/recommendations')
  }

  /**
   * Monitoring IA V1: métriques + drift
   */
  async getAIMonitoring(): Promise<any> {
    return apiService.get<any>('/analytics/monitoring')
  }

  /**
   * Tableau de bord exécutif DG/Assistant DG — synthèse réseau complète
   */
  async getExecutiveSummary(): Promise<any> {
    return apiService.get<any>('/dashboard/executive-summary')
  }

  /**
   * Tableau de bord agence — Chef d'agence (données filtrées par agence)
   */
  async getAgenceSummary(): Promise<any> {
    return apiService.get<any>('/dashboard/agence-summary')
  }

  /**
   * Rapport hebdomadaire (génération à la demande)
   */
  async getWeeklyReport(): Promise<any> {
    return apiService.get<any>('/dashboard/weekly-report')
  }

  /**
   * Scoring des agences (semaine en cours ou passée)
   */
  async getAgencyScores(weeksAgo = 0): Promise<any[]> {
    return apiService.get<any[]>(`/dashboard/agency-scores?weeksAgo=${weeksAgo}`)
  }
}

export const dashboardService = new DashboardService()

// ── Services colis étendus ─────────────────────────────────────────────────

export const colisExtendedService = {
  /** Import en masse de colis depuis données Excel */
  batchImport: (rows: any[]): Promise<{ created: number; errors: any[] }> =>
    apiService.post('/colis/batch-import', { rows }),

  /** Validation en lot de colis */
  batchValidate: (ids: number[], statut: string): Promise<{ updated: number; errors: any[] }> =>
    apiService.post('/colis/batch-validate', { ids, statut }),

  /** Historique client par téléphone */
  getClientHistory: (phone: string): Promise<any> =>
    apiService.get(`/colis/client-history?phone=${encodeURIComponent(phone)}`),

  /** Calcul tarif automatique */
  calculateTarif: (poids_kg: number, destination: string, type_envoi?: string): Promise<any> => {
    const params = new URLSearchParams({ poids_kg: String(poids_kg), destination })
    if (type_envoi) params.set('type_envoi', type_envoi)
    return apiService.get(`/colis/calculate-tarif?${params.toString()}`)
  },
}
