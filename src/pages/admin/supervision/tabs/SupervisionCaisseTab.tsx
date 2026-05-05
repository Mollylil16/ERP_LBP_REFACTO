import React, { useMemo } from 'react'
import { Button, Card, Col, Row, Statistic, Table, Tag, Typography, message } from 'antd'
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
    refetchInterval: 60_000,
  })

  const totaux = useMemo(() => {
    if (!data?.caisses?.length) return null
    const totalSolde = data.caisses.reduce((s, c) => s + (c.solde_actuel ?? 0), 0)
    const totalEntrees = data.caisses.reduce((s, c) => s + (c.volume_entrees_periode ?? 0), 0)
    const nbNegatifs = data.caisses.filter((c) => c.solde_actuel < 0).length
    const nbNuls = data.caisses.filter((c) => c.solde_actuel === 0).length
    return { totalSolde, totalEntrees, nbNegatifs, nbNuls }
  }, [data])

  // Tri : négatifs en premier, puis par solde croissant (les plus faibles d'abord)
  const sortedCaisses = useMemo(
    () => [...(data?.caisses ?? [])].sort((a, b) => a.solde_actuel - b.solde_actuel),
    [data],
  )

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
    <div>
      {/* Cartes de synthèse réseau */}
      {totaux && (
        <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
          <Col xs={24} sm={8}>
            <Card size="small">
              <Statistic
                title="Solde total réseau"
                value={Math.round(totaux.totalSolde)}
                suffix="F"
                valueStyle={{ color: totaux.totalSolde < 0 ? '#cf1322' : '#3f8600' }}
              />
            </Card>
          </Col>
          <Col xs={24} sm={8}>
            <Card size="small">
              <Statistic
                title={`Entrées réseau (${debut} → ${fin})`}
                value={Math.round(totaux.totalEntrees)}
                suffix="F"
              />
            </Card>
          </Col>
          <Col xs={24} sm={8}>
            <Card size="small">
              <Statistic
                title="Caisses en solde négatif"
                value={totaux.nbNegatifs}
                valueStyle={{ color: totaux.nbNegatifs > 0 ? '#cf1322' : '#3f8600' }}
                suffix={
                  totaux.nbNuls > 0 ? (
                    <Text type="secondary" style={{ fontSize: 12, marginLeft: 8 }}>
                      + {totaux.nbNuls} à zéro
                    </Text>
                  ) : undefined
                }
              />
            </Card>
          </Col>
        </Row>
      )}

      <Card
        size="small"
        title="Caisse par agence — soldes et entrées sur la période"
        extra={
          canExport ? (
            <Button size="small" icon={<FileExcelOutlined />} onClick={onExportExcel}>
              Exporter Excel
            </Button>
          ) : null
        }
      >
        <Text type="secondary" style={{ display: 'block', marginBottom: 12 }}>
          Volume d'entrées = somme des mouvements d'entrée (hors décaissements) sur l'intervalle
          sélectionné. Lignes en rouge : solde négatif — en orange : solde nul. Triées par solde croissant.
        </Text>
        <Table
          size="small"
          loading={isLoading}
          rowKey={(r: CaisseLigne) => `${r.id_caisse}-${r.agence.id}`}
          dataSource={sortedCaisses}
          pagination={{ pageSize: 15, showSizeChanger: true }}
          scroll={{ x: true }}
          rowClassName={(r: CaisseLigne) =>
            r.solde_actuel < 0
              ? 'lbp-row-danger'
              : r.solde_actuel === 0
                ? 'lbp-row-warning'
                : ''
          }
          columns={[
            {
              title: 'Agence',
              key: 'agence',
              render: (_: unknown, r: CaisseLigne) => `${r.agence.code} — ${r.agence.nom}`,
            },
            { title: 'Caisse', dataIndex: 'nom_caisse' },
            {
              title: 'Principale',
              width: 90,
              render: (_: unknown, r: CaisseLigne) =>
                r.est_caisse_principale ? <Tag color="blue">Oui</Tag> : <Tag>Non</Tag>,
            },
            {
              title: 'Solde actuel',
              dataIndex: 'solde_actuel',
              sorter: (a: CaisseLigne, b: CaisseLigne) => a.solde_actuel - b.solde_actuel,
              render: (v: number) => (
                <Text
                  style={{
                    color: v < 0 ? '#ff4d4f' : v === 0 ? '#faad14' : undefined,
                    fontWeight: v < 0 ? 600 : undefined,
                  }}
                >
                  {Number(v).toLocaleString('fr-FR')} F
                </Text>
              ),
            },
            {
              title: 'Entrées (période)',
              dataIndex: 'volume_entrees_periode',
              sorter: (a: CaisseLigne, b: CaisseLigne) =>
                a.volume_entrees_periode - b.volume_entrees_periode,
              render: (v: number) => `${Number(v).toLocaleString('fr-FR')} F`,
            },
          ]}
        />
      </Card>
    </div>
  )
}
