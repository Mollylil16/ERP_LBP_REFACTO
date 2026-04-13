import React, { useState, useEffect, useRef } from 'react'
import { Card, Typography, Space, Tag, Input, Select, Empty, Badge, Tooltip } from 'antd'
import { GlobalOutlined, SearchOutlined, EnvironmentOutlined, TeamOutlined, WifiOutlined, DisconnectOutlined } from '@ant-design/icons'
import { MapContainer, TileLayer, Marker, Popup, Polyline, useMap } from 'react-leaflet'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { useColisList } from '@hooks/useColis'
import { Colis } from '@types'
import { formatDateTime } from '@utils/format'
import { io, Socket } from 'socket.io-client'

// Fix Leaflet marker icon issue
// @ts-ignore
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png',
    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
});

const { Title, Text } = Typography

// Coordonnées statiques des villes connues
const CITY_COORDS: Record<string, [number, number]> = {
    'Abidjan': [5.36, -4.0083],
    'Paris': [48.8566, 2.3522],
    'Dakar': [14.7167, -17.4677],
    'Cotonou': [6.3667, 2.4333],
    'Lomé': [6.1375, 1.2125],
    'Ouagadougou': [12.3714, -1.5197],
    'Bamako': [12.6392, -8.0029],
    'Bonoua': [5.273, -3.598],
    'Marseille': [43.2965, 5.3698],
    'Lyon': [45.7640, 4.8357],
}

// Icônes selon le type
const getMarkerIcon = (type: 'destination' | 'live' | 'live-stale') => {
    const colorMap = {
        'destination': 'blue',
        'live': 'green',
        'live-stale': 'orange',
    }
    return new L.Icon({
        iconUrl: `https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-${colorMap[type]}.png`,
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    })
}

// Pulse animée pour les marqueurs live
const LivePulseIcon = () => new L.DivIcon({
    className: '',
    html: `<div style="
        width:20px;height:20px;border-radius:50%;
        background:#52c41a;border:3px solid white;
        box-shadow:0 0 0 0 rgba(82,196,26,0.7);
        animation: pulse-green 1.5s infinite;
    "></div>
    <style>
    @keyframes pulse-green {
        0% { box-shadow: 0 0 0 0 rgba(82,196,26,0.7); }
        70% { box-shadow: 0 0 0 12px rgba(82,196,26,0); }
        100% { box-shadow: 0 0 0 0 rgba(82,196,26,0); }
    }
    </style>`,
    iconSize: [20, 20],
    iconAnchor: [10, 10],
});

interface LivePosition {
    tracker_id: string
    ref_colis: string
    latitude: number
    longitude: number
    vitesse?: number
    batterie?: number
    statut?: string
    timestamp_gps?: string
    updated_at?: string
}

const BACKEND_URL = import.meta.env.VITE_API_URL?.replace('/api', '') || 'http://localhost:3000'

