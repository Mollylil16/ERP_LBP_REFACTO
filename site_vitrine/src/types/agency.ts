export interface Agency {
  id: string;
  country: string;
  countryCode: string; // ISO 3166-1 numeric or alpha-2 (384: CI, 686: SN, 250: FR, 124: CA)
  city: string;
  hubName: string;
  region: 'Africa' | 'Europe' | 'NorthAmerica';
  coordinates: [number, number]; // [longitude, latitude]
  color: string;
  address: string;
  phone: string;
  email: string;
  stats: {
    monthlyVolume: string;
    avgCustomsTime: string;
    hubSurface: string;
  };
}

export interface RouteFlow {
  id: string;
  originId: string;
  destinationId: string;
  type: 'air' | 'sea';
  speedSeconds: number;
  label: string;
  frequency: string;
  color?: string;
}

export interface StatItem {
  id: string;
  label: string;
  value: string;
  unit?: string;
  change?: string;
  description: string;
}

export interface ServiceItem {
  id: string;
  title: string;
  category: string;
  description: string;
  features: string[];
  icon: string;
}
