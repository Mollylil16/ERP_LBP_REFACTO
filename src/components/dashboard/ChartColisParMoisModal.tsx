import React, { useState, useRef } from 'react'
import { Modal, Typography, Table, Tag, Card, Row, Col, Statistic, Alert, Divider, Button, Space, message } from 'antd'
import {
  LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend,
  ResponsiveContainer, Area, AreaChart, Cell
} from 'recharts'
import {
  WarningOutlined, ArrowDownOutlined, ArrowUpOutlined,
  DollarOutlined, InboxOutlined, FilePdfOutlined, FileExcelOutlined
} from '@ant-design/icons'
import { formatMontantWithDevise } from '@utils/format'
import { exportChartToPDF, exportTableToExcel, exportMultiSheetToExcel } from '@utils/export'
import { svgToPng } from '@utils/export/chart'

const { Title, Text } = Typography

interface ChartData {
  mois: string
  groupage: number
  autresEnvois: number
  total: number
}

interface DropAnalysis {
  mois: string
  type: 'groupage' | 'autresEnvois' | 'total'
  valeurPrecedente: number
  valeurActuelle: number
  baisse: number
  pourcentageBaisse: number
  causes: string[]
  depenses?: number
  revenus?: number
}

interface ChartColisParMoisModalProps {
  visible: boolean
  onClose: () => void
  data: ChartData[]
  selectedPoint?: { mois: string; type: string; value: number }
}

// Fonction pour analyser les chutes dans les données
const analyzeDrops = (data: ChartData[]): DropAnalysis[] => {
  const drops: DropAnalysis[] = []

  for (let i = 1; i < data.length; i++) {
    const prev = data[i - 1]
    const current = data[i]

    // Analyser Groupage
    if (current.groupage < prev.groupage) {
      const baisse = prev.groupage - current.groupage
      const pourcentageBaisse = (baisse / prev.groupage) * 100

      drops.push({
        mois: current.mois,
        type: 'groupage',
        valeurPrecedente: prev.groupage,
        valeurActuelle: current.groupage,
        baisse,
        pourcentageBaisse: Math.round(pourcentageBaisse * 10) / 10,
        causes: generateCauses('groupage', pourcentageBaisse),
        depenses: calculateDepenses(baisse),
        revenus: calculateRevenus(baisse),
      })
    }

    // Analyser Autres Envois
    if (current.autresEnvois < prev.autresEnvois) {
      const baisse = prev.autresEnvois - current.autresEnvois
      const pourcentageBaisse = (baisse / prev.autresEnvois) * 100

      drops.push({
        mois: current.mois,
        type: 'autresEnvois',
        valeurPrecedente: prev.autresEnvois,
        valeurActuelle: current.autresEnvois,
        baisse,
        pourcentageBaisse: Math.round(pourcentageBaisse * 10) / 10,
        causes: generateCauses('autresEnvois', pourcentageBaisse),
        depenses: calculateDepenses(baisse),
        revenus: calculateRevenus(baisse),
      })
    }

    // Analyser Total
    if (current.total < prev.total) {
      const baisse = prev.total - current.total
      const pourcentageBaisse = (baisse / prev.total) * 100

      drops.push({
        mois: current.mois,
        type: 'total',
        valeurPrecedente: prev.total,
        valeurActuelle: current.total,
        baisse,
        pourcentageBaisse: Math.round(pourcentageBaisse * 10) / 10,
        causes: generateCauses('total', pourcentageBaisse),
        depenses: calculateDepenses(baisse),
        revenus: calculateRevenus(baisse),
      })
    }
  }

  return drops
}

// Générer les causes possibles selon le type et le pourcentage de baisse
const generateCauses = (type: string, pourcentageBaisse: number): string[] => {
  const causes: string[] = []

  if (pourcentageBaisse > 30) {
    causes.push('Baisse significative - Vérifier les problèmes opérationnels majeurs')
    causes.push('Augmentation des coûts de transport')
    causes.push('Problèmes de logistique ou de stockage')
  } else if (pourcentageBaisse > 15) {
    causes.push('Baisse modérée - Analyser les tendances du marché')
    causes.push('Concurrence accrue')
    causes.push('Saisonnalité des envois')
  } else {
    causes.push('Baisse légère - Variation normale')
    causes.push('Ajustement des prix ou des services')
  }

  if (type === 'groupage') {
    causes.push('Diminution des volumes de groupage')
    causes.push('Problèmes de consolidation')
  } else if (type === 'autresEnvois') {
    causes.push('Diminution des envois express')
    causes.push('Changement de stratégie client')
  }

  return causes
}

// Calculer les dépenses estimées liées à la baisse
const calculateDepenses = (baisse: number): number => {
  // Estimation : chaque colis perdu représente environ 50 000 FCFA de revenus
  return baisse * 50000
}