export const ColisMapView: React.FC = () => {
    const [envoiType, setEnvoiType] = useState<'groupage' | 'autres_envoi'>('groupage')
    const { data: colisResponse } = useColisList(envoiType, { page: 1, limit: 100 })
    const [searchTerm, setSearchTerm] = useState('')
    const [statusFilter, setStatusFilter] = useState<number | null>(null)
    const [dynamicCoords, setDynamicCoords] = useState<Record<string, [number, number]>>({})

    // ── WebSocket : positions temps réel ──────────────────────────────────
    const [livePositions, setLivePositions] = useState<Record<string, LivePosition>>({})
    const [wsConnected, setWsConnected] = useState(false)
    const socketRef = useRef<Socket | null>(null)

    useEffect(() => {
        const token = sessionStorage.getItem('lbp_token') ?? localStorage.getItem('lbp_token')
        const authHeaders: HeadersInit = token
            ? { Authorization: `Bearer ${token}` }
            : {}

        // Charger les positions existantes au démarrage (JWT + droits colis côté API)
        fetch(`${BACKEND_URL}/tracking/live`, { headers: authHeaders })
            .then(r => r.json())
            .then((data: LivePosition[]) => {
                const map: Record<string, LivePosition> = {}
                data.forEach(p => { map[p.ref_colis || p.tracker_id] = p })
                setLivePositions(map)
            })
            .catch(() => { })

        // Connexion WebSocket — JWT exigé par le gateway
        const socket = io(`${BACKEND_URL}/tracking`, {
            transports: ['websocket'],
            reconnectionAttempts: 5,
            auth: token ? { token } : {},
        })
        socketRef.current = socket

        socket.on('connect', () => setWsConnected(true))
        socket.on('disconnect', () => setWsConnected(false))

        // Mise à jour temps réel : quand un traceur envoie une position
        socket.on('tracking:live', (pos: LivePosition) => {
            setLivePositions(prev => ({
                ...prev,
                [pos.ref_colis || pos.tracker_id]: {
                    ...pos,
                    updated_at: new Date().toISOString(),
                }
            }))
        })

        return () => { socket.disconnect() }
    }, [])

    const allColis = colisResponse?.data || []
    const filteredColis = allColis.filter((c: Colis) => {
        const matchesSearch = c.ref_colis.toLowerCase().includes(searchTerm.toLowerCase()) ||
            c.nom_dest?.toLowerCase().includes(searchTerm.toLowerCase())
        const matchesStatus = statusFilter === null || c.etat_validation === statusFilter
        return matchesSearch && matchesStatus
    })

    // Géocodage dynamique pour villes inconnues
    useEffect(() => {
        const toFetch = new Set<string>();
        filteredColis.forEach((c: Colis) => {
            if (c.lieu_dest && !CITY_COORDS[c.lieu_dest] && !dynamicCoords[c.lieu_dest]) {
                toFetch.add(c.lieu_dest);
            }
        });
        toFetch.forEach(async (loc) => {
            try {
                const r = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(loc)}`);
                const d = await r.json();
                if (d?.length > 0) {
                    setDynamicCoords(prev => ({ ...prev, [loc]: [parseFloat(d[0].lat), parseFloat(d[0].lon)] }));
                }
            } catch {
                void 0 // géocodage Nominatim optionnel : ignorer les erreurs réseau
            }
        });
    }, [filteredColis])

    // Vérifie si une position live est récente (< 10 minutes)
    const isRecent = (pos: LivePosition) => {
        if (!pos.updated_at) return false
        return (Date.now() - new Date(pos.updated_at).getTime()) < 10 * 60 * 1000
    }

    return (
        <div style={{ padding: '24px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                <Title level={2} style={{ margin: 0 }}>
                    <Space><GlobalOutlined /> Cartographie en temps réel</Space>
                </Title>
                <Space>
                    {/* Indicateur de connexion WebSocket */}
                    <Tooltip title={wsConnected ? 'Tracking en direct connecté' : 'Déconnecté du tracking en direct'}>
                        <Tag icon={wsConnected ? <WifiOutlined /> : <DisconnectOutlined />}
                            color={wsConnected ? 'success' : 'error'}>
                            {wsConnected ? 'Live' : 'Hors ligne'}
                        </Tag>
                    </Tooltip>
                    <Select value={envoiType} style={{ width: 150 }}
                        onChange={(v: 'groupage' | 'autres_envoi') => setEnvoiType(v)}>
                        <Select.Option value="groupage">Groupage</Select.Option>
                        <Select.Option value="autres_envoi">Autres Envois</Select.Option>
                    </Select>
                    <Input placeholder="Rechercher un colis..." prefix={<SearchOutlined />}
                        value={searchTerm} onChange={(e: React.ChangeEvent<HTMLInputElement>) => setSearchTerm(e.target.value)} style={{ width: 220 }} />
                    <Select placeholder="Statut" style={{ width: 130 }} allowClear
                        onChange={(v: number | undefined) => setStatusFilter(v === undefined ? null : v)}>
                        <Select.Option value={0}>Brouillon</Select.Option>
                        <Select.Option value={1}>Validé</Select.Option>
                    </Select>
                </Space>
            </div>

            {/* Légende */}
            <div style={{ display: 'flex', gap: 16, marginBottom: 12 }}>
                <Tag color="blue">🔵 Destination (statique)</Tag>
                <Tag color="green">🟢 Position traceur (temps réel)</Tag>
                <Tag color="orange">🟠 Traceur ({'>'}10 min)</Tag>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 350px', gap: 24, height: 'calc(100vh - 230px)' }}>
                {/* CARTE */}
                <Card bodyStyle={{ padding: 0, height: '100%' }} style={{ borderRadius: 12, overflow: 'hidden', height: '100%' }}>
                    <MapContainer center={[20, 0]} zoom={2} style={{ height: '100%', width: '100%', zIndex: 0 }}>
                        <TileLayer
                            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                        />

                        {/* MARQUEURS DESTINATION (bleus) */}
                        {filteredColis.map((c: Colis) => {
                            const coords = CITY_COORDS[c.lieu_dest || ''] || dynamicCoords[c.lieu_dest || '']
                            if (!coords || isNaN(coords[0])) return null
                            // Ne pas afficher le marqueur destination si un traceur live existe pour ce colis
                            const hasLive = !!livePositions[c.ref_colis]
                            if (hasLive) return null // Le marqueur live le remplace
                            return (
                                <Marker key={`dest-${c.id}`} position={coords} icon={getMarkerIcon('destination')}>
                                    <Popup>
                                        <Text strong>{c.ref_colis}</Text><br />
                                        <Text type="secondary">Destination : {c.lieu_dest}</Text><br />
                                        <Text type="secondary">Destinataire : {c.nom_dest}</Text><br />
                                        <Tag color={c.etat_validation === 1 ? 'green' : 'blue'}>
                                            {c.etat_validation === 1 ? 'Validé' : 'Brouillon'}
                                        </Tag>
                                        <br /><Text type="secondary" style={{ fontSize: 11 }}>⚠️ Position de destination (pas de traceur)</Text>
                                    </Popup>
                                </Marker>
                            )
                        })}

                        {/* MARQUEURS LIVE (verts/oranges) — positions réelles des traceurs */}
                        {Object.values(livePositions).map((pos) => {
                            const live = isRecent(pos)
                            const lat = Number(pos.latitude)
                            const lon = Number(pos.longitude)
                            if (isNaN(lat) || isNaN(lon)) return null
                            return (
                                <Marker key={`live-${pos.tracker_id}`}
                                    position={[lat, lon]}
                                    icon={live ? LivePulseIcon() : getMarkerIcon('live-stale')}>
                                    <Popup>
                                        <div style={{ minWidth: 200 }}>
                                            <Text strong style={{ fontSize: 15 }}>📦 {pos.ref_colis}</Text><br />
                                            <Tag color="green" style={{ marginTop: 4 }}>🛰️ Position GPS réelle</Tag><br />
                                            <div style={{ marginTop: 8, display: 'flex', flexDirection: 'column', gap: 3 }}>
                                                <Text type="secondary">Traceur : <b>{pos.tracker_id}</b></Text>
                                                <Text type="secondary">Statut : <b>{pos.statut || 'EN_TRANSIT'}</b></Text>
                                                {pos.vitesse !== undefined && <Text type="secondary">Vitesse : <b>{pos.vitesse} km/h</b></Text>}
                                                {pos.batterie !== undefined && <Text type="secondary">Batterie : <b>{pos.batterie}%</b></Text>}
                                                <Text type="secondary">Coordonnées : {lat.toFixed(4)}, {lon.toFixed(4)}</Text>
                                                {pos.timestamp_gps && <Text type="secondary" style={{ fontSize: 10 }}>
                                                    Dernière MAJ : {formatDateTime(pos.timestamp_gps)}
                                                </Text>}
                                            </div>
                                        </div>
                                    </Popup>
                                </Marker>
                            )
                        })}
                    </MapContainer>
                </Card>

                {/* LISTE LATÉRALE */}
                <Card title={<Space><EnvironmentOutlined /><span>Colis ({filteredColis.length})</span></Space>}
                    style={{ borderRadius: 12, overflowY: 'auto', height: '100%' }}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                        {filteredColis.map((c: Colis) => {
                            const livePos = livePositions[c.ref_colis]
                            return (
                                <Card key={c.id} bodyStyle={{ padding: 10 }} hoverable
                                    style={{ border: `1px solid ${livePos ? '#52c41a' : '#f0f0f0'}`, borderRadius: 8 }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6 }}>
                                        <Text strong>{c.ref_colis}</Text>
                                        <Space size={4}>
                                            {livePos && (
                                                <Tooltip title={`Traceur ${livePos.tracker_id} actif`}>
                                                    <Badge status={isRecent(livePos) ? 'processing' : 'warning'} />
                                                </Tooltip>
                                            )}
                                            <Tag color={c.etat_validation === 1 ? 'green' : 'blue'}>
                                                {c.etat_validation === 1 ? 'Validé' : 'Brouillon'}
                                            </Tag>
                                        </Space>
                                    </div>
                                    <div style={{ fontSize: 12, color: '#666' }}>
                                        <Space direction="vertical" size={2}>
                                            <Space>
                                                <EnvironmentOutlined style={{ color: '#1890ff' }} />
                                                <Text>{livePos ? `${Number(livePos.latitude).toFixed(4)}, ${Number(livePos.longitude).toFixed(4)}` : (c.lieu_dest || 'Non défini')}</Text>
                                            </Space>
                                            <Space>
                                                <TeamOutlined style={{ color: '#52c41a' }} />
                                                <Text type="secondary">{c.nom_dest}</Text>
                                            </Space>
                                            {livePos && (
                                                <Text style={{ color: '#52c41a', fontSize: 11 }}>
                                                    🛰️ GPS live · {livePos.statut}
                                                    {livePos.batterie !== undefined ? ` · 🔋${livePos.batterie}%` : ''}
                                                </Text>
                                            )}
                                        </Space>
                                    </div>
                                </Card>
                            )
                        })}
                        {filteredColis.length === 0 && <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Aucun colis trouvé" />}
                    </div>
                </Card>
            </div>
        </div>
    )
}
