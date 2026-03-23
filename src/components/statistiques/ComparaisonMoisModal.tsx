import React from 'react'
import { Modal, Typography, Table, Card, Row, Col, Statistic, Tag, Alert, Divider } from 'antd'
import {
  ArrowUpOutlined, ArrowDownOutlined, DollarOutlined,
  InboxOutlined, TrophyOutlined, WarningOutlined
} from '@ant-design/icons'
type RangePickerProps = any;
import { DonneesAnnuelles } from '@services/statistiques.service'
import { formatMontantWithDevise } from '@utils/format'
import './ComparaisonMoisModal.css'

const { Title, Text } = Typography

interface ComparaisonMoisModalProps {
  visible: boolean
  onClose: () => void
  mois: string | null
  data: DonneesAnnuelles[]
  type: 'colis' | 'revenus'
}

export const ComparaisonMoisModal: React.FC<ComparaisonMoisModalProps> = ({
  visible,
  onClose,
  mois,
  data,
  type,
}) => {
  if (!mois) return null

  // Récupérer les données pour ce mois pour toutes les années
  const moisData = data
    .map(yearData => {
      const moisInfo = yearData.mois.find(m => m.mois === mois)
      if (!moisInfo) return null
      return {
        annee: yearData.annee,
        groupage: moisInfo.groupage || 0,
        autresEnvois: moisInfo.autresEnvois || 0,
        total: moisInfo.total || 0,
        revenus: moisInfo.revenus || 0,
      }
    })
    .filter((item): item is NonNullable<typeof item> => item !== null)

  // Vérifier qu'on a des données
  if (moisData.length === 0) {
    return (
      <Modal
        title={`Détails - ${mois} (${type === 'colis' ? 'Colis' : 'Revenus'})`}
        open={visible}
        onCancel={onClose}
        footer={null}
      >
        <Alert
          message="Aucune donnée disponible"
          description={`Aucune donnée n'est disponible pour le mois de ${mois} sur les années sélectionnées.`}
          type="info"
        />
      </Modal>
    )
  }

  // Calculer les statistiques
  const valeurs = moisData.map(d => type === 'colis' ? (d.total || 0) : (d.revenus || 0)).filter((v): v is number => typeof v === 'number')

  if (valeurs.length === 0) {
    return null
  }

  const maxValeur = Math.max(...valeurs)
  const minValeur = Math.min(...valeurs)
  const maxIndex = valeurs.indexOf(maxValeur)
  const minIndex = valeurs.indexOf(minValeur)
  const meilleureAnnee = maxIndex >= 0 && maxIndex < moisData.length ? moisData[maxIndex] : null
  const pireAnnee = minIndex >= 0 && minIndex < moisData.length ? moisData[minIndex] : null
  const moyenne = valeurs.length > 0
    ? valeurs.reduce((sum, v) => sum + (v || 0), 0) / valeurs.length
    : 0
  const firstValue = valeurs[0]
  const lastValue = valeurs[valeurs.length - 1]
  const evolution = valeurs.length > 1 && firstValue !== undefined && lastValue !== undefined && firstValue > 0
    ? ((lastValue - firstValue) / firstValue) * 100
    : 0

  const columns = [
    {
      title: 'Année',
      dataIndex: 'annee',
      key: 'annee',
      width: 100,
      render: (annee: number, record: any) => {
        const isBest = record.annee === meilleureAnnee?.annee
        const isWorst = record.annee === pireAnnee?.annee
        return (
          <div>
            <Tag color={isBest ? 'green' : isWorst ? 'red' : 'default'}>
              {isBest && <TrophyOutlined />} {annee}
            </Tag>
          </div>
        )
      },
    },
    {
      title: 'Groupage',
      dataIndex: 'groupage',
      key: 'groupage',
      width: 120,
      align: 'right' as const,
      render: (value: number | undefined) => (value || 0).toLocaleString(),
    },
    {
      title: 'Autres Envois',
      dataIndex: 'autresEnvois',
      key: 'autresEnvois',
      width: 120,
      align: 'right' as const,
      render: (value: number | undefined) => (value || 0).toLocaleString(),
    },
    {
      title: type === 'colis' ? 'Total Colis' : 'Revenus',
      key: 'total',
      width: 150,
      align: 'right' as const,
      render: (_value: any, record: any) => {
        const value = type === 'colis' ? (record.total || 0) : (record.revenus || 0)
        const isBest = record.annee === meilleureAnnee?.annee
        const isWorst = record.annee === pireAnnee?.annee
        return (
          <Text strong style={{
            color: isBest ? '#52c41a' : isWorst ? '#ff4d4f' : undefined,
            fontSize: 16
          }}>
            {type === 'colis'
              ? `${value.toLocaleString()} colis`
              : formatMontantWithDevise(value)
            }
          </Text>
        )
      },
    },
    {
      title: 'Évolution',
      key: 'evolution',
      width: 150,
      render: (_value: any, record: any, index: number) => {
        if (index === 0) return <Text type="secondary">-</Text>
        const prevRecord = moisData[index - 1]
        if (!prevRecord) return <Text type="secondary">-</Text>

        const prevValue = type === 'colis'
          ? (prevRecord.total || 0)
          : (prevRecord.revenus || 0)
        const currentValue = type === 'colis'
          ? (record.total || 0)
          : (record.revenus || 0)
        const evolution = prevValue > 0
          ? ((currentValue - prevValue) / prevValue) * 100
          : 0
        const isPositive = evolution >= 0
        return (
          <Tag color={isPositive ? 'green' : 'red'} icon={isPositive ? <ArrowUpOutlined /> : <ArrowDownOutlined />}>
            {Math.abs(evolution).toFixed(1)}%
          </Tag>
        )
      },
    },
  ]

  return (
    <Modal
      title={
        <div>
          <Title level={4} style={{ margin: 0 }}>
            Détails - {mois} ({type === 'colis' ? 'Colis' : 'Revenus'})
          </Title>
          <Text type="secondary">
            Comparaison détaillée sur toutes les années
          </Text>
        </div>
      }
      open={visible}
      onCancel={onClose}
      width={1000}
      footer={null}
      style={{ top: 20 }}
    >
      <div style={{ maxHeight: '80vh', overflowY: 'auto' }}>
        {/* Statistiques résumées */}
        <Row gutter={16} style={{ marginBottom: 24 }}>
          <Col xs={24} sm={8}>
            <Card>
              <Statistic
                title="Meilleure Performance"
                value={type === 'colis' ? meilleureAnnee?.total : (meilleureAnnee?.revenus || 0)}
                prefix={type === 'colis' ? <InboxOutlined /> : <DollarOutlined />}
                suffix={type === 'colis' ? 'colis' : ''}
                valueStyle={{ color: '#52c41a', fontSize: 20 }}
                formatter={(value) => type === 'colis'
                  ? value.toLocaleString()
                  : formatMontantWithDevise(Number(value))
                }
              />
              <div style={{ marginTop: 8 }}>
                <Text type="secondary">Année {meilleureAnnee?.annee}</Text>
              </div>
            </Card>
          </Col>
          <Col xs={24} sm={8}>
            <Card>
              <Statistic
                title="Pire Performance"
                value={type === 'colis' ? pireAnnee?.total : (pireAnnee?.revenus || 0)}
                prefix={type === 'colis' ? <InboxOutlined /> : <DollarOutlined />}
                suffix={type === 'colis' ? 'colis' : ''}
                valueStyle={{ color: '#ff4d4f', fontSize: 20 }}
                formatter={(value) => type === 'colis'
                  ? value.toLocaleString()
                  : formatMontantWithDevise(Number(value))
                }
              />
              <div style={{ marginTop: 8 }}>
                <Text type="secondary">Année {pireAnnee?.annee}</Text>
              </div>
            </Card>
          </Col>
          <Col xs={24} sm={8}>
            <Card>
              <Statistic
                title="Moyenne"
                value={moyenne}
                prefix={type === 'colis' ? <InboxOutlined /> : <DollarOutlined />}
                suffix={type === 'colis' ? 'colis' : ''}
                valueStyle={{ fontSize: 20 }}
                formatter={(value: any) => type === 'colis'
                  ? Math.round(Number(value)).toLocaleString()
                  : formatMontantWithDevise(Math.round(Number(value)))
                }
              />
              <div style={{ marginTop: 8 }}>
                <Text type="secondary">
                  Évolution globale: {evolution >= 0 ? '+' : ''}{evolution.toFixed(1)}%
                </Text>
              </div>
            </Card>
          </Col>
        </Row>

        {/* Tableau détaillé */}
        <Card title="Comparaison par Année">
          <Table
            dataSource={moisData}
            columns={columns}
            rowKey="annee"
            pagination={false}
            size="small"
            rowClassName={(record) => {
              if (record.annee === meilleureAnnee?.annee) return 'row-success'
              if (record.annee === pireAnnee?.annee) return 'row-danger'
              return ''
            }}
          />
        </Card>

        {/* Analyse et recommandations */}
        <Card
          title={
            <div>
              <WarningOutlined style={{ color: '#1890ff', marginRight: 8 }} />
              Analyse et Recommandations
            </div>
          }
          style={{ marginTop: 24 }}
        >
          {evolution > 10 && (
            <Alert
              message="Tendance positive"
              description={`Le mois de ${mois} montre une évolution positive de ${evolution.toFixed(1)}% sur la période analysée. C'est un bon signe pour la planification future.`}
              type="success"
              showIcon
              style={{ marginBottom: 16 }}
            />
          )}
          {evolution < -10 && (
            <Alert
              message="Tendance négative"
              description={`Le mois de ${mois} montre une évolution négative de ${Math.abs(evolution).toFixed(1)}%. Il est recommandé d'analyser les causes et de mettre en place des actions correctives.`}
              type="warning"
              showIcon
              style={{ marginBottom: 16 }}
            />
          )}
          {Math.abs(evolution) <= 10 && (
            <Alert
              message="Performance stable"
              description={`Le mois de ${mois} montre une performance relativement stable. Maintenir les efforts actuels.`}
              type="info"
              showIcon
              style={{ marginBottom: 16 }}
            />
          )}

          <div>
            <Text strong>Recommandations :</Text>
            <ul style={{ marginTop: 8, marginBottom: 0 }}>
              {meilleureAnnee && (
                <li>
                  <Text>
                    L'année <strong>{meilleureAnnee.annee}</strong> a été la meilleure pour {mois}.
                    Analyser les facteurs de succès de cette année pour les reproduire.
                  </Text>
                </li>
              )}
              {pireAnnee && (
                <li>
                  <Text>
                    L'année <strong>{pireAnnee.annee}</strong> a été la plus faible pour {mois}.
                    Identifier les causes de cette baisse pour éviter qu'elle ne se reproduise.
                  </Text>
                </li>
              )}
              <li>
                <Text>
                  La moyenne sur toutes les années est de {type === 'colis'
                    ? `${Math.round(moyenne).toLocaleString()} colis`
                    : formatMontantWithDevise(Math.round(moyenne))
                  }. Utiliser cette référence pour fixer des objectifs réalistes.
                </Text>
              </li>
            </ul>
          </div>
        </Card>
      </div>
    </Modal>
  )
}