// Calculer les revenus perdus
const calculateRevenus = (baisse: number): number => {
  // Même calcul que les dépenses (revenus perdus)
  return baisse * 50000
}

export const ChartColisParMoisModal: React.FC<ChartColisParMoisModalProps> = ({
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

  const drops = analyzeDrops(data)
  const dropsForSelectedMonth = selectedMonth
    ? drops.filter(d => d.mois === selectedMonth)
    : drops

  const handleExportPDF = async () => {
    try {
      setExporting(true)
      if (chartRef.current) {
        const svgElement = chartRef.current.querySelector('svg') as SVGElement
        if (svgElement) {
          const imageData = await svgToPng(svgElement)
          exportChartToPDF(imageData, 'evolution_colis_mois', 'Évolution des Colis par Mois')
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
      // Export des données du graphique
      const tableData = {
        headers: ['Mois', 'Groupage', 'Autres Envois', 'Total'],
        rows: data.map(d => [d.mois, d.groupage, d.autresEnvois, d.total]),
      }

      // Si des chutes sont analysées, ajouter une feuille supplémentaire
      if (dropsForSelectedMonth.length > 0) {
        const dropsData = {
          headers: ['Mois', 'Type', 'Valeur Précédente', 'Valeur Actuelle', 'Baisse', '% Baisse'],
          rows: dropsForSelectedMonth.map(d => [
            d.mois,
            d.type,
            d.valeurPrecedente,
            d.valeurActuelle,
            d.baisse,
            `${d.pourcentageBaisse}%`
          ]),
        }

        await exportMultiSheetToExcel(
          [
            { name: 'Données Graphique', data: tableData },
            { name: 'Analyse des Chutes', data: dropsData },
          ],
          'evolution_colis_mois',
          { title: 'Évolution des Colis par Mois' }
        )
      } else {
        await exportTableToExcel(tableData, 'evolution_colis_mois', {
          title: 'Évolution des Colis par Mois',
        })
      }
      message.success('Export Excel réussi')
    } catch (error) {
      message.error('Erreur lors de l\'export Excel')
      console.error(error)
    } finally {
      setExporting(false)
    }
  }

  const columns = [
    {
      title: 'Mois',
      dataIndex: 'mois',
      key: 'mois',
      width: 100,
    },
    {
      title: 'Type',
      dataIndex: 'type',
      key: 'type',
      width: 120,
      render: (type: string) => {
        const colors: Record<string, string> = {
          groupage: 'purple',
          autresEnvois: 'green',
          total: 'orange',
        }
        const labels: Record<string, string> = {
          groupage: 'Groupage',
          autresEnvois: 'Autres Envois',
          total: 'Total',
        }
        return <Tag color={colors[type]}>{labels[type]}</Tag>
      },
    },
    {
      title: 'Valeur Précédente',
      dataIndex: 'valeurPrecedente',
      key: 'valeurPrecedente',
      width: 120,
      align: 'right' as const,
    },
    {
      title: 'Valeur Actuelle',
      dataIndex: 'valeurActuelle',
      key: 'valeurActuelle',
      width: 120,
      align: 'right' as const,
    },
    {
      title: 'Baisse',
      dataIndex: 'baisse',
      key: 'baisse',
      width: 100,
      align: 'right' as const,
      render: (baisse: number) => (
        <Text type="danger" strong>
          <ArrowDownOutlined /> {baisse}
        </Text>
      ),
    },
    {
      title: '% Baisse',
      dataIndex: 'pourcentageBaisse',
      key: 'pourcentageBaisse',
      width: 100,
      align: 'right' as const,
      render: (pourcentage: number) => (
        <Tag color={pourcentage > 30 ? 'red' : pourcentage > 15 ? 'orange' : 'default'}>
          {pourcentage}%
        </Tag>
      ),
    },
  ]

  const causeColumns = [
    {
      title: 'Cause',
      dataIndex: 'cause',
      key: 'cause',
    },
  ]

  return (
    <Modal
      title={
        <div>
          <Title level={4} style={{ margin: 0 }}>
            Détails - Évolution des Colis par Mois
          </Title>
          {selectedPoint && (
            <Text type="secondary">
              Point sélectionné : {selectedPoint.mois} - {selectedPoint.type} ({selectedPoint.value})
            </Text>
          )}
        </div>
      }
      open={visible}
      onCancel={onClose}
      width={1200}
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
        {/* Graphique détaillé */}
        <Card title="Graphique Détaillé" style={{ marginBottom: 24 }}>
          <div ref={chartRef}>
            <ResponsiveContainer width="100%" height={400}>
              <AreaChart data={data} margin={{ top: 10, right: 30, left: 0, bottom: 0 }}>
                <defs>
                  <linearGradient id="colorGroupage" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#667eea" stopOpacity={0.3} />
                    <stop offset="95%" stopColor="#667eea" stopOpacity={0} />
                  </linearGradient>
                  <linearGradient id="colorAutres" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#10b981" stopOpacity={0.3} />
                    <stop offset="95%" stopColor="#10b981" stopOpacity={0} />
                  </linearGradient>
                  <linearGradient id="colorTotal" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#f59e0b" stopOpacity={0.3} />
                    <stop offset="95%" stopColor="#f59e0b" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" opacity={0.5} />
                <XAxis dataKey="mois" stroke="#9ca3af" />
                <YAxis stroke="#9ca3af" />
                <Tooltip />
                <Legend />
                <Area
                  type="monotone"
                  dataKey="groupage"
                  stroke="#667eea"
                  strokeWidth={3}
                  fill="url(#colorGroupage)"
                  name="Groupage"
                  dot={{ fill: '#667eea', strokeWidth: 2, r: 5 }}
                  activeDot={{ r: 8, onClick: (e: any) => setSelectedMonth(e.payload.mois) }}
                />
                <Area
                  type="monotone"
                  dataKey="autresEnvois"
                  stroke="#10b981"
                  strokeWidth={3}
                  fill="url(#colorAutres)"
                  name="Autres Envois"
                  dot={{ fill: '#10b981', strokeWidth: 2, r: 5 }}
                  activeDot={{ r: 8, onClick: (e: any) => setSelectedMonth(e.payload.mois) }}
                />
                <Area
                  type="monotone"
                  dataKey="total"
                  stroke="#f59e0b"
                  strokeWidth={3}
                  fill="url(#colorTotal)"
                  name="Total"
                  dot={{ fill: '#f59e0b', strokeWidth: 2, r: 5 }}
                  activeDot={{ r: 8, onClick: (e: any) => setSelectedMonth(e.payload.mois) }}
                />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </Card>

        {/* Analyse des chutes */}
        {dropsForSelectedMonth.length > 0 && (
          <Card
            title={
              <div>
                <WarningOutlined style={{ color: '#ff4d4f', marginRight: 8 }} />
                Analyse des Chutes {selectedMonth ? `- ${selectedMonth}` : ''}
              </div>
            }
            style={{ marginBottom: 24 }}
          >
            <Table
              dataSource={dropsForSelectedMonth}
              columns={columns}
              rowKey={(record: any, index: number) => `${record.mois}-${record.type}-${index}`}
              pagination={false}
              size="small"
            />

            <Divider />

            {/* Détails des causes et impacts financiers */}
            {dropsForSelectedMonth.map((drop, index) => (
              <Card
                key={`${drop.mois}-${drop.type}-${index}`}
                type="inner"
                title={`Analyse détaillée - ${drop.mois} (${drop.type})`}
                style={{ marginBottom: 16 }}
              >
                <Row gutter={16}>
                  <Col xs={24} sm={12}>
                    <Statistic
                      title="Revenus Perdus"
                      value={drop.revenus || 0}
                      prefix={<DollarOutlined />}
                      formatter={(value: any) => formatMontantWithDevise(Number(value))}
                      valueStyle={{ color: '#ff4d4f' }}
                    />
                  </Col>
                  <Col xs={24} sm={12}>
                    <Statistic
                      title="Impact sur les Dépenses"
                      value={drop.depenses || 0}
                      prefix={<DollarOutlined />}
                      formatter={(value: any) => formatMontantWithDevise(Number(value))}
                      valueStyle={{ color: '#faad14' }}
                    />
                  </Col>
                </Row>

                <Divider style={{ margin: '16px 0' }} />

                <div>
                  <Text strong>Causes Probables :</Text>
                  <ul style={{ marginTop: 8, marginBottom: 0 }}>
                    {drop.causes.map((cause, i) => (
                      <li key={i} style={{ marginBottom: 4 }}>
                        <Text>{cause}</Text>
                      </li>
                    ))}
                  </ul>
                </div>

                {drop.pourcentageBaisse > 20 && (
                  <Alert
                    message="Attention : Baisse significative détectée"
                    description="Cette baisse importante nécessite une analyse approfondie et des actions correctives."
                    type="warning"
                    showIcon
                    style={{ marginTop: 16 }}
                  />
                )}
              </Card>
            ))}
          </Card>
        )}

        {dropsForSelectedMonth.length === 0 && (
          <Alert
            message="Aucune chute détectée"
            description="Les données montrent une évolution positive ou stable pour la période sélectionnée."
            type="success"
            showIcon
          />
        )}
      </div>
    </Modal>
  )
}
