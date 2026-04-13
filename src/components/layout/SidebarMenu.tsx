import React, { useEffect, useMemo, useState } from "react";
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
  ArrowUpOutlined,
  AlertOutlined,
  MessageOutlined,
  ApartmentOutlined,
  EnvironmentOutlined,
  SendOutlined,
  UserOutlined,
  ClusterOutlined,
  GlobalOutlined,
  ShoppingOutlined,
  HistoryOutlined,
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
  const canStatistiques = hasPermission(ROUTE_ACCESS.statistiques);

  const showExploitation =
    canColisGroupage ||
    canColisAutres ||
    canColisMap ||
    canExpeditions;

  const canExploitationAgentDash = hasAnyPermission([
    ...ROUTE_ACCESS.exploitationDashboard,
  ]);
  const canExploitationCredits = hasAnyPermission([
    ...ROUTE_ACCESS.exploitationCredits,
  ]);
  const canExploitationPoints = hasAnyPermission([
    ...ROUTE_ACCESS.exploitationPointsJournaliers,
  ]);
  const canAgenceRecap = hasPermission(ROUTE_ACCESS.agenceCreditsRecap);
  const canAgencePJ = hasAnyPermission([...ROUTE_ACCESS.agencePointJournalier]);
  const canExploitationFournitures = hasAnyPermission([
    ...ROUTE_ACCESS.exploitationFournitures,
  ]);
  const canAgenceFournitures = hasAnyPermission([
    ...ROUTE_ACCESS.agenceFournituresDemande,
  ]);
  const showExploitationAgentBlock =
    canExploitationAgentDash ||
    canExploitationCredits ||
    canExploitationPoints ||
    canAgenceRecap ||
    canAgencePJ ||
    canExploitationFournitures ||
    canAgenceFournitures;

  const showClientsSuivi =
    hasPermission(ROUTE_ACCESS.clients) ||
    hasPermission(ROUTE_ACCESS.litiges) ||
    hasPermission(ROUTE_ACCESS.callcenterInbox);

  const showRapportsAnalyse = canColisRapports || canStatistiques;

  const showFacturationTresorerie =
    hasPermission(ROUTE_ACCESS.factures) ||
    hasPermission(ROUTE_ACCESS.paiements) ||
    hasPermission(ROUTE_ACCESS.caisse);

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
    ...(hasPermission(ROUTE_ACCESS.settingsCatalogueProduits)
      ? [
          {
            key: "/settings/catalogue-produits",
            icon: <ShoppingOutlined />,
            label: "Catalogue produits",
          },
        ]
      : []),
    ...(hasPermission(ROUTE_ACCESS.settingsProduitsHistorique)
      ? [
          {
            key: "/settings/produits-historique",
            icon: <HistoryOutlined />,
            label: "Historique marchandises",
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

  const exploitationChildren: any[] = [
    ...(canExploitationAgentDash
      ? [
          {
            key: "/exploitation",
            icon: <BarChartOutlined />,
            label: "Synthèse agent exploitation",
          },
        ]
      : []),
    ...(canExploitationCredits
      ? [
          {
            key: "/exploitation/credits",
            icon: <DollarOutlined />,
            label: "Crédits inter-agences",
          },
        ]
      : []),
    ...(canExploitationPoints
      ? [
          {
            key: "/exploitation/points-journaliers",
            icon: <FileTextOutlined />,
            label: "Points journaliers",
          },
        ]
      : []),
    ...(canExploitationFournitures
      ? [
          {
            key: "/exploitation/fournitures",
            icon: <ShoppingOutlined />,
            label: "Fournitures bureau",
          },
        ]
      : []),
    ...(canAgenceRecap
      ? [
          {
            key: "/agence/credits-recap",
            icon: <GlobalOutlined />,
            label: "Récap crédits (agence)",
          },
        ]
      : []),
    ...(canAgencePJ
      ? [
          {
            key: "/agence/point-journalier/nouveau",
            icon: <FileTextOutlined />,
            label: "Nouveau point journalier",
          },
        ]
      : []),
    ...(canAgenceFournitures
      ? [
          {
            key: "/agence/fournitures/demande",
            icon: <ShoppingOutlined />,
            label: "Demande fournitures",
          },
        ]
      : []),
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
    ...(canExpeditions
      ? [
          {
            key: "/expeditions",
            icon: <SendOutlined />,
            label: "Expéditions (Manifestes)",
          },
        ]
      : []),
    ...(canColisMap
      ? [
          {
            key: "/colis/map",
            icon: <EnvironmentOutlined />,
            label: "Cartographie",
          },
        ]
      : []),
  ];

  const clientsSuiviChildren: any[] = [
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
  ];

  const rapportsAnalyseChildren: any[] = [
    ...(canColisRapports
      ? [
          {
            key: "/colis/rapports",
            icon: <BarChartOutlined />,
            label: "Rapports",
          },
        ]
      : []),
    ...(canStatistiques
      ? [
          {
            key: "stats_finance_sub",
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
  ];

  const facturationChildren: any[] = [
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
  ];

  const administrationChildren: any[] = [
    ...(hasPermission(ROUTE_ACCESS.users)
      ? [
          {
            key: "/users",
            icon: <UserOutlined />,
            label: "Gestion Utilisateurs",
          },
        ]
      : []),
  ];

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
    ...((showExploitation || showExploitationAgentBlock) &&
    exploitationChildren.length > 0
      ? [
          {
            key: "exploitation_root",
            icon: <ApartmentOutlined />,
            label: "Exploitation",
            children: exploitationChildren,
          },
        ]
      : []),
    ...(showClientsSuivi && clientsSuiviChildren.length > 0
      ? [
          {
            key: "clients_suivi_root",
            icon: <TeamOutlined />,
            label: "Clients & suivi",
            children: clientsSuiviChildren,
          },
        ]
      : []),
    ...(showRapportsAnalyse && rapportsAnalyseChildren.length > 0
      ? [
          {
            key: "rapports_analyse_root",
            icon: <BarChartOutlined />,
            label: "Rapports & analyse",
            children: rapportsAnalyseChildren,
          },
        ]
      : []),
    ...(showFacturationTresorerie && facturationChildren.length > 0
      ? [
          {
            key: "facturation_tresorerie_root",
            icon: <DollarOutlined />,
            label: "Facturation & trésorerie",
            children: facturationChildren,
          },
        ]
      : []),
    ...settingsMenuBlock,
    ...(administrationChildren.length > 0
      ? [
          {
            key: "administration_root",
            icon: <ClusterOutlined />,
            label: "Administration",
            children: administrationChildren,
          },
        ]
      : []),
  ];

  const parentKeysNoNavigate = useMemo(
    () =>
      new Set([
        "exploitation_root",
        "clients_suivi_root",
        "rapports_analyse_root",
        "stats_finance_sub",
        "facturation_tresorerie_root",
        "caisse_root",
        "settings_root",
        "administration_root",
        "flux_auth",
      ]),
    [],
  );

  const handleMenuClick: any = ({ key }: any) => {
    if (key && !parentKeysNoNavigate.has(String(key))) {
      navigate(key as string);
    }
  };

  const selectedKeys = [location.pathname];

  const openKeysForPath = useMemo(() => {
    const p = location.pathname;
    const keys: string[] = [];
    if (
      p.startsWith("/colis/groupage") ||
      p.startsWith("/colis/autres-envois") ||
      p.startsWith("/colis/map") ||
      p.startsWith("/expeditions") ||
      p.startsWith("/exploitation") ||
      p.startsWith("/agence/")
    ) {
      keys.push("exploitation_root");
    }
    if (
      p.startsWith("/clients") ||
      p.startsWith("/litiges") ||
      p.startsWith("/callcenter")
    ) {
      keys.push("clients_suivi_root");
    }
    if (p.startsWith("/colis/rapports") || p.startsWith("/statistiques")) {
      keys.push("rapports_analyse_root");
      if (p.startsWith("/statistiques")) {
        keys.push("stats_finance_sub");
      }
    }
    if (
      p.startsWith("/factures") ||
      p.startsWith("/paiements") ||
      p.startsWith("/caisse")
    ) {
      keys.push("facturation_tresorerie_root");
      if (p.startsWith("/caisse")) {
        keys.push("caisse_root");
      }
    }
    if (p.startsWith("/settings")) {
      keys.push("settings_root");
    }
    if (p.startsWith("/users")) {
      keys.push("administration_root");
    }
    return keys;
  }, [location.pathname]);

  const [openKeys, setOpenKeys] = useState<string[]>(openKeysForPath);
  useEffect(() => {
    setOpenKeys(openKeysForPath);
  }, [openKeysForPath]);

  return (
    <nav aria-label="Navigation principale">
      <Menu
        mode="inline"
        selectedKeys={selectedKeys}
        openKeys={openKeys}
        onOpenChange={(keys: string[]) => setOpenKeys(keys)}
        items={menuItems}
        onClick={handleMenuClick}
        className="modern-sidebar-menu"
        role="menubar"
        aria-label="Menu principal"
      />
    </nav>
  );
};
