/**
 * Points journaliers au statut SOUMIS, filtrés par agence de la caisse sélectionnée (optionnel).
 * Réservé aux profils avec permission de validation exploitation.
 */

import React, { useCallback, useEffect, useState } from 'react'
import { Button, Card, Input, Modal, Space, Table, Tag, Typography, message } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { exploitationService, type PointJournalierRow } from '@services/exploitation.service'

const { Text } = Typography

const statutColor: Record<string, string> = {
  BROUILLON: 'default',
  SOUMIS: 'orange',
  VALIDE: 'green',
  REJETE: 'red',
}

export interface PointsSoumisCaisseTabProps {
  /** Agence rattachée à la caisse sélectionnée ; si absent, liste tous les SOUMIS visibles côté API. */
  idAgence?: number
  refreshKey?: number
}

export const PointsSoumisCaisseTab: React.FC<PointsSoumisCaisseTabProps> = ({
  idAgence,
  refreshKey = 0,
}) => {
  const [loading, setLoading] = useState(false)
  const [rows, setRows] = useState<PointJournalierRow[]>([])
  const [rejectId, setRejectId] = useState<number | null>(null)
  const [motif, setMotif] = useState('')

  const load = useCallback(() => {
    setLoading(true)
    const params: Record<string, string> = { statut: 'SOUMIS' }
    if (idAgence != null) params.agence_id = String(idAgence)
    exploitationService
      .listPointsJournaliers(params)
      .then(setRows)
      .catch(() => message.error('Chargement des points impossible'))
      .finally(() => setLoading(false))
  }, [idAgence])

  useEffect(() => {
    load()
  }, [load, refreshKey])

  const columns: ColumnsType<PointJournalierRow> = [
    {
      title: 'Agence',
      key: 'ag',
      render: (_: unknown, r: PointJournalierRow) => r.agence?.nom ?? '—',
    },
    { title: 'Date', dataIndex: 'date_point', key: 'd' },
    { title: 'Recettes', dataIndex: 'total_recettes', key: 't' },
    { title: 'Devise', dataIndex: 'devise', key: 'dv' },
    {
      title: 'Statut',
      dataIndex: 'statut',
      key: 's',
      render: (s: string) => <Tag color={statutColor[s] || 'default'}>{s}</Tag>,
    },
    {
      title: 'Chef',
      key: 'ch',
      render: (_: unknown, r: PointJournalierRow) => r.chefAgence?.nom_complet ?? '—',
    },
    {
      title: 'Actions',
      key: 'a',
      width: 200,
      render: (_: unknown, r: PointJournalierRow) =>
        r.statut === 'SOUMIS' ? (
          <Space>
            <Button
              type="primary"
              size="small"
              onClick={async () => {
                try {
                  await exploitationService.validerPointJournalier(r.id)
                  message.success('Point validé')
                  load()
                } catch {
                  message.error('Validation impossible')
                }
              }}
            >
              Valider
            </Button>
            <Button danger size="small" onClick={() => setRejectId(r.id)}>
              Rejeter
            </Button>
          </Space>
        ) : null,
    },
  ]

  return (
    <Space direction="vertical" style={{ width: '100%' }} size="middle">
      {idAgence == null ? (
        <Text type="secondary">
          Cette caisse n&apos;est pas rattachée à une agence : affichage de tous les points SOUMIS
          autorisés par votre profil.
        </Text>
      ) : (
        <Text type="secondary">
          Points journaliers soumis pour l&apos;agence de la caisse sélectionnée (filtre agence #{idAgence}
          ).
        </Text>
      )}
      <Card size="small">
        <Space style={{ marginBottom: 12 }}>
          <Button onClick={load} loading={loading}>
            Actualiser
          </Button>
        </Space>
        <Table<PointJournalierRow>
          rowKey="id"
          loading={loading}
          dataSource={rows}
          columns={columns}
          scroll={{ x: true }}
          expandable={{
            rowExpandable: (r: PointJournalierRow) => (r.credits?.length ?? 0) > 0,
            expandedRowRender: (r: PointJournalierRow) => (
              <ul style={{ margin: 0, paddingLeft: 20 }}>
                {(r.credits ?? []).map((c: NonNullable<PointJournalierRow['credits']>[number]) => (
                  <li key={c.id}>
                    Crédit #{c.id} — colis {c.colis?.ref_colis ?? '—'} — {c.montant} {c.devise}
                  </li>
                ))}
              </ul>
            ),
          }}
        />
      </Card>

      <Modal
        title="Motif du rejet"
        open={rejectId != null}
        onCancel={() => {
          setRejectId(null)
          setMotif('')
        }}
        onOk={async () => {
          if (!rejectId || !motif.trim()) {
            message.warning('Motif obligatoire')
            return
          }
          try {
            await exploitationService.rejeterPointJournalier(rejectId, motif.trim())
            message.success('Point rejeté')
            setRejectId(null)
            setMotif('')
            load()
          } catch {
            message.error('Rejet impossible')
          }
        }}
      >
        <Input.TextArea
          rows={4}
          value={motif}
          onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setMotif(e.target.value)}
          placeholder="Précisez la raison du rejet…"
        />
      </Modal>
    </Space>
  )
}
