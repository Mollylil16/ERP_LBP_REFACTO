import React, { useMemo, useState } from 'react'
import {
  Alert,
  Button,
  Card,
  Input,
  InputNumber,
  Space,
  Table,
  Tag,
  Typography,
  message,
} from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { ThunderboltOutlined, FilePdfOutlined } from '@ant-design/icons'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { APP_CONFIG } from '@constants/application'
import { PERMISSIONS } from '@constants/permissions'
import { WithPermission } from '@components/common/WithPermission'
import { facturesService } from '@services/factures.service'
import { paiementsService } from '@services/paiements.service'
import type { FactureColis, Paiement } from '@types'
import { formatDate, formatMontantWithDevise } from '@utils/format'

const QUICK_MODES = ['especes', 'comptant', 'wave', 'om', 'cheque', 'virement'] as const
type QuickMode = (typeof QUICK_MODES)[number]

function needsReference(mode: string): boolean {
  return ['wave', 'om', 'cheque', 'virement'].includes(mode)
}

async function resolveFacture(raw: string): Promise<FactureColis> {
  const q = raw.trim()
  if (!q) throw new Error('Saisissez une référence colis, un numéro de facture ou un téléphone.')
  if (/^FCO-/i.test(q)) {
    return facturesService.getFactureByNum(q)
  }
  const byLookup = await facturesService.getEncaissementLookup(q)
  if (byLookup) return byLookup
  const byColis = await facturesService.getFactureByColis(q)
  if (byColis) return byColis
  return facturesService.getFactureByNum(q)
}

interface EncaissementRapideCardProps {
  isSessionOpen: boolean
  onEncaissementSuccess?: () => void
  /** Consultation seule (ex. caisse d’agence pour une caissière siège). */
  readOnly?: boolean
}

