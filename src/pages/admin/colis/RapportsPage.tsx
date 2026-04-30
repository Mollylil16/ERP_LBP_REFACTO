import React, { useState, useMemo } from 'react'
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
  Statistic,
  Table,
  Tag,
} from 'antd'
import {
  SearchOutlined,
  FileExcelOutlined,
  FilePdfOutlined,
  BarChartOutlined,
  TableOutlined,
  InboxOutlined,
  SwapOutlined,
  ApartmentOutlined,
} from '@ant-design/icons'
import type { ColumnsType } from 'antd/es/table'
const { RangePicker } = DatePicker
import dayjs from 'dayjs'
import { rapportsService } from '@services/rapports.service'
import { ChartColisParMois } from '@components/dashboard/ChartColisParMois'
import { ChartRepartitionTrafic } from '@components/dashboard/ChartRepartitionTrafic'
import toast from 'react-hot-toast'
import { APP_CONFIG } from '@constants/application'
import jsPDF from 'jspdf'
import autoTable from 'jspdf-autotable'
import ExcelJS from 'exceljs'
import { saveAs } from 'file-saver'
import { fmtPdf, fmtPdfNum, loadLogoBase64, drawLBPHeader, drawLBPFooters, LBP_TABLE_HEAD_STYLES, LBP_TABLE_ALT_ROW } from '@utils/pdfHelpers'

const { Title } = Typography
const { Option } = Select

interface RapportRow {
  key: number
  ref: string
  date: string
  type: string
  trafic: string
  mode: string
  expediteur: string
  destinataire: string
  montant: number
}

const calculerMontantColis = (colis: any): number => {
  if (colis.marchandises?.length) {
    return colis.marchandises.reduce((acc: number, m: any) => {
      return (
        acc +
        Number(m.poids_total || 0) * Number(m.prix_unit || 0) +
        Number(m.prix_emballage || 0) +
        Number(m.prix_assurance || 0)
      )
    }, 0)
  }
  return Number(colis.total_montant || 0)
}

