import React, { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { Card, Table, Typography, Tag, Pagination, Space, Select, Button } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useQuery } from '@tanstack/react-query'
import type { LitigeListItem } from '@types'
import { litigesService } from '@services/litiges.service'
import { formatDate } from '@utils/format'
import { EmptyErrorState } from '@components/common/EmptyState'
import { LitigeQuickCreateModal } from '@components/litiges/LitigeQuickCreateModal'
import { WithPermission } from '@components/common/WithPermission'
import { PERMISSIONS } from '@constants/permissions'

const { Title } = Typography

const STATUT_OPTIONS = [
  { value: 'OUVERT', label: 'Ouvert' },
  { value: 'EN_COURS', label: 'En cours' },
  { value: 'RESOLU', label: 'Résolu' },
  { value: 'FERME', label: 'Fermé' },
  { value: 'REJETE', label: 'Rejeté' },
]

export const LitigesListPage: React.FC = () => {
  const [searchParams, setSearchParams] = useSearchParams()
  const [page, setPage] = useState(1)
  const [limit] = useState(20)
  const [statut, setStatut] = useState<string | undefined>(undefined)
  const [litigeModalOpen, setLitigeModalOpen] = useState(false)

  const clientIdParam = searchParams.get('client_id')
  const phoneParam = searchParams.get('phone') ?? undefined
  const factureIdParam = searchParams.get('facture_id')
  const colisRefParam = searchParams.get('colis_ref') ?? undefined
  const fromCallcenter = searchParams.get('from') === 'callcenter'

  useEffect(() => {
    if (clientIdParam && fromCallcenter) {
      setLitigeModalOpen(true)
    }
  }, [clientIdParam, fromCallcenter])

  const closeLitigeModal = () => {
    setLitigeModalOpen(false)
    setSearchParams(
      (prev) => {
        const n = new URLSearchParams(prev)
        n.delete('client_id')
        n.delete('phone')
        n.delete('facture_id')
        n.delete('colis_ref')
        n.delete('from')
        return n
      },
      { replace: true },
    )
  }

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['litiges', page, limit, statut],
    queryFn: () =>
      litigesService.list({
        page,
        limit,
        ...(statut !== undefined && statut !== '' ? { statut } : {}),
      }),
  })

  const columns: ColumnsType<LitigeListItem> = [
    { title: 'N°', dataIndex: 'num_litige', key: 'num_litige', width: 120 },
    { title: 'Type', dataIndex: 'type', key: 'type', width: 100 },
    {
      title: 'Statut',
      dataIndex: 'statut',
      key: 'statut',
      width: 110,
      render: (s: string) => <Tag>{s}</Tag>,
    },
    { title: 'Objet', dataIndex: 'objet', key: 'objet', ellipsis: true },
    {
      title: 'Client',
      key: 'client',
      width: 160,
      render: (_: unknown, row: LitigeListItem) => row.client?.nom_exp ?? '—',
    },
    {
      title: 'Agence',
      key: 'agence',
      width: 140,
      render: (_: unknown, row: LitigeListItem) => row.agence?.nom ?? row.agence?.code ?? '—',
    },
    {
      title: 'Créé le',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 160,
      render: (d: string) => formatDate(d),
    },
    {
      title: '',
      key: 'action',
      width: 100,
      render: (_: unknown, row: LitigeListItem) => (
        <Link to={`/litiges/${row.id}`}>
          <Button type="link" size="small">
            Détails
          </Button>
        </Link>
      ),
    },
  ]

  if (error) {
    return <EmptyErrorState onRetry={() => refetch()} />
  }

  const prefClientIdRaw = clientIdParam ? Number.parseInt(clientIdParam, 10) : NaN
  const prefClientId = Number.isFinite(prefClientIdRaw) ? prefClientIdRaw : undefined
  const prefFactureIdRaw = factureIdParam ? Number.parseInt(factureIdParam, 10) : NaN
  const prefFactureId = Number.isFinite(prefFactureIdRaw) ? prefFactureIdRaw : undefined

  return (
    <div>
      <Title level={2}>Litiges</Title>
      <Card>
        <Space style={{ marginBottom: 16 }} wrap>
          <WithPermission permission={PERMISSIONS.LITIGES.CREATE}>
            <Button type="primary" onClick={() => setLitigeModalOpen(true)}>
              Nouveau litige
            </Button>
          </WithPermission>
          <span>Filtrer par statut :</span>
          <Select
            allowClear
            placeholder="Tous les statuts"
            style={{ width: 220 }}
            value={statut}
            onChange={(v: string | undefined) => {
              setStatut(v)
              setPage(1)
            }}
            options={STATUT_OPTIONS}
          />
        </Space>
        <Table<LitigeListItem>
          rowKey="id"
          loading={isLoading}
          columns={columns}
          dataSource={data?.data ?? []}
          pagination={false}
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

      <LitigeQuickCreateModal
        open={litigeModalOpen}
        onClose={closeLitigeModal}
        defaultClientId={prefClientId}
        defaultPhone={phoneParam}
        defaultFactureId={prefFactureId}
        defaultColisRef={colisRefParam}
      />
    </div>
  )
}
