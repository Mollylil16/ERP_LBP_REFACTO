import React from 'react'
import {
  Row, Col, Card, Statistic, Table, Tag, Alert,
  Typography, Space, Badge, Button, Spin, Timeline, Tooltip,
} from 'antd'
import {
  InboxOutlined, DollarOutlined, WarningOutlined, CheckCircleOutlined,
  ClockCircleOutlined, FileTextOutlined, ShoppingOutlined, SyncOutlined,
  ArrowRightOutlined, ExclamationCircleOutlined,
} from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { dashboardService } from '@services/dashboard.service'

const { Title, Text } = Typography

function fmt(n: number) {
  return Number(n).toLocaleString('fr-FR') + ' FCFA'
}

function minutesToLabel(min: number) {
  if (min < 60) return `${min} min`
  return `${Math.floor(min / 60)}h ${min % 60}min`
}

const FOURNITURE_STATUT_COLORS: Record<string, string> = {
  BROUILLON: 'default',
  SOUMIS: 'processing',
  APPROUVE: 'success',
  REJETE: 'error',
  LIVRE: 'success',
}

const FOURNITURE_STATUT_LABELS: Record<string, string> = {
  BROUILLON: 'Brouillon',
  SOUMIS: 'En attente',
  APPROUVE: 'Approuvée',
  REJETE: 'Refusée',
  LIVRE: 'Livrée',
}

