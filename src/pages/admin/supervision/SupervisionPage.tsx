import React, { useMemo, useState } from 'react'
import {
  Badge,
  Card,
  Col,
  Row,
  Typography,
  Alert,
  Tabs,
  Form,
  Input,
  Select,
  Button,
  DatePicker,
  Space,
  message,
  Table,
  Tag,
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
  BellOutlined,
} from '@ant-design/icons'
import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'
import {
  supervisionService,
  SupervisionAgenceRow,
  SupervisionRapportRow,
  SupervisionSignalementRow,
  SupervisionJustificationRow,
  SupervisionAnnotationRow,
  SupervisionAnomaliesPayload,
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
const { RangePicker } = DatePicker

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

const GRAVITE_COLOR: Record<string, string> = {
  faible: 'default',
  moyen: 'orange',
  critique: 'red',
}

const STATUT_LECTURE_COLOR: Record<string, string> = {
  non_lu: 'orange',
  lu: 'green',
}

const STATUT_SIGNAL_COLOR: Record<string, string> = {
  ouvert: 'orange',
  traite: 'green',
  ferme: 'default',
}

const STATUT_JUSTIF_COLOR: Record<string, string> = {
  en_attente: 'orange',
  repondu: 'green',
  ferme: 'default',
}

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

  const debut = range[0].format('YYYY-MM-DD')
  const fin = range[1].format('YYYY-MM-DD')

  const { data: agences, isLoading: loadA, error: errA } = useQuery({
    queryKey: ['supervision', 'agences'],
    queryFn: () => supervisionService.getEtatAgences(),
    refetchInterval: 60_000,
  })

  const { data: rapports, isLoading: loadR } = useQuery({
    queryKey: ['supervision', 'rapports'],
    queryFn: () => supervisionService.getRapports(),
    enabled: hasPermission(PERMISSIONS.SUPERVISION.RAPPORT_READ),
  })

  const { data: anomaliesBadge } = useQuery({
    queryKey: ['supervision', 'anomalies', debut, fin],
    queryFn: () => supervisionService.getAnomalies(debut, fin),
    staleTime: 120_000,
    refetchInterval: 120_000,
  })

  const { data: agents } = useQuery({
    queryKey: ['supervision', 'agents'],
    queryFn: () => supervisionService.getAgents(),
    staleTime: 300_000,
    enabled: hasPermission(PERMISSIONS.SUPERVISION.DASHBOARD_READ),
  })

  const { data: signalements, isLoading: loadSig } = useQuery({
    queryKey: ['supervision', 'signalements'],
    queryFn: () => supervisionService.getSignalements(),
    enabled: hasPermission(PERMISSIONS.SUPERVISION.DASHBOARD_READ),
  })

  const { data: justifications, isLoading: loadJust } = useQuery({
    queryKey: ['supervision', 'justifications'],
    queryFn: () => supervisionService.getJustifications(),
    enabled: hasPermission(PERMISSIONS.SUPERVISION.DASHBOARD_READ),
  })

  const { data: annotations, isLoading: loadAnnot } = useQuery({
    queryKey: ['supervision', 'annotations'],
    queryFn: () => supervisionService.getAnnotations(),
    enabled: hasPermission(PERMISSIONS.SUPERVISION.DASHBOARD_READ),
  })

  const totalAnomalies = useMemo(() => {
    if (!anomaliesBadge || 'donnees' in anomaliesBadge) return 0
    const s = (anomaliesBadge as SupervisionAnomaliesPayload).summary
    return (s?.doublons ?? 0) + (s?.incoherences ?? 0) + (s?.sequences_avec_trous ?? 0)
  }, [anomaliesBadge])

  const agenceOptions = useMemo(
    () =>
      (agences ?? []).map((r: SupervisionAgenceRow) => ({
        value: r.agence.id,
        label: `${r.agence.code} — ${r.agence.nom}`,
      })),
    [agences],
  )

  const agentOptions = useMemo(
    () =>
      (agents ?? []).map((a) => ({
        value: a.id,
        label: `${a.nom_complet ?? a.username} (${a.role_code}${a.agence_nom ? ' — ' + a.agence_nom : ''})`,
      })),
    [agents],
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
      void queryClient.invalidateQueries({ queryKey: ['supervision', 'signalements'] })
    },
    onError: () => message.error("Enregistrement du signalement impossible."),
  })

  const mutJustif = useMutation({
    mutationFn: supervisionService.demanderJustification,
    onSuccess: () => {
      message.success('Demande de justification envoyée.')
      formJustif.resetFields()
      void queryClient.invalidateQueries({ queryKey: ['supervision', 'justifications'] })
    },
    onError: () => message.error("Envoi de la demande impossible."),
  })

  const mutAnnotation = useMutation({
    mutationFn: supervisionService.creerAnnotation,
    onSuccess: () => {
      message.success('Annotation enregistrée.')
      formAnnotation.resetFields()
      void queryClient.invalidateQueries({ queryKey: ['supervision', 'annotations'] })
    },
    onError: () => message.error("Enregistrement de l'annotation impossible."),
  })

  const colonnesRapports = [
    { title: 'Id', dataIndex: 'id', key: 'id', width: 60 },
    { title: 'Type', dataIndex: 'type', key: 'type' },
    { title: 'Période', dataIndex: 'periode', key: 'periode', width: 100 },
    {
      title: 'Agence',
      key: 'agence',
      render: (_: unknown, r: SupervisionRapportRow) =>
        r.agence ? `${r.agence.code} — ${r.agence.nom}` : 'Réseau',
    },
    {
      title: 'Du',
      dataIndex: 'date_debut',
      key: 'date_debut',
      width: 105,
      render: (d: string) => (d ? dayjs(d).format('DD/MM/YYYY') : '—'),
    },
    {
      title: 'Au',
      dataIndex: 'date_fin',
      key: 'date_fin',
      width: 105,
      render: (d: string) => (d ? dayjs(d).format('DD/MM/YYYY') : '—'),
    },
    {
      title: 'Soumis le',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 135,
      defaultSortOrder: 'descend' as const,
      sorter: (a: SupervisionRapportRow, b: SupervisionRapportRow) =>
        new Date(a.created_at).getTime() - new Date(b.created_at).getTime(),
      render: (d: string) => (d ? dayjs(d).format('DD/MM/YYYY HH:mm') : '—'),
    },
    {
      title: 'Lecture',
      dataIndex: 'statut_lecture',
      key: 'st',
      width: 100,
      render: (s: string) => (
        <Tag color={STATUT_LECTURE_COLOR[s] ?? 'default'}>
          {s === 'non_lu' ? 'Non lu' : s === 'lu' ? 'Lu' : (s ?? '—')}
        </Tag>
      ),
    },
    {
      title: 'Commentaire',
      dataIndex: 'commentaire',
      key: 'commentaire',
      ellipsis: true,
      render: (c: string) =>
        c
          ? <Text type="secondary" style={{ fontSize: 12 }}>{c.slice(0, 80)}{c.length > 80 ? '…' : ''}</Text>
          : <Text type="secondary">—</Text>,
    },
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
      agentId: v.agentId ?? undefined,
      chefAgenceId: v.chefAgenceId ?? undefined,
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

  // ─── Onglet Rapports direction ───────────────────────────────────────────────

  const tabRapports = (
    <WithPermission
      permission={PERMISSIONS.SUPERVISION.RAPPORT_READ}
      fallback={<Alert type="info" showIcon message="Lecture des rapports non autorisée." />}
    >
      <Card title="Historique des rapports soumis à la direction" style={{ marginBottom: 24 }}>
        <Table<SupervisionRapportRow>
          size="small"
          rowKey="id"
          loading={loadR}
          dataSource={rapports ?? []}
          columns={colonnesRapports as any}
          pagination={{ pageSize: 10, showSizeChanger: true }}
          scroll={{ x: true }}
          rowClassName={(r: SupervisionRapportRow) => r.statut_lecture === 'non_lu' ? 'lbp-row-rapport-nonlu' : ''}
        />
      </Card>

      <WithPermission permission={PERMISSIONS.SUPERVISION.RAPPORT_CREATE} fallback={null}>
        <Card
          title={
            <span>
              <FileTextOutlined /> Soumettre un rapport (notification direction)
            </span>
          }
          data-onboarding="soumission-direction-card"
        >
          <Paragraph type="secondary" style={{ marginTop: 0 }}>
            Même contenu exportable en PDF depuis l'onglet « Poste de pilotage » pour remise manuelle
            au directeur.
          </Paragraph>
          <Form
            form={formRapport}
            layout="vertical"
            onFinish={onRapport}
            style={{ maxWidth: 640 }}
            initialValues={{ periode: 'semaine', plage: [dayjs().subtract(6, 'day'), dayjs()] }}
          >
            <Form.Item name="type" label="Type de rapport" rules={[{ required: true, message: 'Requis' }]}>
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
            <Form.Item name="periode" label="Libellé période" rules={[{ required: true, message: 'Requis' }]}>
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
              <Select allowClear showSearch optionFilterProp="label" placeholder="Tout le réseau" options={agenceOptions} />
            </Form.Item>
            <Form.Item name="plage" label="Période couverte (dates)" rules={[{ required: true, message: 'Requis' }]}>
              <RangePicker style={{ width: '100%' }} format="DD/MM/YYYY" />
            </Form.Item>
            <Form.Item name="commentaire" label="Commentaire">
              <TextArea rows={4} placeholder="Synthèse, points d'attention…" maxLength={8000} showCount />
            </Form.Item>
            <Form.Item>
              <Button
                type="primary"
                htmlType="submit"
                loading={mutRapport.isPending}
                data-onboarding="soumettre-direction-btn"
              >
                Soumettre au directeur
              </Button>
            </Form.Item>
          </Form>
        </Card>
      </WithPermission>
    </WithPermission>
  )

  // ─── Sous-onglet Signalements ─────────────────────────────────────────────────

  const tabSignalementsContent = (
    <Row gutter={[16, 16]}>
      <Col xs={24} xl={9}>
        <WithPermission permission={PERMISSIONS.SUPERVISION.SIGNALEMENT_CREATE} fallback={null}>
          <Card title={<span><AlertOutlined /> Nouveau signalement</span>} size="small">
            <Form form={formSignalement} layout="vertical" onFinish={onSignalement}>
              <Form.Item name="agenceId" label="Agence (optionnel)">
                <Select allowClear showSearch optionFilterProp="label" options={agenceOptions} />
              </Form.Item>
              <Form.Item name="type" label="Type" rules={[{ required: true, min: 2, message: 'Au moins 2 caractères' }]}>
                <Input maxLength={80} />
              </Form.Item>
              <Form.Item name="gravite" label="Gravité" initialValue="moyen" rules={[{ required: true }]}>
                <Select options={GRAVITE_OPTIONS} />
              </Form.Item>
              <Form.Item name="description" label="Description" rules={[{ required: true, min: 3, message: 'Au moins 3 caractères' }]}>
                <TextArea rows={3} />
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
      <Col xs={24} xl={15}>
        <Card title={`Historique des signalements (${signalements?.length ?? 0})`} size="small">
          <Table<SupervisionSignalementRow>
            size="small"
            rowKey="id"
            loading={loadSig}
            dataSource={signalements ?? []}
            pagination={{ pageSize: 8 }}
            scroll={{ x: true }}
            rowClassName={(r: SupervisionSignalementRow) =>
              r.gravite === 'critique' ? 'lbp-row-danger' : r.gravite === 'moyen' ? 'lbp-row-warning' : ''
            }
            columns={[
              {
                title: 'Gravité',
                dataIndex: 'gravite',
                width: 90,
                filters: [
                  { text: 'Critique', value: 'critique' },
                  { text: 'Moyen', value: 'moyen' },
                  { text: 'Faible', value: 'faible' },
                ],
                onFilter: (v: unknown, r: SupervisionSignalementRow) => r.gravite === v,
                render: (g: string) => <Tag color={GRAVITE_COLOR[g] ?? 'default'}>{g}</Tag>,
              },
              { title: 'Type', dataIndex: 'type' },
              {
                title: 'Agence',
                key: 'agence',
                render: (_: unknown, r: SupervisionSignalementRow) =>
                  r.agence ? `${r.agence.code} — ${r.agence.nom}` : '—',
              },
              {
                title: 'Description',
                dataIndex: 'description',
                ellipsis: true,
                render: (d: string | null) =>
                  d ? <Text style={{ fontSize: 12 }}>{d.slice(0, 60)}{d.length > 60 ? '…' : ''}</Text> : '—',
              },
              {
                title: 'Statut',
                dataIndex: 'statut',
                width: 90,
                render: (s: string) => <Tag color={STATUT_SIGNAL_COLOR[s] ?? 'default'}>{s}</Tag>,
              },
              {
                title: 'Date',
                dataIndex: 'created_at',
                width: 130,
                defaultSortOrder: 'descend' as const,
                sorter: (a: SupervisionSignalementRow, b: SupervisionSignalementRow) =>
                  new Date(a.created_at).getTime() - new Date(b.created_at).getTime(),
                render: (d: string) => dayjs(d).format('DD/MM/YYYY HH:mm'),
              },
            ]}
          />
        </Card>
      </Col>
    </Row>
  )

  // ─── Sous-onglet Justifications ───────────────────────────────────────────────

  const tabJustificationsContent = (
    <Row gutter={[16, 16]}>
      <Col xs={24} xl={9}>
        <WithPermission permission={PERMISSIONS.SUPERVISION.JUSTIFICATION_CREATE} fallback={null}>
          <Card title={<span><FormOutlined /> Nouvelle demande de justification</span>} size="small">
            <Form form={formJustif} layout="vertical" onFinish={onJustif}>
              <Form.Item name="agenceId" label="Agence" rules={[{ required: true, message: 'Requis' }]}>
                <Select showSearch optionFilterProp="label" options={agenceOptions} />
              </Form.Item>
              <Form.Item name="motif" label="Motif" rules={[{ required: true, min: 3, message: 'Au moins 3 caractères' }]}>
                <TextArea rows={3} />
              </Form.Item>
              <Form.Item name="agentId" label="Agent ciblé (optionnel)">
                <Select
                  allowClear
                  showSearch
                  optionFilterProp="label"
                  placeholder="Sélectionner un agent"
                  options={agentOptions}
                />
              </Form.Item>
              <Form.Item name="chefAgenceId" label="Chef d'agence ciblé (optionnel)">
                <Select
                  allowClear
                  showSearch
                  optionFilterProp="label"
                  placeholder="Sélectionner un chef d'agence"
                  options={agentOptions}
                />
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
      <Col xs={24} xl={15}>
        <Card title={`Historique des demandes de justification (${justifications?.length ?? 0})`} size="small">
          <Table<SupervisionJustificationRow>
            size="small"
            rowKey="id"
            loading={loadJust}
            dataSource={justifications ?? []}
            pagination={{ pageSize: 8 }}
            scroll={{ x: true }}
            rowClassName={(r: SupervisionJustificationRow) => r.statut === 'en_attente' ? 'lbp-row-warning' : ''}
            columns={[
              {
                title: 'Statut',
                dataIndex: 'statut',
                width: 110,
                render: (s: string) => <Tag color={STATUT_JUSTIF_COLOR[s] ?? 'default'}>{s?.replace('_', ' ')}</Tag>,
              },
              {
                title: 'Agence',
                key: 'agence',
                render: (_: unknown, r: SupervisionJustificationRow) =>
                  r.agence ? `${r.agence.code} — ${r.agence.nom}` : '—',
              },
              {
                title: 'Destinataire',
                key: 'dest',
                width: 120,
                render: (_: unknown, r: SupervisionJustificationRow) =>
                  r.destinataire ? r.destinataire.username : '—',
              },
              {
                title: 'Réf. opération',
                dataIndex: 'id_operation',
                width: 120,
                render: (v: string | null) => v ?? '—',
              },
              {
                title: 'Motif',
                dataIndex: 'motif',
                ellipsis: true,
                render: (m: string) =>
                  <Text style={{ fontSize: 12 }}>{m?.slice(0, 60)}{(m?.length ?? 0) > 60 ? '…' : ''}</Text>,
              },
              {
                title: 'Date',
                dataIndex: 'created_at',
                width: 130,
                defaultSortOrder: 'descend' as const,
                sorter: (a: SupervisionJustificationRow, b: SupervisionJustificationRow) =>
                  new Date(a.created_at).getTime() - new Date(b.created_at).getTime(),
                render: (d: string) => dayjs(d).format('DD/MM/YYYY HH:mm'),
              },
            ]}
          />
        </Card>
      </Col>
    </Row>
  )

  // ─── Sous-onglet Annotations ──────────────────────────────────────────────────

  const tabAnnotationsContent = (
    <Row gutter={[16, 16]}>
      <Col xs={24} xl={9}>
        <WithPermission permission={PERMISSIONS.SUPERVISION.ANNOTATION_CREATE} fallback={null}>
          <Card title={<span><CommentOutlined /> Nouvelle annotation interne</span>} size="small">
            <Form form={formAnnotation} layout="vertical" onFinish={onAnnotation}>
              <Form.Item name="cible" label="Cible" initialValue="operation" rules={[{ required: true }]}>
                <Select options={CIBLE_ANNOTATION} />
              </Form.Item>
              <Form.Item name="cibleId" label="Identifiant" rules={[{ required: true, message: 'Requis' }]}>
                <Input maxLength={64} />
              </Form.Item>
              <Form.Item name="contenu" label="Contenu" rules={[{ required: true, min: 3, message: 'Au moins 3 caractères' }]}>
                <TextArea rows={3} />
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
      <Col xs={24} xl={15}>
        <Card title={`Historique des annotations (${annotations?.length ?? 0})`} size="small">
          <Table<SupervisionAnnotationRow>
            size="small"
            rowKey="id"
            loading={loadAnnot}
            dataSource={annotations ?? []}
            pagination={{ pageSize: 8 }}
            scroll={{ x: true }}
            columns={[
              { title: 'Cible', dataIndex: 'cible', width: 90 },
              { title: 'Identifiant', dataIndex: 'cible_id', width: 120 },
              {
                title: 'Contenu',
                dataIndex: 'contenu',
                ellipsis: true,
                render: (c: string) =>
                  <Text style={{ fontSize: 12 }}>{c?.slice(0, 70)}{(c?.length ?? 0) > 70 ? '…' : ''}</Text>,
              },
              {
                title: 'Auteur',
                key: 'auteur',
                width: 110,
                render: (_: unknown, r: SupervisionAnnotationRow) => r.auteur?.username ?? '—',
              },
              {
                title: 'Date',
                dataIndex: 'created_at',
                width: 130,
                defaultSortOrder: 'descend' as const,
                sorter: (a: SupervisionAnnotationRow, b: SupervisionAnnotationRow) =>
                  new Date(a.created_at).getTime() - new Date(b.created_at).getTime(),
                render: (d: string) => dayjs(d).format('DD/MM/YYYY HH:mm'),
              },
            ]}
          />
        </Card>
      </Col>
    </Row>
  )

  // ─── Onglet Signalements & traçabilité ────────────────────────────────────────

  const tabActions = (
    <Tabs
      className="lbp-supervision-actions-tabs"
      items={[
        {
          key: 'signalements',
          label: (
            <span>
              <AlertOutlined /> Signalements
              {(signalements?.filter(s => s.statut === 'ouvert').length ?? 0) > 0 && (
                <Badge
                  count={signalements!.filter(s => s.statut === 'ouvert').length}
                  size="small"
                  style={{ marginLeft: 8 }}
                />
              )}
            </span>
          ),
          children: tabSignalementsContent,
        },
        {
          key: 'justifications',
          label: (
            <span>
              <FormOutlined /> Justifications
              {(justifications?.filter(j => j.statut === 'en_attente').length ?? 0) > 0 && (
                <Badge
                  count={justifications!.filter(j => j.statut === 'en_attente').length}
                  size="small"
                  style={{ marginLeft: 8 }}
                />
              )}
            </span>
          ),
          children: tabJustificationsContent,
        },
        {
          key: 'annotations',
          label: <span><CommentOutlined /> Annotations ({annotations?.length ?? 0})</span>,
          children: tabAnnotationsContent,
        },
      ]}
    />
  )

  // ─── Rendu principal ──────────────────────────────────────────────────────────

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
            <Text strong>Période d'analyse</Text>
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
          message="Impossible de charger certaines données réseau."
        />
      )}

      <WithPermission permission={PERMISSIONS.SUPERVISION.DASHBOARD_READ} fallback={null}>
        <Tabs
          type="card"
          items={[
            {
              key: 'pilotage',
              label: <span><GlobalOutlined /> Poste de pilotage</span>,
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
              label: <span><BankOutlined /> Caisse réseau</span>,
              children: <SupervisionCaisseTab range={range} />,
            },
            {
              key: 'perf',
              label: <span><TeamOutlined /> Performance</span>,
              children: <SupervisionPerformanceTab range={range} />,
            },
            {
              key: 'anomalies',
              label: (
                <span>
                  <RadarChartOutlined />{' '}
                  <Badge count={totalAnomalies} size="small" offset={[4, -2]}>
                    Anomalies
                  </Badge>
                </span>
              ),
              children: <SupervisionAnomaliesTab range={range} />,
            },
            {
              key: 'rapports',
              label: <span><FileTextOutlined /> Rapports direction</span>,
              children: tabRapports,
            },
            {
              key: 'actions',
              label: <span><BellOutlined /> Signalements & traçabilité</span>,
              children: tabActions,
            },
          ]}
        />
      </WithPermission>
    </div>
  )
}
