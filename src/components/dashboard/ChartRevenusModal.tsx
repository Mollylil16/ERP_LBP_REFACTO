import React, { useState, useRef } from 'react'
import { Modal, Typography, Table, Tag, Card, Row, Col, Statistic, Alert, Progress, Divider, Button, Space, message } from 'antd'
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts'
import {
  DollarOutlined, ArrowUpOutlined, ArrowDownOutlined,
  CheckCircleOutlined, WarningOutlined, TrophyOutlined, FilePdfOutlined, FileExcelOutlined
} from '@ant-design/icons'
import { formatMontantWithDevise } from '@utils/format'
import { exportChartToPDF, exportMultiSheetToExcel } from '@utils/export'
import { svgToPng } from '@utils/export/chart'

const { Title, Text } = Typography

interface ChartData {
  mois: string
  revenus: number
  objectif: number
}

interface PerformanceAnalysis {
  mois: string
  revenus: number
  objectif: number
  ecart: number
  pourcentageAtteint: number
  status: 'succes' | 'echec' | 'partiel'
  causes?: string[]
  recommandations?: string[]
}

interface ChartRevenusModalProps {
  visible: boolean
  onClose: () => void
  data: ChartData[]
  selectedPoint?: { mois: string; type: string; value: number }
}

// Analyser les performances par rapport aux objectifs
const analyzePerformance = (data: ChartData[]): PerformanceAnalysis[] => {
  return data.map(item => {
    const ecart = item.revenus - item.objectif
    const pourcentageAtteint = item.objectif > 0 ? (item.revenus / item.objectif) * 100 : 0
    const status = pourcentageAtteint >= 100 ? 'succes' : pourcentageAtteint >= 80 ? 'partiel' : 'echec'

    const causes: string[] = []
    const recommandations: string[] = []

    if (status === 'echec') {
      causes.push(`Revenus inférieurs de ${formatMontantWithDevise(Math.abs(ecart))} par rapport à l'objectif`)
      causes.push('Baisse de la demande client')
      causes.push('Problèmes opérationnels ou logistiques')
      causes.push('Concurrence accrue ou perte de clients')

      recommandations.push('Revoir la stratégie commerciale')
      recommandations.push('Améliorer la qualité de service')
      recommandations.push('Lancer des campagnes marketing ciblées')
      recommandations.push('Analyser les raisons du manque de performance')
    } else if (status === 'partiel') {
      causes.push(`Revenus proches de l'objectif mais pas atteints`)
      causes.push('Besoin d\'un petit effort supplémentaire')

      recommandations.push('Maintenir l\'effort actuel')
      recommandations.push('Identifier les opportunités d\'amélioration')
    } else {
      causes.push('Objectif atteint ou dépassé')
      recommandations.push('Maintenir cette performance')
      recommandations.push('Envisager d\'augmenter les objectifs futurs')
    }

    return {
      mois: item.mois,
      revenus: item.revenus,
      objectif: item.objectif,
      ecart,
      pourcentageAtteint: Math.round(pourcentageAtteint * 10) / 10,
      status,
      causes,
      recommandations,
    }
  })
}