export const AgenceDashboardPage: React.FC = () => {
  const navigate = useNavigate()
  const { data, isLoading, isError, dataUpdatedAt, refetch, isFetching } = useQuery({
    queryKey: ['agence-summary'],
    queryFn: () => dashboardService.getAgenceSummary(),
    refetchInterval: 3 * 60 * 1000,
    staleTime: 60 * 1000,
  })

  const lastUpdate = dataUpdatedAt
    ? new Date(dataUpdatedAt).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
    : '—'

  if (isLoading) {
    return (
      <div style={{ padding: 32, textAlign: 'center' }}>
        <Spin size="large" tip="Chargement du tableau de bord…" />
      </div>
    )
  }

  if (isError || !data) {
    return (
      <div style={{ padding: 32 }}>
        <Alert
          type="error"
          showIcon
          message="Impossible de charger le tableau de bord agence"
          description="Vérifiez votre connexion au serveur."
        />
      </div>
    )
  }

  const hasAlertes =
    data.colis?.en_attente_traitement?.length > 0 ||
    data.colis?.bloqués_transit?.length > 0 ||
    data.caisse?.en_alerte ||
    data.factures_impayees_15j?.count > 0

  return (
    <div style={{ padding: '24px 24px 40px' }}>
      {/* En-tête */}
      <Row justify="space-between" align="middle" style={{ marginBottom: 24 }}>
        <Col>
          <Title level={3} style={{ margin: 0 }}>
            Tableau de bord — Mon agence
          </Title>
          <Text type="secondary">
            {new Date().toLocaleDateString('fr-FR', {
              weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
            })}
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

      {/* Barre d'alertes globale */}
      {hasAlertes && (
        <Alert
          type="warning"
          showIcon
          icon={<ExclamationCircleOutlined />}
          message="Des éléments nécessitent votre attention"
          style={{ marginBottom: 16 }}
          banner
        />
      )}

      {/* KPI Cards */}
      <Row gutter={[16, 16]} style={{ marginBottom: 24 }}>
        <Col xs={24} sm={12} xl={6}>
          <Card
            hoverable
            onClick={() => navigate('/colis/groupage')}
            style={{ cursor: 'pointer' }}
          >
            <Statistic
              title="Colis saisis aujourd'hui"
              value={data.colis?.aujourd_hui ?? 0}
              prefix={<InboxOutlined style={{ color: '#1677ff' }} />}
              valueStyle={{ color: '#1677ff', fontSize: 28 }}
            />
            {data.colis?.en_attente_traitement?.length > 0 && (
              <Badge
                count={`${data.colis.en_attente_traitement.length} en attente`}
                style={{ backgroundColor: '#faad14', marginTop: 4 }}
              />
            )}
          </Card>
        </Col>

        <Col xs={24} sm={12} xl={6}>
          <Card>
            <Statistic
              title="Encaissé aujourd'hui"
              value={data.encaisse_jour ?? 0}
              formatter={(v: string | number) => fmt(Number(v))}
              prefix={<DollarOutlined style={{ color: '#52c41a' }} />}
              valueStyle={{ color: '#52c41a', fontSize: 22 }}
            />
          </Card>
        </Col>

        <Col xs={24} sm={12} xl={6}>
          <Card
            hoverable
            onClick={() => navigate('/factures')}
            style={{ cursor: 'pointer' }}
          >
            <Statistic
              title="Factures impayées"
              value={data.factures_impayees?.count ?? 0}
              suffix="facture(s)"
              prefix={
                <FileTextOutlined
                  style={{ color: data.factures_impayees?.count > 0 ? '#ff4d4f' : '#52c41a' }}
                />
              }
              valueStyle={{
                color: data.factures_impayees?.count > 0 ? '#ff4d4f' : '#52c41a',
                fontSize: 28,
              }}
            />
            <Text type="secondary" style={{ fontSize: 12 }}>
              {fmt(data.factures_impayees?.total ?? 0)} à recouvrer
            </Text>
          </Card>
        </Col>

        <Col xs={24} sm={12} xl={6}>
          <Card>
            <Statistic
              title="Solde caisse"
              value={data.caisse?.solde ?? 0}
              formatter={(v: string | number) => fmt(Number(v))}
              prefix={
                <DollarOutlined
                  style={{ color: data.caisse?.en_alerte ? '#ff4d4f' : '#52c41a' }}
                />
              }
              valueStyle={{
                color: data.caisse?.en_alerte ? '#ff4d4f' : '#52c41a',
                fontSize: 22,
              }}
            />
            {data.caisse?.en_alerte && (
              <Tag color="red" style={{ marginTop: 4 }}>
                Sous le seuil ({fmt(data.caisse.seuil_alerte)})
              </Tag>
            )}
          </Card>
        </Col>
      </Row>

      <Row gutter={[16, 16]}>
        {/* Colonne gauche */}
        <Col xs={24} lg={14}>
          <Space direction="vertical" style={{ width: '100%' }} size={16}>
            {/* Colis en attente de traitement */}
            {data.colis?.en_attente_traitement?.length > 0 && (
              <Card
                title={
                  <Space>
                    <ClockCircleOutlined style={{ color: '#faad14' }} />
                    <span>Colis en attente de traitement (+2h)</span>
                    <Badge count={data.colis.en_attente_traitement.length} />
                  </Space>
                }
                extra={
                  <Button
                    size="small"
                    type="link"
                    icon={<ArrowRightOutlined />}
                    onClick={() => navigate('/colis/groupage')}
                  >
                    Voir tous
                  </Button>
                }
              >
                <Table
                  size="small"
                  pagination={false}
                  dataSource={data.colis.en_attente_traitement}
                  rowKey="id"
                  columns={[
                    { title: 'Référence', dataIndex: 'ref', key: 'ref', render: (v: string) => <Text strong>{v}</Text> },
                    { title: 'Destination', dataIndex: 'destination', key: 'dest' },
                    {
                      title: 'En attente depuis',
                      dataIndex: 'attente_minutes',
                      key: 'attente',
                      render: (v: number) => (
                        <Tag color={v > 180 ? 'red' : 'orange'}>
                          {minutesToLabel(v)}
                        </Tag>
                      ),
                    },
                  ]}
                />
              </Card>
            )}

            {/* Colis bloqués en transit */}
            {data.colis?.bloqués_transit?.length > 0 && (
              <Card
                title={
                  <Space>
                    <WarningOutlined style={{ color: '#ff4d4f' }} />
                    <span>Colis bloqués en transit (+7j)</span>
                    <Badge count={data.colis.bloqués_transit.length} color="red" />
                  </Space>
                }
              >
                <Table
                  size="small"
                  pagination={false}
                  dataSource={data.colis.bloqués_transit}
                  rowKey="id"
                  columns={[
                    { title: 'Référence', dataIndex: 'ref', key: 'ref', render: (v: string) => <Text strong>{v}</Text> },
                    { title: 'Destination', dataIndex: 'destination', key: 'dest' },
                    { title: 'Statut', dataIndex: 'statut', key: 'statut', render: (v: string) => <Tag>{v}</Tag> },
                    {
                      title: 'Jours transit',
                      dataIndex: 'jours_transit',
                      key: 'jours',
                      render: (v: number) => <Tag color="red">{v} jours</Tag>,
                    },
                  ]}
                />
              </Card>
            )}

            {/* Factures impayées +15 jours */}
            {data.factures_impayees_15j?.count > 0 && (
              <Card
                title={
                  <Space>
                    <WarningOutlined style={{ color: '#faad14' }} />
                    Factures impayées depuis +15 jours
                    <Badge count={data.factures_impayees_15j.count} style={{ backgroundColor: '#faad14' }} />
                  </Space>
                }
                extra={
                  <Button size="small" type="primary" danger onClick={() => navigate('/factures')}>
                    Voir & relancer
                  </Button>
                }
              >
                <Text>
                  {data.factures_impayees_15j.count} facture(s) pour un montant total de{' '}
                  <Text strong style={{ color: '#ff4d4f' }}>
                    {fmt(data.factures_impayees_15j.total)}
                  </Text>
                </Text>
              </Card>
            )}

            {/* Tout va bien */}
            {!hasAlertes && (
              <Card>
                <Space>
                  <CheckCircleOutlined style={{ color: '#52c41a', fontSize: 20 }} />
                  <Text style={{ color: '#52c41a' }}>
                    Aucune alerte pour aujourd'hui — tout est à jour !
                  </Text>
                </Space>
              </Card>
            )}
          </Space>
        </Col>

        {/* Colonne droite */}
        <Col xs={24} lg={10}>
          <Space direction="vertical" style={{ width: '100%' }} size={16}>
            {/* Point journalier du jour */}
            <Card
              size="small"
              title={<Space><FileTextOutlined /> Point journalier</Space>}
              extra={
                <Button
                  size="small"
                  type={!data.point_journalier ? 'primary' : 'default'}
                  onClick={() => navigate('/agence/point-journalier/nouveau')}
                >
                  {!data.point_journalier ? 'Créer' : 'Voir'}
                </Button>
              }
            >
              {!data.point_journalier ? (
                <Alert
                  type="warning"
                  showIcon
                  message="Point journalier non créé pour aujourd'hui"
                  banner
                />
              ) : (
                <Space>
                  <Tag
                    color={
                      data.point_journalier.statut === 'VALIDE' ? 'success'
                      : data.point_journalier.statut === 'SOUMIS' ? 'processing'
                      : data.point_journalier.statut === 'REJETE' ? 'error'
                      : 'default'
                    }
                  >
                    {data.point_journalier.statut}
                  </Tag>
                  <Text type="secondary">
                    Recettes déclarées : {fmt(data.point_journalier.total_recettes)}
                  </Text>
                </Space>
              )}
            </Card>

            {/* Demandes fournitures */}
            <Card
              size="small"
              title={<Space><ShoppingOutlined /> Mes demandes de fournitures</Space>}
              extra={
                <Button
                  size="small"
                  onClick={() => navigate('/agence/fournitures-demande')}
                  icon={<ArrowRightOutlined />}
                >
                  Nouvelle
                </Button>
              }
            >
              {!data.fournitures_recentes?.length ? (
                <Text type="secondary">Aucune demande récente</Text>
              ) : (
                <Timeline
                  items={data.fournitures_recentes.map((d: any) => ({
                    color:
                      d.statut === 'LIVRE' ? 'green'
                      : d.statut === 'APPROUVE' ? 'blue'
                      : d.statut === 'REJETE' ? 'red'
                      : d.statut === 'SOUMIS' ? 'orange'
                      : 'gray',
                    children: (
                      <Space>
                        <Tag color={FOURNITURE_STATUT_COLORS[d.statut] ?? 'default'}>
                          {FOURNITURE_STATUT_LABELS[d.statut] ?? d.statut}
                        </Tag>
                        <Text type="secondary" style={{ fontSize: 12 }}>
                          {new Date(d.created_at).toLocaleDateString('fr-FR')}
                        </Text>
                      </Space>
                    ),
                  }))}
                />
              )}
            </Card>

            {/* Accès rapides */}
            <Card size="small" title="Accès rapides">
              <Space wrap>
                <Button icon={<InboxOutlined />} onClick={() => navigate('/colis/groupage')}>
                  Nouveau colis
                </Button>
                <Button icon={<FileTextOutlined />} onClick={() => navigate('/factures')}>
                  Factures
                </Button>
                <Button icon={<DollarOutlined />} onClick={() => navigate('/paiements')}>
                  Paiements
                </Button>
              </Space>
            </Card>
          </Space>
        </Col>
      </Row>
    </div>
  )
}
