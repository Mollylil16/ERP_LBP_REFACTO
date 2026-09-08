import React, { useState, useMemo } from 'react';
import { Agency, RouteFlow } from '../types/agency';
import { AGENCIES, ROUTE_FLOWS } from '../data/agencies';

interface AgencyFlowMapProps {
  agencies?: Agency[];
  routes?: RouteFlow[];
  activeAgencyId?: string;
  onAgencySelect?: (agency: Agency) => void;
  className?: string;
  theme?: 'light' | 'dark';
}

// Projections bounds for North America, Europe and West Africa
// Longitude: -85 to +15, Latitude: 0 to 55
const MAP_CONFIG = {
  minLng: -88,
  maxLng: 15,
  minLat: -2,
  maxLat: 56,
  width: 960,
  height: 520,
};

// Convert Lat/Lng to SVG [x, y] coordinates via Mercator formula
function projectCoordinates(lng: number, lat: number): [number, number] {
  const { minLng, maxLng, minLat, maxLat, width, height } = MAP_CONFIG;

  // Mercator Projection Math
  const latRad = (lat * Math.PI) / 180;
  const minLatRad = (minLat * Math.PI) / 180;
  const maxLatRad = (maxLat * Math.PI) / 180;

  const mercN = Math.log(Math.tan(Math.PI / 4 + latRad / 2));
  const minMercN = Math.log(Math.tan(Math.PI / 4 + minLatRad / 2));
  const maxMercN = Math.log(Math.tan(Math.PI / 4 + maxLatRad / 2));

  const x = ((lng - minLng) / (maxLng - minLng)) * width;
  const y = height - ((mercN - minMercN) / (maxMercN - minMercN)) * height;

  return [Math.round(x * 10) / 10, Math.round(y * 10) / 10];
}

// Calculate SVG Bezier curve control point for curved flight path
function getCurvePath(x1: number, y1: number, x2: number, y2: number, curvature = 0.25): string {
  const dx = x2 - x1;
  const dy = y2 - y1;
  const cx = (x1 + x2) / 2 - dy * curvature;
  const cy = (y1 + y2) / 2 + dx * curvature;
  return `M ${x1} ${y1} Q ${cx} ${cy} ${x2} ${y2}`;
}

