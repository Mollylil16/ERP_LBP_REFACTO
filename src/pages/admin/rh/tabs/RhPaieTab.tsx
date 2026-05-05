import React, { useState } from 'react'
import {
  Table, Button, Tag, Card, Row, Col, Statistic, Modal, Form,
  Input, Select, Popconfirm, message, Descriptions, Divider,
} from 'antd'
import {
  PlusOutlined, CalculatorOutlined, CheckOutlined, DollarOutlined, WarningOutlined,
} from '@ant-design/icons'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { rhService, RhPaieRun, RhPaieLigne, RhMasseSalariale } from '@services/rh.service'
import { usePermissions } from '@hooks/usePermissions'
import { PERMISSIONS } from '@constants/permissions'
import dayjs from 'dayjs'

const STATUT_COLOR: Record<string, string> = {
  brouillon: 'default',
  calcule: 'blue',
  valide_rh: 'cyan',
  valide_daf: 'purple',
  cloture: 'green',
}

const STATUT_LABEL: Record<string, string> = {
  brouillon: 'Brouillon',
  calcule: 'Calculé',
  valide_rh: 'Validé RH',
  valide_daf: 'Validé DAF',
  cloture: 'Clôturé',
}

const fmt = (n: number) =>
  new Intl.NumberFormat('fr-CI', { style: 'decimal', maximumFractionDigits: 0 }).format(n) + ' FCFA'

