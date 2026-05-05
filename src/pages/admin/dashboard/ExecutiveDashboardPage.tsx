import React from 'react'
import {
  Row, Col, Card, Statistic, Table, Tag, Badge, Alert,
  Typography, Space, Progress, Tooltip, Empty, Spin,
} from 'antd'
import {
  TrophyOutlined, WarningOutlined, ClockCircleOutlined,
  DollarOutlined, BankOutlined, AlertOutlined, CheckCircleOutlined,
  SyncOutlined, RiseOutlined,
} from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import { dashboardService } from '@services/dashboard.service'

const { Title, Text } = Typography

function fmt(n: number): string {
  return Number(n).toLocaleString('fr-FR') + ' FCFA'
}

function fmtShort(n: number): string {
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + ' M'
  if (n >= 1_000) return (n / 1_000).toFixed(0) + ' K'
  return String(n)
}

const RANG_COLORS = ['#FFD700', '#C0C0C0', '#CD7F32', '#4096ff', '#73d13d']
const RANG_LABELS = ['🥇', '🥈', '🥉', '4e', '5e']

const PRIORITE_COLORS: Record<string, string> = {
  CRITIQUE: 'red',
  HAUTE: 'orange',
  NORMALE: 'blue',
  BASSE: 'default',
}

export const ExecutiveDashboardPage: React.FC = () => {
  const { data, isLoading, isError, dataUpdatedAt, refetch, isFetching } = useQuery({
    queryKey: ['executive-summary'],
    queryFn: () => dashboardService.getExecutiveSummary(),
    refetchInterval: 5 * 60 * 1000, // Rafraîchissement automatique toutes les 5 min
    staleTime: 2 * 60 * 1000,
  })

  const lastUpdate = dataUpdatedAt
    ? new Date(dataUpdatedAt).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
    : '—'

  if (isLoading) {
    return (
      <div style={{ padding: 32, textAlign: 'center' }}>
        <Spin size="large" tip="Chargement du tableau exécutif…" />
      </div>
    )
  }

  if (isError || !data) {
    return (
      <div style={{ padding: 32 }}>
        <Alert
          type="error"
          showIcon
          message="Impossible de charger le tableau de bord exécutif"
          description="Vérifiez la connexion au serveur."
        />
      </div>
    )
  }

  const maxCa = data.top_agences?.[0]?.ca ?? 1

  return (
    <div style={{ padding: '24px 24px 40px' }}>
      {/* En-tête */}
      <Row justify="space-between" align="middle" style={{ marginBottom: 24 }}>
        <Col>
          <Title level={3} style={{ margin: 0 }}>
            <TrophyOutlined style={{ color: '#FFD700', marginRight: 8 }} />
            Tableau de bord exécutif
          </Title>
          <Text type="secondary">
            {new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}
          </Text>
        </Col>
        <Col>
          <Space>
            <Text type="secondary" style={{ fontSize: 12 }}>Mis à jour à {lastUpdate}</Text>
            <Tooltip title="Rafraîchir">
              <SyncOutlined
                spin={isFetching}
                onClick={() => refetch()}
                style={{ cursor: 'pointer', color: '#1677ff' }}
              />
            </Tooltip>
          </Space>
        </Col>
      </Row>

      {/* KPI Cards */}
      <Row gutter={[16, 16]} style={{ marginBottom: 24 }}>
        <Col xs={24} sm={12} xl={6}>
          <Card>
            <Statistic
              title="CA Réseau — Aujourd'hui"
              value={data.ca_jour}
              formatter={(v: string | number) => fmt(Number(v))}
              prefix={<RiseOutlined style={{ color: '#52c41a' }} />}
              valueStyle={{ color: '#52c41a', fontSize: 22 }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} xl={6}>
          <Card>
            <Statistic
              title="Solde Consolidé Caisses"
              value={data.solde_consolide}
              formatter={(v: string | number) => fmt(Number(v))}
              prefix={<BankOutlined style={{ color: '#1677ff' }} />}
              valueStyle={{ color: '#1677ff', fontSize: 22 }}
            />
            {data.caisses_en_alerte?.length > 0 && (
              <Badge
                count={`${data.caisses_en_alerte.length} en alerte`}
                style={{ backgroundColor: '#ff4d4f', marginTop: 4 }}
              />
            )}
          </Card>
        </Col>
        <Col xs={24} sm={12} xl={6}>
          <Card>
            <Statistic
              title="Litiges Ouverts +7 jours"
              value={data.litiges?.urgents_plus_7j ?? 0}
              prefix={<AlertOutlined style={{ color: '#ff4d4f' }} />}
              valueStyle={{ color: data.litiges?.urgents_plus_7j > 0 ? '#ff4d4f' : '#52c41a', fontSize: 22 }}
            />
            <Text type="secondary" style={{ fontSize: 12 }}>
              {data.litiges?.ouverts_total ?? 0} litige(s) ouverts au total
            </Text>
          </Card>
        </Col>
        <Col xs={24} sm={12} xl={6}>
          <Card>
            <Statistic
              title="Encaissements en Attente"
              value={data.encaissements_attente?.count ?? 0}
              suffix="paiement(s)"
              prefix={<DollarOutlined style={{ color: '#fa8c16' }} />}
              valueStyle={{ color: '#fa8c16', fontSize: 22 }}
            />
            <Text type="secondary" style={{ fontSize: 12 }}>
              Montant : {fmt(data.encaissements_attente?.total ?? 0)}
            </Text>
          </Card>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        {/* Top agences */}
        <Col xs={24} lg={14}>
          <Card
            title={<Space><TrophyOutlined style={{ color: '#FFD700' }} /> Top agences — CA du jour</Space>}
            style={{ height: '100%' }}
          >
            {!data.top_agences?.length ? (
              <Empty description="Aucune donnée pour aujourd'hui" />
            ) : (
              <div>
                {data.top_agences.map((ag: any) => (
                  <div key={ag.agenceId} style={{ marginBottom: 16 }}>
                    <Row align="middle" gutter={8} style={{ marginBottom: 4 }}>
                      <Col>
                        <Text style={{ fontSize: 18 }}>{RANG_LABELS[ag.rang - 1]}</Text>
                      </Col>
                      <Col flex={1}>
                        <Text strong>{ag.agenceNom}</Text>
                        <Text type="secondary" style={{ marginLeft: 8, fontSize: 12 }}>
                          {ag.nb_colis} colis
                        </Text>
                      </Col>
                      <Col>
                        <Text strong style={{ color: RANG_COLORS[ag.rang - 1] }}>
                          {fmtShort(ag.ca)} FCFA
                        </Text>
                      </Col>
                    </Row>
                    <Progress
                      percent={Math.round((ag.ca / maxCa) * 100)}
                      showInfo={false}
                      strokeColor={RANG_COLORS[ag.rang - 1]}
                      size="small"
                    />
                  </div>
                ))}
              </div>
            )}
          </Card>
        </Col>

        {/* Colonne droite : alertes */}
        <Col xs={24} lg={10}>
          <Space direction="vertical" style={{ width: '100%' }} size={16}>
            {/* Points journaliers */}
            <Card
              size="small"
              title={
                <Space>
                  <ClockCircleOutlined
                    style={{ color: data.points_journaliers?.agences_en_retard > 0 ? '#fa8c16' : '#52c41a' }}
                  />
                  Points journaliers du jour
                </Space>
              }
            >
              <Row gutter={16}>
                <Col span={12} style={{ textAlign: 'center' }}>
                  <Statistic
                    value={data.points_journaliers?.agences_soumis_auj ?? 0}
                    suffix={`/ ${data.points_journaliers?.total_agences ?? 0}`}
                    valueStyle={{ color: '#52c41a', fontSize: 20 }}
                    prefix={<CheckCircleOutlined />}
                  />
                  <Text type="secondary" style={{ fontSize: 12 }}>Soumis</Text>
                </Col>
                <Col span={12} style={{ textAlign: 'center' }}>
                  <Statistic
                    value={data.points_journaliers?.agences_en_retard ?? 0}
                    valueStyle={{
                      color: (data.points_journaliers?.agences_en_retard ?? 0) > 0 ? '#ff4d4f' : '#52c41a',
                      fontSize: 20,
                    }}
                    prefix={<WarningOutlined />}
                  />
                  <Text type="secondary" style={{ fontSize: 12 }}>En retard</Text>
                </Col>
              </Row>
              {(data.points_journaliers?.agences_en_retard ?? 0) > 0 && (
                <Alert
                  type="warning"
                  showIcon
                  message={`${data.points_journaliers.agences_en_retard} agence(s) n'ont pas soumis leur point journalier`}
                  style={{ marginTop: 8 }}
                  banner
                />
              )}
            </Card>

            {/* Caisses en alerte */}
            <Card
              size="small"
              title={
                <Space>
                  <BankOutlined
                    style={{ color: data.caisses_en_alerte?.length > 0 ? '#ff4d4f' : '#52c41a' }}
                  />
                  Caisses sous le seuil d'alerte
                </Space>
              }
            >
              {!data.caisses_en_alerte?.length ? (
                <Text type="secondary">
                  <CheckCircleOutlined style={{ color: '#52c41a', marginRight: 6 }} />
                  Toutes les caisses sont au-dessus du seuil
                </Text>
              ) : (
                <Table
                  size="small"
                  pagination={false}
                  dataSource={data.caisses_en_alerte}
                  rowKey={(r: any) => r.caisseNom}
                  columns={[
                    { title: 'Caisse', dataIndex: 'caisseNom', key: 'nom', render: (v: string) => <Text strong>{v}</Text> },
                    {
                      title: 'Solde', dataIndex: 'solde', key: 'solde',
                      render: (v: number) => <Text style={{ color: '#ff4d4f' }}>{fmtShort(v)}</Text>,
                    },
                    {
                      title: 'Déficit', dataIndex: 'deficit', key: 'deficit',
                      render: (v: number) => <Tag color="red">-{fmtShort(v)}</Tag>,
                    },
                  ]}
                />
              )}
            </Card>

            {/* Litiges par priorité */}
            <Card
              size="small"
              title={<Space><AlertOutlined style={{ color: '#ff4d4f' }} /> Litiges ouverts par priorité</Space>}
            >
              {!data.litiges?.ouverts_total ? (
                <Text type="secondary">
                  <CheckCircleOutlined style={{ color: '#52c41a', marginRight: 6 }} />
                  Aucun litige ouvert
                </Text>
              ) : (
                <Space wrap>
                  {Object.entries(data.litiges?.par_priorite ?? {}).map(([priorite, cnt]: [string, any]) => (
                    <Tag key={priorite} color={PRIORITE_COLORS[priorite] ?? 'default'} style={{ fontSize: 13 }}>
                      {priorite} : {cnt}
                    </Tag>
                  ))}
                </Space>
              )}
            </Card>
          </Space>
        </Col>
      </Row>
    </div>
  )
}
