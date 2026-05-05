import React from 'react'
import { Tabs, Typography, Alert, Button } from 'antd'
import {
  DashboardOutlined,
  TeamOutlined,
  FileTextOutlined,
  CalendarOutlined,
  DollarOutlined,
  ClockCircleOutlined,
  StarOutlined,
  SearchOutlined,
  BookOutlined,
  BarChartOutlined,
  SettingOutlined,
} from '@ant-design/icons'
import { RhDashboardTab } from './tabs/RhDashboardTab'
import { RhEmployesTab } from './tabs/RhEmployesTab'
import { RhContratsTab } from './tabs/RhContratsTab'
import { RhCongesTab } from './tabs/RhCongesTab'
import { RhPaieTab } from './tabs/RhPaieTab'
import { RhPresencesTab } from './tabs/RhPresencesTab'
import { RhEvaluationsTab } from './tabs/RhEvaluationsTab'
import { RhRecrutementTab } from './tabs/RhRecrutementTab'
import { RhFormationTab } from './tabs/RhFormationTab'
import { RhRapportsTab } from './tabs/RhRapportsTab'
import { RhParametresTab } from './tabs/RhParametresTab'
import { usePermissions } from '@hooks/usePermissions'
import { PERMISSIONS } from '@constants/permissions'
import { ErrorBoundary } from '@components/common/ErrorBoundary'

const { Title } = Typography

const TABS = [
  {
    key: 'dashboard',
    label: 'Tableau de bord',
    icon: <DashboardOutlined />,
    permission: PERMISSIONS.RH.DASHBOARD_READ,
    component: <RhDashboardTab />,
  },
  {
    key: 'employes',
    label: 'Employés',
    icon: <TeamOutlined />,
    permission: PERMISSIONS.RH.EMPLOYES_READ,
    component: <RhEmployesTab />,
  },
  {
    key: 'contrats',
    label: 'Contrats',
    icon: <FileTextOutlined />,
    permission: PERMISSIONS.RH.CONTRATS_READ,
    component: <RhContratsTab />,
  },
  {
    key: 'conges',
    label: 'Congés',
    icon: <CalendarOutlined />,
    permission: PERMISSIONS.RH.CONGES_READ,
    component: <RhCongesTab />,
  },
  {
    key: 'presences',
    label: 'Présences',
    icon: <ClockCircleOutlined />,
    permission: PERMISSIONS.RH.PRESENCES_READ,
    component: <RhPresencesTab />,
  },
  {
    key: 'paie',
    label: 'Paie',
    icon: <DollarOutlined />,
    permission: PERMISSIONS.RH.PAIE_READ,
    component: <RhPaieTab />,
  },
  {
    key: 'evaluations',
    label: 'Évaluations',
    icon: <StarOutlined />,
    permission: PERMISSIONS.RH.EVALUATIONS_READ,
    component: <RhEvaluationsTab />,
  },
  {
    key: 'recrutement',
    label: 'Recrutement',
    icon: <SearchOutlined />,
    permission: PERMISSIONS.RH.RECRUTEMENT_READ,
    component: <RhRecrutementTab />,
  },
  {
    key: 'formation',
    label: 'Formation',
    icon: <BookOutlined />,
    permission: PERMISSIONS.RH.FORMATION_READ,
    component: <RhFormationTab />,
  },
  {
    key: 'rapports',
    label: 'Rapports légaux',
    icon: <BarChartOutlined />,
    permission: PERMISSIONS.RH.RAPPORTS_READ,
    component: <RhRapportsTab />,
  },
  {
    key: 'parametres',
    label: 'Paramètres paie',
    icon: <SettingOutlined />,
    permission: PERMISSIONS.RH.PAIE_UPDATE,
    component: <RhParametresTab />,
  },
]

export const RhPage: React.FC = () => {
  const { hasPermission } = usePermissions()

  const items = TABS.filter((t) => hasPermission(t.permission)).map((t) => ({
    key: t.key,
    label: (
      <span>
        {t.icon}
        {t.label}
      </span>
    ),
    children: t.component,
  }))

  const rhFallback = (
    <Alert
      type="error"
      showIcon
      message="Erreur de chargement du module RH"
      description={
        <span>
          Une erreur est survenue dans le module Ressources Humaines.{' '}
          <Button size="small" onClick={() => window.location.reload()}>
            Recharger la page
          </Button>
        </span>
      }
      style={{ margin: '24px' }}
    />
  )

  return (
    <div style={{ padding: '24px 24px 0' }}>
      <Title level={3} style={{ marginBottom: 16 }}>
        Ressources Humaines — SIRH
      </Title>
      <ErrorBoundary fallback={rhFallback}>
        <Tabs
          items={items}
          destroyInactiveTabPane={true}
          tabBarStyle={{ marginBottom: 16 }}
        />
      </ErrorBoundary>
    </div>
  )
}
