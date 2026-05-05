import React, { useState } from 'react'
import {
  Table,
  Button,
  Select,
  Tag,
  Space,
  Modal,
  Form,
  DatePicker,
  InputNumber,
  Input,
  Row,
  Col,
  message,
  Popconfirm,
} from 'antd'
import { PlusOutlined, CheckOutlined, CloseOutlined } from '@ant-design/icons'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { rhService, RhCongeRequest, RhCongeType } from '@services/rh.service'
import { usePermissions } from '@hooks/usePermissions'
import { PERMISSIONS } from '@constants/permissions'
import dayjs from 'dayjs'

const { Option } = Select

const STATUT_COLOR: Record<string, string> = {
  en_attente: 'gold',
  approuve_manager: 'blue',
  approuve: 'green',
  refuse: 'red',
  annule: 'default',
}

const STATUT_LABEL: Record<string, string> = {
  en_attente: 'En attente',
  approuve_manager: 'Approuvé manager',
  approuve: 'Approuvé RH',
  refuse: 'Refusé',
  annule: 'Annulé',
}

export const RhCongesTab: React.FC = () => {
  const qc = useQueryClient()
  const { hasPermission } = usePermissions()
  const canCreate = hasPermission(PERMISSIONS.RH.CONGES_CREATE)
  const canValidate = hasPermission(PERMISSIONS.RH.CONGES_VALIDATE)

  const [statutFilter, setStatutFilter] = useState<string | undefined>('en_attente')
  const [showForm, setShowForm] = useState(false)
  const [commentaire, setCommentaire] = useState('')
  const [form] = Form.useForm()

  const { data: conges = [], isLoading } = useQuery<RhCongeRequest[]>({
    queryKey: ['rh-conges', statutFilter],
    queryFn: () => rhService.getConges(statutFilter),
    staleTime: 30_000,
    refetchInterval: 60_000,
  })

  const { data: congeTypes = [] } = useQuery<RhCongeType[]>({
    queryKey: ['rh-conge-types'],
    queryFn: rhService.getCongeTypes,
    staleTime: 300_000,
  })

  const createMut = useMutation({
    mutationFn: (data: Partial<RhCongeRequest>) => rhService.createConge(data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['rh-conges'] })
      qc.invalidateQueries({ queryKey: ['rh-dashboard'] })
      message.success('Demande de congé soumise')
      setShowForm(false)
      form.resetFields()
    },
    onError: (e: { message?: string }) => message.error(e.message ?? 'Erreur'),
  })

  const validerMut = useMutation({
    mutationFn: ({ id, approuve, comm }: { id: number; approuve: boolean; comm?: string }) =>
      rhService.validerConge(id, approuve, comm),
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['rh-conges'] })
      qc.invalidateQueries({ queryKey: ['rh-dashboard'] })
      message.success(vars.approuve ? 'Congé approuvé' : 'Congé refusé')
    },
    onError: (e: { message?: string }) => message.error(e.message ?? 'Erreur'),
  })

  const columns = [
    {
      title: 'Employé',
      key: 'employe',
      render: (_: unknown, r: RhCongeRequest) =>
        r.employe ? `${r.employe.matricule} — ${r.employe.nom} ${r.employe.prenoms}` : r.id_employe,
      width: 240,
    },
    {
      title: 'Type de congé',
      key: 'type',
      render: (_: unknown, r: RhCongeRequest) => r.type_conge?.libelle ?? r.id_conge_type,
      width: 160,
    },
    {
      title: 'Période',
      key: 'periode',
      render: (_: unknown, r: RhCongeRequest) =>
        `${dayjs(r.date_debut).format('DD/MM/YYYY')} → ${dayjs(r.date_fin).format('DD/MM/YYYY')}`,
      width: 200,
    },
    {
      title: 'Jours',
      dataIndex: 'nb_jours',
      key: 'jours',
      width: 70,
      render: (v: number) => <strong>{v}</strong>,
    },
    {
      title: 'Statut',
      dataIndex: 'statut',
      key: 'statut',
      render: (v: string) => <Tag color={STATUT_COLOR[v] ?? 'default'}>{STATUT_LABEL[v] ?? v}</Tag>,
      width: 140,
    },
    {
      title: 'Soumis le',
      dataIndex: 'created_at',
      key: 'date',
      render: (v: string) => dayjs(v).format('DD/MM/YYYY'),
      width: 110,
    },
    ...(canValidate
      ? [
          {
            title: 'Actions',
            key: 'actions',
            width: 160,
            render: (_: unknown, r: RhCongeRequest) =>
              r.statut === 'en_attente' || r.statut === 'approuve_manager' ? (
                <Space>
                  <Popconfirm
                    title="Approuver ce congé ?"
                    onConfirm={() => validerMut.mutate({ id: r.id, approuve: true, comm: commentaire })}
                  >
                    <Button size="small" type="primary" icon={<CheckOutlined />}>
                      Approuver
                    </Button>
                  </Popconfirm>
                  <Popconfirm
                    title="Refuser ce congé ?"
                    onConfirm={() => validerMut.mutate({ id: r.id, approuve: false, comm: commentaire })}
                  >
                    <Button size="small" danger icon={<CloseOutlined />}>
                      Refuser
                    </Button>
                  </Popconfirm>
                </Space>
              ) : null,
          },
        ]
      : []),
  ]

  return (
    <div>
      <Row gutter={12} style={{ marginBottom: 16 }} align="middle">
        <Col>
          <Select
            value={statutFilter}
            allowClear
            placeholder="Statut"
            style={{ width: 180 }}
            onChange={setStatutFilter}
          >
            <Option value="en_attente">En attente</Option>
            <Option value="approuve_manager">Approuvé manager</Option>
            <Option value="approuve">Approuvé RH</Option>
            <Option value="refuse">Refusé</Option>
            <Option value="annule">Annulé</Option>
          </Select>
        </Col>
        {canCreate && (
          <Col>
            <Button type="primary" icon={<PlusOutlined />} onClick={() => setShowForm(true)}>
              Nouvelle demande
            </Button>
          </Col>
        )}
      </Row>

      {canValidate && (
        <div style={{ marginBottom: 12 }}>
          <Input.TextArea
            placeholder="Commentaire de validation (optionnel)"
            value={commentaire}
            onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setCommentaire(e.target.value)}
            rows={1}
            style={{ maxWidth: 500 }}
          />
        </div>
      )}

      <Table
        dataSource={conges}
        columns={columns}
        rowKey="id"
        loading={isLoading}
        size="small"
        pagination={{ pageSize: 20 }}
        scroll={{ x: 1100 }}
        rowClassName={(r: RhCongeRequest) =>
          r.statut === 'en_attente' ? 'lbp-row-warning' : ''
        }
      />

      <Modal
        title="Nouvelle demande de congé"
        open={showForm}
        onCancel={() => { setShowForm(false); form.resetFields() }}
        onOk={() => form.validateFields().then((vals: Record<string, unknown> & { date_debut?: { format: (f: string) => string }; date_fin?: { format: (f: string) => string } }) => {
          createMut.mutate({
            ...vals,
            date_debut: vals.date_debut?.format('YYYY-MM-DD'),
            date_fin: vals.date_fin?.format('YYYY-MM-DD'),
          })
        })}
        confirmLoading={createMut.isPending}
        width={580}
      >
        <Form form={form} layout="vertical" size="small">
          <Row gutter={12}>
            <Col span={12}>
              <Form.Item name="id_employe" label="ID employé" rules={[{ required: true }]}>
                <InputNumber style={{ width: '100%' }} min={1} />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="id_conge_type" label="Type de congé" rules={[{ required: true }]}>
                <Select>
                  {congeTypes.map((t) => (
                    <Option key={t.id} value={t.id}>{t.libelle}</Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="date_debut" label="Date de début" rules={[{ required: true }]}>
                <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="date_fin" label="Date de fin" rules={[{ required: true }]}>
                <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="nb_jours" label="Nombre de jours" rules={[{ required: true }]}>
                <InputNumber style={{ width: '100%' }} min={1} />
              </Form.Item>
            </Col>
            <Col span={24}>
              <Form.Item name="motif" label="Motif">
                <Input.TextArea rows={2} />
              </Form.Item>
            </Col>
          </Row>
        </Form>
      </Modal>
    </div>
  )
}
