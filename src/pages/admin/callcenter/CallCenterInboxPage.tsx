import React, { useState } from 'react'
import { Link } from 'react-router-dom'
import { Card, Table, Typography, Tag, Pagination, Button } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useQuery } from '@tanstack/react-query'
import type { CallCenterConversationRow } from '@types'
import { callcenterService } from '@services/callcenter.service'
import { formatDate } from '@utils/format'
import { EmptyErrorState } from '@components/common/EmptyState'

const { Title } = Typography

export const CallCenterInboxPage: React.FC = () => {
  const [page, setPage] = useState(1)
  const [limit] = useState(20)

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['callcenter-conversations', page, limit],
    queryFn: () => callcenterService.listConversations({ page, limit }),
  })

  const columns: ColumnsType<CallCenterConversationRow> = [
    {
      title: 'Canal',
      dataIndex: 'channel',
      key: 'channel',
      width: 100,
      render: (c: string) => <Tag color={c === 'whatsapp' ? 'green' : 'blue'}>{c}</Tag>,
    },
    { title: 'Téléphone client', dataIndex: 'customer_phone', key: 'customer_phone', width: 160 },
    {
      title: 'Ligne / WhatsApp Business',
      dataIndex: 'callcenter_phone',
      key: 'callcenter_phone',
      width: 180,
      render: (p: string | null) => p ?? '—',
    },
    {
      title: 'Client ID',
      dataIndex: 'client_id',
      key: 'client_id',
      width: 100,
      render: (id: number | null) => (id != null ? id : '—'),
    },
    {
      title: 'Non lus',
      dataIndex: 'unread_count',
      key: 'unread_count',
      width: 90,
    },
    {
      title: 'Dernier message',
      dataIndex: 'last_message_at',
      key: 'last_message_at',
      width: 180,
      render: (d: string | null) => (d ? formatDate(d) : '—'),
    },
    {
      title: '',
      key: 'action',
      width: 100,
      render: (_: unknown, row: CallCenterConversationRow) => (
        <Link to={`/callcenter/inbox/${row.id}`} state={{ conversation: row }}>
          <Button type="link" size="small">
            Ouvrir
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
      <Title level={2}>Boîte de réception (SMS / WhatsApp)</Title>
      <Card>
        <Table<CallCenterConversationRow>
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
