import React, { useState, useEffect } from 'react'
import { Outlet, useNavigate, useLocation } from 'react-router-dom'
import { Layout, Avatar, Dropdown, Space, Button } from 'antd'
import {
  MenuFoldOutlined,
  MenuUnfoldOutlined,
  MenuOutlined,
  UserOutlined,
  LogoutOutlined,
  SettingOutlined,
  CompassOutlined,
  QuestionCircleOutlined,
} from '@ant-design/icons'
import { useAuth } from '@hooks/useAuth'
import { usePermissions } from '@hooks/usePermissions'
import { ROUTE_ACCESS } from '@constants/routeAccess'
import { useTheme } from '@contexts/ThemeContext'
import { useKeyboardNav } from '@hooks/useKeyboardNav'
import type { ItemType } from 'antd/es/menu/interface'
import type { MenuInfo } from 'rc-menu/lib/interface'
import { SidebarMenu } from './SidebarMenu'
import { SkipToMain } from '../common/SkipToMain'
import { ThemeToggle } from '../common/ThemeToggle'
import { NotificationBell } from '../notifications/NotificationBell'
import { OfflineIndicator } from '../common/OfflineIndicator'
import { KeyboardShortcutsHelp } from '../common/KeyboardShortcutsHelp'
import { Breadcrumbs } from '../common/Breadcrumbs'
import { useServiceWorker } from '../../hooks/useServiceWorker'
import { useMobileMenu } from '../../hooks/useMobileMenu'
import { useOnboardingTour } from '../onboarding/AppOnboardingTour'
import { useExportsTour } from '../onboarding/ExportsTour'
import '../../styles/responsive.css'
import './MainLayout.css'

const { Header, Sider, Content } = Layout