export const EncaissementRapideCard: React.FC<EncaissementRapideCardProps> = ({
  isSessionOpen,
  onEncaissementSuccess,
  readOnly = false,
}) => {
  const queryClient = useQueryClient()
  const [searchInput, setSearchInput] = useState('')
  const [facture, setFacture] = useState<FactureColis | null>(null)
  const [montant, setMontant] = useState<number>(0)
  const [selectedMode, setSelectedMode] = useState<QuickMode | null>(null)
  const [reference, setReference] = useState('')

  const restant = useMemo(() => {
    if (!facture) return 0
    return Math.max(0, Number(facture.montant_ttc) - Number(facture.montant_paye || 0))
  }, [facture])

  const { data: historique, isLoading: histLoading, refetch: refetchHist } = useQuery({
    queryKey: ['paiements', 'facture', facture?.id],
    queryFn: () => paiementsService.getPaiementsByFacture(facture!.id),
    enabled: Boolean(facture?.id),
  })

  const lookupMutation = useMutation({
    mutationFn: () => resolveFacture(searchInput),
    onSuccess: (f) => {
      setFacture(f)
      const r = Math.max(0, Number(f.montant_ttc) - Number(f.montant_paye || 0))
      setMontant(r > 0 ? r : 0)
      setSelectedMode(null)
      setReference('')
      message.success('Facture chargée.')
    },
    onError: (e: unknown) => {
      setFacture(null)
      const msg =
        e && typeof e === 'object' && 'message' in e
          ? String((e as { message?: string }).message)
          : 'Facture introuvable.'
      message.error(msg)
    },
  })

  const payMutation = useMutation({
    mutationFn: async (mode: QuickMode) => {
      if (!facture?.id) throw new Error('Aucune facture.')
      const cashInstant = mode === 'especes' || mode === 'comptant'
      if (cashInstant && !isSessionOpen) {
        throw new Error('Ouvrez une session de caisse avant un encaissement espèces ou comptant.')
      }
      if (montant <= 0) throw new Error('Montant invalide.')
      if (montant > restant) throw new Error('Montant supérieur au reste à payer.')
      if (needsReference(mode) && !reference.trim()) {
        throw new Error('Indiquez une référence (n° chèque, virement, transaction…).')
      }
      const today = new Date().toISOString().split('T')[0]
      return paiementsService.createPaiement({
        id_facture: facture.id,
        montant,
        mode_paiement: mode,
        date_paiement: today,
        reference: reference.trim() || undefined,
      })
    },
    onSuccess: async (_p: Paiement) => {
      message.success('Paiement enregistré.')
      await queryClient.invalidateQueries({ queryKey: ['paiements', 'facture', facture?.id] })
      await queryClient.invalidateQueries({ queryKey: ['caisses'] })
      await queryClient.invalidateQueries({ queryKey: ['caisses', 'solde'] })
      await queryClient.invalidateQueries({ queryKey: ['caisse-point'] })
      await queryClient.invalidateQueries({ queryKey: ['caisse-active-session'] })
      await queryClient.invalidateQueries({ queryKey: ['caisse-mouvements'] })
      try {
        const f = await facturesService.getFactureById(facture!.id)
        setFacture(f)
        setMontant(Math.max(0, Number(f.montant_ttc) - Number(f.montant_paye || 0)))
      } catch {
        void refetchHist()
      }
      setSelectedMode(null)
      onEncaissementSuccess?.()
    },
    onError: (e: unknown) => {
      const msg =
        e && typeof e === 'object' && 'message' in e
          ? String((e as { message?: string }).message)
          : 'Enregistrement impossible.'
      message.error(msg)
    },
  })

  const columns: ColumnsType<Paiement> = [
    {
      title: 'Date',
      dataIndex: 'date_paiement',
      key: 'date_paiement',
      width: 120,
      render: (d: string) => formatDate(d),
    },
    { title: 'Mode', dataIndex: 'mode_paiement', key: 'mode', width: 110 },
    {
      title: 'Montant',
      dataIndex: 'montant',
      key: 'montant',
      width: 120,
      render: (m: number) => formatMontantWithDevise(m),
    },
    {
      title: 'Statut',
      key: 'etat',
      width: 110,
      render: (_: unknown, row: Paiement) =>
        row.etat_validation === 1 ? (
          <Tag color="green">Validé</Tag>
        ) : (
          <Tag color="orange">En attente</Tag>
        ),
    },
    {
      title: '',
      key: 'recu',
      width: 100,
      render: (_: unknown, row: Paiement) =>
        row.etat_validation === 1 ? (
          <Button
            type="link"
            size="small"
            icon={<FilePdfOutlined />}
            onClick={() =>
              void paiementsService.downloadReceipt(row.id).catch(() =>
                message.error('Téléchargement du reçu impossible.'),
              )
            }
          >
            Reçu
          </Button>
        ) : null,
    },
  ]

  const instantModes = useMemo(() => new Set(['especes', 'comptant']), [])

  return (
    <WithPermission permission={PERMISSIONS.PAIEMENTS.CREATE}>
      <Card
        title={
          <Space>
            <ThunderboltOutlined />
            <Typography.Text strong>Encaissement rapide</Typography.Text>
          </Space>
        }
        size="small"
        style={{ marginBottom: 16 }}
      >
        {readOnly ? (
          <Alert
            type="info"
            showIcon
            style={{ marginBottom: 12 }}
            message="Encaissement indisponible sur cette caisse"
            description="Sélectionnez la caisse principale siège pour enregistrer des paiements."
          />
        ) : null}
        <Typography.Paragraph type="secondary" style={{ marginBottom: 12 }}>
          Recherche par référence colis, numéro de facture (FCO-…) ou téléphone client, puis choix du mode et du montant.
        </Typography.Paragraph>
        <Space wrap style={{ marginBottom: 12 }}>
          <Input
            placeholder="Réf. colis, n° facture FCO-… ou téléphone client"
            value={searchInput}
            onChange={(e: React.ChangeEvent<HTMLInputElement>) => setSearchInput(e.target.value)}
            onPressEnter={() => !readOnly && lookupMutation.mutate()}
            style={{ minWidth: 260, maxWidth: 400 }}
            allowClear
            disabled={readOnly}
          />
          <Button
            type="primary"
            loading={lookupMutation.isPending}
            disabled={readOnly}
            onClick={() => lookupMutation.mutate()}
          >
            Rechercher
          </Button>
        </Space>

        {facture ? (
          <Space direction="vertical" style={{ width: '100%' }} size="middle">
            <div>
              <Typography.Text strong>{facture.num_facture}</Typography.Text>
              <Typography.Text type="secondary">
                {' '}
                · Colis {facture.ref_colis || '—'}
              </Typography.Text>
              <div style={{ marginTop: 8 }}>
                <Space wrap size="large">
                  <span>Total {formatMontantWithDevise(facture.montant_ttc)}</span>
                  <span>Payé {formatMontantWithDevise(facture.montant_paye)}</span>
                  <Typography.Text type="danger">
                    Reste {formatMontantWithDevise(restant)}
                  </Typography.Text>
                </Space>
              </div>
            </div>

            <Space align="center" wrap>
              <Typography.Text>Montant</Typography.Text>
              <InputNumber
                min={1}
                max={restant || undefined}
                value={montant}
                onChange={(v: number | null) => setMontant(typeof v === 'number' ? v : 0)}
                style={{ width: 160 }}
                disabled={readOnly}
              />
              <Button size="small" disabled={readOnly || restant <= 0} onClick={() => setMontant(restant)}>
                Tout le reste
              </Button>
            </Space>

            <div>
              <Typography.Text style={{ display: 'block', marginBottom: 8 }}>
                Mode de paiement
              </Typography.Text>
              <Space wrap>
                {QUICK_MODES.map((m) => {
                  const cfg = APP_CONFIG.modesPaiement.find((x) => x.value === m)
                  const sessionBlocked = !isSessionOpen && instantModes.has(m)
                  return (
                    <Button
                      key={m}
                      type={selectedMode === m ? 'primary' : 'default'}
                      disabled={readOnly || restant <= 0 || payMutation.isPending || sessionBlocked}
                      onClick={() => {
                        setSelectedMode(m)
                        if (!needsReference(m)) {
                          payMutation.mutate(m)
                        }
                      }}
                    >
                      {cfg?.label ?? m}
                    </Button>
                  )
                })}
              </Space>
              {!isSessionOpen ? (
                <Typography.Paragraph type="warning" style={{ marginTop: 8, marginBottom: 0 }}>
                  Ouvrez la session pour enregistrer un paiement espèces ou comptant (impact caisse
                  immédiat).
                </Typography.Paragraph>
              ) : null}
            </div>

            {selectedMode && needsReference(selectedMode) ? (
              <Space direction="vertical" style={{ width: '100%' }}>
                <Input
                  placeholder="Référence (chèque, virement, transaction MM…)"
                  value={reference}
                  onChange={(e: React.ChangeEvent<HTMLInputElement>) => setReference(e.target.value)}
                  disabled={readOnly}
                />
                <Button
                  type="primary"
                  loading={payMutation.isPending}
                  disabled={readOnly || restant <= 0}
                  onClick={() => selectedMode && payMutation.mutate(selectedMode)}
                >
                  Enregistrer ({selectedMode})
                </Button>
              </Space>
            ) : null}

            <WithPermission permission={PERMISSIONS.PAIEMENTS.READ}>
              <Typography.Text strong>Historique des paiements (facture)</Typography.Text>
              <Table<Paiement>
                size="small"
                rowKey="id"
                loading={histLoading}
                columns={columns}
                dataSource={historique ?? []}
                pagination={false}
                locale={{ emptyText: 'Aucun paiement' }}
              />
            </WithPermission>
          </Space>
        ) : null}
      </Card>
    </WithPermission>
  )
}
