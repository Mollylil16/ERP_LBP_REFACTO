/**
 * Fil d'Ariane dynamique basé sur le pathname courant
 * Conforme WCAG 2.1 — role="navigation" + aria-label + aria-current
 */
import React, { useMemo } from 'react';
import { Breadcrumb } from 'antd';
import { Link, useLocation } from 'react-router-dom';
import { HomeOutlined } from '@ant-design/icons';
import './Breadcrumbs.css';

interface BreadcrumbItem {
  label: string;
  path?: string;
  icon?: React.ReactNode;
}

// ─── Map complète des segments de route → labels ─────────────────
const ROUTE_LABELS: Record<string, string> = {
  dashboard: 'Tableau de bord',
  colis: 'Colis',
  groupage: 'Groupage',
  'autres-envois': 'Autres envois',
  rapports: 'Rapports',
  map: 'Carte',
  expeditions: 'Expéditions',
  clients: 'Clients',
  litiges: 'Litiges',
  callcenter: 'Messagerie',
  inbox: 'Boîte de réception',
  factures: 'Factures',
  preview: 'Aperçu',
  paiements: 'Paiements',
  caisse: 'Caisse',
  suivi: 'Suivi de caisse',
  retraits: 'Retraits',
  statistiques: 'Statistiques',
  historiques: 'Historiques',
  rentabilite: 'Rentabilité',
  settings: 'Paramètres',
  tarifs: 'Grilles tarifaires',
  'catalogue-produits': 'Catalogue produits',
  'produits-historique': 'Historique marchandises',
  users: 'Utilisateurs',
  roles: 'Rôles',
  admin: 'Administration',
  track: 'Suivi de colis',
};

const buildCrumbs = (pathname: string): BreadcrumbItem[] => {
  const segments = pathname.split('/').filter(Boolean);
  const crumbs: BreadcrumbItem[] = [
    { label: 'Accueil', path: '/dashboard', icon: <HomeOutlined aria-hidden="true" /> },
  ];

  // Dashboard = juste accueil
  if (segments.length === 1 && segments[0] === 'dashboard') {
    return crumbs;
  }

  let cumulativePath = '';
  segments.forEach((segment, index) => {
    cumulativePath += `/${segment}`;
    const isLast = index === segments.length - 1;

    // IDs numériques → label générique
    const isId = /^\d+$/.test(segment) || /^[a-f0-9-]{36}$/.test(segment);
    const label = isId
      ? 'Détails'
      : (ROUTE_LABELS[segment] ?? segment.charAt(0).toUpperCase() + segment.slice(1));

    crumbs.push({
      label,
      path: isLast ? undefined : cumulativePath,
    });
  });

  return crumbs;
};

export const Breadcrumbs: React.FC = () => {
  const { pathname } = useLocation();

  const crumbs = useMemo(() => buildCrumbs(pathname), [pathname]);

  // Ne pas afficher si on est au dashboard (un seul crumb)
  if (crumbs.length <= 1) return null;

  const items = crumbs.map((crumb, index) => {
    const isLast = index === crumbs.length - 1;
    return {
      key: crumb.path ?? crumb.label,
      title: isLast ? (
        <span aria-current="page" className="breadcrumb-current">
          {crumb.icon}
          {crumb.label}
        </span>
      ) : crumb.path ? (
        <Link
          to={crumb.path}
          className="breadcrumb-link"
          aria-label={`Aller à ${crumb.label}`}
        >
          {crumb.icon}
          {crumb.label}
        </Link>
      ) : (
        <span className="breadcrumb-segment">
          {crumb.icon}
          {crumb.label}
        </span>
      ),
    };
  });

  return (
    <nav
      aria-label="Fil d'Ariane"
      className="lbp-breadcrumbs"
    >
      <Breadcrumb items={items} />
    </nav>
  );
};
