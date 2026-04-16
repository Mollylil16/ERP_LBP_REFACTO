import React, { useEffect, useState } from 'react'
import {
  Table,
  Button,
  Space,
  Input,
  Tag,
  Popconfirm,
  Tooltip,
  Card,
  Row,
  Col,
} from 'antd'
import {
  EditOutlined,
  DeleteOutlined,
  EyeOutlined,
  SearchOutlined,
  PlusOutlined,
  ReloadOutlined,
} from '@ant-design/icons'
import type { ColumnsType } from 'antd/es/table'
import { ClientColis } from '@services/clients.service'
import { formatDate, formatPhone } from '@utils/format'
import { useClientsList, useDeleteClient } from '@hooks/useClients'
import { WithPermission } from '@components/common/WithPermission'
import { PERMISSIONS } from '@constants/permissions'

interface ClientListProps {
  onEdit?: (client: ClientColis) => void
  onView?: (client: ClientColis) => void
  onCreate?: () => void
  /** Pré-remplissage (ex: depuis Call center : `?search=...`) */
  initialSearch?: string
}

export const ClientList: React.FC<ClientListProps> = ({
  onEdit,
  onView,
  onCreate,
  initialSearch,
}) => {
  const [pagination, setPagination] = useState({ page: 1, limit: 20 })
  const [searchTerm, setSearchTerm] = useState(initialSearch ?? '')

  useEffect(() => {
    if (typeof initialSearch === 'string') {
      setSearchTerm(initialSearch)
      setPagination((p) => ({ ...p, page: 1 }))
    }
  }, [initialSearch])

  const { data, isLoading, refetch } = useClientsList({
    ...pagination,
    search: searchTerm || undefined,
  })

  const deleteMutation = useDeleteClient()

  const handleSearch = (value: string) => {
    setSearchTerm(value)
    setPagination({ ...pagination, page: 1 })
  }

  const handleTableChange = (newPagination: any) => {
    setPagination({
      page: newPagination.current || 1,
      limit: newPagination.pageSize || 20,
    })
  }

  const handleDelete = async (id: number) => {
    await deleteMutation.mutateAsync(id)
    refetch()
  }

  const columns: ColumnsType<ClientColis> = [
    {
      title: 'Nom Expéditeur',
      dataIndex: 'nom_exp',
      key: 'nom_exp',
      fixed: 'left',
      width: 200,
      render: (text: string) => <strong>{text}</strong>,
    },
    {
      title: 'Type Pièce',
      dataIndex: 'type_piece_exp',
      key: 'type_piece_exp',
      width: 150,
      render: (type: string) => <Tag>{type}</Tag>,
    },
    {
      title: 'N° Pièce',
      dataIndex: 'num_piece_exp',
      key: 'num_piece_exp',
      width: 150,
    },
    {
      title: 'Téléphone',
      dataIndex: 'tel_exp',
      key: 'tel_exp',
      width: 150,
      render: (phone: string) => formatPhone(phone),
    },
    {
      title: 'Email',
      dataIndex: 'email_exp',
      key: 'email_exp',
      width: 200,
      ellipsis: true,
    },
    {
      title: 'Date enregistrement',
      dataIndex: 'date_enrg',
      key: 'date_enrg',
      width: 150,
      render: (date: string) => formatDate(date),
      sorter: true,
    },
    {
      title: 'Actions',
      key: 'actions',
      fixed: 'right',
      width: 150,
      render: (_: any, record: ClientColis) => (
        <Space size="small">
          {onView && (
            <Tooltip title="Voir détails">
              <Button
                size="small"
                icon={<EyeOutlined />}
                onClick={() => onView(record)}
              />
            </Tooltip>
          )}

          <WithPermission permission={PERMISSIONS.CLIENTS.UPDATE}>
            {onEdit && (
              <Tooltip title="Modifier">
                <Button
                  type="primary"
                  size="small"
                  icon={<EditOutlined />}
                  onClick={() => onEdit(record)}
                />
              </Tooltip>
            )}
          </WithPermission>

          <WithPermission permission={PERMISSIONS.CLIENTS.DELETE}>
            <Popconfirm
              title="Êtes-vous sûr de vouloir supprimer ce client ?"
              onConfirm={() => handleDelete(record.id)}
              okText="Oui"
              cancelText="Non"
            >
              <Tooltip title="Supprimer">
                <Button
                  type="primary"
                  danger
                  size="small"
                  icon={<DeleteOutlined />}
                  loading={deleteMutation.isPending}
                />
              </Tooltip>
            </Popconfirm>
          </WithPermission>
        </Space>
      ),
    },
  ]

  return (
    <div>
      {/* BARRE DE RECHERCHE */}
      <Card style={{ marginBottom: 16 }}>
        <Row gutter={16} align="middle">
          <Col xs={24} sm={16} md={12}>
            <Input
              placeholder="Rechercher par nom, téléphone, email..."
              prefix={<SearchOutlined />}
              allowClear
              value={searchTerm}
              onChange={(e: React.ChangeEvent<HTMLInputElement>) => handleSearch(e.target.value)}
              onPressEnter={(e: React.KeyboardEvent<HTMLInputElement>) => handleSearch(e.currentTarget.value)}
              size="large"
            />
          </Col>

          <Col xs={24} sm={8} md={12} style={{ textAlign: 'right' }}>
            <Space>
              <Button
                icon={<ReloadOutlined />}
                onClick={() => refetch()}
                size="large"
              >
                Actualiser
              </Button>

              <WithPermission permission={PERMISSIONS.CLIENTS.CREATE}>
                {onCreate && (
                  <Button
                    type="primary"
                    icon={<PlusOutlined />}
                    onClick={onCreate}
                    size="large"
                  >
                    Nouveau Client
                  </Button>
                )}
              </WithPermission>
            </Space>
          </Col>
        </Row>
      </Card>

      {/* TABLEAU */}
      <Card>
        <Table
          columns={columns}
          dataSource={data || []}
          loading={isLoading}
          rowKey="id"
          scroll={{ x: 1000 }}
          pagination={{
            current: pagination.page,
            pageSize: pagination.limit,
            total: data?.length || 0,
            showSizeChanger: true,
            showTotal: (total: number) => `Total: ${total} clients`,
            pageSizeOptions: ['10', '20', '50', '100'],
          }}
          onChange={handleTableChange}
        />
      </Card>
    </div>
  )
}
