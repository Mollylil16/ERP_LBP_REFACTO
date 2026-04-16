import React, { useCallback, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  Button,
  Card,
  DatePicker,
  Input,
  Pagination,
  Select,
  Space,
  Table,
  Tag,
  Typography,
} from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useQuery } from '@tanstack/react-query'
import type { CallCenterConversationRow } from '@types'
import { callcenterService } from '@services/callcenter.service'
import { formatDate } from '@utils/format'
import { EmptyErrorState } from '@components/common/EmptyState'
import { WithPermission } from '@components/common/WithPermission'
import { PERMISSIONS } from '@constants/permissions'
import { usePermissions } from '@hooks/usePermissions'
import { agencesService } from '@services/agences.service'
import { buildWhatsAppChatUrl } from '@utils/whatsapp'
import dayjs, { type Dayjs } from 'dayjs'

const { Title } = Typography
const { RangePicker } = DatePicker

type ReadFilter = 'all' | 'unread' | 'read'
type CaseFilter = 'all' | 'open' | 'in_progress' | 'resolved'

const CASE_LABELS: Record<string, string> = {
  open: 'Ouvert',
  in_progress: 'En cours',
  resolved: 'Résolu',
}

export const CallCenterInboxPage: React.FC = () => {
  const { hasPermission } = usePermissions()
  const [page, setPage] = useState(1)
  const [limit] = useState(20)
  const [channel, setChannel] = useState<'sms' | 'whatsapp' | undefined>(undefined)
  const [readStatus, setReadStatus] = useState<ReadFilter>('all')
  const [caseStatus, setCaseStatus] = useState<CaseFilter>('all')
  const [agenceId, setAgenceId] = useState<number | undefined>(undefined)
  const [dateRange, setDateRange] = useState<[Dayjs | null, Dayjs | null] | null>(null)
  const [qInput, setQInput] = useState('')
  const [qApplied, setQApplied] = useState('')

  const dateFrom =
    dateRange?.[0]?.startOf('day').toISOString() ??
    undefined
  const dateTo = dateRange?.[1]?.endOf('day').toISOString() ?? undefined

  const applySearch = useCallback(() => {
    setPage(1)
    setQApplied(qInput.trim())
  }, [qInput])

  const resetFilters = useCallback(() => {
    setPage(1)
    setChannel(undefined)
    setReadStatus('all')
    setCaseStatus('all')
    setAgenceId(undefined)
    setDateRange(null)
    setQInput('')
    setQApplied('')
  }, [])

  const { data: agences } = useQuery({
    queryKey: ['agences', 'callcenter-filter'],
    queryFn: () => agencesService.getAll(),
    enabled: hasPermission(PERMISSIONS.AGENCES.READ),
    staleTime: 120_000,
  })

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: [
      'callcenter-conversations',
      page,
      limit,
      channel,
      qApplied,
      dateFrom,
      dateTo,
      readStatus,
      caseStatus,
      agenceId,
    ],
    queryFn: () =>
      callcenterService.listConversations({
        page,
        limit,
        ...(channel ? { channel } : {}),
        ...(qApplied ? { q: qApplied } : {}),
        ...(dateFrom ? { date_from: dateFrom } : {}),
        ...(dateTo ? { date_to: dateTo } : {}),
        read_status: readStatus,
        case_status: caseStatus,
        ...(agenceId != null ? { agence_id: agenceId } : {}),
      }),
  })

  const columns: ColumnsType<CallCenterConversationRow> = [
    {
      title: 'Canal',
      dataIndex: 'channel',
      key: 'channel',
      width: 100,
      render: (c: string) => <Tag color={c === 'whatsapp' ? 'green' : 'blue'}>{c}</Tag>,
    },
    {
      title: 'Dossier',
      key: 'case_status',
      width: 110,
      render: (_: unknown, row: CallCenterConversationRow) => {
        const s = row.case_status || 'open'
        const color =
          s === 'resolved' ? 'default' : s === 'in_progress' ? 'processing' : 'blue'
        return <Tag color={color}>{CASE_LABELS[s] ?? s}</Tag>
      },
    },
    {
      title: 'Téléphone client',
      dataIndex: 'customer_phone',
      key: 'customer_phone',
      width: 200,
      render: (p: string) => <span>{p}</span>,
    },
    {
      title: 'Client',
      key: 'client',
      width: 200,
      ellipsis: true,
      render: (_: unknown, row: CallCenterConversationRow) =>
        row.client_nom ?? (row.client_id != null ? `#${row.client_id}` : '—'),
    },
    {
      title: 'Ligne / WhatsApp Business',
      dataIndex: 'callcenter_phone',
      key: 'callcenter_phone',
      width: 160,
      render: (p: string | null) => p ?? '—',
    },
    {
      title: 'Non lus',
      dataIndex: 'unread_count',
      key: 'unread_count',
      width: 80,
    },
    {
      title: 'Dernier message',
      dataIndex: 'last_message_at',
      key: 'last_message_at',
      width: 170,
      render: (d: string | null) => (d ? formatDate(d) : '—'),
    },
    {
      title: 'Actions',
      key: 'action',
      width: 400,
      fixed: 'right',
      render: (_: unknown, row: CallCenterConversationRow) => {
        const waUrl = buildWhatsAppChatUrl(row.customer_phone)
        const clientSearch =
          row.client_nom?.trim() ||
          row.customer_phone ||
          (row.client_id != null ? String(row.client_id) : '')
        const litigeQs = new URLSearchParams()
        if (row.client_id != null) litigeQs.set('client_id', String(row.client_id))
        litigeQs.set('phone', row.customer_phone || '')
        if (row.last_facture_id != null) litigeQs.set('facture_id', String(row.last_facture_id))
        litigeQs.set('from', 'callcenter')
        const litigeHref = `/litiges?${litigeQs.toString()}`
        return (
          <Space size="small" wrap>
            <Link to={`/callcenter/inbox/${row.id}`} state={{ conversation: row }}>
              <Button type="link" size="small">
                Conversation
              </Button>
            </Link>
            {waUrl ? (
              <Button
                size="small"
                onClick={(e: React.MouseEvent<HTMLElement>) => {
                  e.preventDefault()
                  window.open(waUrl, '_blank', 'noopener,noreferrer')
                }}
              >
                WhatsApp
              </Button>
            ) : null}
            <WithPermission permission={PERMISSIONS.CLIENTS.READ}>
              {clientSearch ? (
                <Link to={`/clients?search=${encodeURIComponent(clientSearch)}`}>
                  <Button size="small">Clients</Button>
                </Link>
              ) : null}
            </WithPermission>
            <WithPermission permission={PERMISSIONS.LITIGES.CREATE}>
              {row.client_id != null ? (
                <Link to={litigeHref}>
                  <Button size="small">Nouveau litige</Button>
                </Link>
              ) : null}
            </WithPermission>
            <WithPermission permission={PERMISSIONS.LITIGES.VIEW}>
              {row.last_litige_id != null ? (
                <Link to={`/litiges/${row.last_litige_id}`}>
                  <Button size="small">Litige lié</Button>
                </Link>
              ) : null}
            </WithPermission>
            <WithPermission permission={PERMISSIONS.FACTURES.READ}>
              {row.last_facture_id != null ? (
                <Link to={`/factures/${row.last_facture_id}/preview`}>
                  <Button size="small">Facture</Button>
                </Link>
              ) : null}
            </WithPermission>
          </Space>
        )
      },
    },
  ]

  if (error) {
    return <EmptyErrorState onRetry={() => refetch()} />
  }

  return (
    <div>
      <Title level={2}>Boîte de réception (SMS / WhatsApp)</Title>
      <Card style={{ marginBottom: 16 }}>
        <Space wrap align="start" size="middle">
          <Input
            allowClear
            placeholder="Téléphone, nom client, n° colis / facture…"
            value={qInput}
            onChange={(e: React.ChangeEvent<HTMLInputElement>) => setQInput(e.target.value)}
            onPressEnter={() => applySearch()}
            style={{ minWidth: 280, maxWidth: 420 }}
          />
          <Button type="primary" onClick={() => applySearch()}>
            Rechercher
          </Button>
          <Select<'sms' | 'whatsapp' | 'all'>
            placeholder="Canal"
            style={{ width: 140 }}
            value={channel ?? 'all'}
            onChange={(v: 'sms' | 'whatsapp' | 'all') => {
              setPage(1)
              setChannel(v === 'all' ? undefined : v)
            }}
            options={[
              { value: 'all', label: 'Tous canaux' },
              { value: 'sms', label: 'SMS' },
              { value: 'whatsapp', label: 'WhatsApp' },
            ]}
          />
          <Select<ReadFilter>
            style={{ width: 150 }}
            value={readStatus}
            onChange={(v: ReadFilter) => {
              setPage(1)
              setReadStatus(v)
            }}
            options={[
              { value: 'all', label: 'Lecture : tous' },
              { value: 'unread', label: 'Non lus' },
              { value: 'read', label: 'Lus' },
            ]}
          />
          <Select<CaseFilter>
            style={{ width: 170 }}
            value={caseStatus}
            onChange={(v: CaseFilter) => {
              setPage(1)
              setCaseStatus(v)
            }}
            options={[
              { value: 'all', label: 'Dossier : tous' },
              { value: 'open', label: 'Ouvert' },
              { value: 'in_progress', label: 'En cours' },
              { value: 'resolved', label: 'Résolu' },
            ]}
          />
          {hasPermission(PERMISSIONS.AGENCES.READ) && Array.isArray(agences) && agences.length > 0 ? (
            <Select<number | 'all'>
              allowClear
              placeholder="Agence (colis client)"
              style={{ width: 200 }}
              value={agenceId ?? 'all'}
              onChange={(v: number | 'all' | null) => {
                setPage(1)
                setAgenceId(v === 'all' || v == null ? undefined : v)
              }}
              options={[
                { value: 'all', label: 'Toutes agences' },
                ...agences.map((a) => ({ value: a.id, label: a.name ?? a.code ?? `#${a.id}` })),
              ]}
            />
          ) : null}
          <RangePicker
            value={dateRange}
            onChange={(r: null | (Dayjs | null)[]) => {
              setPage(1)
              if (!r || r.length !== 2) {
                setDateRange(null)
                return
              }
              setDateRange([r[0], r[1]])
            }}
          />
          <Button onClick={() => resetFilters()}>Réinitialiser</Button>
        </Space>
      </Card>
      <Card>
        <Table<CallCenterConversationRow>
          rowKey="id"
          loading={isLoading}
          columns={columns}
          dataSource={data?.data ?? []}
          pagination={false}
          scroll={{ x: 1280 }}
        />
        {data && data.totalPages > 1 ? (
          <Pagination
            style={{ marginTop: 16, textAlign: 'right' }}
            current={page}
            pageSize={limit}
            total={data.total}
            onChange={(p: number) => setPage(p)}
            showSizeChanger={false}
          />
        ) : null}
      </Card>
    </div>
  )
}
