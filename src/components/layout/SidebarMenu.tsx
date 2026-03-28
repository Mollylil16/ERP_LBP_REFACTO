
import React from "react";
import { useNavigate, useLocation } from "react-router-dom";
import { Menu } from "antd";
import {
  DashboardOutlined,
  InboxOutlined,
  FolderOutlined,
  FileTextOutlined,
  TeamOutlined,
  BarChartOutlined,
  SettingOutlined,
  DollarOutlined,
  WalletOutlined,
  LineChartOutlined,
  GlobalOutlined,
  ArrowUpOutlined,
  AlertOutlined,
  MessageOutlined,
} from "@ant-design/icons";
import { usePermissions } from "@hooks/usePermissions";
import { ROUTE_ACCESS, COLIS_READ_ANY } from "@constants/routeAccess";
import "./SidebarMenu.css";

interface SidebarMenuProps {
  collapsed: boolean;
}

export const SidebarMenu: React.FC<SidebarMenuProps> = ({ collapsed: _collapsed }) => {
  const navigate = useNavigate();
  const location = useLocation();
  const { hasPermission, hasAnyPermission } = usePermissions();

  const canColisGroupage =
    hasPermission("colis.groupage.read") ||
    hasPermission("colis.groupage.create") ||
    hasPermission("colis.groupage.update");
  const canColisAutres =
    hasPermission("colis.autres-envois.read") ||
    hasPermission("colis.autres-envois.create") ||
    hasPermission("colis.autres-envois.update");
  const canColisRapports = hasPermission(ROUTE_ACCESS.colisRapports);
  const canColisMap = hasAnyPermission([...COLIS_READ_ANY]);
  const canExpeditions = hasAnyPermission([...ROUTE_ACCESS.expeditions]);

  const showColisSection =
    canColisGroupage || canColisAutres || canColisRapports || canColisMap;

  const settingsChildren = [
    ...(hasPermission(ROUTE_ACCESS.settings)
      ? [
          {
            key: "/settings",
            icon: <SettingOutlined />,
            label: "Général",
          },
        ]
      : []),
    ...(hasPermission(ROUTE_ACCESS.settingsTarifs)
      ? [
          {
            key: "/settings/tarifs",
            icon: <DollarOutlined />,
            label: "Grilles Tarifaires",
          },
        ]
      : []),
    ...(hasPermission(ROUTE_ACCESS.settingsAgences)
      ? [
          {
            key: "/settings/agences",
            icon: <GlobalOutlined />,
            label: "Gestion Agences",
          },
        ]
      : []),
  ];
  const settingsMenuBlock =
    settingsChildren.length > 0
      ? [
          {
            key: "settings_root",
            icon: <SettingOutlined />,
            label: "Paramètres",
            children: settingsChildren,
          },
        ]
      : [];

  const menuItems: any[] = [
    ...(hasPermission(ROUTE_ACCESS.dashboard)
      ? [
          {
            key: "/dashboard",
            icon: <DashboardOutlined />,
            label: "Tableau de bord",
          },
        ]
      : []),
    ...(showColisSection
      ? [
          {
            key: "colis",
            icon: <InboxOutlined />,
            label: "Gestion Colis",
            children: [
              ...(canColisGroupage
                ? [
                    {
                      key: "/colis/groupage",
                      icon: <FolderOutlined />,
                      label: "Groupage",
                    },
                  ]
                : []),
              ...(canColisAutres
                ? [
                    {
                      key: "/colis/autres-envois",
                      icon: <InboxOutlined />,
                      label: "Autres Envois",
                    },
                  ]
                : []),
              ...(canColisRapports
                ? [
                    {
                      key: "/colis/rapports",
                      icon: <BarChartOutlined />,
                      label: "Rapports",
                    },
                  ]
                : []),
              ...(canColisMap
                ? [
                    {
                      key: "/colis/map",
                      icon: <GlobalOutlined />,
                      label: "Cartographie",
                    },
                  ]
                : []),
            ],
          },
        ]
      : []),
    ...(canExpeditions
      ? [
          {
            key: "/expeditions",
            icon: <GlobalOutlined />,
            label: "Expéditions (Manifestes)",
          },
        ]
      : []),
    ...(hasPermission(ROUTE_ACCESS.clients)
      ? [
          {
            key: "/clients",
            icon: <TeamOutlined />,
            label: "Clients Expéditeurs",
          },
        ]
      : []),
    ...(hasPermission(ROUTE_ACCESS.litiges)
      ? [
          {
            key: "/litiges",
            icon: <AlertOutlined />,
            label: "Litiges",
          },
        ]
      : []),
    ...(hasPermission(ROUTE_ACCESS.callcenterInbox)
      ? [
          {
            key: "/callcenter/inbox",
            icon: <MessageOutlined />,
            label: "Messagerie (inbox)",
          },
        ]
      : []),
    ...(hasPermission(ROUTE_ACCESS.factures)
      ? [
          {
            key: "/factures",
            icon: <FileTextOutlined />,
            label: "Facturation",
          },
        ]
      : []),
    ...(hasPermission(ROUTE_ACCESS.paiements)
      ? [
          {
            key: "/paiements",
            icon: <DollarOutlined />,
            label: "Paiements",
          },
        ]
      : []),
    ...(hasPermission(ROUTE_ACCESS.caisse)
      ? [
          {
            key: "caisse_root",
            icon: <WalletOutlined />,
            label: "Gestion Caisse",
            children: [
              {
                key: "/caisse/suivi",
                icon: <WalletOutlined />,
                label: "Suivi Caisse",
              },
              {
                key: "/caisse/retraits",
                icon: <ArrowUpOutlined />,
                label: "Suivi des Retraits",
              },
            ],
          },
        ]
      : []),
    ...(hasPermission(ROUTE_ACCESS.statistiques)
      ? [
          {
            key: "/statistiques",
            icon: <LineChartOutlined />,
            label: "Statistiques & Finance",
            children: [
              {
                key: "/statistiques/historiques",
                icon: <LineChartOutlined />,
                label: "Statistiques Historiques",
              },
              {
                key: "/statistiques/rentabilite",
                icon: <BarChartOutlined />,
                label: "Analyse Rentabilité",
              },
            ],
          },
        ]
      : []),
    ...settingsMenuBlock,
    ...(hasPermission(ROUTE_ACCESS.users)
      ? [
          {
            key: "/users",
            icon: <TeamOutlined />,
            label: "Gestion Utilisateurs",
          },
        ]
      : []),
  ];

  const handleMenuClick: any = ({ key }: any) => {
    if (key && !["colis", "/statistiques", "settings_root", "caisse_root", "flux_auth"].includes(key)) {
      navigate(key as string);
    }
  };

  const selectedKeys = [location.pathname];
  const openKeys = location.pathname.startsWith("/colis") ? ["colis"] :
    location.pathname.startsWith("/statistiques") ? ["/statistiques"] :
      location.pathname.startsWith("/caisse") ? ["caisse_root"] :
        location.pathname.startsWith("/settings") ? ["settings_root"] : [];

  return (
    <nav aria-label="Navigation principale">
      <Menu
        mode="inline"
        selectedKeys={selectedKeys}
        defaultOpenKeys={openKeys}
        items={menuItems}
        onClick={handleMenuClick}
        className="modern-sidebar-menu"
        role="menubar"
        aria-label="Menu principal"
      />
    </nav>
  );
};
