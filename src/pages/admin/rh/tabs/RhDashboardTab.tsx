import React from 'react'
import { Row, Col, Statistic, Card, Table, Tag, Alert } from 'antd'
import {
  TeamOutlined,
  UserOutlined,
  FileTextOutlined,
  ClockCircleOutlined,
  WarningOutlined,
} from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import { rhService, RhDashboard } from '@services/rh.service'
import dayjs from 'dayjs'

const STATUT_COLOR: Record<string, string> = {
  actif: 'green',
  suspendu: 'orange',
  sorti: 'red',
}

export const RhDashboardTab: React.FC = () => {
  const { data, isLoading, isError, error } = useQuery<RhDashboard>({
    queryKey: ['rh-dashboard'],
    queryFn: rhService.getDashboard,
    refetchInterval: 120_000,
    staleTime: 60_000,
    retry: 1,
  })

  if (isError) {
    return (
      <Alert
        type="warning"
        showIcon
        message="Impossible de charger le tableau de bord RH"
        description={
          (error as any)?.response?.status === 500
            ? 'Les tables RH n\'ont peut-être pas encore été créées. Exécutez les migrations sur le serveur.'
            : 'Vérifiez la connexion au serveur et réessayez.'
        }
        style={{ margin: '16px 0' }}
      />
    )
  }

  const alertesCdd = data?.alertes_cdd ?? []

  const colonnesAlertes = [
    { title: 'Matricule', dataIndex: 'matricule', key: 'matricule', width: 120 },
    {
      title: 'Employé',
      key: 'nom',
      render: (_: unknown, r: RhDashboard['alertes_cdd'][number]) =>
        `${r.nom} ${r.prenoms}`,
    },
    {
      title: 'Fin contrat',
      dataIndex: 'date_fin',
      key: 'date_fin',
      render: (v: string) => dayjs(v).format('DD/MM/YYYY'),
    },
    {
      title: 'Jours restants',
      dataIndex: 'jours_restants',
      key: 'jours_restants',
      render: (v: number) => (
        <Tag color={v <= 7 ? 'red' : v <= 15 ? 'orange' : 'gold'}>{v} j</Tag>
      ),
    },
  ]

  return (
    <div style={{ padding: '0 4px' }}>
      {alertesCdd.length > 0 && (
        <Alert
          type="warning"
          icon={<WarningOutlined />}
          showIcon
          message={`${alertesCdd.length} CDD expire(nt) dans les 30 prochains jours`}
          style={{ marginBottom: 16 }}
        />
      )}

      {/* KPIs */}
      <Row gutter={[16, 16]} style={{ marginBottom: 24 }}>
        <Col xs={24} sm={12} md={6}>
          <Card loading={isLoading}>
            <Statistic
              title="Effectif total"
              value={data?.effectif_total ?? 0}
              prefix={<TeamOutlined />}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} md={6}>
          <Card loading={isLoading}>
            <Statistic
              title="Employés actifs"
              value={data?.effectif_actif ?? 0}
              valueStyle={{ color: '#52c41a' }}
              prefix={<UserOutlined />}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} md={6}>
          <Card loading={isLoading}>
            <Statistic
              title="CDD actifs"
              value={data?.cdd_actifs ?? 0}
              prefix={<FileTextOutlined />}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} md={6}>
          <Card loading={isLoading}>
            <Statistic
              title="Congés en attente"
              value={data?.conges_en_attente ?? 0}
              valueStyle={data?.conges_en_attente ? { color: '#fa8c16' } : {}}
              prefix={<ClockCircleOutlined />}
            />
          </Card>
        </Col>
      </Row>

      {/* Alertes CDD */}
      {alertesCdd.length > 0 && (
        <Card
          title={<span><WarningOutlined style={{ color: '#fa8c16', marginRight: 8 }} />Alertes CDD expirants</span>}
          style={{ marginBottom: 24 }}
          size="small"
        >
          <Table
            dataSource={alertesCdd}
            columns={colonnesAlertes}
            rowKey="id"
            pagination={false}
            size="small"
          />
        </Card>
      )}

      {/* Répartition par statut et type de contrat */}
      <Row gutter={16}>
        <Col xs={24} md={12}>
          <Card title="Répartition par statut" size="small">
            {(data?.par_statut ?? []).map((r) => (
              <Row key={r.statut} justify="space-between" style={{ marginBottom: 6 }}>
                <Tag color={STATUT_COLOR[r.statut] ?? 'default'}>{r.statut}</Tag>
                <strong>{r.nb}</strong>
              </Row>
            ))}
          </Card>
        </Col>
        <Col xs={24} md={12}>
          <Card title="Répartition par type de contrat" size="small">
            {(data?.par_type_contrat ?? []).map((r) => (
              <Row key={r.type} justify="space-between" style={{ marginBottom: 6 }}>
                <Tag>{r.type}</Tag>
                <strong>{r.nb}</strong>
              </Row>
            ))}
          </Card>
        </Col>
      </Row>
    </div>
  )
}
