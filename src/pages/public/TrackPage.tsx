import React, { useState, useEffect } from 'react'
import { useParams } from 'react-router-dom'
import { Card, Grid, Input, Button, Typography, Space, Timeline, Empty, Descriptions, Tag, Divider, Alert } from 'antd'
import { SearchOutlined, EnvironmentOutlined, GlobalOutlined } from '@ant-design/icons'
import { colisService, ColisTrackingInfo } from '@services/colis.service'
import { formatDateTime } from '@utils/format'
import { MapContainer, TileLayer, Marker, Popup, useMap } from 'react-leaflet'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

// Fix Leaflet marker icon issue
// @ts-ignore
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png',
  iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
});

const { Title, Text } = Typography
const { Search } = Input

// Mock geocoding - Mapping cities to coordinates
const CITY_COORDS: Record<string, [number, number]> = {
  'Abidjan': [5.36, -4.0083],
  'Paris': [48.8566, 2.3522],
  'Dakar': [14.7167, -17.4677],
  'Cotonou': [6.3667, 2.4333],
  'Lomé': [6.1375, 1.2125],
  'Ouagadougou': [12.3714, -1.5197],
  'Bamako': [12.6392, -8.0029],
}

// Component to handle map centering
const ChangeView = ({ center }: { center: [number, number] }) => {
  const map = useMap()
  useEffect(() => {
    map.setView(center, 13)
  }, [center, map])
  return null
}

