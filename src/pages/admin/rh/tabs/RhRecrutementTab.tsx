import React, { useState } from 'react'
import {
  Table, Button, Tag, Card, Row, Col, Statistic, Modal, Form,
  Input, Select, InputNumber, DatePicker, Popconfirm, message, Tabs,
} from 'antd'
import { PlusOutlined, ArrowRightOutlined } from '@ant-design/icons'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  rhService, RhPoste, RhCandidature, RhDashboardRecrutement,
} from '@services/rh.service'
import { usePermissions } from '@hooks/usePermissions'
import { PERMISSIONS } from '@constants/permissions'
import dayjs from 'dayjs'

const POSTE_COLOR: Record<string, string> = {
  ouvert: 'green',
  en_cours: 'blue',
  pourvu: 'purple',
  annule: 'red',
}

const CAND_COLOR: Record<string, string> = {
  nouveau: 'default',
  preselectionne: 'blue',
  entretien: 'gold',
  retenu: 'cyan',
  refuse: 'red',
  embauche: 'green',
}

const PIPELINE_STEPS = ['nouveau', 'preselectionne', 'entretien', 'retenu', 'embauche']

export const RhRecrutementTab: React.FC = () => {
  const { hasPermission } = usePermissions()
  const qc = useQueryClient()
  const canCreate = hasPermission(PERMISSIONS.RH.RECRUTEMENT_CREATE)
  const canUpdate = hasPermission(PERMISSIONS.RH.RECRUTEMENT_UPDATE)

  const [posteOpen, setPosteOpen] = useState(false)
  const [candOpen, setCandOpen] = useState(false)
  const [selectedPoste, setSelectedPoste] = useState<number | null>(null)
  const [posteForm] = Form.useForm()
  const [candForm] = Form.useForm()

  const { data: postes = [], isLoading: loadingPostes } = useQuery<RhPoste[]>({
    queryKey: ['rh-postes'],
    queryFn: () => rhService.getPostes(),
  })

  const { data: candidatures = [], isLoading: loadingCands } = useQuery<RhCandidature[]>({
    queryKey: ['rh-candidatures', selectedPoste],
    queryFn: () => rhService.getCandidatures(selectedPoste ?? undefined),
  })

  const { data: dashboard } = useQuery<RhDashboardRecrutement>({
    queryKey: ['rh-dashboard-recrutement'],
    queryFn: rhService.getDashboardRecrutement,
  })

  const createPosteMut = useMutation({
    mutationFn: (data: Partial<RhPoste>) => rhService.createPoste(data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['rh-postes'] })
      qc.invalidateQueries({ queryKey: ['rh-dashboard-recrutement'] })
      setPosteOpen(false)
      posteForm.resetFields()
      message.success('Poste créé')
    },
    onError: () => message.error('Erreur lors de la création'),
  })

  const createCandMut = useMutation({
    mutationFn: (data: Partial<RhCandidature> & { date_entretien?: { format: (f: string) => string } }) => {
      const { date_entretien, ...rest } = data
      return rhService.createCandidature({
        ...rest,
        date_entretien: date_entretien?.format('YYYY-MM-DD'),
      })
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['rh-candidatures'] })
      qc.invalidateQueries({ queryKey: ['rh-dashboard-recrutement'] })
      setCandOpen(false)
      candForm.resetFields()
      message.success('Candidature enregistrée')
    },
    onError: () => message.error('Erreur lors de l\'enregistrement'),
  })

  const updateStatutMut = useMutation({
    mutationFn: ({ id, statut }: { id: number; statut: string }) =>
      rhService.updateStatutCandidature(id, statut),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['rh-candidatures'] })
      qc.invalidateQueries({ queryKey: ['rh-dashboard-recrutement'] })
      message.success('Statut mis à jour')
    },
  })

  const updatePosteMut = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<RhPoste> }) =>
      rhService.updatePoste(id, data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['rh-postes'] }); message.success('Poste mis à jour') },
  })

  const colonnesPostes = [
    { title: 'Intitulé', dataIndex: 'intitule', key: 'intitule' },
    { title: 'Département', dataIndex: 'departement', key: 'departement', render: (v: string | null) => v ?? '—' },
    { title: 'Postes', dataIndex: 'nb_postes', key: 'nb_postes', width: 80 },
    {
      title: 'Statut',
      dataIndex: 'statut',
      key: 'statut',
      render: (v: string) => <Tag color={POSTE_COLOR[v]}>{v}</Tag>,
    },
    {
      title: 'Date limite',
      dataIndex: 'date_limite',
      key: 'date_limite',
      render: (v: string | null) => v ? dayjs(v).format('DD/MM/YYYY') : '—',
    },
    {
      title: 'Actions',
      key: 'actions',
      render: (_: unknown, r: RhPoste) => (
        <div style={{ display: 'flex', gap: 4 }}>
          <Button size="small" onClick={() => setSelectedPoste(r.id === selectedPoste ? null : r.id)}>
            {r.id === selectedPoste ? 'Masquer' : 'Candidatures'}
          </Button>
          {canUpdate && r.statut === 'ouvert' && (
            <Popconfirm title="Passer en cours ?" onConfirm={() => updatePosteMut.mutate({ id: r.id, data: { statut: 'en_cours' } })}>
              <Button size="small" type="link">En cours</Button>
            </Popconfirm>
          )}
          {canUpdate && r.statut !== 'annule' && r.statut !== 'pourvu' && (
            <Popconfirm title="Marquer comme pourvu ?" onConfirm={() => updatePosteMut.mutate({ id: r.id, data: { statut: 'pourvu' } })}>
              <Button size="small" type="link">Pourvu</Button>
            </Popconfirm>
          )}
        </div>
      ),
    },
  ]

  const colonnesCands = [
    { title: 'Nom', key: 'nom', render: (_: unknown, r: RhCandidature) => `${r.nom} ${r.prenoms}` },
    { title: 'Email', dataIndex: 'email', key: 'email', render: (v: string | null) => v ?? '—' },
    {
      title: 'Statut',
      dataIndex: 'statut',
      key: 'statut',
      render: (v: string) => <Tag color={CAND_COLOR[v]}>{v}</Tag>,
    },
    {
      title: 'Note entretien',
      dataIndex: 'note_entretien',
      key: 'note_entretien',
      render: (v: number | null) => v !== null ? `${v}/20` : '—',
    },
    {
      title: 'Date entretien',
      dataIndex: 'date_entretien',
      key: 'date_entretien',
      render: (v: string | null) => v ? dayjs(v).format('DD/MM/YYYY') : '—',
    },
    {
      title: 'Pipeline',
      key: 'pipeline',
      render: (_: unknown, r: RhCandidature) => {
        const idx = PIPELINE_STEPS.indexOf(r.statut)
        const next = PIPELINE_STEPS[idx + 1]
        if (!canUpdate || !next || r.statut === 'refuse' || r.statut === 'embauche') return null
        return (
          <Popconfirm title={`Passer à "${next}" ?`} onConfirm={() => updateStatutMut.mutate({ id: r.id, statut: next })}>
            <Button size="small" icon={<ArrowRightOutlined />} type="primary" ghost>
              {next}
            </Button>
          </Popconfirm>
        )
      },
    },
    {
      title: 'Refuser',
      key: 'refuser',
      render: (_: unknown, r: RhCandidature) =>
        canUpdate && r.statut !== 'refuse' && r.statut !== 'embauche' ? (
          <Popconfirm title="Refuser cette candidature ?" onConfirm={() => updateStatutMut.mutate({ id: r.id, statut: 'refuse' })}>
            <Button size="small" danger type="link">Refuser</Button>
          </Popconfirm>
        ) : null,
    },
  ]

  const postesOuverts = postes.filter((p) => p.statut === 'ouvert' || p.statut === 'en_cours').length

  return (
    <div>
      <Row gutter={16} style={{ marginBottom: 16 }}>
        <Col xs={8}>
          <Card size="small">
            <Statistic title="Postes ouverts" value={dashboard?.postes_ouverts ?? postesOuverts} valueStyle={{ color: '#52c41a' }} />
          </Card>
        </Col>
        <Col xs={8}>
          <Card size="small">
            <Statistic title="Candidatures totales" value={dashboard?.candidatures_total ?? 0} />
          </Card>
        </Col>
        <Col xs={8}>
          <Card size="small">
            <Statistic title="Entretiens planifiés" value={dashboard?.par_statut.find((s) => s.statut === 'entretien')?.nb ?? 0} />
          </Card>
        </Col>
      </Row>

      <Tabs
        items={[
          {
            key: 'postes',
            label: 'Postes',
            children: (
              <>
                <div style={{ marginBottom: 12, display: 'flex', justifyContent: 'flex-end' }}>
                  {canCreate && (
                    <Button type="primary" icon={<PlusOutlined />} onClick={() => setPosteOpen(true)}>
                      Nouveau poste
                    </Button>
                  )}
                </div>
                <Table
                  dataSource={postes}
                  columns={colonnesPostes}
                  rowKey="id"
                  loading={loadingPostes}
                  size="small"
                  pagination={{ pageSize: 10 }}
                />
              </>
            ),
          },
          {
            key: 'candidatures',
            label: `Candidatures${selectedPoste ? ` (poste #${selectedPoste})` : ''}`,
            children: (
              <>
                <div style={{ marginBottom: 12, display: 'flex', gap: 8, justifyContent: 'space-between', flexWrap: 'wrap' }}>
                  {selectedPoste && (
                    <Button onClick={() => setSelectedPoste(null)}>Voir toutes</Button>
                  )}
                  {canCreate && (
                    <Button type="primary" icon={<PlusOutlined />} onClick={() => setCandOpen(true)}>
                      Enregistrer candidature
                    </Button>
                  )}
                </div>
                <Table
                  dataSource={candidatures}
                  columns={colonnesCands}
                  rowKey="id"
                  loading={loadingCands}
                  size="small"
                  pagination={{ pageSize: 15 }}
                />
              </>
            ),
          },
        ]}
      />

      {/* Modal poste */}
      <Modal
        title="Nouveau poste"
        open={posteOpen}
        onCancel={() => { setPosteOpen(false); posteForm.resetFields() }}
        onOk={() => posteForm.validateFields().then((vals: Partial<RhPoste>) => createPosteMut.mutate(vals))}
        confirmLoading={createPosteMut.isPending}
      >
        <Form form={posteForm} layout="vertical">
          <Form.Item name="intitule" label="Intitulé du poste" rules={[{ required: true }]}>
            <Input />
          </Form.Item>
          <Form.Item name="departement" label="Département">
            <Input />
          </Form.Item>
          <Form.Item name="nb_postes" label="Nombre de postes" initialValue={1}>
            <InputNumber min={1} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="description" label="Description">
            <Input.TextArea rows={3} />
          </Form.Item>
          <Form.Item name="competences_requises" label="Compétences requises">
            <Input.TextArea rows={2} />
          </Form.Item>
          <Form.Item name="date_limite" label="Date limite">
            <DatePicker style={{ width: '100%' }} />
          </Form.Item>
        </Form>
      </Modal>

      {/* Modal candidature */}
      <Modal
        title="Enregistrer une candidature"
        open={candOpen}
        onCancel={() => { setCandOpen(false); candForm.resetFields() }}
        onOk={() => candForm.validateFields().then((vals: Partial<RhCandidature> & { date_entretien?: { format: (f: string) => string } }) => createCandMut.mutate(vals))}
        confirmLoading={createCandMut.isPending}
      >
        <Form form={candForm} layout="vertical">
          <Form.Item name="id_poste" label="Poste" rules={[{ required: true }]}>
            <Select placeholder="Sélectionner un poste">
              {postes.filter((p) => p.statut === 'ouvert' || p.statut === 'en_cours').map((p) => (
                <Select.Option key={p.id} value={p.id}>{p.intitule}</Select.Option>
              ))}
            </Select>
          </Form.Item>
          <Row gutter={8}>
            <Col xs={12}>
              <Form.Item name="nom" label="Nom" rules={[{ required: true }]}>
                <Input />
              </Form.Item>
            </Col>
            <Col xs={12}>
              <Form.Item name="prenoms" label="Prénoms" rules={[{ required: true }]}>
                <Input />
              </Form.Item>
            </Col>
          </Row>
          <Form.Item name="email" label="Email">
            <Input type="email" />
          </Form.Item>
          <Form.Item name="telephone" label="Téléphone">
            <Input />
          </Form.Item>
          <Form.Item name="date_entretien" label="Date entretien">
            <DatePicker style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="notes_recruteur" label="Notes">
            <Input.TextArea rows={2} />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  )
}