export const RhPaieTab: React.FC = () => {
  const { hasPermission } = usePermissions()
  const qc = useQueryClient()
  const canCreate = hasPermission(PERMISSIONS.RH.PAIE_CREATE)
  const canUpdate = hasPermission(PERMISSIONS.RH.PAIE_UPDATE)

  const [createOpen, setCreateOpen] = useState(false)
  const [detailRun, setDetailRun] = useState<number | null>(null)
  const [form] = Form.useForm()

  const { data: runs = [], isLoading } = useQuery<RhPaieRun[]>({
    queryKey: ['rh-paie-runs'],
    queryFn: rhService.getPaieRuns,
  })

  const { data: masse = [] } = useQuery<RhMasseSalariale[]>({
    queryKey: ['rh-masse-salariale'],
    queryFn: rhService.getMasseSalariale,
  })

  const { data: detail } = useQuery<RhPaieRun & { lignes: RhPaieLigne[] }>({
    queryKey: ['rh-paie-run', detailRun],
    queryFn: () => rhService.getPaieRunDetail(detailRun!),
    enabled: detailRun !== null,
  })

  const createMut = useMutation({
    mutationFn: (periode: string) => rhService.createPaieRun(periode),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['rh-paie-runs'] }); setCreateOpen(false); form.resetFields() },
    onError: () => message.error('Erreur lors de la création'),
  })

  const calculerMut = useMutation({
    mutationFn: (id: number) => rhService.calculerPaieRun(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['rh-paie-runs'] }); message.success('Calcul effectué') },
    onError: () => message.error('Erreur de calcul'),
  })

  const validerMut = useMutation({
    mutationFn: ({ id, role }: { id: number; role: 'rh' | 'daf' }) => rhService.validerPaieRun(id, role),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['rh-paie-runs'] }); message.success('Run validé') },
    onError: () => message.error('Erreur de validation'),
  })

  const colonnes = [
    { title: 'Période', dataIndex: 'periode', key: 'periode', width: 100 },
    {
      title: 'Statut',
      dataIndex: 'statut',
      key: 'statut',
      width: 120,
      render: (v: string) => <Tag color={STATUT_COLOR[v]}>{STATUT_LABEL[v]}</Tag>,
    },
    { title: 'Employés', dataIndex: 'nb_employes', key: 'nb_employes', width: 100 },
    {
      title: 'Total brut',
      dataIndex: 'total_brut',
      key: 'total_brut',
      render: (v: number) => fmt(v),
    },
    {
      title: 'Total net',
      dataIndex: 'total_net',
      key: 'total_net',
      render: (v: number) => fmt(v),
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
      render: (_: unknown, r: RhPaieRun) => (
        <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
          <Button size="small" onClick={() => setDetailRun(r.id)}>Détail</Button>
          {canCreate && r.statut === 'brouillon' && (
            <Popconfirm title="Calculer les salaires ?" onConfirm={() => calculerMut.mutate(r.id)}>
              <Button size="small" icon={<CalculatorOutlined />} type="primary" ghost>Calculer</Button>
            </Popconfirm>
          )}
          {canUpdate && r.statut === 'calcule' && (
            <Popconfirm title="Valider (RH) ?" onConfirm={() => validerMut.mutate({ id: r.id, role: 'rh' })}>
              <Button size="small" icon={<CheckOutlined />} type="primary" ghost>Valider RH</Button>
            </Popconfirm>
          )}
          {canUpdate && r.statut === 'valide_rh' && (
            <Popconfirm title="Valider (DAF) ?" onConfirm={() => validerMut.mutate({ id: r.id, role: 'daf' })}>
              <Button size="small" icon={<CheckOutlined />} type="primary">Valider DAF</Button>
            </Popconfirm>
          )}
        </div>
      ),
    },
  ]

  const colonnesLignes = [
    {
      title: 'Employé',
      key: 'employe',
      render: (_: unknown, r: RhPaieLigne) =>
        r.employe ? `${r.employe.nom} ${r.employe.prenoms}` : `#${r.id_employe}`,
    },
    { title: 'Brut', dataIndex: 'salaire_brut', key: 'salaire_brut', render: (v: number) => fmt(v) },
    { title: 'Déductions', dataIndex: 'total_deductions_salariales', key: 'deductions', render: (v: number) => fmt(v) },
    {
      title: 'Net',
      dataIndex: 'salaire_net',
      key: 'salaire_net',
      render: (v: number, r: RhPaieLigne) => (
        <span>
          {fmt(v)} {r.alerte_smig && <Tag color="red" icon={<WarningOutlined />}>SMIG</Tag>}
        </span>
      ),
    },
    { title: 'Charges patronales', dataIndex: 'total_charges_patronales', key: 'patronales', render: (v: number) => fmt(v) },
    { title: 'Coût employeur', dataIndex: 'cout_total_employeur', key: 'cout', render: (v: number) => fmt(v) },
  ]

  const dernierMasse = masse[masse.length - 1]

  return (
    <div>
      {/* KPIs masse salariale */}
      <Row gutter={16} style={{ marginBottom: 24 }}>
        <Col xs={24} md={8}>
          <Card size="small">
            <Statistic title="Masse salariale (dernier mois)" value={dernierMasse?.total_net ?? 0} suffix="FCFA" prefix={<DollarOutlined />} />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card size="small">
            <Statistic title="Effectif payé (dernier mois)" value={dernierMasse?.nb_employes ?? 0} />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card size="small">
            <Statistic title="Campagnes de paie" value={runs.length} />
          </Card>
        </Col>
      </Row>

      <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'flex-end' }}>
        {canCreate && (
          <Button type="primary" icon={<PlusOutlined />} onClick={() => setCreateOpen(true)}>
            Nouvelle campagne
          </Button>
        )}
      </div>

      <Table
        dataSource={runs}
        columns={colonnes}
        rowKey="id"
        loading={isLoading}
        size="small"
        pagination={{ pageSize: 12 }}
      />

      {/* Modal création campagne */}
      <Modal
        title="Nouvelle campagne de paie"
        open={createOpen}
        onCancel={() => { setCreateOpen(false); form.resetFields() }}
        onOk={() =>
          form.validateFields().then((vals: { periode: string }) => {
            createMut.mutate(vals.periode)
          })
        }
        confirmLoading={createMut.isPending}
      >
        <Form form={form} layout="vertical">
          <Form.Item name="periode" label="Période (YYYY-MM)" rules={[{ required: true, pattern: /^\d{4}-\d{2}$/ }]}>
            <Input placeholder={dayjs().format('YYYY-MM')} />
          </Form.Item>
        </Form>
      </Modal>

      {/* Drawer détail run */}
      <Modal
        title={`Détail — ${detail?.periode ?? ''}`}
        open={detailRun !== null}
        onCancel={() => setDetailRun(null)}
        footer={null}
        width={900}
      >
        {detail && (
          <>
            <Descriptions size="small" column={3} bordered>
              <Descriptions.Item label="Statut">
                <Tag color={STATUT_COLOR[detail.statut]}>{STATUT_LABEL[detail.statut]}</Tag>
              </Descriptions.Item>
              <Descriptions.Item label="Employés">{detail.nb_employes}</Descriptions.Item>
              <Descriptions.Item label="Total brut">{fmt(detail.total_brut)}</Descriptions.Item>
              <Descriptions.Item label="Total net">{fmt(detail.total_net)}</Descriptions.Item>
              <Descriptions.Item label="Charges salariales">{fmt(detail.total_charges_salariales)}</Descriptions.Item>
              <Descriptions.Item label="Charges patronales">{fmt(detail.total_charges_patronales)}</Descriptions.Item>
            </Descriptions>
            <Divider />
            <Table
              dataSource={detail.lignes ?? []}
              columns={colonnesLignes}
              rowKey="id"
              size="small"
              pagination={{ pageSize: 20 }}
              rowClassName={(r: RhPaieLigne) => r.alerte_smig ? 'ant-table-row-danger' : ''}
            />
          </>
        )}
      </Modal>
    </div>
  )
}
