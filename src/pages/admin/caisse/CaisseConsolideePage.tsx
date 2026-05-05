/**
 * Vue journée consolidée : totaux + entrées / sorties / solde par caisse (agence).
 * Caissier principal et direction : réseau complet. Caissier d'agence : son agence seule (API).
 */

import React, { useCallback, useMemo, useState } from 'react'
import { Card, DatePicker, Table, Typography, Space, Button, Tag, Alert, Statistic, Row, Col, message } from 'antd'
import {
  ReloadOutlined,
  WalletOutlined,
  LineChartOutlined,
  LinkOutlined,
  TeamOutlined,
  FilePdfOutlined,
  FileExcelOutlined,
} from '@ant-design/icons'
import jsPDF from 'jspdf'
import autoTable from 'jspdf-autotable'
import { fmtPdf, fmtPdfNum, loadLogoBase64, drawLBPHeader, drawLBPFooters, LBP_TABLE_HEAD_STYLES, LBP_TABLE_ALT_ROW } from '@utils/pdfHelpers'
import { exportTableToExcel } from '@utils/export/excel'
import { Link } from 'react-router-dom'
import type { ColumnsType } from 'antd/es/table'
import dayjs, { type Dayjs } from 'dayjs'
import { useJourneeConsolidee } from '@hooks/useCaisse'
import { usePermissions } from '@hooks/usePermissions'
import { PERMISSIONS } from '@constants/permissions'
import { useAuth } from '@hooks/useAuth'

const { Title, Paragraph, Text } = Typography

function formatFcfa(n: number) {
  return `${Number(n || 0).toLocaleString('fr-FR', { maximumFractionDigits: 0 })} FCFA`
}

function numOnly(n: number) {
  return Number(n || 0).toLocaleString('fr-FR', { maximumFractionDigits: 0 })
}

