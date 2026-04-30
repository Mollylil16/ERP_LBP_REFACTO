import React, { useMemo, useState } from 'react'
import { Card, Col, Row, Statistic, Table, Button, Space, Select, message, Typography } from 'antd'
import { useQuery } from '@tanstack/react-query'
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
  LineChart,
  Line,
} from 'recharts'
import { FileExcelOutlined, FilePdfOutlined, ReloadOutlined } from '@ant-design/icons'
import type { Dayjs } from 'dayjs'
import { supervisionService, SupervisionAgenceRow } from '@services/supervision.service'
import { exportSupervisionSynthesePdf, fmtPdf } from '@utils/supervisionPdfExport'
import { exportSupervisionPilotageExcel } from '@utils/supervisionExcelExport'
import { usePermissions } from '@hooks/usePermissions'
import { PERMISSIONS } from '@constants/permissions'
import { apiService } from '@services/api.service'

const { Text } = Typography

type Props = {
  range: [Dayjs, Dayjs]
  agences: SupervisionAgenceRow[] | undefined
  loadAgences: boolean
  err?: boolean
}

export const SupervisionPilotageTab: React.FC<Props> = ({ range, agences, loadAgences, err }) => {
  const { hasPermission } = usePermissions()
  const [bucket, setBucket] = useState<'day' | 'month'>('day')
  const [a1, setA1] = useState(new Date().getFullYear() - 1)
  const [a2, setA2] = useState(new Date().getFullYear())
  const debut = range[0].format('YYYY-MM-DD')
  const fin = range[1].format('YYYY-MM-DD')
  const y0 = new Date().getFullYear()

  const { data: insKpis, isLoading: l1 } = useQuery({
    queryKey: ['supervision', 'insights', 'kpis', debut, fin],
    queryFn: () => supervisionService.getInsightsKpis(debut, fin),
    refetchInterval: 90_000,
  })
  const { data: activity, isLoading: l2 } = useQuery({
    queryKey: ['supervision', 'insights', 'activity', debut, fin, bucket],
    queryFn: () => supervisionService.getInsightsActivity(debut, fin, bucket),
    refetchInterval: 90_000,
  })
  const { data: revenue, isLoading: l3 } = useQuery({
    queryKey: ['supervision', 'revenue-years', y0 - 5, y0 + 1],
    queryFn: () => supervisionService.getRevenueYears(y0 - 5, y0 + 1),
  })
  const { data: compare, isLoading: l4 } = useQuery({
    queryKey: ['supervision', 'compare', a1, a2],
    queryFn: () => supervisionService.getCompareYears(a1, a2),
  })
  const { data: projection } = useQuery({
    queryKey: ['supervision', 'projection'],
    queryFn: () => supervisionService.getProjection(),
  })

  const canPdf = hasPermission(PERMISSIONS.SUPERVISION.DASHBOARD_READ)
  const canAi = hasPermission(PERMISSIONS.RAPPORTS.VIEW)

  const { data: chartData } = useQuery({
    queryKey: ['supervision', 'ia', 'chart'],
    queryFn: () => apiService.get<unknown[]>('/analytics/chart-data'),
    enabled: canAi,
    staleTime: 60_000,
  })

  const columns = useMemo(
    () => [
      { title: 'Code', dataIndex: ['agence', 'code'], key: 'code' },
      { title: 'Agence', dataIndex: ['agence', 'nom'], key: 'nom' },
      { title: 'Colis (jour)', dataIndex: 'colis_aujourdhui', key: 'colis' },
      {
        title: 'Solde caisse',
        dataIndex: 'solde_caisse',
        key: 'solde',
        render: (v: number) =>
          v != null ? `${Number(v).toLocaleString('fr-FR')} F` : '—',
      },
      { title: 'Statut', dataIndex: 'statut', key: 'statut' },
    ],
    [],
  )

  const handleExportPdf = async () => {
    if (!insKpis) {
      message.warning('Chargement des indicateurs…')
      return
    }
    try {
      await exportSupervisionSynthesePdf({
        titre: 'Synthese de supervision (consolidation reseau)',
        periode: range,
        kpis: {
          colisCrees: insKpis.colisCrees,
          facturesEmises: insKpis.facturesEmises,
          encaissementsValides: insKpis.encaissementsValides,
          nouveauxClients: insKpis.nouveauxClients,
          nbAgences: insKpis.nbAgences,
        },
        comparatifAnnees: compare
          ? {
              a1,
              a2,
              e1: compare.encaissements[a1] ?? 0,
              e2: compare.encaissements[a2] ?? 0,
              ecartPct: compare.ecart_pourcent,
            }
          : undefined,
        projection: projection
          ? {
              baseMensuelle: projection.base_moyenne_mensuelle,
              estimeAnnee: `${fmtPdf(projection.encaissement_annee_reference_estime)} (ordre de grandeur)`,
            }
          : undefined,
      })
      message.success('PDF genere — vous pouvez le remettre au directeur')
    } catch {
      message.error('Export PDF impossible')
    }
  }

  const handleExportExcel = async () => {
    if (!insKpis) {
      message.warning('Chargement des indicateurs…')
      return
    }
    try {
      await exportSupervisionPilotageExcel({
        range,
        insKpis,
        activity: activity ?? [],
        revenue: revenue ?? [],
        a1,
        a2,
        compare: compare ?? undefined,
        projection: projection ?? undefined,
        agences: agences ?? [],
      })
      message.success('Fichier Excel généré')
    } catch {
      message.error('Export Excel impossible')
    }
  }

  return (
    <div className="lbp-supervision-pilotage">
      <Row justify="space-between" align="middle" style={{ marginBottom: 16 }} wrap>
        <Col>
          <Text type="secondary">
            Période sélectionnée : {insKpis?.periode.label ?? `${debut} → ${fin}`} — rafraîchissement
            auto ~1,5 min.
          </Text>
        </Col>
        <Col>
          <Space wrap>
            <Select
              value={bucket}
              onChange={setBucket}
              style={{ width: 140 }}
              options={[
                { value: 'day', label: 'Courbe : jour' },
                { value: 'month', label: 'Courbe : mois' },
              ]}
            />
            {canPdf && (
              <>
                <Button icon={<FileExcelOutlined />} onClick={handleExportExcel}>
                  Exporter Excel
                </Button>
                <Button icon={<FilePdfOutlined />} onClick={handleExportPdf}>
                  Exporter synthèse PDF
                </Button>
              </>
            )}
          </Space>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col xs={24} sm={12} lg={4}>
          <Card size="small" loading={l1}>
            <Statistic title="Colis" value={insKpis?.colisCrees ?? '—'} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={4}>
          <Card size="small" loading={l1}>
            <Statistic title="Factures" value={insKpis?.facturesEmises ?? '—'} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={5}>
          <Card size="small" loading={l1}>
            <Statistic
              title="Encaissements validés"
              value={insKpis ? Math.round(insKpis.encaissementsValides) : '—'}
              suffix="F"
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={5}>
          <Card size="small" loading={l1}>
            <Statistic title="Nouveaux clients" value={insKpis?.nouveauxClients ?? '—'} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card size="small" loading={l1}>
            <Statistic title="Agences (réf.)" value={insKpis?.nbAgences ?? '—'} />
          </Card>
        </Col>
      </Row>

      <Card
        title="Activité (colis / factures)"
        size="small"
        style={{ marginTop: 16 }}
        extra={l2 ? <ReloadOutlined spin /> : null}
        loading={l2}
      >
        <div style={{ height: 300 }}>
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={activity ?? []}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
              <XAxis dataKey="point" tick={{ fontSize: 11 }} />
              <YAxis tick={{ fontSize: 11 }} />
              <Tooltip />
              <Legend />
              <Line type="monotone" dataKey="colis" name="Colis" stroke="#1677ff" dot={false} />
              <Line type="monotone" dataKey="factures" name="Factures" stroke="#52c41a" dot={false} />
            </LineChart>
          </ResponsiveContainer>
        </div>
      </Card>

      <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
        <Col xs={24} lg={12}>
          <Card title="Encaissements par année" size="small" loading={l3}>
            <div style={{ height: 260 }}>
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={revenue ?? []}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="annee" />
                  <YAxis tickFormatter={(v) => `${(v / 1e6).toFixed(1)}M`} />
                  <Tooltip
                    formatter={(v: number) => [`${v.toLocaleString('fr-FR')} F`, 'Encaissements']}
                  />
                  <Bar dataKey="encaissements_valides" name="Validés" fill="#1677ff" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </Card>
        </Col>
        <Col xs={24} lg={12}>
          <Card
            title="Comparatif & tendance"
            size="small"
            extra={
              <Space>
                <Select
                  value={a1}
                  onChange={setA1}
                  options={Array.from({ length: 8 }, (_, i) => y0 - i).map((y) => ({
                    value: y,
                    label: String(y),
                  }))}
                />
                <Select
                  value={a2}
                  onChange={setA2}
                  options={Array.from({ length: 8 }, (_, i) => y0 - i).map((y) => ({
                    value: y,
                    label: String(y),
                  }))}
                />
              </Space>
            }
            loading={l4}
          >
            {compare && (
              <>
                <p>
                  <Text strong>Écart : </Text>
                  {compare.ecart_pourcent == null
                    ? '—'
                    : `${compare.ecart_pourcent.toFixed(1)} % entre ${a1} et ${a2}`}
                </p>
                <Text type="secondary" style={{ fontSize: 12, display: 'block', marginBottom: 8 }}>
                  {projection?.avertissement}
                </Text>
                <p>
                  Moy. mens. indic. :{' '}
                  {projection?.base_moyenne_mensuelle.toLocaleString('fr-FR') ?? '—'} F — est. 12 mois
                  : {projection?.encaissement_annee_reference_estime.toLocaleString('fr-FR') ?? '—'} F
                </p>
              </>
            )}
          </Card>
        </Col>
      </Row>

      {canAi && Array.isArray(chartData) && chartData.length > 0 && (
        <Card title="Aperçu analytique (données dashboard)" size="small" style={{ marginTop: 16 }}>
          <Text type="secondary" style={{ fontSize: 12 }}>
            Cohérent avec le module rapports / statistiques — l’IA contextuelle reste sur le tableau de
            bord principal si vous l’y activez.
          </Text>
        </Card>
      )}

      <Card
        title="Aperçu par agence (activité du jour en cours)"
        size="small"
        style={{ marginTop: 16 }}
      >
        {err ? (
          <Text type="danger">Données agences indisponibles.</Text>
        ) : (
          <Table<SupervisionAgenceRow>
            size="small"
            rowKey={(r: SupervisionAgenceRow) => r.agence.id}
            loading={loadAgences}
            dataSource={agences ?? []}
            columns={columns as any}
            pagination={{ pageSize: 10 }}
          />
        )}
      </Card>
    </div>
  )
}
