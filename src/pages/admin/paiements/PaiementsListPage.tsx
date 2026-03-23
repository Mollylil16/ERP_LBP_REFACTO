import React, { useState } from 'react'
import { Modal, Typography, Tabs } from 'antd'
import { PaiementList } from '@components/paiements/PaiementList'
import { PaiementForm } from '@components/paiements/PaiementForm'
import { SuiviPaiementsPage } from './SuiviPaiementsPage'
import { CreatePaiementDto } from '@services/paiements.service'
import { useCreatePaiement } from '@hooks/usePaiements'
import {
  Input, Button, Table, Tag, Space, Tooltip, Badge,
} from 'antd'
import {
  SearchOutlined, DollarOutlined, BarChartOutlined, AlertOutlined,
} from '@ant-design/icons'
import { WithPermission } from '@components/common/WithPermission'
import { PERMISSIONS } from '@constants/permissions'
import { useQuery } from '@tanstack/react-query'
import { apiService } from '@services/api.service'
import { formatMontantWithDevise } from '@utils/format'

const { Title, Text } = Typography

// ─── Type pour une facture impayée retournée par le backend ─────────────────
interface FactureImpayee {
  id: number
  num_facture: string
  date_facture: string
  montant_ttc: number
  montant_paye: number
  montantRestant: number
  joursDepuisCreation: number
  colis?: {
    ref_colis: string
    client?: { nom_exp?: string; tel_exp?: string }
    agence?: { nom?: string }
  }
}

// ─── Composant interne : onglet Encaissement ─────────────────────────────────
const EncaissementTab: React.FC<{
  onEncaisser: (refColis: string) => void
  isModalOpen: boolean
  refColis: string
}> = ({ onEncaisser }) => {
  const [search, setSearch] = useState('')

  const { data: factures, isLoading, refetch } = useQuery<FactureImpayee[]>({
    queryKey: ['paiements', 'unpaid'],
    queryFn: () => apiService.get('/paiements/history/unpaid'),
    refetchOnWindowFocus: false,
  })

  const filtered = (factures || []).filter(f => {
    const q = search.toLowerCase()
    return (
      f.num_facture?.toLowerCase().includes(q) ||
      f.colis?.ref_colis?.toLowerCase().includes(q) ||
      f.colis?.client?.nom_exp?.toLowerCase().includes(q)
    )
  })

  const totalDu = filtered.reduce((s, f) => s + f.montantRestant, 0)

  const columns = [
    {
      title: 'N° Facture',
      dataIndex: 'num_facture',
      key: 'num_facture',
      render: (v: string) => <Tag color="blue" style={{ fontWeight: 700 }}>{v}</Tag>,
    },
    {
      title: 'Réf. Colis',
      key: 'ref_colis',
      render: (_: any, r: FactureImpayee) => (
        <Text strong>{r.colis?.ref_colis || '—'}</Text>
      ),
    },
    {
      title: 'Client',
      key: 'client',
      render: (_: any, r: FactureImpayee) => (
        <div>
          <div>{r.colis?.client?.nom_exp || '—'}</div>
          <Text type="secondary" style={{ fontSize: 11 }}>
            {r.colis?.client?.tel_exp}
          </Text>
        </div>
      ),
    },
    {
      title: 'Montant Total',
      key: 'montant_ttc',
      render: (_: any, r: FactureImpayee) => formatMontantWithDevise(r.montant_ttc),
    },
    {
      title: 'Déjà Payé',
      key: 'montant_paye',
      render: (_: any, r: FactureImpayee) => (
        <Text style={{ color: r.montant_paye > 0 ? '#fa8c16' : '#aaa' }}>
          {formatMontantWithDevise(r.montant_paye)}
        </Text>
      ),
    },
    {
      title: 'Restant Dû',
      key: 'restant',
      render: (_: any, r: FactureImpayee) => (
        <Text strong style={{ color: '#ff4d4f' }}>
          {formatMontantWithDevise(r.montantRestant)}
        </Text>
      ),
    },
    {
      title: 'Retard',
      key: 'jours',
      render: (_: any, r: FactureImpayee) => {
        const j = r.joursDepuisCreation
        return (
          <Tag color={j > 30 ? 'red' : j > 7 ? 'orange' : 'default'}>
            {j === 0 ? "Auj." : `${j}j`}
          </Tag>
        )
      },
      sorter: (a: FactureImpayee, b: FactureImpayee) =>
        b.joursDepuisCreation - a.joursDepuisCreation,
    },
    {
      title: 'Action',
      key: 'action',
      fixed: 'right' as const,
      width: 120,
      render: (_: any, r: FactureImpayee) => (
        <WithPermission permission={PERMISSIONS.PAIEMENTS.CREATE}>
          <Tooltip title="Enregistrer un paiement">
            <Button
              type="primary"
              icon={<DollarOutlined />}
              size="small"
              disabled={!r.colis?.ref_colis}
              onClick={() => onEncaisser(r.colis!.ref_colis)}
            >
              Encaisser
            </Button>
          </Tooltip>
        </WithPermission>
      ),
    },
  ]

  const retard = (factures || []).filter(f => f.joursDepuisCreation > 30).length

  return (
    <div>
      {/* ─── Résumé ─────────────────────────────────────────── */}
      <div style={{ display: 'flex', gap: 16, marginBottom: 20, flexWrap: 'wrap' }}>
        <div style={{
          background: '#fff2f0', border: '1px solid #ffccc7',
          borderRadius: 8, padding: '12px 20px', minWidth: 160,
        }}>
          <Text type="secondary" style={{ fontSize: 12 }}>Total restant dû</Text>
          <div style={{ fontSize: 20, fontWeight: 700, color: '#ff4d4f' }}>
            {formatMontantWithDevise(totalDu)}
          </div>
        </div>
        <div style={{
          background: '#fffbe6', border: '1px solid #ffe58f',
          borderRadius: 8, padding: '12px 20px', minWidth: 160,
        }}>
          <Text type="secondary" style={{ fontSize: 12 }}>Factures impayées</Text>
          <div style={{ fontSize: 20, fontWeight: 700, color: '#d46b08' }}>
            <Badge count={filtered.length} color="#fa8c16" showZero>
              <span style={{ paddingRight: 8 }}>{filtered.length}</span>
            </Badge>
          </div>
        </div>
        {retard > 0 && (
          <div style={{
            background: '#fff1f0', border: '1px solid #ff7875',
            borderRadius: 8, padding: '12px 20px', minWidth: 160,
          }}>
            <Text type="secondary" style={{ fontSize: 12 }}>
              <AlertOutlined style={{ color: '#ff4d4f', marginRight: 4 }} />
              En retard (&gt;30j)
            </Text>
            <div style={{ fontSize: 20, fontWeight: 700, color: '#cf1322' }}>
              {retard}
            </div>
          </div>
        )}
      </div>

      {/* ─── Barre de recherche ── */}
      <Space style={{ marginBottom: 16 }} size="middle">
        <Input
          placeholder="Rechercher par n° facture, réf. colis, client..."
          prefix={<SearchOutlined />}
          value={search}
          onChange={(e: React.ChangeEvent<HTMLInputElement>) => setSearch(e.target.value)}
          allowClear
          size="large"
          style={{ width: 400 }}
        />
        <Button icon={<SearchOutlined />} onClick={() => refetch()} size="large">
          Actualiser
        </Button>
      </Space>

      {/* ─── Tableau ─────────────────────────────────────────── */}
      <Table<FactureImpayee>
        columns={columns}
        dataSource={filtered}
        rowKey="id"
        loading={isLoading}
        size="small"
        scroll={{ x: 900 }}
        rowClassName={(r: FactureImpayee) =>
          r.joursDepuisCreation > 30 ? 'ant-table-row-danger' : ''
        }
        pagination={{
          pageSize: 15,
          showTotal: (t: number) => `${t} facture(s) impayée(s)`,
        }}
      />
    </div>
  )
}