export const ChartRevenusModal: React.FC<ChartRevenusModalProps> = ({
  visible,
  onClose,
  data,
  selectedPoint,
}) => {
  const [selectedMonth, setSelectedMonth] = useState<string | null>(
    selectedPoint?.mois || null
  )
  const [exporting, setExporting] = useState(false)
  const chartRef = useRef<HTMLDivElement>(null)

  const performances = analyzePerformance(data)
  const performancesForSelectedMonth = selectedMonth
    ? performances.filter(p => p.mois === selectedMonth)
    : performances

  const totalRevenus = data.reduce((sum, item) => sum + item.revenus, 0)
  const totalObjectif = data.reduce((sum, item) => sum + item.objectif, 0)
  const performanceGlobale = totalObjectif > 0 ? (totalRevenus / totalObjectif) * 100 : 0

  const columns = [
    {
      title: 'Mois',
      dataIndex: 'mois',
      key: 'mois',
      width: 100,
    },
    {
      title: 'Revenus Réels',
      dataIndex: 'revenus',
      key: 'revenus',
      width: 150,
      align: 'right' as const,
      render: (revenus: number) => (
        <Text strong>{formatMontantWithDevise(revenus)}</Text>
      ),
    },
    {
      title: 'Objectif',
      dataIndex: 'objectif',
      key: 'objectif',
      width: 150,
      align: 'right' as const,
      render: (objectif: number) => (
        <Text>{formatMontantWithDevise(objectif)}</Text>
      ),
    },
    {
      title: 'Écart',
      dataIndex: 'ecart',
      key: 'ecart',
      width: 150,
      align: 'right' as const,
      render: (ecart: number) => {
        const isPositive = ecart >= 0
        return (
          <Text strong style={{ color: isPositive ? '#52c41a' : '#ff4d4f' }}>
            {isPositive ? <ArrowUpOutlined /> : <ArrowDownOutlined />}
            {' '}
            {formatMontantWithDevise(Math.abs(ecart))}
          </Text>
        )
      },
    },
    {
      title: '% Atteint',
      dataIndex: 'pourcentageAtteint',
      key: 'pourcentageAtteint',
      width: 150,
      align: 'right' as const,
      render: (pourcentage: number, record: PerformanceAnalysis) => (
        <div>
          <Progress
            percent={Math.min(pourcentage, 100)}
            status={record.status === 'succes' ? 'success' : record.status === 'echec' ? 'exception' : 'active'}
            format={() => `${pourcentage}%`}
          />
        </div>
      ),
    },
    {
      title: 'Statut',
      dataIndex: 'status',
      key: 'status',
      width: 120,
      render: (status: string) => {
        const config: Record<string, { color: string; label: string; icon: any }> = {
          succes: { color: 'green', label: 'Objectif atteint', icon: <CheckCircleOutlined /> },
          echec: { color: 'red', label: 'Non atteint', icon: <WarningOutlined /> },
          partiel: { color: 'orange', label: 'Partiellement', icon: <TrophyOutlined /> },
        }
        const conf = config[status] || config.echec
        return (
          <Tag color={conf.color} icon={conf.icon}>
            {conf.label}
          </Tag>
        )
      },
    },
  ]

  const handleExportPDF = async () => {
    try {
      setExporting(true)
      if (chartRef.current) {
        const svgElement = chartRef.current.querySelector('svg') as SVGElement
        if (svgElement) {
          const imageData = await svgToPng(svgElement)
          exportChartToPDF(imageData, 'evolution_revenus', 'Évolution des Revenus par Mois')
          message.success('Export PDF réussi')
        }
      }
    } catch (error) {
      message.error('Erreur lors de l\'export PDF')
      console.error(error)
    } finally {
      setExporting(false)
    }
  }

  const handleExportExcel = async () => {
    try {
      setExporting(true)
      const sheets = [
        {
          name: 'Données Graphique',
          data: {
            headers: ['Mois', 'Revenus Réels', 'Objectif', 'Écart', '% Atteint'],
            rows: performances.map(p => [
              p.mois,
              formatMontantWithDevise(p.revenus),
              formatMontantWithDevise(p.objectif),
              formatMontantWithDevise(p.ecart),
              `${p.pourcentageAtteint}%`,
            ]),
          },
        },
        {
          name: 'Analyse Performances',
          data: {
            headers: ['Mois', 'Statut', 'Causes Probables', 'Recommandations'],
            rows: performances.map(p => [
              p.mois,
              p.status === 'succes' ? 'Objectif atteint' : p.status === 'echec' ? 'Non atteint' : 'Partiellement',
              p.causes?.join('; ') || '',
              p.recommandations?.join('; ') || '',
            ]),
          },
        },
      ]

      await exportMultiSheetToExcel(sheets, 'evolution_revenus', {
        title: 'Évolution des Revenus par Mois',
      })
      message.success('Export Excel réussi')
    } catch (error) {
      message.error('Erreur lors de l\'export Excel')
      console.error(error)
    } finally {
      setExporting(false)
    }
  }

  return (
    <Modal
      title={
        <div>
          <Title level={4} style={{ margin: 0 }}>
            Détails - Évolution des Revenus par Mois
          </Title>
          {selectedPoint && (
            <Text type="secondary">
              Point sélectionné : {selectedPoint.mois} - {selectedPoint.type} ({formatMontantWithDevise(selectedPoint.value)})
            </Text>
          )}
        </div>
      }
      open={visible}
      onCancel={onClose}
      width={1400}
      footer={
        <Space>
          <Button onClick={onClose}>Fermer</Button>
          <Button
            icon={<FilePdfOutlined />}
            onClick={handleExportPDF}
            loading={exporting}
          >
            Exporter PDF
          </Button>
          <Button
            type="primary"
            icon={<FileExcelOutlined />}
            onClick={handleExportExcel}
            loading={exporting}
          >
            Exporter Excel
          </Button>
        </Space>
      }
      style={{ top: 20 }}
    >
      <div style={{ maxHeight: '80vh', overflowY: 'auto' }}>
        {/* Résumé global */}
        <Row gutter={16} style={{ marginBottom: 24 }}>
          <Col xs={24} sm={8}>
            <Card>
              <Statistic
                title="Total Revenus"
                value={totalRevenus}
                prefix={<DollarOutlined />}
                valueStyle={{ color: '#52c41a' }}
                formatter={(value: any) => formatMontantWithDevise(Number(value))}
              />
            </Card>
          </Col>
          <Col xs={24} sm={8}>
            <Card>
              <Statistic
                title="Total Objectif"
                value={totalObjectif}
                prefix={<TrophyOutlined />}
                valueStyle={{ color: '#1890ff' }}
                formatter={(value: any) => formatMontantWithDevise(Number(value))}
              />
            </Card>
          </Col>
          <Col xs={24} sm={8}>
            <Card>
              <Statistic
                title="Performance Globale"
                value={performanceGlobale}
                suffix="%"
                valueStyle={{
                  color: performanceGlobale >= 100 ? '#52c41a' : performanceGlobale >= 80 ? '#faad14' : '#ff4d4f',
                  fontSize: 24
                }}
              />
              <Progress
                percent={Math.min(performanceGlobale, 100)}
                status={performanceGlobale >= 100 ? 'success' : performanceGlobale >= 80 ? 'active' : 'exception'}
                style={{ marginTop: 8 }}
              />
            </Card>
          </Col>
        </Row>

        {/* Graphique détaillé */}
        <Card title="Graphique Détaillé" style={{ marginBottom: 24 }}>
          <div ref={chartRef}>
            <ResponsiveContainer width="100%" height={400}>
              <BarChart data={data} margin={{ top: 10, right: 30, left: 0, bottom: 0 }}>
                <defs>
                  <linearGradient id="colorRevenus" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#10b981" stopOpacity={0.8} />
                    <stop offset="95%" stopColor="#10b981" stopOpacity={0.3} />
                  </linearGradient>
                  <linearGradient id="colorObjectif" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.8} />
                    <stop offset="95%" stopColor="#3b82f6" stopOpacity={0.3} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" opacity={0.5} />
                <XAxis dataKey="mois" stroke="#9ca3af" />
                <YAxis
                  stroke="#9ca3af"
                  tickFormatter={(value) => `${(value / 1000000).toFixed(0)}M`}
                />
                <Tooltip
                  formatter={(value: number) => formatMontantWithDevise(value)}
                />
                <Legend />
                <Bar
                  dataKey="revenus"
                  fill="url(#colorRevenus)"
                  name="Revenus réels"
                  radius={[8, 8, 0, 0]}
                  onClick={(e: any) => setSelectedMonth(e.mois)}
                  cursor="pointer"
                />
                <Bar
                  dataKey="objectif"
                  fill="url(#colorObjectif)"
                  name="Objectif"
                  radius={[8, 8, 0, 0]}
                />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </Card>

        {/* Analyse des performances */}
        <Card
          title={
            <div>
              <WarningOutlined style={{ color: '#1890ff', marginRight: 8 }} />
              Analyse des Performances {selectedMonth ? `- ${selectedMonth}` : ''}
            </div>
          }
          style={{ marginBottom: 24 }}
        >
          <Table
            dataSource={performancesForSelectedMonth}
            columns={columns}
            rowKey={(record: any) => record.mois}
            pagination={false}
            size="small"
          />

          <Divider />

          {/* Détails par mois */}
          {performancesForSelectedMonth.map((performance, index) => (
            <Card
              key={`${performance.mois}-${index}`}
              type="inner"
              title={`Analyse détaillée - ${performance.mois}`}
              style={{ marginBottom: 16 }}
            >
              <Row gutter={16}>
                <Col xs={24} sm={12}>
                  <Statistic
                    title="Revenus Réalisés"
                    value={performance.revenus}
                    prefix={<DollarOutlined />}
                    formatter={(value: any) => formatMontantWithDevise(Number(value))}
                    valueStyle={{ color: '#52c41a' }}
                  />
                </Col>
                <Col xs={24} sm={12}>
                  <Statistic
                    title="Objectif"
                    value={performance.objectif}
                    prefix={<TrophyOutlined />}
                    formatter={(value: any) => formatMontantWithDevise(Number(value))}
                    valueStyle={{ color: '#1890ff' }}
                  />
                </Col>
              </Row>

              <Divider style={{ margin: '16px 0' }} />

              {performance.ecart < 0 && (
                <Alert
                  message={`Écart négatif de ${formatMontantWithDevise(Math.abs(performance.ecart))}`}
                  description="Les revenus sont inférieurs à l'objectif pour ce mois."
                  type="warning"
                  showIcon
                  style={{ marginBottom: 16 }}
                />
              )}

              {performance.ecart >= 0 && (
                <Alert
                  message={`Dépassement de ${formatMontantWithDevise(performance.ecart)}`}
                  description="Excellent ! L'objectif a été atteint ou dépassé."
                  type="success"
                  showIcon
                  style={{ marginBottom: 16 }}
                />
              )}

              <div>
                <Text strong>Causes Probables :</Text>
                <ul style={{ marginTop: 8, marginBottom: 16 }}>
                  {performance.causes?.map((cause, i) => (
                    <li key={i} style={{ marginBottom: 4 }}>
                      <Text>{cause}</Text>
                    </li>
                  ))}
                </ul>
              </div>

              <div>
                <Text strong>Recommandations :</Text>
                <ul style={{ marginTop: 8, marginBottom: 0 }}>
                  {performance.recommandations?.map((rec, i) => (
                    <li key={i} style={{ marginBottom: 4 }}>
                      <Text>{rec}</Text>
                    </li>
                  ))}
                </ul>
              </div>
            </Card>
          ))}
        </Card>
      </div>
    </Modal>
  )
}