export const ColisRapportsPage: React.FC = () => {
  const [form] = Form.useForm()
  const [reportParams, setReportParams] = useState<any>(null)
  const [reportData, setReportData] = useState<any[]>([])
  const [loading, setLoading] = useState(false)

  const chartData = useMemo(() => {
    if (!reportData.length) return []
    const byMonth = new Map<string, { mois: string; groupage: number; autresEnvois: number; total: number }>()
    reportData.forEach((c) => {
      const d = dayjs(c.date_envoi)
      const key = d.format('YYYY-MM')
      const mois = d.format('MMM')
      if (!byMonth.has(key)) byMonth.set(key, { mois, groupage: 0, autresEnvois: 0, total: 0 })
      const entry = byMonth.get(key)!
      entry.total++
      if (c.forme_envoi === 'groupage') entry.groupage++
      else entry.autresEnvois++
    })
    return Array.from(byMonth.entries())
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([, v]) => v)
  }, [reportData])

  const traficData = useMemo(() => {
    if (!reportData.length) return []
    const counts = new Map<string, number>()
    reportData.forEach((c) => {
      const key = c.trafic_envoi || 'Non défini'
      counts.set(key, (counts.get(key) || 0) + 1)
    })
    return Array.from(counts.entries()).map(([name, value]) => ({ name, value }))
  }, [reportData])

  const kpis = useMemo(() => {
    const total = reportData.length
    const groupage = reportData.filter((c) => c.forme_envoi === 'groupage').length
    const montantTotal = reportData.reduce((acc, c) => acc + calculerMontantColis(c), 0)
    return { total, groupage, autresEnvois: total - groupage, montantTotal }
  }, [reportData])

  const tableRows: RapportRow[] = useMemo(
    () =>
      reportData.map((c) => ({
        key: c.id,
        ref: c.ref_colis,
        date: dayjs(c.date_envoi).format('DD/MM/YYYY'),
        type: c.forme_envoi === 'groupage' ? 'Groupage' : 'Autres Envois',
        trafic: c.trafic_envoi || '-',
        mode: c.mode_envoi || '-',
        expediteur: c.client?.nom_exp || '-',
        destinataire: c.nom_dest || c.nom_destinataire || '-',
        montant: calculerMontantColis(c),
      })),
    [reportData]
  )

  const handleGenerateReport = async (values: any) => {
    setLoading(true)
    try {
      const params = {
        start_date: values.dateRange[0].format('YYYY-MM-DD'),
        end_date: values.dateRange[1].format('YYYY-MM-DD'),
        trafic_envoi: values.trafic_envoi,
        mode_envoi: values.mode_envoi,
        forme_envoi: values.forme_envoi,
      }
      setReportParams(params)
      const data = await rapportsService.generateRapportColis(params)
      const list = Array.isArray(data) ? data : []
      setReportData(list)
      toast.success(`${list.length} colis trouvés`)
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Erreur lors de la génération du rapport')
    } finally {
      setLoading(false)
    }
  }

  const handleExportExcel = async () => {
    if (!reportData.length) {
      toast.error('Générez d\'abord un rapport')
      return
    }
    const wb = new ExcelJS.Workbook()
    const sheet = wb.addWorksheet('Rapport Colis')
    const periode = `${reportParams.start_date} → ${reportParams.end_date}`

    sheet.mergeCells('A1:H1')
    const titleCell = sheet.getCell('A1')
    titleCell.value = `Rapport Colis — La Belle Porte | Période : ${periode}`
    titleCell.font = { bold: true, size: 13, color: { argb: 'FF1a3a5c' } }
    titleCell.alignment = { horizontal: 'center' }
    sheet.addRow([])

    const headerRow = sheet.addRow([
      'Référence', 'Date', 'Type', 'Trafic', 'Mode', 'Expéditeur', 'Destinataire', 'Montant (FCFA)',
    ])
    headerRow.font = { bold: true, color: { argb: 'FFFFFFFF' } }
    headerRow.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1a3a5c' } }
    headerRow.alignment = { horizontal: 'center' }

    tableRows.forEach((r, i) => {
      const row = sheet.addRow([r.ref, r.date, r.type, r.trafic, r.mode, r.expediteur, r.destinataire, r.montant])
      if (i % 2 === 0) {
        row.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F7FA' } }
      }
      row.getCell(8).alignment = { horizontal: 'right' }
    })

    ;[18, 14, 16, 28, 18, 24, 24, 18].forEach((w, i) => {
      sheet.getColumn(i + 1).width = w
    })

    const kpiSheet = wb.addWorksheet('Résumé')
    kpiSheet.addRow(['Indicateur', 'Valeur'])
    kpiSheet.getRow(1).font = { bold: true }
    kpiSheet.addRow(['Total colis', kpis.total])
    kpiSheet.addRow(['Groupage', kpis.groupage])
    kpiSheet.addRow(['Autres envois', kpis.autresEnvois])
    kpiSheet.addRow(['Montant total (FCFA)', kpis.montantTotal])
    kpiSheet.getColumn(1).width = 25
    kpiSheet.getColumn(2).width = 20

    const buffer = await wb.xlsx.writeBuffer()
    saveAs(
      new Blob([buffer]),
      `rapport-colis-${reportParams.start_date}-${reportParams.end_date}.xlsx`
    )
    toast.success('Rapport Excel téléchargé')
  }

  const handleExportPDF = async () => {
    if (!reportData.length) {
      toast.error("Générez d'abord un rapport")
      return
    }
    const periode = `${reportParams.start_date} -> ${reportParams.end_date}`
    const logo    = await loadLogoBase64()

    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
    const ml  = 14

    let y = drawLBPHeader(doc, {
      title:    'Rapport Colis',
      subtitle: `Periode : ${periode}`,
      logoBase64: logo,
    })

    doc.setFontSize(8.5)
    doc.text(
      `Total : ${kpis.total} colis  |  Groupage : ${kpis.groupage}  |  Autres envois : ${kpis.autresEnvois}  |  Montant total : ${fmtPdf(kpis.montantTotal)}`,
      ml, y,
    )

    autoTable(doc, {
      head: [['Référence', 'Date', 'Type', 'Trafic', 'Mode', 'Expéditeur', 'Destinataire', 'Montant']],
      body: tableRows.map((r) => [
        r.ref,
        r.date,
        r.type,
        r.trafic,
        r.mode,
        r.expediteur,
        r.destinataire,
        fmtPdfNum(r.montant) + ' FCFA',
      ]),
      startY: y + 5,
      styles: { fontSize: 7.5, cellPadding: 2 },
      headStyles: LBP_TABLE_HEAD_STYLES,
      alternateRowStyles: LBP_TABLE_ALT_ROW,
      columnStyles: { 7: { halign: 'right' } },
    })

    drawLBPFooters(doc)
    doc.save(`rapport-colis-${reportParams.start_date}-${reportParams.end_date}.pdf`)
    toast.success('Rapport PDF téléchargé')
  }

  const columns: ColumnsType<RapportRow> = [
    {
      title: 'Référence',
      dataIndex: 'ref',
      key: 'ref',
      render: (v: string) => <strong>{v}</strong>,
    },
    { title: 'Date', dataIndex: 'date', key: 'date', sorter: (a: RapportRow, b: RapportRow) => a.date.localeCompare(b.date) },
    {
      title: 'Type',
      dataIndex: 'type',
      key: 'type',
      filters: [
        { text: 'Groupage', value: 'Groupage' },
        { text: 'Autres Envois', value: 'Autres Envois' },
      ],
      onFilter: (value: React.Key | boolean, record: RapportRow) => record.type === value,
      render: (v: string) => <Tag color={v === 'Groupage' ? 'blue' : 'green'}>{v}</Tag>,
    },
    { title: 'Trafic', dataIndex: 'trafic', key: 'trafic' },
    { title: 'Mode', dataIndex: 'mode', key: 'mode' },
    { title: 'Expéditeur', dataIndex: 'expediteur', key: 'expediteur' },
    { title: 'Destinataire', dataIndex: 'destinataire', key: 'destinataire' },
    {
      title: 'Montant',
      dataIndex: 'montant',
      key: 'montant',
      align: 'right',
      sorter: (a: RapportRow, b: RapportRow) => a.montant - b.montant,
      render: (v: number) => (
        <strong>{v.toLocaleString('fr-FR')} FCFA</strong>
      ),
    },
  ]

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
              <Form.Item
                noStyle
                shouldUpdate={(prev: any, curr: any) => prev.forme_envoi !== curr.forme_envoi}
              >
                {({ getFieldValue }: { getFieldValue: (name: string) => any }) => {
                  const currentFormeEnvoi = getFieldValue('forme_envoi')
                  return (
                    <Form.Item name="trafic_envoi" label="Trafic d'envoi">
                      <Select placeholder="Tous" allowClear size="large">
                        {APP_CONFIG.traficEnvoi
                          .filter((t) => {
                            if (!currentFormeEnvoi) return true
                            return currentFormeEnvoi === 'groupage'
                              ? t.includes('Groupage')
                              : t.includes('Colis Rapide')
                          })
                          .map((t) => (
                            <Option key={t} value={t}>{t}</Option>
                          ))}
                      </Select>
                    </Form.Item>
                  )
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
                    <Option key={mode} value={mode}>{mode}</Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>

            <Col xs={24}>
              <Space wrap>
                <Button
                  type="primary"
                  icon={<SearchOutlined />}
                  htmlType="submit"
                  size="large"
                  loading={loading}
                >
                  Générer le Rapport
                </Button>

                {reportData.length > 0 && (
                  <>
                    <Button
                      icon={<FileExcelOutlined />}
                      onClick={handleExportExcel}
                      size="large"
                      style={{ color: '#217346', borderColor: '#217346' }}
                    >
                      Exporter Excel
                    </Button>
                    <Button
                      icon={<FilePdfOutlined />}
                      onClick={handleExportPDF}
                      size="large"
                      style={{ color: '#c0392b', borderColor: '#c0392b' }}
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

      {reportData.length > 0 && (
        <>
          <Row gutter={[16, 16]} style={{ marginBottom: 24 }}>
            <Col xs={12} sm={6}>
              <Card>
                <Statistic
                  title="Total Colis"
                  value={kpis.total}
                  prefix={<InboxOutlined />}
                />
              </Card>
            </Col>
            <Col xs={12} sm={6}>
              <Card>
                <Statistic
                  title="Groupage"
                  value={kpis.groupage}
                  prefix={<ApartmentOutlined />}
                  valueStyle={{ color: '#1890ff' }}
                />
              </Card>
            </Col>
            <Col xs={12} sm={6}>
              <Card>
                <Statistic
                  title="Autres Envois"
                  value={kpis.autresEnvois}
                  prefix={<SwapOutlined />}
                  valueStyle={{ color: '#52c41a' }}
                />
              </Card>
            </Col>
            <Col xs={12} sm={6}>
              <Card>
                <Statistic
                  title="Montant Total"
                  value={kpis.montantTotal.toLocaleString('fr-FR')}
                  suffix="FCFA"
                  valueStyle={{ color: '#1a3a5c', fontSize: 16 }}
                />
              </Card>
            </Col>
          </Row>

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
                label: (
                  <>
                    <TableOutlined /> Détails ({kpis.total})
                  </>
                ),
                children: (
                  <Table
                    columns={columns}
                    dataSource={tableRows}
                    size="middle"
                    scroll={{ x: 900 }}
                    pagination={{
                      pageSize: 20,
                      showSizeChanger: true,
                      showTotal: (t: number) => `${t} colis`,
                    }}
                    style={{ marginTop: 16 }}
                  />
                ),
              },
            ]}
          />
        </>
      )}
    </div>
  )
}