export const CaisseConsolideePage: React.FC = () => {
  const [date, setDate] = useState<Dayjs>(() => dayjs())
  const dateStr = date.format('YYYY-MM-DD')
  const { data, isLoading, isFetching, refetch } = useJourneeConsolidee(dateStr)
  const { hasPermission } = usePermissions()
  const { user } = useAuth()
  const roleCode = String(user?.role?.code ?? '').toUpperCase()

  const canPoints = hasPermission(PERMISSIONS.EXPLOITATION.POINTS_READ)
  const canFactures = hasPermission(PERMISSIONS.FACTURES.READ)

  const isVueReseau = useMemo(
    () =>
      ['CAISSIER', 'DIRECTEUR', 'ADMIN', 'SUPER_ADMIN', 'SUPERVISEUR_REGIONAL'].includes(roleCode),
    [roleCode],
  )

  const exportExcel = useCallback(async () => {
    if (!data?.par_caisse?.length) {
      message.warning('Aucune donnée à exporter')
      return
    }
    try {
      const rows = data.par_caisse.map((row: Record<string, unknown>) => {
        const ag = row.agence as { nom?: string; code?: string } | null
        const agLabel = ag ? `${ag.nom} (${ag.code})` : '--'
        const p = row.point_du_jour as
          | { entrees?: number; sorties?: number; mouvementsCount?: number }
          | undefined
        return [
          agLabel,
          String((row.nom_caisse as string | null) || '--'),
          Number(p?.entrees ?? 0),
          Number(p?.sorties ?? 0),
          Number(p?.mouvementsCount ?? 0),
          Number(row.solde_actuel ?? 0),
        ]
      })
      await exportTableToExcel(
        {
          headers: [
            'Agence',
            'Caisse',
            'Entrées jour (FCFA)',
            'Sorties jour (FCFA)',
            'Mouvements jour',
            'Solde actuel (FCFA)',
          ],
          rows,
        },
        `journee-consolidee_${dateStr}`,
        {
          title: `Journée consolidée — ${dateStr}`,
          sheetName: 'Journée consolidée',
        },
      )
      message.success('Fichier Excel généré')
    } catch {
      message.error('Export Excel impossible')
    }
  }, [data, dateStr])

  const exportPdf = useCallback(async () => {
    if (!data?.par_caisse) {
      message.warning('Aucune donnée à exporter')
      return
    }
    try {
      const logo = await loadLogoBase64()
      const doc  = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
      const ml   = 14

      const cons = data.consolide
      const subtitle = cons
        ? `Entrees : ${fmtPdf(cons.entrees)}  |  Sorties : ${fmtPdf(cons.sorties)}  |  Mouvements : ${cons.mouvementsCount}`
        : undefined

      let y = drawLBPHeader(doc, {
        title:    'Journee consolidee -- caisses par agence',
        subtitle,
        rightInfo: `Date : ${dateStr}  |  Utilisateur : ${user?.username ?? '--'}`,
        logoBase64: logo,
      })

      const body: string[][] = data.par_caisse.map((row: Record<string, unknown>) => {
        const ag = row.agence as { nom?: string; code?: string } | null
        const agLabel = ag ? `${ag.nom} (${ag.code})` : '--'
        const p = row.point_du_jour as
          | { entrees?: number; sorties?: number; mouvementsCount?: number }
          | undefined
        return [
          agLabel,
          String((row.nom_caisse as string | null) || '--'),
          fmtPdfNum(Number(p?.entrees)),
          fmtPdfNum(Number(p?.sorties)),
          String(p?.mouvementsCount ?? 0),
          fmtPdfNum(Number(row.solde_actuel)),
        ]
      })

      autoTable(doc, {
        startY: y,
        head: [['Agence', 'Caisse', 'Entrees jour (FCFA)', 'Sorties jour (FCFA)', 'Mouv.', 'Solde actuel (FCFA)']],
        body,
        styles: { fontSize: 8, cellPadding: 3.5 },
        headStyles: LBP_TABLE_HEAD_STYLES,
        alternateRowStyles: LBP_TABLE_ALT_ROW,
        tableWidth: 'auto',
        columnStyles: {
          0: { cellWidth: 60 },
          1: { cellWidth: 50 },
          2: { halign: 'right' },
          3: { halign: 'right' },
          4: { halign: 'center', cellWidth: 18 },
          5: { halign: 'right', fontStyle: 'bold' },
        },
      })

      drawLBPFooters(doc)
      doc.save(`journee-consolidee_${dateStr}.pdf`)
      message.success('PDF généré')
    } catch {
      message.error('Export PDF impossible')
    }
  }, [data, dateStr, user?.username])

  const columns: ColumnsType<Record<string, unknown>> = [
    {
      title: 'Agence',
      key: 'agence',
      width: 220,
      render: (_: unknown, row: Record<string, unknown>) => {
        const ag = row.agence as { nom?: string; code?: string } | null
        return ag ? (
          <Space direction="vertical" size={0}>
            <Text strong>{ag.nom}</Text>
            <Tag>{ag.code}</Tag>
          </Space>
        ) : (
          <Text type="secondary">--</Text>
        )
      },
    },
    {
      title: 'Caisse',
      dataIndex: 'nom_caisse',
      key: 'nom_caisse',
      ellipsis: true,
      render: (t: string | null) => t || '--',
    },
    {
      title: 'Entrées (jour)',
      key: 'entrees',
      align: 'right',
      render: (_: unknown, row: Record<string, unknown>) => {
        const p = row.point_du_jour as { entrees?: number } | undefined
        return formatFcfa(Number(p?.entrees))
      },
    },
    {
      title: 'Sorties (jour)',
      key: 'sorties',
      align: 'right',
      render: (_: unknown, row: Record<string, unknown>) => {
        const p = row.point_du_jour as { sorties?: number } | undefined
        return formatFcfa(Number(p?.sorties))
      },
    },
    {
      title: 'Mouv. jour',
      key: 'mouv',
      align: 'center',
      width: 100,
      render: (_: unknown, row: Record<string, unknown>) => {
        const p = row.point_du_jour as { mouvementsCount?: number } | undefined
        return p?.mouvementsCount ?? 0
      },
    },
    {
      title: 'Solde actuel',
      key: 'solde',
      align: 'right',
      render: (_: unknown, row: Record<string, unknown>) =>
        formatFcfa(Number(row.solde_actuel)),
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 160,
      render: (_: unknown, row: Record<string, unknown>) => (
        <Link to={`/caisse/suivi?id_caisse=${String(row.id_caisse)}`}>
          <Button type="link" size="small" icon={<WalletOutlined />}>
            Suivi caisse
          </Button>
        </Link>
      ),
    },
  ]

  return (
    <div className="caisse-consolidee-page" style={{ padding: '0 0 24px' }}>
      <Space direction="vertical" size="large" style={{ width: '100%' }}>
        <div>
          <Title level={2} style={{ marginBottom: 8 }}>
            <TeamOutlined /> Journée consolidée -- caisses par agence
          </Title>
          <Paragraph type="secondary" style={{ marginBottom: 0 }}>
            Synthèse des entrées, sorties et soldes pour la date choisie. Les versements vers la caisse
            principale apparaissent dans les mouvements de chaque caisse. Utilisez le suivi caisse pour le
            détail des opérations.
          </Paragraph>
        </div>

        {isVueReseau && (
          <Alert
            type="info"
            showIcon
            message="Vue réseau (caissier principal / direction)"
            description="Vous visualisez l'ensemble des agences. Pour la caisse de votre propre agence, les règles sont les mêmes que sur « Suivi caisse » (opérations sur la caisse hub lorsque c'est la caisse principale)."
            style={{ maxWidth: 900 }}
          />
        )}

        <Card>
          <Space wrap style={{ marginBottom: 16, width: '100%', justifyContent: 'space-between' }}>
            <Space wrap>
              <Text>Date :</Text>
              <DatePicker
                value={date}
                onChange={(d: Dayjs | null) => d && setDate(d)}
                format="DD/MM/YYYY"
                allowClear={false}
              />
              <Button
                icon={<ReloadOutlined />}
                onClick={() => void refetch()}
                loading={isFetching}
              >
                Actualiser
              </Button>
              <Button
                type="primary"
                icon={<FilePdfOutlined />}
                onClick={exportPdf}
                disabled={!data?.par_caisse?.length || isLoading}
              >
                Exporter PDF
              </Button>
              <Button
                icon={<FileExcelOutlined />}
                onClick={exportExcel}
                disabled={!data?.par_caisse?.length || isLoading}
              >
                Exporter Excel
              </Button>
            </Space>
            <Space wrap>
              {canFactures && (
                <Link to="/factures">
                  <Button icon={<LineChartOutlined />}>Factures (toutes agences autorisées)</Button>
                </Link>
              )}
              {canPoints && (
                <Link to="/exploitation/points-journaliers">
                  <Button icon={<LinkOutlined />}>Points journaliers</Button>
                </Link>
              )}
            </Space>
          </Space>

          {data?.consolide && (
            <Row gutter={[16, 16]} style={{ marginBottom: 24 }}>
              <Col xs={24} sm={8}>
                <Card size="small" bordered>
                  <Statistic
                    title="Entrées (toutes caisses, jour)"
                    value={data.consolide.entrees}
                    prefix={<LineChartOutlined />}
                    formatter={(v: string | number) => formatFcfa(Number(v ?? 0))}
                  />
                </Card>
              </Col>
              <Col xs={24} sm={8}>
                <Card size="small" bordered>
                  <Statistic
                    title="Sorties (toutes caisses, jour)"
                    value={data.consolide.sorties}
                    formatter={(v: string | number) => formatFcfa(Number(v ?? 0))}
                  />
                </Card>
              </Col>
              <Col xs={24} sm={8}>
                <Card size="small" bordered>
                  <Statistic title="Mouvements (jour)" value={data.consolide.mouvementsCount} />
                </Card>
              </Col>
            </Row>
          )}

          <Table
            rowKey="id_caisse"
            loading={isLoading || isFetching}
            columns={columns}
            dataSource={data?.par_caisse ?? []}
            pagination={false}
            size="middle"
            scroll={{ x: 900 }}
            locale={{ emptyText: isLoading ? 'Chargement…' : 'Aucune caisse' }}
          />
        </Card>
      </Space>
    </div>
  )
}
