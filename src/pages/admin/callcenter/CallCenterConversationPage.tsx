import React, { useEffect, useMemo, useState } from 'react'
import { Link, useLocation, useParams } from 'react-router-dom'
import { Button, Card, Input, Space, Spin, Tag, Typography } from 'antd'
import { ArrowLeftOutlined } from '@ant-design/icons'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { CallCenterConversationRow, CallCenterMessageRow } from '@types'
import { callcenterService } from '@services/callcenter.service'
import { formatDate } from '@utils/format'
import { EmptyErrorState } from '@components/common/EmptyState'

const { Title, Text } = Typography

type LocationState = { conversation?: CallCenterConversationRow }

function inferRecipient(
  row: CallCenterConversationRow | undefined,
  messages: CallCenterMessageRow[],
): { channel: 'sms' | 'whatsapp'; to: string } | null {
  if (row?.customer_phone) {
    const ch =
      row.channel === 'whatsapp' || row.channel === 'sms'
        ? (row.channel as 'sms' | 'whatsapp')
        : 'sms'
    return { channel: ch, to: row.customer_phone }
  }
  const inbound = messages.find((m) => m.direction === 'in')
  if (inbound) {
    return { channel: inbound.channel, to: inbound.from_phone }
  }
  const outbound = messages.find((m) => m.direction === 'out')
  if (outbound) {
    return { channel: outbound.channel, to: outbound.to_phone }
  }
  return null
}

export const CallCenterConversationPage: React.FC = () => {
  const { conversationId } = useParams<{ conversationId: string }>()
  const id = conversationId ? Number.parseInt(conversationId, 10) : NaN
  const location = useLocation()
  const state = (location.state || {}) as LocationState
  const [row, setRow] = useState<CallCenterConversationRow | undefined>(state.conversation)
  const [reply, setReply] = useState('')
  const queryClient = useQueryClient()

  useEffect(() => {
    if (state.conversation) {
      setRow(state.conversation)
    }
  }, [state.conversation])

  useEffect(() => {
    if (!Number.isFinite(id)) return
    void callcenterService.markConversationRead(id).then(() => {
      void queryClient.invalidateQueries({ queryKey: ['callcenter-conversations'] })
    })
  }, [id, queryClient])

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['callcenter-messages', id],
    queryFn: () => callcenterService.getConversationMessages(id, { limit: 200, offset: 0 }),
    enabled: Number.isFinite(id),
  })

  const sendMutation = useMutation({
    mutationFn: async () => {
      const target = inferRecipient(row, data?.data ?? [])
      if (!target) throw new Error('Destinataire introuvable')
      return callcenterService.send({
        channel: target.channel,
        to: target.to,
        message: reply.trim(),
      })
    },
    onSuccess: () => {
      setReply('')
      void queryClient.invalidateQueries({ queryKey: ['callcenter-messages', id] })
      void queryClient.invalidateQueries({ queryKey: ['callcenter-conversations'] })
    },
  })

  const messages = useMemo(() => data?.data ?? [], [data?.data])

  const headerSubtitle = useMemo(() => {
    if (row) {
      return `${row.channel} · ${row.customer_phone}`
    }
    const t = inferRecipient(undefined, messages)
    return t ? `${t.channel} · ${t.to}` : `Conversation #${id}`
  }, [row, messages, id])

  if (!Number.isFinite(id)) {
    return (
      <div>
        <Title level={2}>Conversation</Title>
        <Text type="danger">Identifiant invalide.</Text>
      </div>
    )
  }

  if (error) {
    return <EmptyErrorState onRetry={() => refetch()} />
  }

  return (
    <div>
      <Space style={{ marginBottom: 16 }}>
        <Link to="/callcenter/inbox">
          <Button icon={<ArrowLeftOutlined />}>Retour à la boîte</Button>
        </Link>
      </Space>
      <Title level={2} style={{ marginBottom: 4 }}>
        Fil de messages
      </Title>
      <Text type="secondary" style={{ display: 'block', marginBottom: 16 }}>
        {headerSubtitle}
      </Text>

      <Card>
        {isLoading ? (
          <div style={{ textAlign: 'center', padding: 48 }}>
            <Spin size="large" />
          </div>
        ) : messages.length === 0 ? (
          <Text type="secondary">Aucun message dans cette conversation.</Text>
        ) : (
          <Space direction="vertical" style={{ width: '100%' }} size="middle">
            {messages.map((m) => {
              const isOut = m.direction === 'out'
              return (
                <div
                  key={m.id}
                  style={{
                    display: 'flex',
                    justifyContent: isOut ? 'flex-end' : 'flex-start',
                  }}
                >
                  <div
                    style={{
                      maxWidth: 'min(520px, 92%)',
                      padding: '10px 14px',
                      borderRadius: 12,
                      background: isOut ? '#1677ff' : 'var(--ant-color-fill-quaternary, #f0f0f0)',
                      color: isOut ? '#fff' : 'inherit',
                    }}
                  >
                    <Space size="small" wrap style={{ marginBottom: 6, opacity: 0.9 }}>
                      <Tag color={isOut ? 'blue' : 'default'}>{m.direction === 'out' ? 'Sortant' : 'Entrant'}</Tag>
                      <Text style={{ fontSize: 12, color: isOut ? 'rgba(255,255,255,0.85)' : undefined }}>
                        {formatDate(m.created_at)}
                      </Text>
                    </Space>
                    <div style={{ whiteSpace: 'pre-wrap' }}>{m.message}</div>
                    <Text
                      style={{
                        fontSize: 11,
                        display: 'block',
                        marginTop: 6,
                        color: isOut ? 'rgba(255,255,255,0.75)' : undefined,
                      }}
                    >
                      {m.from_phone} → {m.to_phone}
                    </Text>
                  </div>
                </div>
              )
            })}
          </Space>
        )}

        <div style={{ marginTop: 24 }}>
          <Input.TextArea
            rows={3}
            value={reply}
            onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setReply(e.target.value)}
            placeholder="Réponse SMS / WhatsApp…"
            maxLength={4000}
            showCount
          />
          <Button
            type="primary"
            style={{ marginTop: 8 }}
            loading={sendMutation.isPending}
            disabled={!reply.trim() || !inferRecipient(row, messages)}
            onClick={() => sendMutation.mutate()}
          >
            Envoyer
          </Button>
          {!inferRecipient(row, messages) ? (
            <Text type="secondary" style={{ display: 'block', marginTop: 8 }}>
              Ouvrez cette conversation depuis la liste pour associer le numéro client, ou attendez un message entrant.
            </Text>
          ) : null}
        </div>
      </Card>
    </div>
  )
}
