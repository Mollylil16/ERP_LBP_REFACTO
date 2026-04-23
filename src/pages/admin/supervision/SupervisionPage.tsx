import React, { useMemo, useState } from 'react'
import {
  Card,
  Col,
  Row,
  Typography,
  Alert,
  Spin,
  Tabs,
  Form,
  Input,
  Select,
  Button,
  DatePicker,
  Space,
  message,
  InputNumber,
  Table,
} from 'antd'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  GlobalOutlined,
  LineChartOutlined,
  FileTextOutlined,
  AlertOutlined,
  CommentOutlined,
  FormOutlined,
  CalendarOutlined,
  BankOutlined,
  TeamOutlined,
  RadarChartOutlined,
} from '@ant-design/icons'
import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'
import {
  supervisionService,
  SupervisionAgenceRow,
  SupervisionRapportRow,
} from '@services/supervision.service'
import { WithPermission } from '@components/common/WithPermission'
import { PERMISSIONS } from '@constants/permissions'
import { usePermissions } from '@hooks/usePermissions'
import { SupervisionPilotageTab } from './tabs/SupervisionPilotageTab'
import { SupervisionCaisseTab } from './tabs/SupervisionCaisseTab'
import { SupervisionPerformanceTab } from './tabs/SupervisionPerformanceTab'
import { SupervisionAnomaliesTab } from './tabs/SupervisionAnomaliesTab'
import './SupervisionPage.css'

const { Title, Paragraph, Text } = Typography
const { TextArea } = Input

const GRAVITE_OPTIONS = [
  { value: 'faible', label: 'Faible' },
  { value: 'moyen', label: 'Moyen' },
  { value: 'critique', label: 'Critique' },
]

const CIBLE_ANNOTATION = [
  { value: 'operation', label: 'Opération' },
  { value: 'colis', label: 'Colis' },
  { value: 'facture', label: 'Facture' },
  { value: 'caisse', label: 'Caisse' },
  { value: 'autre', label: 'Autre' },
]

const { RangePicker } = DatePicker

function defaultRange(): [Dayjs, Dayjs] {
  return [dayjs().startOf('month'), dayjs()]
}