export const MainLayout: React.FC = () => {
  const [collapsed, setCollapsed] = useState(false)
  const navigate = useNavigate()
  const location = useLocation()
  const { user, logout } = useAuth()
  const { hasPermission } = usePermissions()
  const canOpenSettings =
    hasPermission(ROUTE_ACCESS.settings) ||
    hasPermission(ROUTE_ACCESS.settingsTarifs) ||
    hasPermission(ROUTE_ACCESS.settingsAgences)
  const { isDark } = useTheme()
  const { isMobile, isMenuOpen, closeMenu, toggleMenu } = useMobileMenu()
  const { startTour } = useOnboardingTour()
  const { startExportsTour } = useExportsTour()
  useServiceWorker()    // Enregistre le service worker
  useKeyboardNav()      // Active les raccourcis clavier globaux

  useEffect(() => {
    if (isMobile) closeMenu()
  }, [location.pathname, isMobile, closeMenu])

  useEffect(() => {
    if (isMobile && isMenuOpen) {
      document.body.classList.add('lbp-mobile-nav-open')
      return () => document.body.classList.remove('lbp-mobile-nav-open')
    }
    document.body.classList.remove('lbp-mobile-nav-open')
    return undefined
  }, [isMobile, isMenuOpen])

  useEffect(() => {
    if (!isMobile || !isMenuOpen) return
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') closeMenu()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [isMobile, isMenuOpen, closeMenu])

  const userMenuItems: ItemType[] = [
    {
      key: 'profile',
      icon: <UserOutlined />,
      label: 'Mon profil',
    },
    {
      key: 'onboarding',
      icon: <CompassOutlined />,
      label: 'Visite guidée',
    },
    ...(canOpenSettings
      ? [
          {
            key: 'settings',
            icon: <SettingOutlined />,
            label: 'Paramètres',
          } as const,
        ]
      : []),
    {
      type: 'divider',
    },
    {
      key: 'logout',
      icon: <LogoutOutlined />,
      label: 'Déconnexion',
      danger: true,
    },
  ]

  const onUserMenuClick = ({ key }: MenuInfo) => {
    if (key === 'onboarding') {
      startTour()
      return
    }
    if (key === 'profile') {
      navigate('/profile')
      return
    }
    if (key === 'settings') {
      if (hasPermission(ROUTE_ACCESS.settings)) navigate('/settings')
      else if (hasPermission(ROUTE_ACCESS.settingsTarifs)) navigate('/settings/tarifs')
      else if (hasPermission(ROUTE_ACCESS.settingsAgences)) navigate('/settings/agences')
      return
    }
    if (key === 'logout') {
      logout()
    }
  }

  const handleSidebarToggle = () => {
    if (isMobile) {
      toggleMenu()
    } else {
      setCollapsed(!collapsed)
    }
  }

  return (
    <Layout className="main-layout">
      <SkipToMain mainId="main-content" />
      <OfflineIndicator />
      <KeyboardShortcutsHelp />
      {/* Bouton flottant "Revoir le tuto" : toujours disponible si besoin */}
      <Button
        type="primary"
        shape="round"
        icon={<QuestionCircleOutlined />}
        onClick={startTour}
        className="lbp-floating-tour-btn"
      >
        Revoir le tuto
      </Button>
      <Button
        shape="round"
        onClick={startExportsTour}
        className="lbp-floating-exports-tour-btn"
      >
        Revoir le tuto États
      </Button>
      {/* Overlay pour mobile */}
      {isMobile && isMenuOpen && (
        <div className="sidebar-overlay active" onClick={closeMenu} aria-hidden="true" />
      )}
      <Sider
        trigger={null}
        collapsible
        collapsed={!isMobile && collapsed}
        theme={isDark ? 'dark' : 'light'}
        width={280}
        className={`modern-sidebar ${isMobile && isMenuOpen ? 'mobile-open' : ''}`}
        data-onboarding="sidebar"
      >
        <div className="sidebar-logo">
          <div className="logo-icon-container">
            <img
              src="/logo_lbp.png"
              alt="Logo La Belle Porte"
              className="sidebar-logo-img"
            />
          </div>
          {(!collapsed || isMobile) && (
            <div className="logo-text">
              <span className="logo-title">LA BELLE PORTE</span>
              <span className="logo-subtitle">Gestion de Colis</span>
            </div>
          )}
        </div>
        <SidebarMenu collapsed={collapsed} />
      </Sider>

      <Layout className="layout-content" style={{ marginLeft: collapsed ? 80 : 280 }}>
        <Header className="modern-header">
          <div className="header-left">
            {React.createElement(
              isMobile
                ? isMenuOpen
                  ? MenuFoldOutlined
                  : MenuOutlined
                : collapsed
                  ? MenuUnfoldOutlined
                  : MenuFoldOutlined,
              {
                className: 'sidebar-trigger',
                onClick: handleSidebarToggle,
                'aria-label': isMobile
                  ? isMenuOpen
                    ? 'Fermer le menu'
                    : 'Ouvrir le menu'
                  : 'Réduire ou agrandir le menu latéral',
                role: 'button',
                tabIndex: 0,
              },
            )}
          </div>

          <Space size="middle" className="header-right header-actions">
            <ThemeToggle />
            <span data-onboarding="notifications">
              <NotificationBell />
            </span>

            <Dropdown
              menu={{ items: userMenuItems, onClick: onUserMenuClick }}
              placement="bottomRight"
              trigger={['click']}
            >
              <div className="user-menu-trigger" data-onboarding="user-menu">
                <Avatar
                  size={42}
                  icon={<UserOutlined />}
                  className="user-avatar"
                  style={{
                    background: 'linear-gradient(135deg, #3B82F6 0%, #2DD4BF 100%)',
                    cursor: 'pointer'
                  }}
                />
                <div className="user-info">
                  <span className="user-name">{user?.username}</span>
                  <span className="user-role">
                    {typeof user?.role === 'string' ? user.role : user?.role?.name || 'Utilisateur'}
                  </span>
                </div>
              </div>
            </Dropdown>
          </Space>
        </Header>

        <Content
          id="main-content"
          data-onboarding="main-content"
          className="modern-content"
          tabIndex={-1}
          role="main"
          aria-label="Contenu principal"
        >
          <Breadcrumbs />
          <div className="page-content">
            <Outlet />
          </div>
        </Content>
      </Layout>
    </Layout>
  )
}
