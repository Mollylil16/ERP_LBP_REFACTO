import React, { useState } from 'react'
import { Link } from 'react-router-dom'
import { Card, Table, Typography, Tag, Pagination, Space, Select, Button } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useQuery } from '@tanstack/react-query'
import type { LitigeListItem } from '@types'
import { litigesService } from '@services/litiges.service'
import { formatDate } from '@utils/format'
import { EmptyErrorState } from '@components/common/EmptyState'

const { Title } = Typography

const STATUT_OPTIONS = [
  { value: 'OUVERT', label: 'Ouvert' },
  { value: 'EN_COURS', label: 'En cours' },
  { value: 'RESOLU', label: 'Résolu' },
  { value: 'FERME', label: 'Fermé' },
  { value: 'REJETE', label: 'Rejeté' },
]

export const LitigesListPage: React.FC = () => {
  const [page, setPage] = useState(1)
  const [limit] = useState(20)
  const [statut, setStatut] = useState<string | undefined>(undefined)

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

  return (
    <div>
      <Title level={2}>Litiges</Title>
      <Card>
        <Space style={{ marginBottom: 16 }} wrap>
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
    </div>
  )
}
