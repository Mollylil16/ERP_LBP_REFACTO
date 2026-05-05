import React, { useState } from 'react'
import {
  Table, Button, Tag, Card, Row, Col, Statistic, Modal, Form,
  Input, Select, InputNumber, DatePicker, Popconfirm, message, Progress, Badge,
} from 'antd'
import { PlusOutlined, UserAddOutlined, TrophyOutlined } from '@ant-design/icons'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  rhService, RhFormation, RhInscription, RhDashboardFormation,
} from '@services/rh.service'
import { usePermissions } from '@hooks/usePermissions'
import { PERMISSIONS } from '@constants/permissions'
import dayjs from 'dayjs'

const TYPE_COLOR: Record<string, string> = {
  presentiel: 'blue',
  distanciel: 'cyan',
  elearning: 'purple',
  mixte: 'geekblue',
}

const INSCR_COLOR: Record<string, string> = {
  en_attente: 'default',
  confirme: 'blue',
  termine: 'green',
  annule: 'red',
}

const fmt = (n: number) =>
  new Intl.NumberFormat('fr-CI', { style: 'decimal', maximumFractionDigits: 0 }).format(n) + ' FCFA'

export const RhFormationTab: React.FC = () => {
  const { hasPermission } = usePermissions()
  const qc = useQueryClient()
  const canCreate = hasPermission(PERMISSIONS.RH.FORMATION_CREATE)
  const canUpdate = hasPermission(PERMISSIONS.RH.FORMATION_UPDATE)

  const [formationOpen, setFormationOpen] = useState(false)
  const [inscrOpen, setInscrOpen] = useState(false)
  const [selectedFormation, setSelectedFormation] = useState<RhFormation | null>(null)
  const [filterAnnee, setFilterAnnee] = useState<number>(dayjs().year())
  const [form] = Form.useForm()
  const [inscrForm] = Form.useForm()

  const { data: formations = [], isLoading } = useQuery<RhFormation[]>({
    queryKey: ['rh-formations', filterAnnee],
    queryFn: () => rhService.getFormations(filterAnnee),
  })

  const { data: inscriptions = [] } = useQuery<RhInscription[]>({
    queryKey: ['rh-inscriptions', selectedFormation?.id],
    queryFn: () => rhService.getInscriptions(selectedFormation?.id),
    enabled: selectedFormation !== null,
  })

  const { data: dashboard } = useQuery<RhDashboardFormation>({
    queryKey: ['rh-dashboard-formation'],
    queryFn: rhService.getDashboardFormation,
  })

  const createFormationMut = useMutation({
    mutationFn: (data: Partial<RhFormation> & { date_debut?: { format?: (f: string) => string }; date_fin?: { format?: (f: string) => string } }) => {
      const { date_debut, date_fin, ...rest } = data
      return rhService.createFormation({
        ...rest,
        date_debut: date_debut?.format?.('YYYY-MM-DD'),
        date_fin: date_fin?.format?.('YYYY-MM-DD'),
      })
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['rh-formations'] })
      qc.invalidateQueries({ queryKey: ['rh-dashboard-formation'] })
      setFormationOpen(false)
      form.resetFields()
      message.success('Formation créée')
    },
    onError: () => message.error('Erreur lors de la création'),
  })

  const inscrireMut = useMutation({
    mutationFn: (data: { id_formation: number; id_employe: number }) => rhService.inscrire(data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['rh-inscriptions'] })
      qc.invalidateQueries({ queryKey: ['rh-dashboard-formation'] })
      setInscrOpen(false)
      inscrForm.resetFields()
      message.success('Employé inscrit')
    },
    onError: () => message.error('Erreur lors de l\'inscription'),
  })

  const updateInscrMut = useMutation({
    mutationFn: ({ id, statut }: { id: number; statut: string }) =>
      rhService.updateInscription(id, { statut: statut as RhInscription['statut'] }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['rh-inscriptions'] })
      qc.invalidateQueries({ queryKey: ['rh-dashboard-formation'] })
      message.success('Statut mis à jour')
    },
  })

  const colonnesFormations = [
    {
      title: 'Formation',
      dataIndex: 'titre',
      key: 'titre',
      render: (v: string, r: RhFormation) => (
        <a onClick={() => setSelectedFormation(r === selectedFormation ? null : r)}>{v}</a>
      ),
    },
    {
      title: 'Type',
      dataIndex: 'type',
      key: 'type',
      render: (v: string) => <Tag color={TYPE_COLOR[v]}>{v}</Tag>,
    },
    {
      title: 'Organisme',
      dataIndex: 'organisme',
      key: 'organisme',
      render: (v: string | null) => v ?? '—',
    },
    {
      title: 'Dates',
      key: 'dates',
      render: (_: unknown, r: RhFormation) => {
        if (!r.date_debut) return '—'
        return `${dayjs(r.date_debut).format('DD/MM')} → ${r.date_fin ? dayjs(r.date_fin).format('DD/MM/YYYY') : '?'}`
      },
    },
    {
      title: 'Durée',
      dataIndex: 'duree_heures',
      key: 'duree_heures',
      render: (v: number | null) => v ? `${v}h` : '—',
    },
    {
      title: 'Coût',
      dataIndex: 'cout',
      key: 'cout',
      render: (v: number | null) => v ? fmt(v) : '—',
    },
    {
      title: 'Places',
      dataIndex: 'places_max',
      key: 'places_max',
      render: (v: number) => v > 0 ? `${v} max` : '∞',
    },
    {
      title: 'Actif',
      dataIndex: 'est_actif',
      key: 'est_actif',
      render: (v: boolean) => <Badge status={v ? 'success' : 'default'} text={v ? 'Oui' : 'Non'} />,
    },
  ]

  const colonnesInscriptions = [
    {
      title: 'Employé',
      key: 'employe',
      render: (_: unknown, r: RhInscription) =>
        r.employe ? `${r.employe.nom} ${r.employe.prenoms}` : `#${r.id_employe}`,
    },
    {
      title: 'Statut',
      dataIndex: 'statut',
      key: 'statut',
      render: (v: string) => <Tag color={INSCR_COLOR[v]}>{v}</Tag>,
    },
    {
      title: 'Satisfaction',
      dataIndex: 'note_satisfaction',
      key: 'note_satisfaction',
      render: (v: number | null) => v !== null ? (
        <span><TrophyOutlined style={{ color: '#faad14', marginRight: 4 }} />{v}/10</span>
      ) : '—',
    },
    {
      title: 'Actions',
      key: 'actions',
      render: (_: unknown, r: RhInscription) => canUpdate && (
        <div style={{ display: 'flex', gap: 4 }}>
          {r.statut === 'en_attente' && (
            <Popconfirm title="Confirmer ?" onConfirm={() => updateInscrMut.mutate({ id: r.id, statut: 'confirme' })}>
              <Button size="small" type="primary" ghost>Confirmer</Button>
            </Popconfirm>
          )}
          {r.statut === 'confirme' && (
            <Popconfirm title="Marquer comme terminé ?" onConfirm={() => updateInscrMut.mutate({ id: r.id, statut: 'termine' })}>
              <Button size="small" type="primary">Terminé</Button>
            </Popconfirm>
          )}
          {r.statut !== 'annule' && r.statut !== 'termine' && (
            <Popconfirm title="Annuler ?" onConfirm={() => updateInscrMut.mutate({ id: r.id, statut: 'annule' })}>
              <Button size="small" danger type="link">Annuler</Button>
            </Popconfirm>
          )}
        </div>
      ),
    },
  ]

  return (
    <div>
      <Row gutter={16} style={{ marginBottom: 16 }}>
        <Col xs={24} md={6}>
          <Card size="small">
            <Statistic title="Formations planifiées" value={dashboard?.formations_planifiees ?? 0} />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card size="small">
            <Statistic title="Inscriptions totales" value={dashboard?.inscriptions_total ?? 0} />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card size="small">
            <div style={{ marginBottom: 4 }}>Taux de réalisation</div>
            <Progress percent={dashboard?.taux_realisation ?? 0} strokeColor="#52c41a" />
          </Card>
        </Col>
        <Col xs={24} md={6}>
          <Card size="small">
            <Statistic title="Coût total plan" value={dashboard?.cout_total ?? 0} suffix="FCFA" />
          </Card>
        </Col>
      </Row>

      <div style={{ marginBottom: 16, display: 'flex', gap: 8, justifyContent: 'space-between', flexWrap: 'wrap', alignItems: 'center' }}>
        <Select
          style={{ width: 120 }}
          value={filterAnnee}
          onChange={(v: number) => setFilterAnnee(v)}
        >
          {[dayjs().year() - 1, dayjs().year(), dayjs().year() + 1].map((y) => (
            <Select.Option key={y} value={y}>{y}</Select.Option>
          ))}
        </Select>
        {canCreate && (
          <Button type="primary" icon={<PlusOutlined />} onClick={() => setFormationOpen(true)}>
            Nouvelle formation
          </Button>
        )}
      </div>

      <Row gutter={16}>
        <Col xs={24} md={selectedFormation ? 14 : 24}>
          <Table
            dataSource={formations}
            columns={colonnesFormations}
            rowKey="id"
            loading={isLoading}
            size="small"
            pagination={{ pageSize: 10 }}
            rowClassName={(r: RhFormation) => r.id === selectedFormation?.id ? 'ant-table-row-selected' : ''}
          />
        </Col>

        {selectedFormation && (
          <Col xs={24} md={10}>
            <Card
              title={`Inscriptions — ${selectedFormation.titre}`}
              size="small"
              extra={
                canCreate && (
                  <Button
                    size="small"
                    icon={<UserAddOutlined />}
                    type="primary"
                    onClick={() => { inscrForm.setFieldValue('id_formation', selectedFormation.id); setInscrOpen(true) }}
                  >
                    Inscrire
                  </Button>
                )
              }
            >
              <Table
                dataSource={inscriptions}
                columns={colonnesInscriptions}
                rowKey="id"
                size="small"
                pagination={{ pageSize: 8 }}
              />
            </Card>
          </Col>
        )}
      </Row>

      {/* Modal formation */}
      <Modal
        title="Nouvelle formation"
        open={formationOpen}
        onCancel={() => { setFormationOpen(false); form.resetFields() }}
        onOk={() => form.validateFields().then((vals: Partial<RhFormation> & { date_debut?: { format?: (f: string) => string }; date_fin?: { format?: (f: string) => string } }) => createFormationMut.mutate(vals))}
        confirmLoading={createFormationMut.isPending}
        width={600}
      >
        <Form form={form} layout="vertical" initialValues={{ annee_plan: filterAnnee }}>
          <Form.Item name="titre" label="Titre" rules={[{ required: true }]}>
            <Input />
          </Form.Item>
          <Row gutter={8}>
            <Col xs={12}>
              <Form.Item name="type" label="Type" rules={[{ required: true }]}>
                <Select>
                  {['presentiel', 'distanciel', 'elearning', 'mixte'].map((t) => (
                    <Select.Option key={t} value={t}>{t}</Select.Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
            <Col xs={12}>
              <Form.Item name="organisme" label="Organisme">
                <Input />
              </Form.Item>
            </Col>
          </Row>
          <Row gutter={8}>
            <Col xs={12}>
              <Form.Item name="date_debut" label="Date début">
                <DatePicker style={{ width: '100%' }} />
              </Form.Item>
            </Col>
            <Col xs={12}>
              <Form.Item name="date_fin" label="Date fin">
                <DatePicker style={{ width: '100%' }} />
              </Form.Item>
            </Col>
          </Row>
          <Row gutter={8}>
            <Col xs={8}>
              <Form.Item name="duree_heures" label="Durée (h)">
                <InputNumber style={{ width: '100%' }} min={0} />
              </Form.Item>
            </Col>
            <Col xs={8}>
              <Form.Item name="cout" label="Coût (FCFA)">
                <InputNumber style={{ width: '100%' }} min={0} />
              </Form.Item>
            </Col>
            <Col xs={8}>
              <Form.Item name="places_max" label="Places max" initialValue={0}>
                <InputNumber style={{ width: '100%' }} min={0} />
              </Form.Item>
            </Col>
          </Row>
          <Form.Item name="annee_plan" label="Année plan">
            <InputNumber style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="description" label="Description">
            <Input.TextArea rows={2} />
          </Form.Item>
        </Form>
      </Modal>

      {/* Modal inscription */}
      <Modal
        title="Inscrire un employé"
        open={inscrOpen}
        onCancel={() => { setInscrOpen(false); inscrForm.resetFields() }}
        onOk={() =>
          inscrForm.validateFields().then((vals: { id_formation: number; id_employe: number }) =>
            inscrireMut.mutate(vals)
          )
        }
        confirmLoading={inscrireMut.isPending}
      >
        <Form form={inscrForm} layout="vertical">
          <Form.Item name="id_formation" label="Formation" rules={[{ required: true }]}>
            <Select disabled={selectedFormation !== null}>
              {formations.map((f) => (
                <Select.Option key={f.id} value={f.id}>{f.titre}</Select.Option>
              ))}
            </Select>
          </Form.Item>
          <Form.Item name="id_employe" label="ID Employé" rules={[{ required: true }]}>
            <InputNumber style={{ width: '100%' }} />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  )
}
