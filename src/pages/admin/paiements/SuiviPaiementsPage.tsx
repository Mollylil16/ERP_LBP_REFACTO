import React, { useState } from 'react'
import {
  Card,
  Input,
  Button,
  Tag,
  Tabs,
  Row,
  Col,
  Statistic,
  Typography,
  Space,
  DatePicker,
  Tooltip,
  Progress,
} from 'antd'
// import type { RangePickerProps } from 'antd/es/date-picker'
// import type { ColumnsType } from 'antd/es/table'
import type { ChangeEvent } from 'react'
import {
  SearchOutlined,
  ReloadOutlined,
  CheckCircleOutlined,
  ClockCircleOutlined,
  CloseCircleOutlined,
  PhoneOutlined,
  WalletOutlined,
  MobileOutlined,
  BankOutlined,
  FileTextOutlined,
  CalendarOutlined,
  DollarOutlined,
} from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import dayjs, { Dayjs } from 'dayjs'
import {
  paiementsService,
  type SuiviPaiementItem,
  type StatutPaiement,
} from '@services/paiements.service'
import { formatDate, formatMontantWithDevise } from '@utils/format'
import { VirtualTable } from '@components/common/VirtualTable'
import { APP_CONFIG } from '@constants/application'

const { Title, Text } = Typography
const { RangePicker } = DatePicker

// ─── Config statuts ────────────────────────────────────────────────
const STATUT_CONFIG: Record<StatutPaiement, { label: string; color: string; icon: React.ReactNode }> = {
  paye: { label: 'Payé', color: 'success', icon: <CheckCircleOutlined /> },
  partiel: { label: 'Partiel', color: 'warning', icon: <ClockCircleOutlined /> },
  impaye: { label: 'Non payé', color: 'error', icon: <CloseCircleOutlined /> },
}

const StatutTag: React.FC<{ statut: StatutPaiement }> = ({ statut }) => {
  const cfg = STATUT_CONFIG[statut]
  return (
    <Tag
      color={statut === 'paye' ? 'green' : statut === 'partiel' ? 'orange' : 'red'}
      icon={cfg.icon}
    >
      {cfg.label}
    </Tag>
  )
}

const getModeIcon = (value: string) => {
  switch (value) {
    case 'wave':
    case 'om': return <MobileOutlined />
    case 'especes': return <WalletOutlined />
    case 'cheque': return <FileTextOutlined />
    case 'virement': return <BankOutlined />
    case '30j':
    case '45j':
    case '60j':
    case '90j': return <CalendarOutlined />
    default: return <DollarOutlined />
  }
}

const getModeTag = (mode?: string) => {
  if (!mode) return <span style={{ color: '#bfbfbf' }}>—</span>
  const config = APP_CONFIG.modesPaiement.find(
    (m) => m.label.toLowerCase() === mode.toLowerCase() || m.value === mode.toLowerCase()
  )
  const iconKey = config?.value ?? mode.toLowerCase()
  return (
    <Tag color={config?.color || 'default'} icon={getModeIcon(iconKey)}>
      {config?.label ?? mode}
    </Tag>
  )
}

type TabKey = 'tous' | StatutPaiement

