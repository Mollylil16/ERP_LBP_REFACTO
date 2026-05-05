import React, { useState } from 'react'
import {
  Table, Button, Tag, Card, Row, Col, Statistic, Modal, Form,
  Input, Select, DatePicker, message, Popconfirm,
} from 'antd'
import {
  PlusOutlined, CheckCircleOutlined, CalendarOutlined,
} from '@ant-design/icons'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { rhService, RhPresence, RhJourFerie } from '@services/rh.service'
import { usePermissions } from '@hooks/usePermissions'
import { PERMISSIONS } from '@constants/permissions'
import dayjs from 'dayjs'

const STATUT_COLOR: Record<string, string> = {
  present: 'green',
  absent: 'red',
  retard: 'orange',
  mission: 'blue',
  conge: 'purple',
  ferie: 'cyan',
}

export const RhPresencesTab: React.FC = () => {
  const { hasPermission } = usePermissions()
  const qc = useQueryClient()
  const canCreate = hasPermission(PERMISSIONS.RH.PRESENCES_CREATE)
  const canUpdate = hasPermission(PERMISSIONS.RH.PRESENCES_UPDATE)

  const [saisirOpen, setSaisirOpen] = useState(false)
  const [feriesOpen, setFeriesOpen] = useState(false)
  const [filterDate, setFilterDate] = useState<[string, string] | null>(null)
  const [form] = Form.useForm()

  const { data: presences = [], isLoading } = useQuery<RhPresence[]>({
    queryKey: ['rh-presences', filterDate],
    queryFn: () =>
      rhService.getPresences(
        undefined,
        filterDate?.[0],
        filterDate?.[1],
      ),
  })

  const annee = dayjs().year()
  const { data: feries = [] } = useQuery<RhJourFerie[]>({
    queryKey: ['rh-jours-feries', annee],
    queryFn: () => rhService.getJoursFeries(annee),
  })

  const saisirMut = useMutation({
    mutationFn: (data: Partial<RhPresence>) => rhService.saisirPresence(data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['rh-presences'] })
      setSaisirOpen(false)
      form.resetFields()
      message.success('Présence enregistrée')
    },
    onError: () => message.error('Erreur lors de la saisie'),
  })

  const validerMut = useMutation({
    mutationFn: (id: number) => rhService.validerPresence(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['rh-presences'] }); message.success('Présence validée') },
  })

  const seedMut = useMutation({
    mutationFn: () => rhService.seedJoursFeries(annee),
    onSuccess: (n: number) => {
      qc.invalidateQueries({ queryKey: ['rh-jours-feries'] })
      message.success(`${n} jours fériés initialisés`)
    },
  })

  const presentes = presences.filter((p) => p.statut === 'present').length
  const absences = presences.filter((p) => p.statut === 'absent').length
  const retards = presences.filter((p) => p.statut === 'retard').length

  const colonnes = [
    {
      title: 'Employé',
      key: 'employe',
      render: (_: unknown, r: RhPresence) =>
        r.employe ? `${r.employe.nom} ${r.employe.prenoms}` : `#${r.id_employe}`,
    },
    {
      title: 'Date',
      dataIndex: 'date_presence',
      key: 'date_presence',
      render: (v: string) => dayjs(v).format('DD/MM/YYYY'),
    },
    {
      title: 'Entrée / Sortie',
      key: 'horaires',
      render: (_: unknown, r: RhPresence) =>
        r.heure_entree ? `${r.heure_entree} → ${r.heure_sortie ?? '—'}` : '—',
    },
    {
      title: 'H. trav.',
      dataIndex: 'heures_travaillees',
      key: 'heures_travaillees',
      render: (v: number) => `${v}h`,
    },
    {
      title: 'H. sup.',
      dataIndex: 'heures_sup',
      key: 'heures_sup',
      render: (v: number) => v > 0 ? <Tag color="orange">{v}h</Tag> : '—',
    },
    {
      title: 'Statut',
      dataIndex: 'statut',
      key: 'statut',
      render: (v: string) => <Tag color={STATUT_COLOR[v]}>{v}</Tag>,
    },
    {
      title: 'Validé',
      dataIndex: 'est_valide',
      key: 'est_valide',
      render: (v: boolean) => v ? <Tag color="green">Oui</Tag> : <Tag>Non</Tag>,
    },
    {
      title: 'Actions',
      key: 'actions',
      render: (_: unknown, r: RhPresence) =>
        canUpdate && !r.est_valide ? (
          <Popconfirm title="Valider cette présence ?" onConfirm={() => validerMut.mutate(r.id)}>
            <Button size="small" icon={<CheckCircleOutlined />} type="link">Valider</Button>
          </Popconfirm>
        ) : null,
    },
  ]

  const colonnesFeries = [
    { title: 'Date', dataIndex: 'date', key: 'date', render: (v: string) => dayjs(v).format('DD/MM/YYYY') },
    { title: 'Libellé', dataIndex: 'libelle', key: 'libelle' },
    { title: 'Islamique', dataIndex: 'est_islamique', key: 'est_islamique', render: (v: boolean) => v ? 'Oui' : 'Non' },
  ]

  return (
    <div>
      <Row gutter={16} style={{ marginBottom: 16 }}>
        <Col xs={8}>
          <Card size="small">
            <Statistic title="Présents" value={presentes} valueStyle={{ color: '#52c41a' }} />
          </Card>
        </Col>
        <Col xs={8}>
          <Card size="small">
            <Statistic title="Absences" value={absences} valueStyle={{ color: '#f5222d' }} />
          </Card>
        </Col>
        <Col xs={8}>
          <Card size="small">
            <Statistic title="Retards" value={retards} valueStyle={{ color: '#fa8c16' }} />
          </Card>
        </Col>
      </Row>

      <div style={{ marginBottom: 16, display: 'flex', gap: 8, justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap' }}>
        <DatePicker.RangePicker
          onChange={(dates: [import('dayjs').Dayjs | null, import('dayjs').Dayjs | null] | null) =>
            setFilterDate(dates ? [dates[0]!.format('YYYY-MM-DD'), dates[1]!.format('YYYY-MM-DD')] : null)
          }
        />
        <div style={{ display: 'flex', gap: 8 }}>
          <Button icon={<CalendarOutlined />} onClick={() => setFeriesOpen(true)}>
            Jours fériés
          </Button>
          {canCreate && (
            <Button type="primary" icon={<PlusOutlined />} onClick={() => setSaisirOpen(true)}>
              Saisir présence
            </Button>
          )}
        </div>
      </div>

      <Table
        dataSource={presences}
        columns={colonnes}
        rowKey="id"
        loading={isLoading}
        size="small"
        pagination={{ pageSize: 20 }}
      />

      {/* Modal saisie */}
      <Modal
        title="Saisir une présence"
        open={saisirOpen}
        onCancel={() => { setSaisirOpen(false); form.resetFields() }}
        onOk={() =>
          form.validateFields().then((vals: Record<string, unknown> & { date_presence?: { format: (f: string) => string } }) => {
            saisirMut.mutate({
              ...vals,
              date_presence: vals.date_presence?.format('YYYY-MM-DD'),
            })
          })
        }
        confirmLoading={saisirMut.isPending}
      >
        <Form form={form} layout="vertical">
          <Form.Item name="id_employe" label="ID Employé" rules={[{ required: true }]}>
            <Input type="number" />
          </Form.Item>
          <Form.Item name="date_presence" label="Date" rules={[{ required: true }]}>
            <DatePicker style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="statut" label="Statut" initialValue="present" rules={[{ required: true }]}>
            <Select>
              {['present', 'absent', 'retard', 'mission', 'conge', 'ferie'].map((s) => (
                <Select.Option key={s} value={s}>{s}</Select.Option>
              ))}
            </Select>
          </Form.Item>
          <Form.Item name="heure_entree" label="Heure entrée">
            <Input placeholder="08:00" />
          </Form.Item>
          <Form.Item name="heure_sortie" label="Heure sortie">
            <Input placeholder="17:00" />
          </Form.Item>
          <Form.Item name="justificatif" label="Justificatif">
            <Input.TextArea rows={2} />
          </Form.Item>
        </Form>
      </Modal>

      {/* Modal jours fériés */}
      <Modal
        title={`Jours fériés ${annee}`}
        open={feriesOpen}
        onCancel={() => setFeriesOpen(false)}
        footer={
          canUpdate ? (
            <Popconfirm
              title={`Initialiser les jours fériés CI pour ${annee} ?`}
              onConfirm={() => seedMut.mutate()}
            >
              <Button loading={seedMut.isPending}>Initialiser jours fériés CI</Button>
            </Popconfirm>
          ) : null
        }
        width={600}
      >
        <Table
          dataSource={feries}
          columns={colonnesFeries}
          rowKey="id"
          size="small"
          pagination={false}
        />
      </Modal>
    </div>
  )
}
