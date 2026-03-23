
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
} from "@ant-design/icons";
import { usePermissions } from "@hooks/usePermissions";
import { useAuth } from "@hooks/useAuth";
import "./SidebarMenu.css";

interface SidebarMenuProps {
  collapsed: boolean;
}

export const SidebarMenu: React.FC<SidebarMenuProps> = ({ collapsed }) => {
  const navigate = useNavigate();
  const location = useLocation();
  const { user } = useAuth();
  const { hasPermission, permissions, isLoading: isPermsLoading } = usePermissions();

  // Construction du menu selon les permissions
  const menuItems: any[] = [
    {
      key: "/dashboard",
      icon: <DashboardOutlined />,
      label: "Tableau de bord",
    },
    // Afficher le menu "Gestion Colis" si l'utilisateur a au moins une permission liée aux colis
    ...(hasPermission("colis.groupage.read") ||
      hasPermission("colis.groupage.create") ||
      hasPermission("colis.groupage.update") ||
      hasPermission("colis.autres-envois.read") ||
      hasPermission("colis.autres-envois.create") ||
      hasPermission("colis.autres-envois.update") ||
      hasPermission("rapports.view")
      ? [
        {
          key: "colis",
          icon: <InboxOutlined />,
          label: "Gestion Colis",
          children: [
            ...(hasPermission("colis.groupage.read") ||
              hasPermission("colis.groupage.create") ||
              hasPermission("colis.groupage.update")
              ? [
                {
                  key: "/colis/groupage",
                  icon: <FolderOutlined />,
                  label: "Groupage",
                },
              ]
              : []),
            ...(hasPermission("colis.autres-envois.read") ||
              hasPermission("colis.autres-envois.create") ||
              hasPermission("colis.autres-envois.update")
              ? [
                {
                  key: "/colis/autres-envois",
                  icon: <InboxOutlined />,
                  label: "Autres Envois",
                },
              ]
              : []),
            ...(hasPermission("rapports.view")
              ? [
                {
                  key: "/colis/rapports",
                  icon: <BarChartOutlined />,
                  label: "Rapports",
                },
              ]
              : []),
            // Map moved here? user said menu doesn't work. The route is /colis/map.
            // If I put it here, key is /colis/map.
            {
              key: "/colis/map",
              icon: <GlobalOutlined />,
              label: "Cartographie",
            },
          ],
        },
      ]
      : []),
    {
      key: "/expeditions",
      icon: <GlobalOutlined />,
      label: "Expéditions (Manifestes)",
    },
    ...(hasPermission("clients.read")
      ? [
        {
          key: "/clients",
          icon: <TeamOutlined />,
          label: "Clients Expéditeurs",
        },
      ]
      : []),
    ...(hasPermission("factures.read")
      ? [
        {
          key: "/factures",
          icon: <FileTextOutlined />,
          label: "Facturation",
        },
      ]
      : []),
    ...(hasPermission("paiements.read")
      ? [
        {
          key: "/paiements",
          icon: <DollarOutlined />,
          label: "Paiements",
        },
      ]
      : []),
    ...(hasPermission("caisse.view")
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
    ...(hasPermission("rapports.view")
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
          ]
        },
      ]
      : []),
    ...(hasPermission("config.view")
      ? [
        {
          key: "settings_root",
          icon: <SettingOutlined />,
          label: "Paramètres",
          children: [
            {
              key: "/settings",
              icon: <SettingOutlined />,
              label: "Général",
            },
            {
              key: "/settings/tarifs",
              icon: <DollarOutlined />,
              label: "Grilles Tarifaires",
            },
            {
              key: "/settings/agences",
              icon: <GlobalOutlined />,
              label: "Gestion Agences",
            },
          ],
        },
      ]
      : []),
    ...(hasPermission("users.read")
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
    // Navigate allowed if NOT a submenu key
    if (key && !["colis", "/statistiques", "settings_root", "caisse_root", "flux_auth"].includes(key)) {
      navigate(key as string);
    }
  };

  // Déterminer la clé sélectionnée
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
