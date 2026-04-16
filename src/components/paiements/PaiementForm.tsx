import React, { useEffect, useMemo, useState } from 'react'
import {
  Form,
  Input,
  Select,
  Button,
  Card,
  Row,
  Col,
  Space,
  Typography,
  InputNumber,
  Alert,
  Divider,
  Tag,
} from 'antd'
import {
  DollarOutlined,
  SaveOutlined,
  MobileOutlined,
  BankOutlined,
  WalletOutlined,
  FileTextOutlined,
  CalendarOutlined,
} from '@ant-design/icons'
import { useForm, Controller } from 'react-hook-form'
import { useFieldArray } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { APP_CONFIG } from '@constants/application'
import { CreateEncaissementDto, CreatePaiementDto, RestantAPayerInfo } from '@services/paiements.service'
import { calculerMonnaieRendue } from '@utils/calculations'
import { formatMontantWithDevise } from '@utils/format'
import { paiementsService } from '@services/paiements.service'
import { useQuery } from '@tanstack/react-query'
import { Alert as AntAlert, message } from 'antd'

const { Title, Text } = Typography
const { Option } = Select

const ligneSchema = z.object({
  montant: z.number().min(0.01, 'Le montant doit être supérieur à 0'),
  mode_paiement: z.string().min(1, 'Le mode de paiement est obligatoire'),
  reference: z.string().optional(),
})

const paiementFormSchema = z.object({
  date_paiement: z.string().min(1, 'La date de paiement est obligatoire'),
  heure_paiement: z.string().optional(),
  lignes: z.array(ligneSchema).min(1, 'Ajoutez au moins une ligne de paiement'),
})

type PaiementFormData = z.infer<typeof paiementFormSchema>

interface PaiementFormProps {
  refColis: string
  /**
   * Compat : si tu passes l’ancien handler (paiement simple), ça marchera toujours.
   * Si tu veux gérer le mix au parent, passe un handler encaissement.
   */
  onSubmit: (data: CreatePaiementDto) => void | Promise<void>
  onSubmitEncaissement?: (data: CreateEncaissementDto) => void | Promise<void>
  onCancel?: () => void
  loading?: boolean
}

// ─── Helpers ─────────────────────────────────────────────────────
const getModeConfig = (value: string) =>
  APP_CONFIG.modesPaiement.find((m) => m.value === value)

const getReferencePlaceholder = (mode: string): string => {
  switch (mode) {
    case 'wave': return 'Ex: TXN-WAVE-XXXXXXXX'
    case 'om': return 'Ex: OM-CI-XXXXXXXXXX'
    case 'cheque': return 'Numéro de chèque'
    case 'virement': return 'Référence virement bancaire'
    default: return 'Référence optionnelle'
  }
}

const getModeIcon = (mode: string) => {
  switch (mode) {
    case 'wave':
    case 'om': return <MobileOutlined />
    case 'especes': return <WalletOutlined />
    case 'cheque': return <FileTextOutlined />
    case 'virement': return <BankOutlined />
    case '30j':
    case '45j':
    case '60j':
    case '90j': return <CalendarOutlined />
    default: return <DollarOutlined />
  }
}

const getReferenceIcon = (mode: string) => {
  switch (mode) {
    case 'wave':
    case 'om': return <MobileOutlined />
    case 'virement':
    case 'cheque': return <BankOutlined />
    default: return <DollarOutlined />
  }
}

const isModeImmédiat = (mode: string) =>
  ['especes', 'comptant', 'wave', 'om', 'orange_money'].includes(mode)

const isModeNécessitantRef = (mode: string) =>
  ['wave', 'om', 'cheque', 'virement'].includes(mode)

const isModeCredit = (mode: string) =>
  ['30j', '45j', '60j', '90j'].includes(mode)

