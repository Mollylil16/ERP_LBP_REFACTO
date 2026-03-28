import type { LitigeDetail, LitigeMessageItem, LitigesListResponse } from '@types'
import { apiService } from './api.service'

class LitigesService {
  async list(params?: { page?: number; limit?: number; statut?: string }): Promise<LitigesListResponse> {
    const q = new URLSearchParams()
    if (params?.page) q.set('page', String(params.page))
    if (params?.limit) q.set('limit', String(params.limit))
    if (params?.statut) q.set('statut', params.statut)
    const qs = q.toString()
    return apiService.get<LitigesListResponse>(qs ? `/litiges?${qs}` : '/litiges')
  }

  async getById(id: number): Promise<LitigeDetail> {
    return apiService.get<LitigeDetail>(`/litiges/${id}`)
  }

  async addMessage(
    litigeId: number,
    body: { contenu: string; type?: string; interne?: boolean },
  ): Promise<LitigeMessageItem> {
    return apiService.post<LitigeMessageItem>(`/litiges/${litigeId}/messages`, body)
  }
}

export const litigesService = new LitigesService()
