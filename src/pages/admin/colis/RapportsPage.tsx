import React, { useState } from 'react'
import {
  Typography,
  Card,
  Row,
  Col,
  Form,
  DatePicker,
  Select,
  Button,
  Space,
  Tabs,
} from 'antd'
import {
  SearchOutlined,
  FileExcelOutlined,
  FilePdfOutlined,
  BarChartOutlined,
} from '@ant-design/icons'
const { RangePicker } = DatePicker
import dayjs, { Dayjs } from 'dayjs'
import { rapportsService } from '@services/rapports.service'
import { ChartColisParMois } from '@components/dashboard/ChartColisParMois'
import { ChartRepartitionTrafic } from '@components/dashboard/ChartRepartitionTrafic'
import { ChartRevenus } from '@components/dashboard/ChartRevenus'
import toast from 'react-hot-toast'
import { useQuery } from '@tanstack/react-query'
import { APP_CONFIG } from '@constants/application'

const { Title } = Typography
const { Option } = Select

export const ColisRapportsPage: React.FC = () => {
  const [form] = Form.useForm()
  const [reportParams, setReportParams] = useState<any>(null)
  const [loading, setLoading] = useState(false)

  const handleGenerateReport = async (values: any) => {
    setLoading(true)
    try {
      const params = {
        start_date: values.dateRange[0].format('YYYY-MM-DD'),
        end_date: values.dateRange[1].format('YYYY-MM-DD'),
        trafic_envoi: values.trafic_envoi,
        mode_envoi: values.mode_envoi,
        forme_envoi: values.forme_envoi,
        client_id: values.client_id,
      }

      setReportParams(params)

      // Générer le rapport via l'API
      const reportData = await rapportsService.generateRapportColis(params)
      console.log('Report data:', reportData)

      toast.success('Rapport généré avec succès')
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Erreur lors de la génération du rapport')
    } finally {
      setLoading(false)
    }
  }

  const handleExportExcel = async () => {
    if (!reportParams) {
      toast.error('Veuillez d\'abord générer un rapport')
      return
    }

    setLoading(true)
    try {
      await rapportsService.downloadRapportExcel(reportParams)
      toast.success('Rapport Excel téléchargé avec succès')
    } catch (error: any) {
      toast.error('Erreur lors de l\'export Excel')
    } finally {
      setLoading(false)
    }
  }

  const handleExportPDF = async () => {
    if (!reportParams) {
      toast.error('Veuillez d\'abord générer un rapport')
      return
    }

    setLoading(true)
    try {
      await rapportsService.downloadRapportPDF(reportParams)
      toast.success('Rapport PDF téléchargé avec succès')
    } catch (error: any) {
      toast.error('Erreur lors de l\'export PDF')
    } finally {
      setLoading(false)
    }
  }

  // Données de graphiques (à remplacer par des données réelles du rapport)
  const chartData = reportParams
    ? [
      { mois: 'Jan', groupage: 120, autresEnvois: 80, total: 200 },
      { mois: 'Fév', groupage: 150, autresEnvois: 100, total: 250 },
    ]
    : []

  const traficData = reportParams
    ? [
      { name: 'Groupage Aérien', value: 45 },
      { name: 'Groupage Maritime', value: 30 },
      { name: 'Colis Rapide CI vers FR', value: 15 },
      { name: 'Colis Rapide FR vers CI', value: 10 },
    ]
    : []

  return (
    <div>
      <Title level={2}>Rapports Colis</Title>

      <Card style={{ marginBottom: 24 }}>
        <Form
          form={form}
          layout="vertical"
          onFinish={handleGenerateReport}
          initialValues={{
            dateRange: [dayjs().startOf('month'), dayjs().endOf('month')],
          }}
        >
          <Row gutter={16}>
            <Col xs={24} sm={12} md={6}>
              <Form.Item
                name="dateRange"
                label="Période"
                rules={[{ required: true, message: 'Sélectionnez une période' }]}
              >
                <RangePicker style={{ width: '100%' }} size="large" format="DD/MM/YYYY" />
              </Form.Item>
            </Col>

            <Col xs={24} sm={12} md={6}>
              <Form.Item noStyle shouldUpdate={(prev: any, curr: any) => prev.forme_envoi !== curr.forme_envoi}>
                {({ getFieldValue }: { getFieldValue: (name: string) => any }) => {
                  const currentFormeEnvoi = getFieldValue('forme_envoi');
                  return (
                    <Form.Item name="trafic_envoi" label="Trafic d'envoi">
                      <Select placeholder="Tous" allowClear size="large">
                        {APP_CONFIG.traficEnvoi
                          .filter((trafic) => {
                            if (!currentFormeEnvoi) return true;
                            return currentFormeEnvoi === 'groupage'
                              ? trafic.includes('Groupage')
                              : trafic.includes('Colis Rapide');
                          })
                          .map((trafic) => (
                            <Option key={trafic} value={trafic}>
                              {trafic}
                            </Option>
                          ))}
                      </Select>
                    </Form.Item>
                  );
                }}
              </Form.Item>
            </Col>

            <Col xs={24} sm={12} md={6}>
              <Form.Item name="forme_envoi" label="Type d'envoi">
                <Select placeholder="Tous" allowClear size="large">
                  <Option value="groupage">Groupage</Option>
                  <Option value="autres_envoi">Autres Envois</Option>
                </Select>
              </Form.Item>
            </Col>

            <Col xs={24} sm={12} md={6}>
              <Form.Item name="mode_envoi" label="Mode d'envoi">
                <Select placeholder="Tous" allowClear size="large">
                  {APP_CONFIG.modeEnvoi.filter((m) => m !== 'groupage').map((mode) => (
                    <Option key={mode} value={mode}>
                      {mode}
                    </Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>

            <Col xs={24}>
              <Space>
                <Button
                  type="primary"
                  icon={<SearchOutlined />}
                  htmlType="submit"
                  size="large"
                  loading={loading}
                >
                  Générer le Rapport
                </Button>

                {reportParams && (
                  <>
                    <Button
                      icon={<FileExcelOutlined />}
                      onClick={handleExportExcel}
                      size="large"
                      loading={loading}
                    >
                      Exporter Excel
                    </Button>
                    <Button
                      icon={<FilePdfOutlined />}
                      onClick={handleExportPDF}
                      size="large"
                      loading={loading}
                    >
                      Exporter PDF
                    </Button>
                  </>
                )}
              </Space>
            </Col>
          </Row>
        </Form>
      </Card>

      {reportParams && (
        <Tabs
          defaultActiveKey="charts"
          items={[
            {
              key: 'charts',
              label: (
                <>
                  <BarChartOutlined /> Graphiques
                </>
              ),
              children: (
                <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
                  <Col xs={24} lg={12}>
                    <ChartColisParMois data={chartData} />
                  </Col>
                  <Col xs={24} lg={12}>
                    <ChartRepartitionTrafic data={traficData} />
                  </Col>
                </Row>
              ),
            },
            {
              key: 'details',
              label: 'Détails',
              children: (
                <Card>
                  <p>Tableau détaillé du rapport à implémenter</p>
                </Card>
              ),
            },
          ]}
        />
      )}
    </div>
  )
}
