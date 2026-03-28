import React, { useEffect } from 'react'
import { Row, Col, Typography, Card, Space, Tag } from 'antd'
import { useQuery } from '@tanstack/react-query'
import { dashboardService } from '@services/dashboard.service'
import { StatsCards } from '@components/dashboard/StatsCards'
import { ChartColisParMois } from '@components/dashboard/ChartColisParMois'
import { ChartRevenus } from '@components/dashboard/ChartRevenus'
import { ChartRepartitionTrafic } from '@components/dashboard/ChartRepartitionTrafic'
import { RecentActivities } from '@components/dashboard/RecentActivities'
import { PointCaisse } from '@components/dashboard/PointCaisse'
import { AgenciesPerformanceGrid } from '@components/dashboard/AgenciesPerformanceGrid'
import { WithPermission } from '@components/common/WithPermission'
import { PERMISSIONS } from '@constants/permissions'
import { APP_CONFIG } from '@constants/application'
import { useAlerts } from '@services/alerts.service'
import { useAuth } from '@hooks/useAuth'
import {
  resolveDashboardPersona,
  DASHBOARD_PERSONA_COPY,
} from './dashboard/resolveDashboardPersona'
import { DashboardQuickActions } from './dashboard/DashboardQuickActions'
import { PredictionCard } from '@components/dashboard/PredictionCard'
import { AIIntelligencePanel } from '@components/dashboard/AIIntelligencePanel'
import { DashboardSkeleton } from '@components/common/SkeletonLoader'
import { agencesService } from '@services/agences.service'
import { TrophyOutlined } from '@ant-design/icons'
import { useNavigate } from 'react-router-dom'
import './DashboardPage.css'

const { Title, Text } = Typography

