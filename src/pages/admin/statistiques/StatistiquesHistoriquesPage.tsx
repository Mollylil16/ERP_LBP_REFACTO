import React, { useState } from 'react'
import { Card, Row, Col, Select, Typography, Space, DatePicker, Button, Alert } from 'antd'
import {
  BarChartOutlined,
  CalendarOutlined,
  DownloadOutlined,
  TrophyOutlined,
  WarningOutlined
} from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import { statistiquesService } from '@services/statistiques.service'
import { ComparaisonAnneesChart } from '@components/statistiques/ComparaisonAnneesChart'
import { ComparaisonAnneesBarChart } from '@components/statistiques/ComparaisonAnneesBarChart'
import { AnalyseTendancesMensuelles } from '@components/statistiques/AnalyseTendancesMensuelles'
import { ResumeComparaison } from '@components/statistiques/ResumeComparaison'
import { RecommandationsHistoriques } from '@components/statistiques/RecommandationsHistoriques'
import { exportTableToPDF, exportTableToExcel } from '@utils/export'
import { message } from 'antd'
import dayjs, { Dayjs } from 'dayjs'
import './StatistiquesHistoriquesPage.css'

const { Title, Text } = Typography
const { RangePicker } = DatePicker
const { Option } = Select

export const StatistiquesHistoriquesPage: React.FC = () => {
  const currentYear = new Date().getFullYear()
  const [selectedYears, setSelectedYears] = useState<number[]>([currentYear - 1, currentYear])
  const [dateRange, setDateRange] = useState<[Dayjs | null, Dayjs | null] | null>(null)

  // Récupérer les données historiques
  const { data: historiqueData, isLoading } = useQuery({
    queryKey: ['statistiques-historiques', selectedYears],
    queryFn: () => statistiquesService.getHistorique(selectedYears),
    enabled: selectedYears.length > 0,
  })

  // Récupérer l'analyse des tendances
  const { data: tendancesData } = useQuery({
    queryKey: ['statistiques-tendances', selectedYears],
    queryFn: () => statistiquesService.getTendancesMensuelles(selectedYears),
    enabled: selectedYears.length > 0,
  })

  const handleExportPDF = () => {
    if (!historiqueData) {
      message.warning('Aucune donnée à exporter')
      return
    }

    const exportData = {
      headers: ['Année', 'Mois', 'Colis Groupage', 'Colis Autres', 'Total Colis', 'Revenus'],
      rows: historiqueData.flatMap(yearData =>
        yearData.mois.map(month => [
          yearData.annee.toString(),
          month.mois,
          month.groupage.toString(),
          month.autresEnvois.toString(),
          month.total.toString(),
          month.revenus?.toLocaleString() || '0',
        ])
      ),
    }

    exportTableToPDF(exportData, 'statistiques_historiques', {
      title: `Statistiques Historiques - Comparaison ${selectedYears.join(' vs ')}`,
    })
    message.success('Export PDF réussi')
  }

  const handleExportExcel = async () => {
    if (!historiqueData) {
      message.warning('Aucune donnée à exporter')
      return
    }

    const exportData = {
      headers: ['Année', 'Mois', 'Colis Groupage', 'Colis Autres', 'Total Colis', 'Revenus'],
      rows: historiqueData.flatMap(yearData =>
        yearData.mois.map(month => [
          yearData.annee.toString(),
          month.mois,
          month.groupage.toString(),
          month.autresEnvois.toString(),
          month.total.toString(),
          month.revenus?.toLocaleString() || '0',
        ])
      ),
    }

    await exportTableToExcel(exportData, 'statistiques_historiques', {
      title: `Statistiques Historiques - Comparaison ${selectedYears.join(' vs ')}`,
    })
    message.success('Export Excel réussi')
  }

  // Générer les options d'années (5 dernières années)
  const availableYears = Array.from({ length: 5 }, (_, i) => currentYear - i)

  return (
    <div className="statistiques-historiques-page">
      <div className="page-header">
        <div>
          <Title level={2}>
            <BarChartOutlined /> Statistiques Historiques
          </Title>
          <Text type="secondary">
            Comparez les performances sur plusieurs années pour identifier les tendances et planifier l'avenir
          </Text>
        </div>
        <Space>
          <Button icon={<DownloadOutlined />} onClick={handleExportPDF}>
            PDF
          </Button>
          <Button type="primary" icon={<DownloadOutlined />} onClick={handleExportExcel}>
            Excel
          </Button>
        </Space>
      </div>

      {/* Filtres */}
      <Card style={{ marginBottom: 24 }}>
        <Row gutter={16} align="middle">
          <Col xs={24} sm={12} md={8}>
            <Text strong>Années à comparer :</Text>
            <Select
              mode="multiple"
              placeholder="Sélectionner les années"
              value={selectedYears}
              onChange={setSelectedYears}
              style={{ width: '100%', marginTop: 8 }}
              maxTagCount={2}
            >
              {availableYears.map(year => (
                <Option key={year} value={year}>
                  {year}
                </Option>
              ))}
            </Select>
          </Col>
          <Col xs={24} sm={12} md={8}>
            <Text strong>Période spécifique (optionnel) :</Text>
            <RangePicker
              style={{ width: '100%', marginTop: 8 }}
              format="DD/MM/YYYY"
              value={dateRange}
              onChange={setDateRange}
              placeholder={['Date début', 'Date fin']}
            />
          </Col>
          <Col xs={24} sm={24} md={8}>
            <Space>
              <Button
                icon={<CalendarOutlined />}
                onClick={() => setSelectedYears([currentYear - 1, currentYear])}
              >
                Dernière année
              </Button>
              <Button
                icon={<CalendarOutlined />}
                onClick={() => setSelectedYears([currentYear - 2, currentYear - 1, currentYear])}
              >
                3 dernières années
              </Button>
            </Space>
          </Col>
        </Row>
      </Card>

      {isLoading ? (
        <Card>
          <div style={{ textAlign: 'center', padding: '40px' }}>
            <Text>Chargement des données...</Text>
          </div>
        </Card>
      ) : historiqueData && historiqueData.length > 0 ? (
        <>
          {/* Résumé de la comparaison */}
          <ResumeComparaison data={historiqueData} />

          {/* Graphiques comparatifs - Histogrammes interactifs */}
          <Row gutter={[16, 16]} style={{ marginTop: 24 }}>
            <Col xs={24} lg={12}>
              <Card
                title="Évolution des Colis - Comparaison Années"
                extra={
                  <Text type="secondary" style={{ fontSize: 12 }}>
                    Cliquez sur les barres pour voir les détails
                  </Text>
                }
              >
                <ComparaisonAnneesBarChart
                  data={historiqueData}
                  type="colis"
                />
              </Card>
            </Col>
            <Col xs={24} lg={12}>
              <Card
                title="Évolution des Revenus - Comparaison Années"
                extra={
                  <Text type="secondary" style={{ fontSize: 12 }}>
                    Cliquez sur les barres pour voir les détails
                  </Text>
                }
              >
                <ComparaisonAnneesBarChart
                  data={historiqueData}
                  type="revenus"
                />
              </Card>
            </Col>
          </Row>

          {/* Graphiques en ligne (alternative) */}
          <Row gutter={[16, 16]} style={{ marginTop: 24 }}>
            <Col xs={24} lg={12}>
              <Card title="Tendances des Colis - Vue Ligne">
                <ComparaisonAnneesChart
                  data={historiqueData}
                  type="colis"
                />
              </Card>
            </Col>
            <Col xs={24} lg={12}>
              <Card title="Tendances des Revenus - Vue Ligne">
                <ComparaisonAnneesChart
                  data={historiqueData}
                  type="revenus"
                />
              </Card>
            </Col>
          </Row>

          {/* Analyse des tendances mensuelles */}
          {tendancesData && (
            <Row gutter={[16, 16]} style={{ marginTop: 24 }}>
              <Col xs={24}>
                <Card
                  title={
                    <Space>
                      <TrophyOutlined />
                      <span>Analyse des Tendances Mensuelles</span>
                    </Space>
                  }
                >
                  <AnalyseTendancesMensuelles data={tendancesData} />
                </Card>
              </Col>
            </Row>
          )}

          {/* Recommandations */}
          <Row gutter={[16, 16]} style={{ marginTop: 24 }}>
            <Col xs={24}>
              <Card
                title={
                  <Space>
                    <WarningOutlined />
                    <span>Recommandations Stratégiques</span>
                  </Space>
                }
              >
                <RecommandationsHistoriques />
              </Card>
            </Col>
          </Row>
        </>
      ) : (
        <Card>
          <Alert
            message="Aucune donnée disponible"
            description="Sélectionnez au moins une année pour afficher les statistiques historiques."
            type="info"
            showIcon
          />
        </Card>
      )}
    </div>
  )
}
