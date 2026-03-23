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
}

export const dashboardService = new DashboardService()
