import type {
  CallCenterConversationsResponse,
  CallCenterMessagesResponse,
} from '@types'
import { apiService } from './api.service'

class CallCenterService {
  async listConversations(params?: {
    page?: number
    limit?: number
    channel?: 'sms' | 'whatsapp'
  }): Promise<CallCenterConversationsResponse> {
    const q = new URLSearchParams()
    if (params?.page) q.set('page', String(params.page))
    if (params?.limit) q.set('limit', String(params.limit))
    if (params?.channel) q.set('channel', params.channel)
    const qs = q.toString()
    return apiService.get<CallCenterConversationsResponse>(
      qs ? `/callcenter/conversations?${qs}` : '/callcenter/conversations',
    )
  }

  async getConversationMessages(
    conversationId: number,
    params?: { limit?: number; offset?: number },
  ): Promise<CallCenterMessagesResponse> {
    const q = new URLSearchParams()
    if (params?.limit != null) q.set('limit', String(params.limit))
    if (params?.offset != null) q.set('offset', String(params.offset))
    const qs = q.toString()
    return apiService.get<CallCenterMessagesResponse>(
      qs
        ? `/callcenter/conversations/${conversationId}/messages?${qs}`
        : `/callcenter/conversations/${conversationId}/messages`,
    )
  }

  async markConversationRead(conversationId: number): Promise<{ ok: boolean }> {
    return apiService.patch<{ ok: boolean }>(`/callcenter/conversations/${conversationId}/read`, {})
  }

  async send(body: {
    channel: 'sms' | 'whatsapp'
    to: string
    message: string
  }): Promise<{ ok: boolean }> {
    return apiService.post<{ ok: boolean }>('/callcenter/send', body)
  }
}

export const callcenterService = new CallCenterService()