export const DashboardPage: React.FC = () => {
  const { isAuthenticated, user } = useAuth()
  const navigate = useNavigate()
  const persona = resolveDashboardPersona(user?.role?.code)
  const copy = DASHBOARD_PERSONA_COPY[persona]
  const showAgenciesPerf = persona === 'direction' || persona === 'manager'
  const showAiPanel = persona === 'direction'
  const showCharts =
    persona !== 'caissier' &&
    (persona === 'direction' ||
      persona === 'manager' ||
      persona === 'agent' ||
      persona === 'suivi' ||
      persona === 'default')

  // Activer les alertes automatiques
  useAlerts();

  // Récupérer les statistiques (seulement si authentifié)
  const { data: stats, isLoading: statsLoading, refetch: refetchStats } = useQuery({
    queryKey: ['dashboard', 'stats'],
    queryFn: () => dashboardService.getStats(),
    refetchInterval: APP_CONFIG.refresh.dashboard,
    enabled: isAuthenticated, // Ne faire la requête que si authentifié
  })

  // Récupérer le point caisse (seulement si authentifié)
  const { data: pointCaisse, isLoading: caisseLoading, refetch: refetchCaisse } = useQuery({
    queryKey: ['dashboard', 'caisse'],
    queryFn: () => dashboardService.getPointCaisse(),
    refetchInterval: APP_CONFIG.refresh.widgets,
    enabled: isAuthenticated, // Ne faire la requête que si authentifié
  })

  // Récupérer la performance par agence (seulement pour les directeurs/admin via permission)
  const { data: agenciesPerf, isLoading: agenciesPerfLoading, refetch: refetchAgenciesPerf } = useQuery({
    queryKey: ['dashboard', 'agencies-perf'],
    queryFn: () => dashboardService.getAgenciesPerformances(),
    refetchInterval: APP_CONFIG.refresh.dashboard,
    enabled: isAuthenticated && showAgenciesPerf,
  })

  // Récupérer les activités récentes (seulement si authentifié)
  const { data: activities, isLoading: activitiesLoading, refetch: refetchActivities } = useQuery({
    queryKey: ['dashboard', 'activities'],
    queryFn: () => dashboardService.getRecentActivities(10),
    refetchInterval: APP_CONFIG.refresh.widgets,
    enabled: isAuthenticated, // Ne faire la requête que si authentifié
  })

  // Graphiques : requêtes uniquement si la persona affiche les graphiques (ex. pas les caissiers)
  const { data: chartData, isLoading: chartLoading } = useQuery({
    queryKey: ['dashboard', 'charts'],
    queryFn: () => dashboardService.getChartData(),
    refetchInterval: APP_CONFIG.refresh.dashboard * 2,
    enabled: isAuthenticated && showCharts,
  })

  const { data: trafficData, isLoading: trafficLoading } = useQuery({
    queryKey: ['dashboard', 'traffic'],
    queryFn: () => dashboardService.getTrafficRepartition(),
    refetchInterval: APP_CONFIG.refresh.dashboard * 2,
    enabled: isAuthenticated && showCharts,
  })

  // Récupérer les recommandations IA
  const { data: recommendations = [], isLoading: recommendationsLoading } = useQuery({
    queryKey: ['dashboard', 'recommendations'],
    queryFn: () => dashboardService.getAIRecommendations(),
    refetchInterval: APP_CONFIG.refresh.dashboard * 5,
    enabled: isAuthenticated && showAiPanel,
  })

  // Monitoring IA V1 (drift + alertes)
  const { data: aiMonitoring } = useQuery({
    queryKey: ['dashboard', 'ai-monitoring'],
    queryFn: () => dashboardService.getAIMonitoring(),
    refetchInterval: APP_CONFIG.refresh.dashboard * 5,
    enabled: isAuthenticated && showAiPanel,
  })

  // Récupérer les stats des agences (nouvelle feature)
  const { data: agencesStats, isLoading: agencesStatsLoading } = useQuery({
    queryKey: ['dashboard', 'agences-stats'],
    queryFn: () => agencesService.getStats(),
    enabled: isAuthenticated && showAgenciesPerf,
  })

  // Rafraîchir automatiquement toutes les données
  useEffect(() => {
    const interval = setInterval(() => {
      refetchStats()
      refetchCaisse()
      refetchAgenciesPerf()
      refetchActivities()
    }, APP_CONFIG.refresh.dashboard)

    return () => {
      if (interval) clearInterval(interval)
    }
  }, [refetchStats, refetchCaisse, refetchAgenciesPerf, refetchActivities])

  const isInitialLoading = statsLoading && !stats

  if (isInitialLoading) {
    return (
      <div className="dashboard-page">
        <div className="dashboard-header">
          <Title level={2} className="dashboard-title">{copy.title}</Title>
          <div className="dashboard-subtitle">Chargement en cours…</div>
        </div>
        <DashboardSkeleton />
      </div>
    )
  }

  return (
    <div className="dashboard-page">
      <div className="dashboard-header">
        <Title level={2} className="dashboard-title">
          {copy.title}
        </Title>
        <div className="dashboard-subtitle">
          {copy.subtitle}
        </div>
      </div>

      <DashboardQuickActions persona={persona} />

      {/* STATISTIQUES */}
      {stats && <StatsCards stats={stats} loading={statsLoading} />}

      {/* POINT CAISSE MULTI-AGENCES (direction / manager) */}
      {showAgenciesPerf ? (
        <WithPermission permission={PERMISSIONS.DASHBOARD.CAISSE}>
          <AgenciesPerformanceGrid
            data={agenciesPerf || []}
            loading={agenciesPerfLoading}
          />
        </WithPermission>
      ) : null}

      {/* POINT CAISSE (Agence locale) — en tête pour caissiers */}
      <WithPermission permission={PERMISSIONS.DASHBOARD.CAISSE}>
        {pointCaisse && persona === 'caissier' ? (
          <div className="dashboard-section">
            <PointCaisse data={pointCaisse} loading={caisseLoading} />
          </div>
        ) : null}
      </WithPermission>

      {/* GRAPHIQUES */}
      {showCharts ? (
        <Row gutter={[24, 24]} className="dashboard-section">
          <Col xs={24} lg={8}>
            <PredictionCard
              data={(chartData || []).map((d: any) => d.total)}
              loading={chartLoading}
            />
          </Col>
          <Col xs={24} lg={16}>
            <ChartRevenus data={chartData || []} loading={chartLoading} />
          </Col>
        </Row>
      ) : null}

      {showCharts ? (
        <Row gutter={[24, 24]} className="dashboard-section">
          <Col xs={24} lg={12}>
            <ChartColisParMois data={chartData || []} loading={chartLoading} />
          </Col>
          <Col xs={24} lg={12}>
            <ChartRepartitionTrafic data={trafficData || []} loading={trafficLoading} />
          </Col>
        </Row>
      ) : null}

      <WithPermission permission={PERMISSIONS.DASHBOARD.CAISSE}>
        {pointCaisse && persona !== 'caissier' ? (
          <div className="dashboard-section">
            <PointCaisse data={pointCaisse} loading={caisseLoading} />
          </div>
        ) : null}
      </WithPermission>

      <Row gutter={[24, 24]} className="dashboard-section">
        <Col xs={24} xl={16}>
          <RecentActivities activities={activities || []} loading={activitiesLoading} />
        </Col>
        <Col xs={24} xl={8}>
          {showAgenciesPerf ? (
            <Card
              title={<span><TrophyOutlined style={{ color: '#faad14', marginRight: 8 }} /> Agences les plus actives</span>}
              className="dashboard-card"
              loading={agencesStatsLoading}
              style={{ marginBottom: 24 }}
            >
              {(agencesStats || []).length > 0 ? (
                <div className="agences-stats-list">
                  {agencesStats?.slice(0, 5).map((a, index) => (
                    <div key={a.id} className="agence-stat-row" style={{
                      display: 'flex',
                      justifyContent: 'space-between',
                      padding: '12px 0',
                      borderBottom: index === 4 ? 'none' : '1px solid #f0f0f0'
                    }}>
                      <Space>
                        <Tag color={index === 0 ? 'gold' : index === 1 ? 'silver' : index === 2 ? 'orange' : 'default'}>
                          #{index + 1}
                        </Tag>
                        <Text strong>{a.name}</Text>
                      </Space>
                      <Text type="secondary">{a.total_colis} colis</Text>
                    </div>
                  ))}
                </div>
              ) : (
                <div style={{ padding: '20px 0', textAlign: 'center' }}>
                  <Text type="secondary">Aucune donnée disponible</Text>
                </div>
              )}
            </Card>
          ) : null}

          {showAiPanel ? (
            <AIIntelligencePanel
              recommendations={recommendations}
              loading={recommendationsLoading}
              monitoring={aiMonitoring}
              onActionClick={(action) => {
                if (action.route) navigate(action.route)
              }}
            />
          ) : null}
        </Col>
      </Row>
    </div>
  )
}
