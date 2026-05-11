import React, { useState } from 'react'
import {
  Table,
  Button,
  Input,
  Select,
  Tag,
  Space,
  Modal,
  Form,
  DatePicker,
  Row,
  Col,
  message,
} from 'antd'
import { PlusOutlined, SearchOutlined, StopOutlined } from '@ant-design/icons'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { rhService, RhEmploye } from '@services/rh.service'
import { usePermissions } from '@hooks/usePermissions'
import { PERMISSIONS } from '@constants/permissions'
import dayjs from 'dayjs'

const { Option } = Select

const STATUT_COLOR: Record<string, string> = {
  actif: 'green',
  suspendu: 'orange',
  sorti: 'red',
}

export const RhEmployesTab: React.FC = () => {
  const qc = useQueryClient()
  const { hasPermission } = usePermissions()
  const canCreate = hasPermission(PERMISSIONS.RH.EMPLOYES_CREATE)
  const canUpdate = hasPermission(PERMISSIONS.RH.EMPLOYES_UPDATE)

  const [search, setSearch] = useState('')
  const [statutFilter, setStatutFilter] = useState<string | undefined>()
  const [showForm, setShowForm] = useState(false)
  const [sortieModal, setSortieModal] = useState<RhEmploye | null>(null)
  const [form] = Form.useForm()
  const [sortieForm] = Form.useForm()

  const { data: employes = [], isLoading } = useQuery<RhEmploye[]>({
    queryKey: ['rh-employes', search, statutFilter],
    queryFn: () => rhService.getEmployes(search || undefined, statutFilter),
    staleTime: 30_000,
  })

  const createMut = useMutation({
    mutationFn: (data: Partial<RhEmploye>) => rhService.createEmploye(data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['rh-employes'] })
      qc.invalidateQueries({ queryKey: ['rh-dashboard'] })
      message.success('Employé créé avec succès')
      setShowForm(false)
      form.resetFields()
    },
    onError: (e: { message?: string }) => message.error(e.message ?? 'Erreur lors de la création'),
  })

  const sortieMut = useMutation({
    mutationFn: ({ id, date_sortie, motif }: { id: number; date_sortie: string; motif?: string }) =>
      rhService.sortirEmploye(id, date_sortie, motif),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['rh-employes'] })
      qc.invalidateQueries({ queryKey: ['rh-dashboard'] })
      message.success('Sortie enregistrée')
      setSortieModal(null)
      sortieForm.resetFields()
    },
    onError: (e: { message?: string }) => message.error(e.message ?? 'Erreur'),
  })

  const columns = [
    { title: 'Matricule', dataIndex: 'matricule', key: 'matricule', width: 120, fixed: 'left' as const },
    {
      title: 'Nom & Prénoms',
      key: 'nom',
      render: (_: unknown, r: RhEmploye) => `${r.nom} ${r.prenoms}`,
      width: 200,
    },
    { title: 'Poste', dataIndex: 'intitule_poste', key: 'poste', width: 160 },
    {
      title: 'Contrat',
      dataIndex: 'type_contrat_actuel',
      key: 'contrat',
      render: (v: string) => <Tag>{v}</Tag>,
      width: 90,
    },
    { title: 'Agence', key: 'agence', render: (_: unknown, r: RhEmploye) => r.agence?.nom ?? '—', width: 140 },
    {
      title: 'Embauche',
      dataIndex: 'date_embauche',
      key: 'embauche',
      render: (v: string) => dayjs(v).format('DD/MM/YYYY'),
      width: 110,
    },
    {
      title: 'Statut',
      dataIndex: 'statut',
      key: 'statut',
      render: (v: string) => <Tag color={STATUT_COLOR[v] ?? 'default'}>{v}</Tag>,
      width: 90,
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 120,
      render: (_: unknown, r: RhEmploye) =>
        canUpdate && r.statut === 'actif' ? (
          <Button
            size="small"
            icon={<StopOutlined />}
            danger
            onClick={() => setSortieModal(r)}
          >
            Sortie
          </Button>
        ) : null,
    },
  ]

  return (
    <div>
      <Row gutter={12} style={{ marginBottom: 16 }} align="middle">
        <Col flex="auto">
          <Input.Search
            placeholder="Rechercher (nom, matricule, poste…)"
            prefix={<SearchOutlined />}
            value={search}
            onChange={(e: React.ChangeEvent<HTMLInputElement>) => setSearch(e.target.value)}
            allowClear
            style={{ maxWidth: 380 }}
          />
        </Col>
        <Col>
          <Select
            placeholder="Statut"
            allowClear
            style={{ width: 140 }}
            onChange={setStatutFilter}
          >
            <Option value="actif">Actif</Option>
            <Option value="suspendu">Suspendu</Option>
            <Option value="sorti">Sorti</Option>
          </Select>
        </Col>
        {canCreate && (
          <Col>
            <Button type="primary" icon={<PlusOutlined />} onClick={() => setShowForm(true)}>
              Nouvel employé
            </Button>
          </Col>
        )}
      </Row>

      <Table
        dataSource={employes}
        columns={columns}
        rowKey="id"
        loading={isLoading}
        scroll={{ x: 1100 }}
        size="small"
        pagination={{ pageSize: 20, showSizeChanger: true }}
      />

      {/* Modal création */}
      <Modal
        title="Nouvel employé"
        open={showForm}
        onCancel={() => { setShowForm(false); form.resetFields() }}
        onOk={() => form.validateFields().then((vals: Record<string, unknown> & { date_embauche?: { format: (f: string) => string }; date_naissance?: { format: (f: string) => string } }) => {
          createMut.mutate({
            ...vals,
            date_embauche: vals.date_embauche?.format('YYYY-MM-DD'),
            date_naissance: vals.date_naissance?.format('YYYY-MM-DD') ?? null,
          })
        })}
        confirmLoading={createMut.isPending}
        width={700}
      >
        <Form form={form} layout="vertical" size="small">
          <Row gutter={12}>
            <Col xs={24} sm={12}>
              <Form.Item name="nom" label="Nom" rules={[{ required: true }]}>
                <Input />
              </Form.Item>
            </Col>
            <Col xs={24} sm={12}>
              <Form.Item name="prenoms" label="Prénoms" rules={[{ required: true }]}>
                <Input />
              </Form.Item>
            </Col>
            <Col xs={24} sm={12}>
              <Form.Item name="date_embauche" label="Date d'embauche" rules={[{ required: true }]}>
                <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" />
              </Form.Item>
            </Col>
            <Col xs={24} sm={12}>
              <Form.Item name="type_contrat_actuel" label="Type de contrat" rules={[{ required: true }]}>
                <Select>
                  <Option value="CDI">CDI</Option>
                  <Option value="CDD">CDD</Option>
                  <Option value="STAGE">Stage</Option>
                  <Option value="INTERIM">Intérim</Option>
                </Select>
              </Form.Item>
            </Col>
            <Col xs={24} sm={12}>
              <Form.Item name="intitule_poste" label="Intitulé du poste">
                <Input />
              </Form.Item>
            </Col>
            <Col xs={24} sm={12}>
              <Form.Item name="telephone" label="Téléphone">
                <Input />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="sexe" label="Sexe">
                <Select allowClear>
                  <Option value="M">Masculin</Option>
                  <Option value="F">Féminin</Option>
                </Select>
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="date_naissance" label="Date de naissance">
                <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" />
              </Form.Item>
            </Col>
          </Row>
        </Form>
      </Modal>

      {/* Modal sortie */}
      <Modal
        title={sortieModal ? `Sortie de ${sortieModal.nom} ${sortieModal.prenoms}` : ''}
        open={!!sortieModal}
        onCancel={() => { setSortieModal(null); sortieForm.resetFields() }}
        onOk={() => sortieForm.validateFields().then((vals: { date_sortie: { format: (f: string) => string }; motif?: string }) => {
          if (!sortieModal) return
          sortieMut.mutate({
            id: sortieModal.id,
            date_sortie: vals.date_sortie.format('YYYY-MM-DD'),
            motif: vals.motif,
          })
        })}
        confirmLoading={sortieMut.isPending}
      >
        <Form form={sortieForm} layout="vertical" size="small">
          <Form.Item name="date_sortie" label="Date de sortie" rules={[{ required: true }]}>
            <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" />
          </Form.Item>
          <Form.Item name="motif" label="Motif de sortie">
            <Input.TextArea rows={3} />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  )
}
