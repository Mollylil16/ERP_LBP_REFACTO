import React, { useEffect, useMemo, useState } from 'react'
import { Link, useLocation, useParams } from 'react-router-dom'
import {
  Button,
  Card,
  Descriptions,
  Dropdown,
  Input,
  Select,
  Space,
  Spin,
  Tag,
  Typography,
  message,
} from 'antd'
import { ArrowLeftOutlined } from '@ant-design/icons'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { CallCenterConversationRow, CallCenterMessageRow } from '@types'
import { callcenterService } from '@services/callcenter.service'
import { formatDate } from '@utils/format'
import { EmptyErrorState } from '@components/common/EmptyState'
import { WithPermission } from '@components/common/WithPermission'
import { PERMISSIONS } from '@constants/permissions'
import { buildWhatsAppChatUrl } from '@utils/whatsapp'
import {
  CALLCENTER_MESSAGE_TEMPLATES,
  applyCallcenterTemplate,
  type CallcenterTemplateVars,
} from '@utils/callcenter-templates'

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

  const { data: summary, isLoading: summaryLoading } = useQuery({
    queryKey: ['callcenter-summary', id],
    queryFn: () => callcenterService.getConversationSummary(id),
    enabled: Number.isFinite(id),
  })

  const caseStatusMutation = useMutation({
    mutationFn: (case_status: 'open' | 'in_progress' | 'resolved') =>
      callcenterService.setConversationCaseStatus(id, case_status),
    onSuccess: () => {
      message.success('Statut dossier enregistré')
      void queryClient.invalidateQueries({ queryKey: ['callcenter-summary', id] })
      void queryClient.invalidateQueries({ queryKey: ['callcenter-conversations'] })
    },
    onError: () => message.error('Mise à jour impossible'),
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
      void queryClient.invalidateQueries({ queryKey: ['callcenter-summary', id] })
    },
  })

  const messages = useMemo(() => data?.data ?? [], [data?.data])

  const templateVars: CallcenterTemplateVars = useMemo(
    () => ({
      nom_client: summary?.client?.nom_exp,
      tel_client: summary?.client?.tel_exp ?? summary?.customer_phone,
      ref_colis: summary?.last_colis?.ref_colis,
      num_facture: summary?.last_facture?.num_facture,
    }),
    [summary],
  )

  const templateMenuItems = useMemo(
    () =>
      CALLCENTER_MESSAGE_TEMPLATES.map((t) => ({
        key: t.id,
        label: t.label,
        onClick: () => {
          const text = applyCallcenterTemplate(t.body, templateVars)
          setReply((prev) => (prev ? `${prev.trim()}\n\n${text}` : text))
        },
      })),
    [templateVars],
  )

  const headerSubtitle = useMemo(() => {
    if (row) {
      return `${row.channel} · ${row.customer_phone}`
    }
    const t = inferRecipient(undefined, messages)
    return t ? `${t.channel} · ${t.to}` : `Conversation #${id}`
  }, [row, messages, id])

  const waUrl = useMemo(() => {
    const t = inferRecipient(row, messages)
    return t?.to ? buildWhatsAppChatUrl(t.to) : null
  }, [row, messages])

  const clientSearch =
    summary?.client?.nom_exp?.trim() ||
    summary?.customer_phone ||
    summary?.client?.tel_exp ||
    (summary?.client?.id != null ? String(summary.client.id) : '')

  const litigeCreateHref = useMemo(() => {
    if (summary?.client?.id == null) return null
    const qs = new URLSearchParams()
    qs.set('client_id', String(summary.client.id))
    qs.set('phone', summary.customer_phone || summary.client.tel_exp || '')
    if (summary.last_facture?.id != null) qs.set('facture_id', String(summary.last_facture.id))
    if (summary.last_colis?.ref_colis) qs.set('colis_ref', summary.last_colis.ref_colis)
    qs.set('from', 'callcenter')
    return `/litiges?${qs.toString()}`
  }, [summary])

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
      <Space style={{ marginBottom: 16 }} wrap>
        <Text type="secondary">{headerSubtitle}</Text>
        {waUrl ? (
          <Button
            size="small"
            onClick={() => window.open(waUrl, '_blank', 'noopener,noreferrer')}
          >
            Ouvrir WhatsApp
          </Button>
        ) : null}
        <WithPermission permission={PERMISSIONS.CLIENTS.READ}>
          {clientSearch ? (
            <Link to={`/clients?search=${encodeURIComponent(clientSearch)}`}>
              <Button size="small">Fiche clients</Button>
            </Link>
          ) : null}
        </WithPermission>
        <WithPermission permission={PERMISSIONS.LITIGES.CREATE}>
          {litigeCreateHref ? (
            <Link to={litigeCreateHref}>
              <Button size="small">Nouveau litige</Button>
            </Link>
          ) : null}
        </WithPermission>
        <WithPermission permission={PERMISSIONS.LITIGES.VIEW}>
          {summary?.last_litige?.id != null ? (
            <Link to={`/litiges/${summary.last_litige.id}`}>
              <Button size="small">Litige lié</Button>
            </Link>
          ) : null}
        </WithPermission>
        <WithPermission permission={PERMISSIONS.FACTURES.READ}>
          {summary?.last_facture?.id != null ? (
            <Link to={`/factures/${summary.last_facture.id}/preview`}>
              <Button size="small">Facture liée</Button>
            </Link>
          ) : null}
        </WithPermission>
      </Space>

      <Card title="Résumé dossier" style={{ marginBottom: 16 }} loading={summaryLoading}>
        {!summary?.found ? (
          <Text type="secondary">Conversation introuvable.</Text>
        ) : (
          <Space direction="vertical" style={{ width: '100%' }} size="middle">
            <Space wrap align="center">
              <Text strong>Statut dossier :</Text>
              <Select<'open' | 'in_progress' | 'resolved'>
                style={{ width: 200 }}
                value={(summary.case_status as 'open' | 'in_progress' | 'resolved') ?? 'open'}
                loading={caseStatusMutation.isPending}
                onChange={(v: 'open' | 'in_progress' | 'resolved') =>
                  caseStatusMutation.mutate(v)
                }
                options={[
                  { value: 'open', label: 'Ouvert' },
                  { value: 'in_progress', label: 'En cours' },
                  { value: 'resolved', label: 'Résolu' },
                ]}
              />
            </Space>
            <Descriptions size="small" column={{ xs: 1, sm: 2 }} bordered>
              <Descriptions.Item label="Client">
                {summary.client
                  ? `${summary.client.nom_exp} (${summary.client.tel_exp})`
                  : '—'}
              </Descriptions.Item>
              <Descriptions.Item label="Dernier colis">
                {summary.last_colis
                  ? `${summary.last_colis.ref_colis}${
                      summary.last_colis.date_envoi
                        ? ` · ${formatDate(summary.last_colis.date_envoi)}`
                        : ''
                    }`
                  : '—'}
              </Descriptions.Item>
              <Descriptions.Item label="Dernière facture (liée)">
                {summary.last_facture?.num_facture ?? '—'}
              </Descriptions.Item>
              <Descriptions.Item label="Dernier litige (lié)">
                {summary.last_litige?.num_litige ?? '—'}
              </Descriptions.Item>
            </Descriptions>
          </Space>
        )}
      </Card>

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
          <Space style={{ marginBottom: 8 }} wrap>
            <Dropdown menu={{ items: templateMenuItems }} trigger={['click']}>
              <Button>Modèles de message</Button>
            </Dropdown>
          </Space>
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
