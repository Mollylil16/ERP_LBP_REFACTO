import React from 'react'
import { Card, Space, Button } from 'antd'
import { useNavigate } from 'react-router-dom'
import {
  BankOutlined,
  FileTextOutlined,
  InboxOutlined,
  GlobalOutlined,
  TruckOutlined,
  TeamOutlined,
  AlertOutlined,
  PhoneOutlined,
  SettingOutlined,
  UserOutlined,
} from '@ant-design/icons'
import { WithPermission } from '@components/common/WithPermission'
import { ROUTE_ACCESS } from '@constants/routeAccess'
import type { DashboardPersona } from './resolveDashboardPersona'

interface DashboardQuickActionsProps {
  persona: DashboardPersona
}

/**
 * Raccourcis métier selon le persona (les boutons restent masqués sans permission).
 */
export const DashboardQuickActions: React.FC<DashboardQuickActionsProps> = ({
  persona,
}) => {
  const navigate = useNavigate()

  if (persona === 'direction') {
    return (
      <Card size="small" className="dashboard-section" title="Accès rapides">
        <Space wrap>
          <WithPermission permission={ROUTE_ACCESS.users}>
            <Button icon={<UserOutlined />} onClick={() => navigate('/users')}>
              Utilisateurs
            </Button>
          </WithPermission>
          <WithPermission permission={ROUTE_ACCESS.settings}>
            <Button icon={<SettingOutlined />} onClick={() => navigate('/settings')}>
              Paramètres
            </Button>
          </WithPermission>
          <WithPermission permission={ROUTE_ACCESS.statistiques}>
            <Button icon={<TeamOutlined />} onClick={() => navigate('/statistiques/historiques')}>
              Statistiques
            </Button>
          </WithPermission>
          <WithPermission permission={ROUTE_ACCESS.litiges}>
            <Button icon={<AlertOutlined />} onClick={() => navigate('/litiges')}>
              Litiges
            </Button>
          </WithPermission>
        </Space>
      </Card>
    )
  }

  if (persona === 'manager') {
    return (
      <Card size="small" className="dashboard-section" title="Files & validations">
        <Space wrap>
          <WithPermission permission={ROUTE_ACCESS.litiges}>
            <Button icon={<AlertOutlined />} onClick={() => navigate('/litiges')}>
              Litiges
            </Button>
          </WithPermission>
          <WithPermission permission={ROUTE_ACCESS.factures}>
            <Button icon={<FileTextOutlined />} onClick={() => navigate('/factures')}>
              Factures
            </Button>
          </WithPermission>
          <WithPermission permission={ROUTE_ACCESS.statistiques}>
            <Button icon={<TeamOutlined />} onClick={() => navigate('/statistiques/historiques')}>
              Rapports
            </Button>
          </WithPermission>
          <WithPermission permission={ROUTE_ACCESS.clients}>
            <Button icon={<TeamOutlined />} onClick={() => navigate('/clients')}>
              Clients
            </Button>
          </WithPermission>
        </Space>
      </Card>
    )
  }

  if (persona === 'caissier') {
    return (
      <Card size="small" className="dashboard-section" title="Caisse & paiements">
        <Space wrap>
          <WithPermission permission={ROUTE_ACCESS.caisse}>
            <Button icon={<BankOutlined />} onClick={() => navigate('/caisse/suivi')}>
              Suivi caisse
            </Button>
          </WithPermission>
          <WithPermission permission={ROUTE_ACCESS.paiements}>
            <Button icon={<InboxOutlined />} onClick={() => navigate('/paiements')}>
              Paiements
            </Button>
          </WithPermission>
          <WithPermission permission={ROUTE_ACCESS.factures}>
            <Button icon={<FileTextOutlined />} onClick={() => navigate('/factures')}>
              Factures
            </Button>
          </WithPermission>
        </Space>
      </Card>
    )
  }

  // Backward compat: ancienne persona "agent"
  if (persona === 'agent') {
    return (
      <Card size="small" className="dashboard-section" title="Flux colis">
        <Space wrap>
          <WithPermission permission={ROUTE_ACCESS.colisGroupage}>
            <Button icon={<InboxOutlined />} onClick={() => navigate('/colis/groupage')}>
              Groupage
            </Button>
          </WithPermission>
          <WithPermission permission={ROUTE_ACCESS.colisAutresEnvois}>
            <Button icon={<TruckOutlined />} onClick={() => navigate('/colis/autres-envois')}>
              Autres envois
            </Button>
          </WithPermission>
          <WithPermission permission={[...ROUTE_ACCESS.expeditions]}>
            <Button icon={<GlobalOutlined />} onClick={() => navigate('/expeditions')}>
              Expéditions
            </Button>
          </WithPermission>
          <WithPermission permission={[...ROUTE_ACCESS.colisMap]}>
            <Button icon={<GlobalOutlined />} onClick={() => navigate('/colis/map')}>
              Carte
            </Button>
          </WithPermission>
        </Space>
      </Card>
    )
  }

  if (persona === 'agent_groupage') {
    return (
      <Card size="small" className="dashboard-section" title="Groupage">
        <Space wrap>
          <WithPermission permission={ROUTE_ACCESS.colisGroupage}>
            <Button icon={<InboxOutlined />} onClick={() => navigate('/colis/groupage')}>
              Colis groupage
            </Button>
          </WithPermission>
          <WithPermission permission={ROUTE_ACCESS.factures}>
            <Button icon={<FileTextOutlined />} onClick={() => navigate('/factures')}>
              Factures
            </Button>
          </WithPermission>
          <WithPermission permission={ROUTE_ACCESS.paiements}>
            <Button icon={<InboxOutlined />} onClick={() => navigate('/paiements')}>
              Paiements
            </Button>
          </WithPermission>
        </Space>
      </Card>
    )
  }

  if (persona === 'agent_exploitation') {
    return (
      <Card size="small" className="dashboard-section" title="Exploitation">
        <Space wrap>
          <WithPermission permission={ROUTE_ACCESS.colisGroupage}>
            <Button icon={<InboxOutlined />} onClick={() => navigate('/colis/groupage')}>
              Groupage
            </Button>
          </WithPermission>
          <WithPermission permission={[...ROUTE_ACCESS.exploitationDashboard]}>
            <Button icon={<TeamOutlined />} onClick={() => navigate('/exploitation')}>
              Exploitation
            </Button>
          </WithPermission>
          <WithPermission permission={ROUTE_ACCESS.litiges}>
            <Button icon={<AlertOutlined />} onClick={() => navigate('/litiges')}>
              Litiges
            </Button>
          </WithPermission>
        </Space>
      </Card>
    )
  }

  if (persona === 'chef_agence') {
    return (
      <Card size="small" className="dashboard-section" title="Pilotage agence">
        <Space wrap>
          <WithPermission permission={[...ROUTE_ACCESS.exploitationPointsJournaliers]}>
            <Button icon={<TeamOutlined />} onClick={() => navigate('/exploitation/points-journaliers')}>
              Points journaliers
            </Button>
          </WithPermission>
          <WithPermission permission={ROUTE_ACCESS.caisse}>
            <Button icon={<BankOutlined />} onClick={() => navigate('/caisse/suivi')}>
              Caisse
            </Button>
          </WithPermission>
          <WithPermission permission={ROUTE_ACCESS.colisGroupage}>
            <Button icon={<InboxOutlined />} onClick={() => navigate('/colis/groupage')}>
              Groupage
            </Button>
          </WithPermission>
          <WithPermission permission={ROUTE_ACCESS.factures}>
            <Button icon={<FileTextOutlined />} onClick={() => navigate('/factures')}>
              Factures
            </Button>
          </WithPermission>
        </Space>
      </Card>
    )
  }

  if (persona === 'callcenter') {
    return (
      <Card size="small" className="dashboard-section" title="Relation client">
        <Space wrap>
          <WithPermission permission={ROUTE_ACCESS.callcenterInbox}>
            <Button icon={<PhoneOutlined />} onClick={() => navigate('/callcenter/inbox')}>
              Boîte messages
            </Button>
          </WithPermission>
          <WithPermission permission={ROUTE_ACCESS.litiges}>
            <Button icon={<AlertOutlined />} onClick={() => navigate('/litiges')}>
              Litiges
            </Button>
          </WithPermission>
          <WithPermission permission={ROUTE_ACCESS.clients}>
            <Button icon={<TeamOutlined />} onClick={() => navigate('/clients')}>
              Clients
            </Button>
          </WithPermission>
        </Space>
      </Card>
    )
  }

  if (persona === 'suivi') {
    return (
      <Card size="small" className="dashboard-section" title="Suivi">
        <Space wrap>
          <WithPermission permission={ROUTE_ACCESS.litiges}>
            <Button icon={<AlertOutlined />} onClick={() => navigate('/litiges')}>
              Litiges
            </Button>
          </WithPermission>
          <WithPermission permission={ROUTE_ACCESS.callcenterInbox}>
            <Button icon={<PhoneOutlined />} onClick={() => navigate('/callcenter/inbox')}>
              Boîte messages
            </Button>
          </WithPermission>
          <Button type="default" onClick={() => navigate('/track')}>
            Suivi public colis
          </Button>
        </Space>
      </Card>
    )
  }

  return (
    <Card size="small" className="dashboard-section" title="Raccourcis">
      <Space wrap>
        <WithPermission permission={ROUTE_ACCESS.colisGroupage}>
          <Button icon={<InboxOutlined />} onClick={() => navigate('/colis/groupage')}>
            Groupage
          </Button>
        </WithPermission>
        <WithPermission permission={ROUTE_ACCESS.caisse}>
          <Button icon={<BankOutlined />} onClick={() => navigate('/caisse/suivi')}>
            Caisse
          </Button>
        </WithPermission>
        <WithPermission permission={ROUTE_ACCESS.factures}>
          <Button icon={<FileTextOutlined />} onClick={() => navigate('/factures')}>
            Factures
          </Button>
        </WithPermission>
      </Space>
    </Card>
  )
}