// ─── Page principale ─────────────────────────────────────────────────────────
export const PaiementsListPage: React.FC = () => {
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [refColis, setRefColis] = useState('')

  const createMutation = useCreatePaiement()

  const handleEncaisser = (ref: string) => {
    setRefColis(ref)
    setIsModalOpen(true)
  }

  const handleSubmit = async (data: CreatePaiementDto) => {
    try {
      await createMutation.mutateAsync(data)
      setIsModalOpen(false)
      setRefColis('')
    } catch (error) {
      console.error('Error submitting payment:', error)
    }
  }

  const handleCancel = () => {
    setIsModalOpen(false)
    setRefColis('')
  }

  const tabItems = [
    {
      key: 'suivi',
      label: <span><BarChartOutlined /> Suivi Paiements</span>,
      children: <SuiviPaiementsPage />,
    },
    {
      key: 'encaisser',
      label: (
        <span>
          <DollarOutlined /> Encaisser
        </span>
      ),
      children: (
        <EncaissementTab
          onEncaisser={handleEncaisser}
          isModalOpen={isModalOpen}
          refColis={refColis}
        />
      ),
    },
    {
      key: 'list',
      label: 'Historique',
      children: <PaiementList />,
    },
  ]

  return (
    <div>
      <Title level={2}>Gestion des Paiements</Title>

      <Tabs defaultActiveKey="encaisser" items={tabItems} />

      {/* ─── Modale d'encaissement ─── */}
      <Modal
        title={`Encaissement — Colis ${refColis}`}
        open={isModalOpen}
        onCancel={handleCancel}
        footer={null}
        width="70%"
        style={{ top: 20 }}
        destroyOnClose
      >
        {refColis && (
          <PaiementForm
            refColis={refColis}
            onSubmit={handleSubmit}
            onCancel={handleCancel}
            loading={createMutation.isPending}
          />
        )}
      </Modal>
    </div>
  )
}