export const PaiementForm: React.FC<PaiementFormProps> = ({
  refColis,
  onSubmit,
  onSubmitEncaissement,
  onCancel,
  loading = false,
}) => {
  const [montantRecu, setMontantRecu] = useState<number>(0)
  const [monnaieRendue, setMonnaieRendue] = useState<number>(0)

  const { data: restantInfo, isLoading: isLoadingRestant } = useQuery({
    queryKey: ['paiements', 'restant', refColis],
    queryFn: () => paiementsService.calculateRestantAPayer(refColis),
    enabled: !!refColis,
  })

  const {
    control,
    handleSubmit,
    watch,
    setValue,
    formState: { errors },
  } = useForm<PaiementFormData>({
    resolver: zodResolver(paiementFormSchema),
    defaultValues: {
      date_paiement: new Date().toISOString().split('T')[0],
      heure_paiement: new Date().toTimeString().slice(0, 5),
      lignes: [{ montant: 0, mode_paiement: 'especes', reference: '' }],
    },
  })

  const { fields, append, remove, update } = useFieldArray({
    control,
    name: 'lignes',
  })

  const lignes = watch('lignes') || []
  const totalSaisi = useMemo(
    () => lignes.reduce((s, l) => s + Number(l?.montant || 0), 0),
    [lignes],
  )
  const isMix = (lignes?.length || 0) > 1

  const ligne0 = lignes?.[0]
  const mode0 = String(ligne0?.mode_paiement || 'especes')
  const montant0 = Number(ligne0?.montant || 0)
  const isEspeces0 = mode0 === 'especes' || mode0 === 'comptant'

  // Calculer monnaie rendue pour espèces/comptant
  useEffect(() => {
    if (isEspeces0 && restantInfo && montant0 > 0) {
      const monnaie = calculerMonnaieRendue(restantInfo.restant_a_payer, montant0)
      setMonnaieRendue(monnaie)
      setMontantRecu(montant0)
    } else {
      setMonnaieRendue(0)
      setMontantRecu(0)
    }
  }, [montant0, isEspeces0, restantInfo])

  // Pré-remplir le montant avec le restant à payer pour les modes immédiats
  useEffect(() => {
    if (
      restantInfo &&
      lignes?.length === 1 &&
      isModeImmédiat(mode0) &&
      Number(lignes?.[0]?.montant || 0) === 0
    ) {
      setValue('lignes.0.montant', restantInfo.restant_a_payer)
    }
  }, [restantInfo, mode0, setValue, lignes])

  const onFormSubmit = (data: PaiementFormData) => {
    // Sécurité UX: éviter un dépassement (le backend bloque aussi)
    const total = data.lignes.reduce((s, l) => s + Number(l.montant || 0), 0)
    if (restantInfo && total > restantInfo.restant_a_payer) {
      message.error('Le total saisi dépasse le reste à payer.')
      // On utilise antd message plus bas dans le composant, mais ici on reste simple.
      // (Le toast global de l'API affichera le message backend si besoin.)
      return
    }
    if (data.lignes.length === 1 || !onSubmitEncaissement) {
      const l = data.lignes[0]
      const isEspeces = l.mode_paiement === 'especes' || l.mode_paiement === 'comptant'
      const submitData: CreatePaiementDto = {
        ref_colis: refColis,
        montant: l.montant,
        mode_paiement: l.mode_paiement,
        date_paiement: data.date_paiement,
        reference: l.reference,
        monnaie_rendue: isEspeces ? monnaieRendue : undefined,
      }
      onSubmit(submitData)
      return
    }

    const submitEnc: CreateEncaissementDto = {
      ref_colis: refColis,
      date_paiement: data.date_paiement,
      lignes: data.lignes.map((l) => ({
        montant: l.montant,
        mode_paiement: l.mode_paiement,
        reference: l.reference,
      })),
    }
    onSubmitEncaissement(submitEnc)
  }

  if (isLoadingRestant) {
    return <div style={{ padding: 24 }}>Chargement des informations...</div>
  }

  const modeActuel = getModeConfig(mode0)

  return (
    <Form layout="vertical" onFinish={handleSubmit(onFormSubmit)}>

      {/* ─── RÉCAPITULATIF FINANCIER ─── */}
      {restantInfo && (
        <div style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(3, 1fr)',
          gap: 12,
          marginBottom: 16,
        }}>
          <Card size="small" style={{ textAlign: 'center', background: '#fafafa' }}>
            <Text type="secondary" style={{ fontSize: 12 }}>Montant total</Text>
            <div>
              <Title level={4} style={{ margin: 0 }}>
                {formatMontantWithDevise(restantInfo.montant_total)}
              </Title>
            </div>
          </Card>
          <Card size="small" style={{ textAlign: 'center', background: '#f6ffed' }}>
            <Text type="secondary" style={{ fontSize: 12 }}>Déjà payé</Text>
            <div>
              <Title level={4} style={{ margin: 0, color: '#52c41a' }}>
                {formatMontantWithDevise(restantInfo.montant_paye)}
              </Title>
            </div>
          </Card>
          <Card size="small" style={{ textAlign: 'center', background: '#fff2f0' }}>
            <Text type="secondary" style={{ fontSize: 12 }}>Reste à payer</Text>
            <div>
              <Title level={4} style={{ margin: 0, color: '#ff4d4f' }}>
                {formatMontantWithDevise(restantInfo.restant_a_payer)}
              </Title>
            </div>
          </Card>
        </div>
      )}

      {restantInfo?.restant_a_payer === 0 && (
        <AntAlert
          message="Ce colis est entièrement payé"
          type="success"
          showIcon
          style={{ marginBottom: 16 }}
        />
      )}

      {/* ─── FORMULAIRE ─── */}
      <Card>
        <Row gutter={16}>

          {/* Date */}
          <Col xs={24} md={12}>
            <Controller
              name="date_paiement"
              control={control}
              render={({ field }) => (
                <Form.Item
                  label="Date du paiement"
                  required
                  validateStatus={errors.date_paiement ? 'error' : ''}
                  help={errors.date_paiement?.message}
                >
                  <Input {...field} type="date" size="large" />
                </Form.Item>
              )}
            />
          </Col>

          {/* Heure */}
          <Col xs={24} md={12}>
            <Controller
              name="heure_paiement"
              control={control}
              render={({ field }) => (
                <Form.Item label="Heure du paiement">
                  <Input {...field} type="time" size="large" />
                </Form.Item>
              )}
            />
          </Col>

          {/* Lignes de paiement (mix) */}
          <Col xs={24}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
              <Title level={5} style={{ margin: 0 }}>Lignes de paiement</Title>
              <Space>
                <Button
                  size="small"
                  onClick={() => append({ montant: 0, mode_paiement: 'wave', reference: '' } as any)}
                >
                  + Ajouter une ligne
                </Button>
                {fields.length > 1 && (
                  <Tag color="purple">Paiement mixte</Tag>
                )}
              </Space>
            </div>
            <Text type="secondary">
              Exemple : une partie en espèces + une partie sur Wave.
            </Text>
          </Col>

          {fields.map((f, idx) => {
            const mode = String(lignes?.[idx]?.mode_paiement || '')
            const isCredit = isModeCredit(mode)
            const needsRef = isModeNécessitantRef(mode)
            const isEspeces = mode === 'especes' || mode === 'comptant'
            return (
              <React.Fragment key={f.id}>
                <Col xs={24} md={8}>
                  <Controller
                    name={`lignes.${idx}.mode_paiement` as const}
                    control={control}
                    render={({ field }) => (
                      <Form.Item label={`Mode (ligne ${idx + 1})`} required>
                        <Select {...field} size="large" optionLabelProp="label">
                          {APP_CONFIG.modesPaiement.map((m) => (
                            <Option key={m.value} value={m.value} label={m.label}>
                              <Space>
                                <Tag color={m.color} icon={getModeIcon(m.value)} style={{ minWidth: 120 }}>
                                  {m.label}
                                </Tag>
                              </Space>
                            </Option>
                          ))}
                        </Select>
                      </Form.Item>
                    )}
                  />
                </Col>
                <Col xs={24} md={8}>
                  <Controller
                    name={`lignes.${idx}.montant` as const}
                    control={control}
                    render={({ field }) => (
                      <Form.Item label={`Montant (ligne ${idx + 1})`} required>
                        <InputNumber
                          {...field}
                          value={field.value as any}
                          onChange={(value: number | null) => field.onChange(value || 0)}
                          min={0.01}
                          max={restantInfo?.restant_a_payer}
                          style={{ width: '100%' }}
                          size="large"
                          prefix={<DollarOutlined />}
                          formatter={(value: any) =>
                            `${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, ' ')
                          }
                        />
                      </Form.Item>
                    )}
                  />
                </Col>
                <Col xs={24} md={8}>
                  <Controller
                    name={`lignes.${idx}.reference` as const}
                    control={control}
                    render={({ field }) => (
                      <Form.Item
                        label={needsRef ? `Référence (ligne ${idx + 1})` : `Référence (optionnel)`}
                        required={needsRef}
                      >
                        <Input
                          {...field}
                          prefix={getReferenceIcon(mode)}
                          placeholder={getReferencePlaceholder(mode)}
                          size="large"
                        />
                      </Form.Item>
                    )}
                  />
                </Col>

                {isCredit && (
                  <Col xs={24}>
                    <AntAlert
                      message={`Paiement différé — ${getModeConfig(mode)?.label ?? mode}`}
                      description="Le client paiera dans les délais convenus. La référence de l'accord est recommandée."
                      type="warning"
                      showIcon
                      style={{ marginBottom: 8 }}
                    />
                  </Col>
                )}

                {idx > 0 && (
                  <Col xs={24}>
                    <div style={{ textAlign: 'right', marginTop: -8, marginBottom: 12 }}>
                      <Button danger size="small" onClick={() => remove(idx)}>
                        Supprimer la ligne {idx + 1}
                      </Button>
                    </div>
                  </Col>
                )}
              </React.Fragment>
            )
          })}

          {/* Total saisi */}
          <Col xs={24}>
            <div style={{ marginTop: 6, marginBottom: 6 }}>
              <Tag color={restantInfo && totalSaisi > restantInfo.restant_a_payer ? 'red' : 'blue'}>
                Total saisi : {formatMontantWithDevise(totalSaisi)}
              </Tag>
              {restantInfo && (
                <Tag color="default">
                  Reste à payer : {formatMontantWithDevise(restantInfo.restant_a_payer)}
                </Tag>
              )}
            </div>
          </Col>

          {/* Monnaie rendue — espèces seulement */}
          {isEspeces0 && montantRecu > 0 && restantInfo && (
            <>
              <Col xs={24}>
                <Divider style={{ margin: '8px 0' }} />
              </Col>
              <Col xs={24} sm={12}>
                <div style={{ background: '#f0f2f5', padding: 16, borderRadius: 8 }}>
                  <Text type="secondary" style={{ fontSize: 12 }}>Montant reçu</Text>
                  <Title level={4} style={{ margin: 0 }}>
                    {formatMontantWithDevise(montantRecu)}
                  </Title>
                </div>
              </Col>
              <Col xs={24} sm={12}>
                <div style={{
                  background: monnaieRendue > 0 ? '#e6f7ff' : '#f6ffed',
                  padding: 16,
                  borderRadius: 8,
                }}>
                  <Text type="secondary" style={{ fontSize: 12 }}>Monnaie à rendre</Text>
                  <Title level={4} style={{ margin: 0, color: monnaieRendue > 0 ? '#1890ff' : '#52c41a' }}>
                    {formatMontantWithDevise(monnaieRendue)}
                  </Title>
                </div>
              </Col>
            </>
          )}
        </Row>

        <Space style={{ marginTop: 24 }}>
          <Button
            type="primary"
            htmlType="submit"
            icon={<SaveOutlined />}
            size="large"
            loading={loading}
            disabled={restantInfo?.restant_a_payer === 0}
          >
            {isMix ? "Enregistrer l'encaissement" : 'Enregistrer le paiement'}
          </Button>
          <Button size="large" onClick={onCancel}>
            Annuler
          </Button>
        </Space>
      </Card>
    </Form>
  )
}
