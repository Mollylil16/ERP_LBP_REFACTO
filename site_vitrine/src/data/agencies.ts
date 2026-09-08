import { Agency, RouteFlow, StatItem, ServiceItem } from '../types/agency';

export const AGENCIES: Agency[] = [
  {
    id: 'abidjan',
    country: "Côte d'Ivoire",
    countryCode: '384',
    city: 'Abidjan',
    hubName: 'Hub Principal Afrique de l\'Ouest',
    region: 'Africa',
    coordinates: [-4.0242, 5.3599],
    color: '#10B981', // Emerald / Gold Africa accent
    address: 'Adjamé Pharmacie Latin & Port-Bouët Aéroport Fret, Abidjan',
    phone: '+225 27 20 00 11 22',
    email: 'abidjan@labelleporte.ci',
    stats: {
      monthlyVolume: '45 000+ Colis / Mois',
      avgCustomsTime: '< 4 Heures',
      hubSurface: '3 500 m² Entrepôt'
    }
  },
  {
    id: 'dakar',
    country: 'Sénégal',
    countryCode: '686',
    city: 'Dakar',
    hubName: 'Hub Régional Dakar-Port',
    region: 'Africa',
    coordinates: [-17.4441, 14.6928],
    color: '#059669', // Emerald
    address: 'Avenue Malick Sy, Quartier Portuaire, Dakar',
    phone: '+221 33 820 44 55',
    email: 'dakar@labelleporte.ci',
    stats: {
      monthlyVolume: '22 000+ Colis / Mois',
      avgCustomsTime: '< 6 Heures',
      hubSurface: '1 800 m² Entrepôt'
    }
  },
  {
    id: 'paris',
    country: 'France',
    countryCode: '250',
    city: 'Paris',
    hubName: 'Hub Européen Roissy CDG & Paris-Nord',
    region: 'Europe',
    coordinates: [2.3522, 48.8566],
    color: '#3B82F6', // Cobalt Blue Europe
    address: '17 Chemin des Vignes, 93000 Bobigny / Roissy CDG',
    phone: '+33 1 48 30 90 00',
    email: 'paris@labelleporte.ci',
    stats: {
      monthlyVolume: '38 000+ Colis / Mois',
      avgCustomsTime: '< 2 Heures Fret Aérien',
      hubSurface: '4 200 m² Logistique'
    }
  },
  {
    id: 'montreal',
    country: 'Canada',
    countryCode: '124',
    city: 'Montréal',
    hubName: 'Hub Amériques & Fret Transatlantique',
    region: 'NorthAmerica',
    coordinates: [-73.5673, 45.5017],
    color: '#F43F5E', // Rose / Coral North America
    address: 'Complexe Cargo YUL, Saint-Laurent, Montréal, QC',
    phone: '+1 514 870 33 44',
    email: 'montreal@labelleporte.ci',
    stats: {
      monthlyVolume: '15 000+ Colis / Mois',
      avgCustomsTime: '< 5 Heures',
      hubSurface: '2 100 m² Entrepôt'
    }
  }
];

export const ROUTE_FLOWS: RouteFlow[] = [
  {
    id: 'abidjan-paris',
    originId: 'abidjan',
    destinationId: 'paris',
    type: 'air',
    speedSeconds: 4.2,
    label: 'Abidjan ⇄ Paris (Cargo Quotidien)',
    frequency: '7 vols / semaine'
  },
  {
    id: 'dakar-paris',
    originId: 'dakar',
    destinationId: 'paris',
    type: 'air',
    speedSeconds: 3.8,
    label: 'Dakar ⇄ Paris (Fret Express)',
    frequency: '5 vols / semaine'
  },
  {
    id: 'abidjan-montreal',
    originId: 'abidjan',
    destinationId: 'montreal',
    type: 'air',
    speedSeconds: 5.5,
    label: 'Abidjan ⇄ Montréal (Ligne Directe)',
    frequency: '3 vols / semaine'
  },
  {
    id: 'paris-montreal',
    originId: 'paris',
    destinationId: 'montreal',
    type: 'air',
    speedSeconds: 4.8,
    label: 'Paris ⇄ Montréal (Pont Transatlantique)',
    frequency: 'Quotidien'
  },
  {
    id: 'abidjan-dakar',
    originId: 'abidjan',
    destinationId: 'dakar',
    type: 'sea',
    speedSeconds: 6.2,
    label: 'Abidjan ⇄ Dakar (Cabotage Maritime)',
    frequency: '2 départs / semaine'
  }
];

export const KEY_STATS: StatItem[] = [
  {
    id: 'vol',
    label: 'Volume Annuel Traité',
    value: '120 000+',
    unit: 'Tonnes de fret',
    description: 'Cargo aérien et conteneurs maritimes acheminés sans rupture'
  },
  {
    id: 'delais',
    label: 'Délai Moyen Aérien',
    value: '48h - 72h',
    unit: 'Porte-à-porte',
    description: 'Enlèvement, dédouanement et livraison finale garantis'
  },
  {
    id: 'fiabilite',
    label: 'Taux de Conformité Douane',
    value: '99,4%',
    unit: 'Zéro blocage',
    description: 'Maîtrise intégrale des procédures douanières internationales'
  },
  {
    id: 'hubs',
    label: 'Agences & Hubs Physiques',
    value: '4 Pays',
    unit: 'Abidjan · Dakar · Paris · Montréal',
    description: 'Équipes en propre et magasins avancés sécurisés'
  }
];

export const SERVICES: ServiceItem[] = [
  {
    id: 'fret-aerien',
    title: 'Fret Aérien Express & Groupage',
    category: 'Aérien',
    description: 'Expéditions prioritaires et groupage sécurisé entre Abidjan, Dakar, Paris et Montréal avec suivi en temps réel.',
    features: ['Expéditions Express 24h/48h', 'Groupage économique régulier', 'Produits périssables & haute valeur', 'Traçabilité GPS continue'],
    icon: 'plane'
  },
  {
    id: 'fret-maritime',
    title: 'Fret Maritime Conteneurs (FCL/LCL)',
    category: 'Maritime',
    description: 'Transport de conteneurs complets ou groupage maritime pour tous types de marchandises commerciales et véhicules.',
    features: ['Conteneurs complets FCL 20\' / 40\'', 'Groupage LCL hebdomadaire', 'Véhicules & Matériel industriel', 'Assurance ad valorem'],
    icon: 'ship'
  },
  {
    id: 'dedouanement',
    title: 'Dédouanement & Ingénierie Douanière',
    category: 'Douane',
    description: 'Déclarations en douane, transit inter-États et conseil en réglementation import-export pour zéro retard en frontière.',
    features: ['Déclarants douane agréés', 'Régimes suspensifs & transit', 'Paiement sécurisé des droits', 'Conformité documentaire strict'],
    icon: 'file-check'
  },
  {
    id: 'entreposage',
    title: 'Entreposage & Logistique du Dernier Borne',
    category: 'Logistique',
    description: 'Espaces d\'entreposage sécurisés 24/7 dans les 4 pays avec préparation de commandes et livraison à domicile.',
    features: ['Entrepôts sécurisés sous alarme', 'Gestion de stock informatisée', 'Livraison dernier kilomètre', 'Services de conditionnement'],
    icon: 'warehouse'
  }
];
