import type {
  CallCenterConversationSummary,
  CallCenterConversationsResponse,
  CallCenterMessagesResponse,
} from '@types'
import { apiService } from './api.service'

class CallCenterService {
  async listConversations(params?: {
    page?: number
    limit?: number
    channel?: 'sms' | 'whatsapp'
    q?: string
    unread_only?: boolean
    date_from?: string
    date_to?: string
    read_status?: 'all' | 'unread' | 'read'
    case_status?: 'all' | 'open' | 'in_progress' | 'resolved'
    agence_id?: number
  }): Promise<CallCenterConversationsResponse> {
    const q = new URLSearchParams()
    if (params?.page) q.set('page', String(params.page))
    if (params?.limit) q.set('limit', String(params.limit))
    if (params?.channel) q.set('channel', params.channel)
    const trimmed = (params?.q ?? '').trim()
    if (trimmed) q.set('q', trimmed)
    if (params?.unread_only) q.set('unread_only', '1')
    if (params?.date_from) q.set('date_from', params.date_from)
    if (params?.date_to) q.set('date_to', params.date_to)
    if (params?.read_status && params.read_status !== 'all') {
      q.set('read_status', params.read_status)
    }
    if (params?.case_status && params.case_status !== 'all') {
      q.set('case_status', params.case_status)
    }
    if (params?.agence_id != null) q.set('agence_id', String(params.agence_id))
    const qs = q.toString()
    return apiService.get<CallCenterConversationsResponse>(
      qs ? `/callcenter/conversations?${qs}` : '/callcenter/conversations',
    )
  }

  async getConversationSummary(conversationId: number): Promise<CallCenterConversationSummary> {
    return apiService.get<CallCenterConversationSummary>(
      `/callcenter/conversations/${conversationId}/summary`,
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

  async setConversationCaseStatus(
    conversationId: number,
    case_status: 'open' | 'in_progress' | 'resolved',
  ): Promise<unknown> {
    return apiService.patch(`/callcenter/conversations/${conversationId}/case-status`, {
      case_status,
    })
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