export const SupervisionPage: React.FC = () => {
  const { hasPermission } = usePermissions()
  const queryClient = useQueryClient()
  const [range, setRange] = useState<[Dayjs, Dayjs]>(defaultRange)
  const [formSignalement] = Form.useForm()
  const [formRapport] = Form.useForm()
  const [formJustif] = Form.useForm()
  const [formAnnotation] = Form.useForm()

  const { data: agences, isLoading: loadA, error: errA } = useQuery({
    queryKey: ['supervision', 'agences'],
    queryFn: () => supervisionService.getEtatAgences(),
  })
  const { data: rapports, isLoading: loadR } = useQuery({
    queryKey: ['supervision', 'rapports'],
    queryFn: () => supervisionService.getRapports(),
    enabled: hasPermission(PERMISSIONS.SUPERVISION.RAPPORT_READ),
  })

  const agenceOptions = useMemo(
    () =>
      (agences ?? []).map((r: SupervisionAgenceRow) => ({
        value: r.agence.id,
        label: `${r.agence.code} — ${r.agence.nom}`,
      })),
    [agences],
  )

  const mutRapport = useMutation({
    mutationFn: supervisionService.soumettreRapport,
    onSuccess: () => {
      message.success('Rapport soumis à la direction (notification DG / Assistant DG).')
      formRapport.resetFields()
      void queryClient.invalidateQueries({ queryKey: ['supervision', 'rapports'] })
    },
    onError: () => message.error("Envoi du rapport impossible."),
  })
  const mutSignalement = useMutation({
    mutationFn: supervisionService.signalerAnomalie,
    onSuccess: () => {
      message.success('Signalement enregistré.')
      formSignalement.resetFields()
    },
    onError: () => message.error("Enregistrement du signalement impossible."),
  })
  const mutJustif = useMutation({
    mutationFn: supervisionService.demanderJustification,
    onSuccess: () => {
      message.success('Demande de justification envoyée.')
      formJustif.resetFields()
    },
    onError: () => message.error("Envoi de la demande impossible."),
  })
  const mutAnnotation = useMutation({
    mutationFn: supervisionService.creerAnnotation,
    onSuccess: () => {
      message.success('Annotation enregistrée.')
      formAnnotation.resetFields()
    },
    onError: () => message.error("Enregistrement de l'annotation impossible."),
  })

  const colonnesRapports = [
    { title: 'Id', dataIndex: 'id', key: 'id', width: 70 },
    { title: 'Type', dataIndex: 'type', key: 'type' },
    { title: 'Période', dataIndex: 'periode', key: 'periode' },
    {
      title: 'Agence',
      key: 'agence',
      render: (_: unknown, r: SupervisionRapportRow) =>
        r.agence ? `${r.agence.code} — ${r.agence.nom}` : '—',
    },
    {
      title: 'Créé le',
      dataIndex: 'created_at',
      key: 'created_at',
      render: (d: string) => (d ? dayjs(d).format('DD/MM/YYYY HH:mm') : '—'),
    },
    { title: 'Lecture', dataIndex: 'statut_lecture', key: 'st', width: 100 },
  ]

  const onRapport = (v: {
    type: string
    periode: string
    agenceId?: number
    plage: [Dayjs, Dayjs]
    commentaire?: string
  }) => {
    const [a, b] = v.plage
    mutRapport.mutate({
      type: v.type,
      periode: v.periode,
      agenceId: v.agenceId,
      dateDebut: a.format('YYYY-MM-DD'),
      dateFin: b.format('YYYY-MM-DD'),
      commentaire: v.commentaire?.trim() || undefined,
    })
  }

  const onSignalement = (v: {
    agenceId?: number
    type: string
    description: string
    gravite: string
  }) => {
    mutSignalement.mutate({
      agenceId: v.agenceId,
      type: v.type.trim(),
      description: v.description.trim(),
      gravite: v.gravite,
    })
  }

  const optUserId = (n: number | null | undefined) =>
    n != null && Number.isFinite(n) && n > 0 ? Math.floor(n) : undefined

  const onJustif = (v: {
    agenceId: number
    motif: string
    agentId?: number | null
    chefAgenceId?: number | null
    operationId?: string
  }) => {
    mutJustif.mutate({
      agenceId: v.agenceId,
      motif: v.motif.trim(),
      agentId: optUserId(v.agentId ?? undefined),
      chefAgenceId: optUserId(v.chefAgenceId ?? undefined),
      operationId: v.operationId?.trim() || undefined,
    })
  }

  const onAnnotation = (v: { cible: string; cibleId: string; contenu: string }) => {
    mutAnnotation.mutate({
      cible: v.cible,
      cibleId: v.cibleId.trim(),
      contenu: v.contenu.trim(),
    })
  }

  const tabRapports = (
    <WithPermission
      permission={PERMISSIONS.SUPERVISION.RAPPORT_READ}
      fallback={<Alert type="info" showIcon message="Lecture des rapports non autorisée." />}
    >
      <Card title="Historique des rapports soumis" style={{ marginBottom: 24 }}>
        <Table<SupervisionRapportRow>
          size="small"
          rowKey="id"
          loading={loadR}
          dataSource={rapports ?? []}
          columns={colonnesRapports as any}
          pagination={{ pageSize: 8 }}
        />
      </Card>
      <WithPermission permission={PERMISSIONS.SUPERVISION.RAPPORT_CREATE} fallback={null}>
        <Card
          title={
            <span>
              <FileTextOutlined /> Soumettre un rapport (notification direction)
            </span>
          }
        >
          <Paragraph type="secondary" style={{ marginTop: 0 }}>
            Même contenu exportable en PDF depuis l’onglet « Poste de pilotage » pour remise manuelle
            au directeur.
          </Paragraph>
          <Form
            form={formRapport}
            layout="vertical"
            onFinish={onRapport}
            style={{ maxWidth: 640 }}
            initialValues={{
              periode: 'semaine',
              plage: [dayjs().subtract(6, 'day'), dayjs()],
            }}
          >
            <Form.Item
              name="type"
              label="Type de rapport"
              rules={[{ required: true, message: 'Requis' }]}
            >
              <Select
                options={[
                  { value: 'caisse_agence', label: 'Rapport de caisse par agence' },
                  { value: 'activite_globale', label: "Rapport d'activité globale" },
                  { value: 'anomalies_incidents', label: 'Rapport anomalies / incidents' },
                  { value: 'performance_agents', label: 'Rapport performance des agents' },
                  { value: 'autre', label: 'Autre' },
                ]}
              />
            </Form.Item>
            <Form.Item
              name="periode"
              label="Libellé période"
              rules={[{ required: true, message: 'Requis' }]}
            >
              <Select
                options={[
                  { value: 'jour', label: 'Jour' },
                  { value: 'semaine', label: 'Semaine' },
                  { value: 'mois', label: 'Mois' },
                  { value: 'trimestre', label: 'Trimestre' },
                  { value: 'annee', label: 'Année' },
                ]}
              />
            </Form.Item>
            <Form.Item name="agenceId" label="Agence (optionnel)">
              <Select
                allowClear
                showSearch
                optionFilterProp="label"
                placeholder="Tout le réseau"
                options={agenceOptions}
              />
            </Form.Item>
            <Form.Item
              name="plage"
              label="Période couverte (dates)"
              rules={[{ required: true, message: 'Requis' }]}
            >
              <RangePicker style={{ width: '100%' }} format="DD/MM/YYYY" />
            </Form.Item>
            <Form.Item name="commentaire" label="Commentaire">
              <TextArea rows={4} placeholder="Synthèse, points d’attention…" maxLength={8000} showCount />
            </Form.Item>
            <Form.Item>
              <Button type="primary" htmlType="submit" loading={mutRapport.isPending}>
                Soumettre au directeur
              </Button>
            </Form.Item>
          </Form>
        </Card>
      </WithPermission>
    </WithPermission>
  )

  const tabActions = (
    <Row gutter={[16, 16]}>
      <Col xs={24} lg={12}>
        <WithPermission permission={PERMISSIONS.SUPERVISION.SIGNALEMENT_CREATE} fallback={null}>
          <Card title={<span><AlertOutlined /> Signaler une anomalie</span>}>
            <Form form={formSignalement} layout="vertical" onFinish={onSignalement}>
              <Form.Item name="agenceId" label="Agence (optionnel)">
                <Select allowClear showSearch optionFilterProp="label" options={agenceOptions} />
              </Form.Item>
              <Form.Item
                name="type"
                label="Type"
                rules={[{ required: true, min: 2, message: 'Au moins 2 caractères' }]}
              >
                <Input maxLength={80} />
              </Form.Item>
              <Form.Item name="gravite" label="Gravité" initialValue="moyen" rules={[{ required: true }]}>
                <Select options={GRAVITE_OPTIONS} />
              </Form.Item>
              <Form.Item
                name="description"
                label="Description"
                rules={[{ required: true, min: 3, message: 'Au moins 3 caractères' }]}
              >
                <TextArea rows={4} />
              </Form.Item>
              <Form.Item>
                <Button type="primary" htmlType="submit" loading={mutSignalement.isPending}>
                  Enregistrer
                </Button>
              </Form.Item>
            </Form>
          </Card>
        </WithPermission>
      </Col>
      <Col xs={24} lg={12}>
        <WithPermission permission={PERMISSIONS.SUPERVISION.JUSTIFICATION_CREATE} fallback={null}>
          <Card title={<span><FormOutlined /> Demander une justification</span>}>
            <Form form={formJustif} layout="vertical" onFinish={onJustif}>
              <Form.Item name="agenceId" label="Agence" rules={[{ required: true, message: 'Requis' }]}>
                <Select showSearch optionFilterProp="label" options={agenceOptions} />
              </Form.Item>
              <Form.Item
                name="motif"
                label="Motif"
                rules={[{ required: true, min: 3, message: 'Au moins 3 caractères' }]}
              >
                <TextArea rows={3} />
              </Form.Item>
              <Form.Item name="agentId" label="ID agent (optionnel)">
                <InputNumber min={1} style={{ width: '100%' }} />
              </Form.Item>
              <Form.Item name="chefAgenceId" label="ID chef d’agence (optionnel)">
                <InputNumber min={1} style={{ width: '100%' }} />
              </Form.Item>
              <Form.Item name="operationId" label="Réf. opération (optionnel)">
                <Input maxLength={64} />
              </Form.Item>
              <Form.Item>
                <Button type="primary" htmlType="submit" loading={mutJustif.isPending}>
                  Envoyer
                </Button>
              </Form.Item>
            </Form>
          </Card>
        </WithPermission>
      </Col>
      <Col xs={24} lg={12}>
        <WithPermission permission={PERMISSIONS.SUPERVISION.ANNOTATION_CREATE} fallback={null}>
          <Card title={<span><CommentOutlined /> Annotation interne</span>}>
            <Form form={formAnnotation} layout="vertical" onFinish={onAnnotation}>
              <Form.Item name="cible" label="Cible" initialValue="operation" rules={[{ required: true }]}>
                <Select options={CIBLE_ANNOTATION} />
              </Form.Item>
              <Form.Item name="cibleId" label="Identifiant" rules={[{ required: true, message: 'Requis' }]}>
                <Input maxLength={64} />
              </Form.Item>
              <Form.Item
                name="contenu"
                label="Contenu"
                rules={[{ required: true, min: 3, message: 'Au moins 3 caractères' }]}
              >
                <TextArea rows={4} />
              </Form.Item>
              <Form.Item>
                <Button type="primary" htmlType="submit" loading={mutAnnotation.isPending}>
                  Enregistrer
                </Button>
              </Form.Item>
            </Form>
          </Card>
        </WithPermission>
      </Col>
    </Row>
  )

  return (
    <div className="lbp-supervision-page lbp-supervision-command">
      <div className="lbp-supervision-hero">
        <div className="lbp-supervision-hero-text">
          <LineChartOutlined className="lbp-supervision-icon" />
          <div>
            <Title level={2} className="lbp-supervision-title" style={{ marginBottom: 4 }}>
              Poste de supervision — Superviseure générale
            </Title>
            <Paragraph type="secondary" className="lbp-supervision-sub" style={{ marginBottom: 0 }}>
              Vision réseau LBP : encaissements, agences, productivité et anomalies — consultation et
              rapports vers la direction, sans modification des données opérationnelles.
            </Paragraph>
          </div>
        </div>
        <Card size="small" className="lbp-supervision-range-card" bordered={false}>
          <Space align="center" wrap>
            <CalendarOutlined style={{ color: 'var(--lbp-supervision-accent, #1677ff)' }} />
            <Text strong>Période d’analyse</Text>
            <RangePicker
              value={range}
              onChange={(d: null | [Dayjs | null, Dayjs | null]) =>
                d && d[0] && d[1] && setRange([d[0], d[1]])
              }
              format="DD/MM/YYYY"
              presets={[
                { label: '7 jours', value: [dayjs().subtract(6, 'day'), dayjs()] },
                { label: 'Mois en cours', value: [dayjs().startOf('month'), dayjs()] },
                { label: 'Mois dernier', value: [dayjs().subtract(1, 'month').startOf('month'), dayjs().subtract(1, 'month').endOf('month')] },
                { label: 'Année en cours', value: [dayjs().startOf('year'), dayjs()] },
              ]}
            />
          </Space>
        </Card>
      </div>

      {errA && (
        <Alert
          type="error"
          showIcon
          className="lbp-supervision-alert"
          message="Impossible de charger certaines données."
        />
      )}

      <WithPermission permission={PERMISSIONS.SUPERVISION.DASHBOARD_READ} fallback={null}>
        <Spin spinning={loadA}>
          <Tabs
            type="card"
            items={[
              {
                key: 'pilotage',
                label: (
                  <span>
                    <GlobalOutlined /> Poste de pilotage
                  </span>
                ),
                children: (
                  <SupervisionPilotageTab
                    range={range}
                    agences={agences}
                    loadAgences={loadA}
                    err={!!errA}
                  />
                ),
              },
              {
                key: 'caisse',
                label: (
                  <span>
                    <BankOutlined /> Caisse réseau
                  </span>
                ),
                children: <SupervisionCaisseTab range={range} />,
              },
              {
                key: 'perf',
                label: (
                  <span>
                    <TeamOutlined /> Performance
                  </span>
                ),
                children: <SupervisionPerformanceTab range={range} />,
              },
              {
                key: 'anomalies',
                label: (
                  <span>
                    <RadarChartOutlined /> Anomalies
                  </span>
                ),
                children: <SupervisionAnomaliesTab range={range} />,
              },
              {
                key: 'rapports',
                label: (
                  <span>
                    <FileTextOutlined /> Rapports direction
                  </span>
                ),
                children: tabRapports,
              },
              {
                key: 'actions',
                label: 'Signalements & traçabilité',
                children: tabActions,
              },
            ]}
          />
        </Spin>
      </WithPermission>
    </div>
  )
}