export const TrackPage: React.FC = () => {
  const [trackingCode, setTrackingCode] = useState('')
  const [trackingData, setTrackingData] = useState<ColisTrackingInfo | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const screens = Grid.useBreakpoint()

  const { ref: pathRef } = useParams<{ ref: string }>()

  // Auto-search if ref is in URL (params or path)
  useEffect(() => {
    // 1. Check path params (for HashRouter /#/track/REF)
    if (pathRef) {
      setTrackingCode(pathRef)
      handleSearch(pathRef)
      return
    }

    // 2. Fallback to query params (?ref=REF)
    const params = new URLSearchParams(window.location.search)
    const ref = params.get('ref')
    if (ref) {
      setTrackingCode(ref)
      handleSearch(ref)
    }
  }, [pathRef])

  const handleSearch = async (value: string) => {
    if (!value) return

    setLoading(true)
    setError(null)
    try {
      const data = await colisService.trackColis(value)
      setTrackingData(data)
    } catch (err: any) {
      console.error("Tracking error:", err)
      setError("Désolé, nous n'avons pas pu trouver de colis avec cette référence. Vérifiez le code et réessayez.")
      setTrackingData(null)
    } finally {
      setLoading(false)
    }
  }

  const destinationCoords = trackingData?.colis?.lieu_dest ? CITY_COORDS[trackingData.colis.lieu_dest] || [5.36, -4.0083] : [5.36, -4.0083];

  return (
    <div style={{ minHeight: '100vh', padding: '40px 20px', background: '#f8faff', fontFamily: 'Inter, sans-serif' }}>
      <div style={{ maxWidth: 1000, margin: '0 auto' }}>
        <div style={{ textAlign: 'center', marginBottom: 40 }}>
          <Title level={1} style={{ color: '#1890ff', marginBottom: 8, fontWeight: 800 }}>
            LBP LOGISTICS - SUIVI
          </Title>
          <Text type="secondary" style={{ fontSize: 16 }}>
            Suivez votre expédition en temps réel partout dans le monde
          </Text>
        </div>

        <Card style={{ marginBottom: 24, borderRadius: 12, boxShadow: '0 4px 12px rgba(0,0,0,0.05)', border: 'none' }}>
          <Search
            placeholder="Entrez votre numéro de référence (Ex: LBP-0124-001)"
            value={trackingCode}
            size="large"
            enterButton={
              <Button type="primary" icon={<SearchOutlined />} loading={loading} style={{ height: 40 }}>
                RECHERCHER
              </Button>
            }
            onSearch={handleSearch}
            onChange={(e: React.ChangeEvent<HTMLInputElement>) => setTrackingCode(e.target.value)}
          />
        </Card>

        {error && (
          <Alert
            message="Erreur de suivi"
            description={error}
            type="error"
            showIcon
            style={{ marginBottom: 24, borderRadius: 8 }}
          />
        )}

        {trackingData ? (
          <div style={{ display: 'grid', gridTemplateColumns: screens.md ? '1fr 1fr' : '1fr', gap: 24 }}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
              <Card title={<Space><SearchOutlined /> Informations Générales</Space>} style={{ borderRadius: 12, border: 'none', boxShadow: '0 4px 12px rgba(0,0,0,0.05)' }}>
                <Descriptions column={1} bordered size="small">
                  <Descriptions.Item label="Référence">{trackingData.ref_colis}</Descriptions.Item>
                  <Descriptions.Item label="Statut">
                    <Tag color={trackingData.status === 'Livré' ? 'green' : 'blue'}>
                      {trackingData.status.toUpperCase()}
                    </Tag>
                  </Descriptions.Item>
                  <Descriptions.Item label="Expéditeur">{trackingData.client_colis.nom_exp}</Descriptions.Item>
                  <Descriptions.Item label="Destination">{trackingData.colis.lieu_dest || 'N/A'}</Descriptions.Item>
                  <Descriptions.Item label="Paiement">
                    <Tag color={trackingData.payment_status === 'Payé' ? 'success' : 'warning'}>
                      {trackingData.payment_status}
                    </Tag>
                  </Descriptions.Item>
                </Descriptions>
              </Card>

              <Card title={<Space><EnvironmentOutlined /> Étapes du colis</Space>} style={{ borderRadius: 12, border: 'none', boxShadow: '0 4px 12px rgba(0,0,0,0.05)' }}>
                <Timeline
                  items={trackingData.steps.map((step, index) => ({
                    color: index === trackingData.steps.length - 1 ? 'blue' : 'green',
                    children: (
                      <div>
                        <Text strong style={{ fontSize: 13 }}>{step.title}</Text>
                        <br />
                        <Text type="secondary" style={{ fontSize: 12 }}>
                          {formatDateTime(step.date)} - {step.location || 'Agence LBP'}
                        </Text>
                      </div>
                    ),
                  }))}
                />
              </Card>
            </div>

            <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
              <Card
                title={<Space><GlobalOutlined /> Localisation de destination</Space>}
                style={{ borderRadius: 12, border: 'none', boxShadow: '0 4px 12px rgba(0,0,0,0.05)', overflow: 'hidden' }}
                bodyStyle={{ padding: 0 }}
              >
                <div style={{ height: screens.md ? 400 : 260, width: '100%' }}>
                  <MapContainer center={destinationCoords as [number, number]} zoom={13} style={{ height: '100%', width: '100%' }}>
                    <TileLayer
                      url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                      attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    />
                    <Marker position={destinationCoords as [number, number]}>
                      <Popup>
                        <b>Destination: {trackingData.colis.nom_dest}</b><br />
                        {trackingData.colis.lieu_dest}
                      </Popup>
                    </Marker>
                    <ChangeView center={destinationCoords as [number, number]} />
                  </MapContainer>
                </div>
                <div style={{ padding: 16 }}>
                  <Text type="secondary" style={{ fontSize: 12 }}>
                    <EnvironmentOutlined /> Adresse de livraison estimée: {trackingData.colis.lieu_dest || 'En attente de précision'}
                  </Text>
                </div>
              </Card>

              {trackingData.colis.adresse_recup && (
                <Card style={{ background: '#e6f7ff', borderRadius: 12, border: '1px solid #91d5ff' }}>
                  <Title level={5} style={{ marginBottom: 8 }}><EnvironmentOutlined /> Adresse de récupération</Title>
                  <Text>{trackingData.colis.adresse_recup}</Text>
                </Card>
              )}
            </div>
          </div>
        ) : !loading && (
          <Card style={{ borderRadius: 12, border: 'none', boxShadow: '0 4px 12px rgba(0,0,0,0.05)' }}>
            <Empty
              image={Empty.PRESENTED_IMAGE_SIMPLE}
              description="Entrez votre numéro de référence pour suivre votre colis"
            />
          </Card>
        )}
      </div>
    </div>
  )
}