export const AgencyFlowMap: React.FC<AgencyFlowMapProps> = ({
  agencies = AGENCIES,
  routes = ROUTE_FLOWS,
  activeAgencyId,
  onAgencySelect,
  className = '',
  theme = 'light',
}) => {
  const [hoveredAgency, setHoveredAgency] = useState<Agency | null>(null);
  const [hoveredRoute, setHoveredRoute] = useState<RouteFlow | null>(null);

  // Map agency ID to projected SVG coordinates
  const projectedAgencies = useMemo(() => {
    return agencies.map((ag) => {
      const [x, y] = projectCoordinates(ag.coordinates[0], ag.coordinates[1]);
      return { ...ag, x, y };
    });
  }, [agencies]);

  // Map route connections
  const projectedRoutes = useMemo(() => {
    const agencyMap = new Map(projectedAgencies.map((a) => [a.id, a]));
    return routes
      .map((r) => {
        const origin = agencyMap.get(r.originId);
        const dest = agencyMap.get(r.destinationId);
        if (!origin || !dest) return null;

        const pathD = getCurvePath(origin.x, origin.y, dest.x, dest.y, 0.22);
        return {
          ...r,
          origin,
          dest,
          pathD,
        };
      })
      .filter(Boolean);
  }, [projectedAgencies, routes]);

  const activeAgency = hoveredAgency || projectedAgencies.find((a) => a.id === activeAgencyId);

  return (
    <div
      className={`relative w-full rounded-2xl overflow-hidden shadow-xl border transition-colors duration-300 ${
        theme === 'dark'
          ? 'bg-slate-950 border-slate-800 text-white'
          : 'bg-[#FAF9F6] border-slate-200 text-slate-900'
      } ${className}`}
    >
      {/* Map Header Overlay */}
      <div className="absolute top-4 left-4 z-20 flex flex-wrap items-center gap-3 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md px-4 py-2.5 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-md">
        <div className="flex items-center gap-2">
          <span className="relative flex h-3 w-3">
            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
            <span className="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
          </span>
          <span className="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200">
            Réseau Transcontinental LBP
          </span>
        </div>
        <div className="h-4 w-px bg-slate-300 dark:bg-slate-700 hidden sm:block"></div>
        <div className="flex items-center gap-3 text-xs font-medium text-slate-500 dark:text-slate-400">
          <span className="flex items-center gap-1.5">
            <span className="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Afrique
          </span>
          <span className="flex items-center gap-1.5">
            <span className="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Europe
          </span>
          <span className="flex items-center gap-1.5">
            <span className="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Amériques
          </span>
        </div>
      </div>

      {/* SVG Container */}
      <div className="relative w-full aspect-[16/9] min-h-[380px] sm:min-h-[480px]">
        <svg
          viewBox={`0 0 ${MAP_CONFIG.width} ${MAP_CONFIG.height}`}
          className="w-full h-full object-cover select-none"
        >
          <defs>
            {/* Gradients for Flow Lines */}
            <linearGradient id="flowGradAfrica" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stopColor="#10B981" stopOpacity="0.8" />
              <stop offset="100%" stopColor="#3B82F6" stopOpacity="0.8" />
            </linearGradient>
            <linearGradient id="flowGradAtlantic" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stopColor="#3B82F6" stopOpacity="0.8" />
              <stop offset="100%" stopColor="#F43F5E" stopOpacity="0.8" />
            </linearGradient>
            
            {/* Glow Filters */}
            <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
              <feGaussianBlur stdDeviation="3" result="blur" />
              <feComposite in="SourceGraphic" in2="blur" operator="over" />
            </filter>
          </defs>

          {/* Grid Background Lines (Latitude / Longitude Grid) */}
          <g className="opacity-15 stroke-slate-400 dark:stroke-slate-700" strokeDasharray="3 3">
            {[100, 200, 300, 400, 500, 600, 700, 800, 900].map((x) => (
              <line key={`grid-x-${x}`} x1={x} y1="0" x2={x} y2={MAP_CONFIG.height} strokeWidth="0.8" />
            ))}
            {[80, 160, 240, 320, 400, 480].map((y) => (
              <line key={`grid-y-${y}`} x1="0" y1={y} x2={MAP_CONFIG.width} y2={y} strokeWidth="0.8" />
            ))}
          </g>

          {/* World Ocean / Land Silhouettes (Stylized Geodesic Map) */}
          <g className="fill-slate-200/60 dark:fill-slate-800/60 stroke-slate-300/80 dark:stroke-slate-700/80 stroke-[0.8]">
            {/* North America outline */}
            <path d="M 80,60 L 220,50 L 280,120 L 250,220 L 190,260 L 140,220 L 90,140 Z" className="transition-opacity duration-300 hover:fill-slate-300 dark:hover:fill-slate-700" />
            {/* Europe outline */}
            <path d="M 460,70 L 580,50 L 640,110 L 590,170 L 500,160 L 450,110 Z" className="transition-opacity duration-300 hover:fill-slate-300 dark:hover:fill-slate-700" />
            {/* Africa outline */}
            <path d="M 450,190 L 580,190 L 630,280 L 580,410 L 500,450 L 440,320 L 420,240 Z" className="transition-opacity duration-300 hover:fill-slate-300 dark:hover:fill-slate-700" />
          </g>

          {/* Highlighted Country Regions */}
          {/* Côte d'Ivoire & Sénégal (Africa Hubs) */}
          <path
            d="M 430,285 L 485,280 L 490,320 L 440,325 Z"
            fill="#10B981"
            fillOpacity={theme === 'dark' ? '0.25' : '0.18'}
            stroke="#10B981"
            strokeWidth="1.5"
            className="transition-all duration-300 hover:fill-opacity-35 cursor-pointer"
          />
          {/* France (Europe Hub) */}
          <path
            d="M 490,110 L 535,105 L 540,145 L 495,145 Z"
            fill="#3B82F6"
            fillOpacity={theme === 'dark' ? '0.25' : '0.18'}
            stroke="#3B82F6"
            strokeWidth="1.5"
            className="transition-all duration-300 hover:fill-opacity-35 cursor-pointer"
          />
          {/* Canada - Montreal Region (North America Hub) */}
          <path
            d="M 175,105 L 245,95 L 240,145 L 180,145 Z"
            fill="#F43F5E"
            fillOpacity={theme === 'dark' ? '0.25' : '0.18'}
            stroke="#F43F5E"
            strokeWidth="1.5"
            className="transition-all duration-300 hover:fill-opacity-35 cursor-pointer"
          />

          {/* Animated Route Flow Lines */}
          {projectedRoutes.map((route) => {
            if (!route) return null;
            const isHovered = hoveredRoute?.id === route.id;
            const strokeColor = route.origin.region === 'Africa' ? '#10B981' : '#3B82F6';

            return (
              <g key={route.id} className="cursor-pointer" onMouseEnter={() => setHoveredRoute(route)} onMouseLeave={() => setHoveredRoute(null)}>
                {/* Background Route Path */}
                <path
                  d={route.pathD}
                  fill="none"
                  stroke={strokeColor}
                  strokeWidth={isHovered ? 3.5 : 2}
                  strokeOpacity={isHovered ? 0.9 : 0.45}
                  strokeDasharray="6 4"
                />

                {/* Animated Moving Glowing Cargo Pulse Dot */}
                <circle r={isHovered ? 6 : 4} fill={strokeColor} filter="url(#glow)">
                  <animateMotion
                    path={route.pathD}
                    dur={`${route.speedSeconds}s`}
                    repeatCount="indefinite"
                    rotate="auto"
                  />
                </circle>
              </g>
            );
          })}

          {/* Agency Hub Markers */}
          {projectedAgencies.map((agency) => {
            const isSelected = activeAgency?.id === agency.id;
            const isHovered = hoveredAgency?.id === agency.id;

            return (
              <g
                key={agency.id}
                transform={`translate(${agency.x}, ${agency.y})`}
                className="cursor-pointer group"
                onClick={() => onAgencySelect?.(agency)}
                onMouseEnter={() => setHoveredAgency(agency)}
                onMouseLeave={() => setHoveredAgency(null)}
              >
                {/* Outer Ripple Pulse Circle */}
                <circle
                  r={isSelected || isHovered ? 24 : 16}
                  fill={agency.color}
                  fillOpacity="0.25"
                  className="animate-ping transition-all duration-300"
                />

                {/* Outer Halo */}
                <circle
                  r={isSelected || isHovered ? 14 : 10}
                  fill={agency.color}
                  fillOpacity="0.35"
                  className="transition-all duration-300"
                />

                {/* Core Hub Dot */}
                <circle
                  r={isSelected || isHovered ? 7 : 5}
                  fill={agency.color}
                  stroke="#FFFFFF"
                  strokeWidth="2"
                  className="shadow-lg transition-all duration-300 group-hover:scale-125"
                />

                {/* City Label Tag */}
                <g transform="translate(0, -16)">
                  <rect
                    x="-42"
                    y="-18"
                    width="84"
                    height="22"
                    rx="6"
                    fill={theme === 'dark' ? '#0F172A' : '#FFFFFF'}
                    stroke={agency.color}
                    strokeWidth="1.5"
                    className="shadow-md transition-all duration-300"
                  />
                  <text
                    x="0"
                    y="-4"
                    textAnchor="middle"
                    fill={theme === 'dark' ? '#F8FAFC' : '#0F172A'}
                    fontSize="10"
                    fontWeight="800"
                    className="tracking-wide"
                  >
                    {agency.city.toUpperCase()}
                  </text>
                </g>
              </g>
            );
          })}
        </svg>

        {/* Hover / Selected Agency Detailed Card Overlay */}
        {activeAgency && (
          <div className="absolute bottom-4 left-4 right-4 sm:right-auto sm:max-w-sm z-30 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl transition-all duration-300 animate-in fade-in slide-in-from-bottom-3">
            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5 mb-2.5">
              <div className="flex items-center gap-2">
                <span
                  className="w-3 h-3 rounded-full"
                  style={{ backgroundColor: activeAgency.color }}
                ></span>
                <h4 className="font-extrabold text-sm text-slate-900 dark:text-white">
                  {activeAgency.city} — {activeAgency.country}
                </h4>
              </div>
              <span className="text-[10px] font-bold uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 px-2 py-0.5 rounded-full">
                Hub Actif
              </span>
            </div>

            <p className="text-xs text-slate-500 dark:text-slate-400 mb-3 font-medium">
              {activeAgency.hubName}
            </p>

            <div className="grid grid-cols-3 gap-2 text-center bg-slate-50 dark:bg-slate-800/60 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800 mb-3">
              <div>
                <span className="block text-[10px] uppercase text-slate-400 font-semibold">Volume</span>
                <span className="text-xs font-bold text-slate-800 dark:text-slate-200">{activeAgency.stats.monthlyVolume.split(' ')[0]}</span>
              </div>
              <div className="border-x border-slate-200 dark:border-slate-700">
                <span className="block text-[10px] uppercase text-slate-400 font-semibold">Douane</span>
                <span className="text-xs font-bold text-emerald-600 dark:text-emerald-400">{activeAgency.stats.avgCustomsTime}</span>
              </div>
              <div>
                <span className="block text-[10px] uppercase text-slate-400 font-semibold">Entrepôt</span>
                <span className="text-xs font-bold text-slate-800 dark:text-slate-200">{activeAgency.stats.hubSurface.split(' ')[0]} m²</span>
              </div>
            </div>

            <div className="text-[11px] text-slate-600 dark:text-slate-300 space-y-1">
              <div className="flex items-center gap-1.5">
                <svg className="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span className="truncate">{activeAgency.address}</span>
              </div>
              <div className="flex items-center gap-1.5">
                <svg className="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 me 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                <span>{activeAgency.phone}</span>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Mobile Responsive Agency Selector Tabs */}
      <div className="p-4 bg-slate-50 dark:bg-slate-900/80 border-t border-slate-200 dark:border-slate-800 grid grid-cols-2 sm:grid-cols-4 gap-2">
        {projectedAgencies.map((ag) => (
          <button
            key={`btn-${ag.id}`}
            onClick={() => {
              setHoveredAgency(ag);
              onAgencySelect?.(ag);
            }}
            className={`flex items-center gap-2 p-2.5 rounded-xl border text-left transition-all ${
              activeAgency?.id === ag.id
                ? 'bg-white dark:bg-slate-800 border-slate-400 dark:border-slate-600 shadow-sm font-bold'
                : 'bg-white/60 dark:bg-slate-950/60 border-slate-200/60 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-white'
            }`}
          >
            <span className="w-2.5 h-2.5 rounded-full flex-shrink-0" style={{ backgroundColor: ag.color }}></span>
            <div className="truncate">
              <span className="block text-xs font-bold text-slate-900 dark:text-white leading-none mb-0.5">{ag.city}</span>
              <span className="block text-[10px] text-slate-500 truncate">{ag.country}</span>
            </div>
          </button>
        ))}
      </div>
    </div>
  );
};
