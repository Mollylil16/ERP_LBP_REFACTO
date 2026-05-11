import React, { useState } from 'react'
import {
  Table, Button, Tag, Card, Row, Col, Statistic, Modal, Form,
  Input, Select, InputNumber, Popconfirm, message, Progress,
} from 'antd'
import { PlusOutlined, CheckOutlined, StarOutlined } from '@ant-design/icons'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { rhService, RhEvaluation, RhDashboardEval } from '@services/rh.service'
import { usePermissions } from '@hooks/usePermissions'
import { PERMISSIONS } from '@constants/permissions'
import dayjs from 'dayjs'

const STATUT_COLOR: Record<string, string> = {
  brouillon: 'default',
  en_cours: 'processing',
  signe_evalue: 'gold',
  signe_evaluateur: 'cyan',
  valide_rh: 'purple',
  cloture: 'green',
}

const STATUT_LABEL: Record<string, string> = {
  brouillon: 'Brouillon',
  en_cours: 'En cours',
  signe_evalue: 'Signé (évalué)',
  signe_evaluateur: 'Signé (évaluateur)',
  valide_rh: 'Validé RH',
  cloture: 'Clôturé',
}

export const RhEvaluationsTab: React.FC = () => {
  const { hasPermission } = usePermissions()
  const qc = useQueryClient()
  const canCreate = hasPermission(PERMISSIONS.RH.EVALUATIONS_CREATE)
  const canUpdate = hasPermission(PERMISSIONS.RH.EVALUATIONS_UPDATE)

  const [createOpen, setCreateOpen] = useState(false)
  const [editEval, setEditEval] = useState<RhEvaluation | null>(null)
  const [filterStatut, setFilterStatut] = useState<string>('')
  const [form] = Form.useForm()
  const [editForm] = Form.useForm()

  const { data: evaluations = [], isLoading } = useQuery<RhEvaluation[]>({
    queryKey: ['rh-evaluations', filterStatut],
    queryFn: () => rhService.getEvaluations(undefined, filterStatut || undefined),
  })

  const { data: dashboard } = useQuery<RhDashboardEval>({
    queryKey: ['rh-dashboard-eval'],
    queryFn: rhService.getDashboardEval,
  })

  const createMut = useMutation({
    mutationFn: (data: Partial<RhEvaluation>) => rhService.createEvaluation(data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['rh-evaluations'] })
      qc.invalidateQueries({ queryKey: ['rh-dashboard-eval'] })
      setCreateOpen(false)
      form.resetFields()
      message.success('Évaluation créée')
    },
    onError: () => message.error('Erreur lors de la création'),
  })

  const updateMut = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<RhEvaluation> }) =>
      rhService.updateEvaluation(id, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['rh-evaluations'] })
      qc.invalidateQueries({ queryKey: ['rh-dashboard-eval'] })
      setEditEval(null)
      message.success('Évaluation mise à jour')
    },
    onError: () => message.error('Erreur lors de la mise à jour'),
  })

  const validerMut = useMutation({
    mutationFn: ({ id, etape }: { id: number; etape: 'evalue' | 'evaluateur' | 'rh' }) =>
      rhService.validerEvaluation(id, etape),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['rh-evaluations'] }); message.success('Étape validée') },
  })

  const colonnes = [
    {
      title: 'Employé',
      key: 'employe',
      render: (_: unknown, r: RhEvaluation) =>
        r.employe ? `${r.employe.nom} ${r.employe.prenoms}` : `#${r.id_employe}`,
    },
    { title: 'Type', dataIndex: 'type', key: 'type', render: (v: string) => <Tag>{v}</Tag> },
    { title: 'Période', dataIndex: 'periode', key: 'periode' },
    {
      title: 'Statut',
      dataIndex: 'statut',
      key: 'statut',
      render: (v: string) => <Tag color={STATUT_COLOR[v]}>{STATUT_LABEL[v]}</Tag>,
    },
    {
      title: 'Score résultats',
      dataIndex: 'score_resultats',
      key: 'score_resultats',
      render: (v: number | null, r: RhEvaluation) =>
        v !== null ? (
          <span>
            {v}/100
            {r.metriques_auto && (
              <Tag color="blue" style={{ marginLeft: 4, fontSize: 10 }}>auto</Tag>
            )}
          </span>
        ) : '—',
    },
    {
      title: 'Note globale',
      dataIndex: 'note_globale',
      key: 'note_globale',
      render: (v: number | null) =>
        v !== null ? (
          <span>
            <StarOutlined style={{ color: '#faad14', marginRight: 4 }} />
            {v}/100
          </span>
        ) : '—',
    },
    {
      title: 'Production auto',
      key: 'metriques_auto',
      render: (_: unknown, r: RhEvaluation) =>
        r.metriques_auto ? (
          <span style={{ fontSize: 11 }}>
            {r.metriques_auto.colis_count ?? 0} colis
          </span>
        ) : '—',
    },
    {
      title: 'Créé le',
      dataIndex: 'created_at',
      key: 'created_at',
      render: (v: string) => dayjs(v).format('DD/MM/YYYY'),
    },
    {
      title: 'Actions',
      key: 'actions',
      render: (_: unknown, r: RhEvaluation) => (
        <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
          {canUpdate && r.statut !== 'cloture' && (
            <Button size="small" onClick={() => { setEditEval(r); editForm.setFieldsValue(r) }}>
              Scores
            </Button>
          )}
          {canUpdate && r.statut === 'en_cours' && (
            <Popconfirm title="Valider (évalué) ?" onConfirm={() => validerMut.mutate({ id: r.id, etape: 'evalue' })}>
              <Button size="small" icon={<CheckOutlined />} type="primary" ghost>Signer évalué</Button>
            </Popconfirm>
          )}
          {canUpdate && r.statut === 'signe_evalue' && (
            <Popconfirm title="Valider (évaluateur) ?" onConfirm={() => validerMut.mutate({ id: r.id, etape: 'evaluateur' })}>
              <Button size="small" icon={<CheckOutlined />} type="primary" ghost>Signer évaluateur</Button>
            </Popconfirm>
          )}
          {canUpdate && r.statut === 'signe_evaluateur' && (
            <Popconfirm title="Valider RH ?" onConfirm={() => validerMut.mutate({ id: r.id, etape: 'rh' })}>
              <Button size="small" icon={<CheckOutlined />} type="primary">Valider RH</Button>
            </Popconfirm>
          )}
        </div>
      ),
    },
  ]

  return (
    <div>
      <Row gutter={16} style={{ marginBottom: 16 }}>
        <Col xs={24} md={8}>
          <Card size="small">
            <Statistic title="En cours" value={dashboard?.en_cours ?? 0} valueStyle={{ color: '#1677ff' }} />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card size="small">
            <Statistic title="Clôturées" value={dashboard?.clotures ?? 0} valueStyle={{ color: '#52c41a' }} />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card size="small">
            <Statistic
              title="Moyenne globale"
              value={dashboard?.moyenne_globale ?? 0}
              suffix="/100"
              precision={1}
              prefix={<StarOutlined />}
            />
          </Card>
        </Col>
      </Row>

      {/* Progression par type */}
      {dashboard?.par_type && dashboard.par_type.length > 0 && (
        <Card size="small" style={{ marginBottom: 16 }}>
          <Row gutter={16}>
            {dashboard.par_type.filter((t) => t.nb > 0).map((t) => (
              <Col key={t.type} xs={12} md={6}>
                <div style={{ marginBottom: 8 }}>
                  <Tag>{t.type}</Tag> {t.nb} éval(s)
                </div>
                {t.moyenne > 0 && <Progress percent={t.moyenne} size="small" strokeColor="#52c41a" />}
              </Col>
            ))}
          </Row>
        </Card>
      )}

      <div style={{ marginBottom: 16, display: 'flex', gap: 8, justifyContent: 'space-between', flexWrap: 'wrap' }}>
        <Select
          style={{ width: 180 }}
          placeholder="Filtrer par statut"
          allowClear
          onChange={(v: string) => setFilterStatut(v ?? '')}
        >
          {Object.entries(STATUT_LABEL).map(([k, v]) => (
            <Select.Option key={k} value={k}>{v}</Select.Option>
          ))}
        </Select>
        {canCreate && (
          <Button type="primary" icon={<PlusOutlined />} onClick={() => setCreateOpen(true)}>
            Nouvelle évaluation
          </Button>
        )}
      </div>

      <Table
        dataSource={evaluations}
        columns={colonnes}
        rowKey="id"
        loading={isLoading}
        size="small"
        pagination={{ pageSize: 15 }}
      />

      {/* Modal création */}
      <Modal
        title="Nouvelle évaluation"
        open={createOpen}
        onCancel={() => { setCreateOpen(false); form.resetFields() }}
        onOk={() => form.validateFields().then((vals: Partial<RhEvaluation>) => createMut.mutate(vals))}
        confirmLoading={createMut.isPending}
      >
        <Form form={form} layout="vertical">
          <Form.Item name="id_employe" label="ID Employé" rules={[{ required: true }]}>
            <InputNumber style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="id_evaluateur" label="ID Évaluateur">
            <InputNumber style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="type" label="Type" rules={[{ required: true }]}>
            <Select>
              {['annuelle', 'semestrielle', 'trimestrielle', 'fin_essai'].map((t) => (
                <Select.Option key={t} value={t}>{t}</Select.Option>
              ))}
            </Select>
          </Form.Item>
          <Form.Item name="periode" label="Période (ex: 2026)" rules={[{ required: true }]}>
            <Input placeholder="2026" />
          </Form.Item>
        </Form>
      </Modal>

      {/* Modal saisie scores */}
      <Modal
        title={`Scores — ${editEval ? (editEval.employe ? editEval.employe.nom : `#${editEval.id_employe}`) : ''}`}
        open={editEval !== null}
        onCancel={() => setEditEval(null)}
        onOk={() =>
          editForm.validateFields().then((vals: Partial<RhEvaluation>) =>
            updateMut.mutate({ id: editEval!.id, data: vals })
          )
        }
        confirmLoading={updateMut.isPending}
      >
        <Form form={editForm} layout="vertical">
          <Row gutter={8}>
            <Col xs={12}>
              <Form.Item name="score_resultats" label="Résultats (40%)">
                <InputNumber min={0} max={100} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
            <Col xs={12}>
              <Form.Item name="score_competences_metier" label="Compétences (25%)">
                <InputNumber min={0} max={100} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
            <Col xs={12}>
              <Form.Item name="score_comportement" label="Comportement (20%)">
                <InputNumber min={0} max={100} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
            <Col xs={12}>
              <Form.Item name="score_conformite" label="Conformité (10%)">
                <InputNumber min={0} max={100} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
            <Col xs={12}>
              <Form.Item name="score_developpement" label="Développement (5%)">
                <InputNumber min={0} max={100} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
          </Row>
          <Form.Item name="commentaire_evaluateur" label="Commentaire évaluateur">
            <Input.TextArea rows={3} />
          </Form.Item>
          <Form.Item name="plan_developpement" label="Plan de développement">
            <Input.TextArea rows={3} />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  )
}
