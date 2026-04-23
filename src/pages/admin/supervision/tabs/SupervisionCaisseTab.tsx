import React from 'react'
import { Button, Card, Table, Tag, Typography, message } from 'antd'
import { useQuery } from '@tanstack/react-query'
import { FileExcelOutlined } from '@ant-design/icons'
import type { Dayjs } from 'dayjs'
import { supervisionService } from '@services/supervision.service'
import { exportSupervisionCaisseExcel } from '@utils/supervisionExcelExport'
import { usePermissions } from '@hooks/usePermissions'
import { PERMISSIONS } from '@constants/permissions'

const { Text } = Typography

type CaisseLigne = {
  agence: { id: number; code: string; nom: string }
  id_caisse: number
  nom_caisse: string
  solde_actuel: number
  volume_entrees_periode: number
  est_caisse_principale: boolean
}

export const SupervisionCaisseTab: React.FC<{ range: [Dayjs, Dayjs] }> = ({ range }) => {
  const { hasPermission } = usePermissions()
  const canExport = hasPermission(PERMISSIONS.SUPERVISION.DASHBOARD_READ)
  const debut = range[0].format('YYYY-MM-DD')
  const fin = range[1].format('YYYY-MM-DD')
  const { data, isLoading } = useQuery({
    queryKey: ['supervision', 'caisse-reseau', debut, fin],
    queryFn: () => supervisionService.getCaisseReseau(debut, fin),
    refetchInterval: 120_000,
  })
  const onExportExcel = async () => {
    if (!canExport) return
    try {
      await exportSupervisionCaisseExcel({ range, caisses: data?.caisses })
      message.success('Fichier Excel généré')
    } catch {
      message.error('Export Excel impossible')
    }
  }
  return (
    <Card
      size="small"
      title="Caisse par agence (soldes + entrées sur la période)"
      extra={
        canExport ? (
          <Button size="small" icon={<FileExcelOutlined />} onClick={onExportExcel}>
            Exporter Excel
          </Button>
        ) : null
      }
    >
      <Text type="secondary" style={{ display: 'block', marginBottom: 12 }}>
        Volume d’entrées = somme des mouvements d’entrée (hors décaissements) sur l’intervalle
        sélectionné. La caisse principale est identifiée automatiquement quand configurée.
      </Text>
      <Table
        size="small"
        loading={isLoading}
        rowKey={(r: CaisseLigne) => `${r.id_caisse}-${r.agence.id}`}
        dataSource={data?.caisses ?? []}
        pagination={{ pageSize: 12 }}
        columns={[
          {
            title: 'Agence',
            render: (_: unknown, r: CaisseLigne) => `${r.agence.code} — ${r.agence.nom}`,
          },
          { title: 'Caisse', dataIndex: 'nom_caisse' },
          {
            title: 'Principal',
            width: 90,
            render: (_: unknown, r: CaisseLigne) =>
              r.est_caisse_principale ? <Tag color="blue">Oui</Tag> : <Tag>Non</Tag>,
          },
          {
            title: 'Solde actuel',
            dataIndex: 'solde_actuel',
            render: (v: number) => `${Number(v).toLocaleString('fr-FR')} F`,
          },
          {
            title: 'Entrées (période)',
            dataIndex: 'volume_entrees_periode',
            render: (v: number) => `${Number(v).toLocaleString('fr-FR')} F`,
          },
        ]}
      />
    </Card>
  )
}