export const SuiviPaiementsPage: React.FC = () => {
  const [activeTab, setActiveTab] = useState<TabKey>('tous')
  const [search, setSearch] = useState('')
  const [dateRange, setDateRange] = useState<[Dayjs | null, Dayjs | null] | null>(null)
  const [pagination, setPagination] = useState({ page: 1, limit: 20 })

  const queryParams = {
    statut: activeTab === 'tous' ? undefined : activeTab as StatutPaiement,
    search: search || undefined,
    date_debut: dateRange?.[0]?.format('YYYY-MM-DD'),
    date_fin: dateRange?.[1]?.format('YYYY-MM-DD'),
    ...pagination,
  }

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['paiements', 'suivi', queryParams],
    queryFn: () => paiementsService.getSuiviPaiements(queryParams),
  })

  const items = data?.data || []
  const stats = data?.stats || { paye: 0, partiel: 0, impaye: 0, totalEncaissé: 0, totalRestant: 0 }

  // ─── Statistiques récapitulatives (Globales) ───────────────────────────────
  const statsPayé = stats.paye
  const statsPartiel = stats.partiel
  const statsImpayé = stats.impaye
  const totalEncaissé = stats.totalEncaissé
  const totalRestant = stats.totalRestant

  const columns: any[] = [
    {
      title: 'Client',
      key: 'client',
      width: 200,
      fixed: 'left',
      render: (_: any, r: SuiviPaiementItem) => (
        <div>
          <div style={{ fontWeight: 600 }}>{r.nom_client}</div>
          {r.tel_client && (
            <div style={{ fontSize: 12, color: '#8c8c8c' }}>
              <PhoneOutlined style={{ marginRight: 4 }} />
              {r.tel_client}
            </div>
          )}
        </div>
      ),
    },
    {
      title: 'Réf. Colis',
      dataIndex: 'ref_colis',
      key: 'ref_colis',
      width: 140,
      render: (ref: string) => (
        <span style={{ fontFamily: 'monospace', fontWeight: 600 }}>{ref}</span>
      ),
    },
    {
      title: 'N° Facture',
      dataIndex: 'facture_num',
      key: 'facture_num',
      width: 140,
      render: (num: string) => num || <span style={{ color: '#bfbfbf' }}>—</span>,
    },
    {
      title: 'Montant total',
      dataIndex: 'montant_total',
      key: 'montant_total',
      width: 150,
      render: (v: number) => <strong>{formatMontantWithDevise(v)}</strong>,
      sorter: (a: SuiviPaiementItem, b: SuiviPaiementItem) => a.montant_total - b.montant_total,
    },
    {
      title: 'Payé',
      dataIndex: 'montant_paye',
      key: 'montant_paye',
      width: 150,
      render: (v: number, r: SuiviPaiementItem) => (
        <div>
          <div style={{ color: '#52c41a', fontWeight: 600 }}>
            {formatMontantWithDevise(v)}
          </div>
          <Progress
            percent={r.montant_total > 0 ? Math.round((v / r.montant_total) * 100) : 0}
            size="small"
            showInfo={false}
            strokeColor={r.statut === 'paye' ? '#52c41a' : r.statut === 'partiel' ? '#faad14' : '#ff4d4f'}
            style={{ marginTop: 4 }}
          />
        </div>
      ),
      sorter: (a: SuiviPaiementItem, b: SuiviPaiementItem) => a.montant_paye - b.montant_paye,
    },
    {
      title: 'Reste à payer',
      dataIndex: 'restant_a_payer',
      key: 'restant_a_payer',
      width: 150,
      render: (v: number) => (
        <strong style={{ color: v > 0 ? '#ff4d4f' : '#52c41a' }}>
          {v > 0 ? formatMontantWithDevise(v) : '✓ Soldé'}
        </strong>
      ),
      sorter: (a: SuiviPaiementItem, b: SuiviPaiementItem) => a.restant_a_payer - b.restant_a_payer,
    },
    {
      title: 'Statut',
      dataIndex: 'statut',
      key: 'statut',
      width: 120,
      render: (statut: StatutPaiement) => <StatutTag statut={statut} />,
      filters: [
        { text: 'Payé', value: 'paye' },
        { text: 'Partiel', value: 'partiel' },
        { text: 'Non payé', value: 'impaye' },
      ],
    },
    {
      title: 'Dernier paiement',
      key: 'dernier',
      width: 180,
      render: (_: any, r: SuiviPaiementItem) => (
        <div>
          <div>{r.dernier_paiement_date ? formatDate(r.dernier_paiement_date) : '—'}</div>
          {r.dernier_mode_paiement && (
            <div style={{ marginTop: 4 }}>
              {getModeTag(r.dernier_mode_paiement)}
            </div>
          )}
        </div>
      ),
    },
    {
      title: 'Paiements',
      dataIndex: 'nb_paiements',
      key: 'nb_paiements',
      width: 100,
      align: 'center',
      render: (nb: number) => (
        <Tag color={nb > 0 ? 'blue' : 'default'}>{nb} versement{nb > 1 ? 's' : ''}</Tag>
      ),
    },
    {
      title: 'Date colis',
      dataIndex: 'date_creation',
      key: 'date_creation',
      width: 120,
      render: (d: string) => formatDate(d),
      sorter: (a: SuiviPaiementItem, b: SuiviPaiementItem) => new Date(a.date_creation).getTime() - new Date(b.date_creation).getTime(),
    },
  ]

  return (
    <div>
      <div style={{ marginBottom: 24 }}>
        <Title level={2} style={{ margin: 0 }}>Suivi des Paiements</Title>
        <Text type="secondary">Vue consolidée par colis — payé, partiel, impayé</Text>
      </div>

      {/* ─── STATS RAPIDES ─── */}
      <Row gutter={16} style={{ marginBottom: 20 }}>
        <Col xs={12} sm={6}>
          <Card size="small">
            <Statistic
              title="Payés"
              value={statsPayé}
              valueStyle={{ color: '#52c41a' }}
              prefix={<CheckCircleOutlined />}
            />
          </Card>
        </Col>
        <Col xs={12} sm={6}>
          <Card size="small">
            <Statistic
              title="Partiels"
              value={statsPartiel}
              valueStyle={{ color: '#faad14' }}
              prefix={<ClockCircleOutlined />}
            />
          </Card>
        </Col>
        <Col xs={12} sm={6}>
          <Card size="small">
            <Statistic
              title="Non payés"
              value={statsImpayé}
              valueStyle={{ color: '#ff4d4f' }}
              prefix={<CloseCircleOutlined />}
            />
          </Card>
        </Col>
        <Col xs={12} sm={6}>
          <Card size="small">
            <Statistic
              title="Total encaissé"
              value={formatMontantWithDevise(totalEncaissé)}
              valueStyle={{ color: '#52c41a', fontSize: 16 }}
            />
            <div style={{ fontSize: 12, color: '#ff4d4f', marginTop: 4 }}>
              Reste : {formatMontantWithDevise(totalRestant)}
            </div>
          </Card>
        </Col>
      </Row>

      {/* ─── FILTRES ─── */}
      <Card style={{ marginBottom: 16 }}>
        <Row gutter={16} align="middle">
          <Col xs={24} sm={12} md={8}>
            <Input
              placeholder="Rechercher par client, réf. colis, facture…"
              prefix={<SearchOutlined />}
              allowClear
              value={search}
              onChange={(e: ChangeEvent<HTMLInputElement>) => {
                setSearch(e.target.value)
                setPagination({ ...pagination, page: 1 })
              }}
              size="large"
            />
          </Col>
          <Col xs={24} sm={12} md={8}>
            <RangePicker
              style={{ width: '100%' }}
              size="large"
              value={dateRange}
              onChange={(dates: any) =>
                setDateRange(dates as [Dayjs | null, Dayjs | null])
              }
              format="DD/MM/YYYY"
              placeholder={['Date début', 'Date fin']}
            />
          </Col>
          <Col xs={24} md={8} style={{ textAlign: 'right' }}>
            <Tooltip title="Actualiser">
              <Button
                icon={<ReloadOutlined />}
                onClick={() => {
                  setPagination({ ...pagination, page: 1 });
                  refetch();
                }}
                size="large"
                loading={isLoading}
              >
                Actualiser
              </Button>
            </Tooltip>
          </Col>
        </Row>
      </Card>

      {/* ─── TABS + TABLEAU ─── */}
      <Card>
        <Tabs
          activeKey={activeTab}
          onChange={(key: string) => { setActiveTab(key as TabKey); setPagination({ page: 1, limit: 20 }) }}
          items={[
            { key: 'tous', label: `Tous (${data?.total ?? 0})` },
            { key: 'paye', label: <Space><CheckCircleOutlined style={{ color: '#52c41a' }} />Payés ({statsPayé})</Space> },
            { key: 'partiel', label: <Space><ClockCircleOutlined style={{ color: '#faad14' }} />Partiels ({statsPartiel})</Space> },
            { key: 'impaye', label: <Space><CloseCircleOutlined style={{ color: '#ff4d4f' }} />Non payés ({statsImpayé})</Space> },
          ]}
        />

        <VirtualTable<SuiviPaiementItem>
          columns={columns}
          dataSource={items}
          loading={isLoading}
          rowKey="id"
          scroll={{ x: 1400 }}
          totalLabel="enregistrements"
          rowClassName={(record) =>
            record.statut === 'impaye' ? 'row-impaye' :
              record.statut === 'partiel' ? 'row-partiel' : ''
          }
          pagination={{
            current: pagination.page,
            pageSize: pagination.limit,
            total: data?.total || 0,
            showSizeChanger: true,
            showTotal: (total: number) => `Total : ${total} enregistrements`,
            pageSizeOptions: ['10', '20', '50', '100'],
          }}
          onChange={(p: any) => setPagination({ page: p.current || 1, limit: p.pageSize || 20 })}
        />
      </Card>

      <style>{`
        .row-impaye td { background: #fff2f0 !important; }
        .row-partiel td { background: #fffbe6 !important; }
        .dark-mode .row-impaye td { background: #2d1315 !important; }
        .dark-mode .row-partiel td { background: #2b2111 !important; }
      `}</style>
    </div>
  )
}
