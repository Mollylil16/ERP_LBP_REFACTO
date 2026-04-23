import React from 'react'
import { Button, Card, Table, Tag, Typography, message } from 'antd'
import { useQuery } from '@tanstack/react-query'
import { FileExcelOutlined } from '@ant-design/icons'
import type { Dayjs } from 'dayjs'
import { supervisionService } from '@services/supervision.service'
import { exportSupervisionPerformanceExcel } from '@utils/supervisionExcelExport'
import { usePermissions } from '@hooks/usePermissions'
import { PERMISSIONS } from '@constants/permissions'

const { Text } = Typography

const niveauColor: Record<string, string> = {
  élevé: 'green',
  modéré: 'gold',
  faible: 'default',
}

type ParAgenceRole = { id_agence: number; nom_agence: string; role_code: string; n: number }

export const SupervisionPerformanceTab: React.FC<{ range: [Dayjs, Dayjs] }> = ({ range }) => {
  const { hasPermission } = usePermissions()
  const canExport = hasPermission(PERMISSIONS.SUPERVISION.DASHBOARD_READ)
  const debut = range[0].format('YYYY-MM-DD')
  const fin = range[1].format('YYYY-MM-DD')
  const { data: prod, isLoading: l1 } = useQuery({
    queryKey: ['supervision', 'user-productivity', debut, fin],
    queryFn: () => supervisionService.getUserProductivity(debut, fin),
  })
  const { data: headcount, isLoading: l2 } = useQuery({
    queryKey: ['supervision', 'performance-agents'],
    queryFn: () => supervisionService.getPerformanceAgents(),
  })

  const onExportExcel = async () => {
    if (!canExport) return
    const par = (headcount as { par_agence_role?: ParAgenceRole[] } | null | undefined)
      ?.par_agence_role
    try {
      await exportSupervisionPerformanceExcel({
        range,
        utilisateurs: prod?.utilisateurs,
        parAgenceRole: Array.isArray(par) ? (par as ParAgenceRole[]) : [],
      })
      message.success('Fichier Excel généré')
    } catch {
      message.error('Export Excel impossible')
    }
  }

  return (
    <div>
      <Card
        size="small"
        title="Productivité opérationnelle (colis + factures saisis)"
        extra={
          canExport ? (
            <Button size="small" icon={<FileExcelOutlined />} onClick={onExportExcel}>
              Exporter Excel
            </Button>
          ) : null
        }
        style={{ marginBottom: 16 }}
      >
        <Text type="secondary" style={{ display: 'block', marginBottom: 12 }}>
          Indice basé sur le volume d’opérations rattachées au compte (username) sur la période — outil
          d’animation, pas de notation RH contractuelle.
        </Text>
        <Table
          size="small"
          loading={l1}
          rowKey="id"
          dataSource={prod?.utilisateurs ?? []}
          pagination={{ pageSize: 15 }}
          scroll={{ x: true }}
          columns={[
            { title: 'Utilisateur', dataIndex: 'username' },
            { title: 'Nom', dataIndex: 'nom_complet' },
            { title: 'Rôle', dataIndex: 'role_code', width: 140 },
            { title: 'Agence', dataIndex: 'agence_nom' },
            { title: 'Colis', dataIndex: 'colis_saisis', width: 70 },
            { title: 'Factures', dataIndex: 'factures_saisies', width: 80 },
            { title: 'Indice', dataIndex: 'indice_activite', width: 70 },
            {
              title: 'Niveau',
              dataIndex: 'niveau_activite',
              render: (t: string) => <Tag color={niveauColor[t] ?? 'default'}>{t}</Tag>,
            },
          ]}
        />
      </Card>
      <Card size="small" title="Effectifs par agence et rôle" loading={l2}>
        <Table
          size="small"
          rowKey={(r: any) => `${r.id_agence}-${r.role_code}`}
          dataSource={(headcount as any)?.par_agence_role ?? []}
          pagination={false}
          columns={[
            { title: 'Agence', dataIndex: 'nom_agence' },
            { title: 'Rôle', dataIndex: 'role_code' },
            { title: 'Effectif', dataIndex: 'n' },
          ]}
        />
      </Card>
    </div>
  )
}
