import React, { useEffect, useState } from 'react'
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
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { APP_CONFIG } from '@constants/application'
import { CreatePaiementDto, RestantAPayerInfo } from '@services/paiements.service'
import { calculerMonnaieRendue } from '@utils/calculations'
import { formatMontantWithDevise } from '@utils/format'
import { paiementsService } from '@services/paiements.service'
import { useQuery } from '@tanstack/react-query'

const { Title, Text } = Typography
const { Option } = Select

const paiementFormSchema = z.object({
  montant: z.number().min(0.01, 'Le montant doit être supérieur à 0'),
  mode_paiement: z.string().min(1, 'Le mode de paiement est obligatoire'),
  date_paiement: z.string().min(1, 'La date de paiement est obligatoire'),
  heure_paiement: z.string().optional(),
  reference: z.string().optional(),
  nom_client: z.string().optional(),
})

type PaiementFormData = z.infer<typeof paiementFormSchema>

interface PaiementFormProps {
  refColis: string
  onSubmit: (data: CreatePaiementDto) => void | Promise<void>
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
  ['especes', 'comptant', 'wave', 'om'].includes(mode)

const isModeNécessitantRef = (mode: string) =>
  ['wave', 'om', 'cheque', 'virement'].includes(mode)

const isModeCredit = (mode: string) =>
  ['30j', '45j', '60j', '90j'].includes(mode)

export const PaiementForm: React.FC<PaiementFormProps> = ({
  refColis,
  onSubmit,
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
      montant: 0,
      mode_paiement: 'especes',
      date_paiement: new Date().toISOString().split('T')[0],
      heure_paiement: new Date().toTimeString().slice(0, 5),
      reference: '',
      nom_client: '',
    },
  })

  const montant = watch('montant') || 0
  const modePaiement = watch('mode_paiement')
  const isEspeces = modePaiement === 'especes' || modePaiement === 'comptant'

  // Calculer monnaie rendue pour espèces/comptant
  useEffect(() => {
    if (isEspeces && restantInfo && montant > 0) {
      const monnaie = calculerMonnaieRendue(restantInfo.restant_a_payer, montant)
      setMonnaieRendue(monnaie)
      setMontantRecu(montant)
    } else {
      setMonnaieRendue(0)
      setMontantRecu(0)
    }
  }, [montant, isEspeces, restantInfo])

  // Pré-remplir le montant avec le restant à payer pour les modes immédiats
  useEffect(() => {
    if (restantInfo && isModeImmédiat(modePaiement) && montant === 0) {
      setValue('montant', restantInfo.restant_a_payer)
    }
  }, [restantInfo, modePaiement, setValue, montant])

  const onFormSubmit = (data: PaiementFormData) => {
    const submitData: CreatePaiementDto = {
      ref_colis: refColis,
      montant: data.montant,
      mode_paiement: data.mode_paiement, // valeur enum : 'especes', 'wave', 'om', etc.
      date_paiement: data.date_paiement,
      reference: data.reference,
      monnaie_rendue: isEspeces ? monnaieRendue : undefined,
    }
    onSubmit(submitData)
  }

  if (isLoadingRestant) {
    return <div style={{ padding: 24 }}>Chargement des informations...</div>
  }

  const modeActuel = getModeConfig(modePaiement)

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
        <Alert
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

          {/* Mode de paiement */}
          <Col xs={24}>
            <Controller
              name="mode_paiement"
              control={control}
              render={({ field }) => (
                <Form.Item
                  label="Mode de paiement"
                  required
                  validateStatus={errors.mode_paiement ? 'error' : ''}
                  help={errors.mode_paiement?.message}
                >
                  <Select {...field} size="large" optionLabelProp="label">
                    {APP_CONFIG.modesPaiement.map((mode) => (
                      <Option
                        key={mode.value}
                        value={mode.value}
                        label={mode.label}
                      >
                        <Space>
                          <Tag color={mode.color} icon={getModeIcon(mode.value)} style={{ minWidth: 120 }}>
                            {mode.label}
                          </Tag>
                          {isModeCredit(mode.value) && (
                            <Text type="secondary" style={{ fontSize: 12 }}>
                              Paiement différé
                            </Text>
                          )}
                        </Space>
                      </Option>
                    ))}
                  </Select>
                </Form.Item>
              )}
            />
          </Col>

          {/* Alerte mode crédit */}
          {isModeCredit(modePaiement) && (
            <Col xs={24}>
              <Alert
                message={`Paiement différé — ${modeActuel?.label}`}
                description="Le client paiera dans les délais convenus. La référence de l'accord est recommandée."
                type="warning"
                showIcon
                style={{ marginBottom: 8 }}
              />
            </Col>
          )}

          {/* Montant */}
          <Col xs={24} md={12}>
            <Controller
              name="montant"
              control={control}
              render={({ field }) => (
                <Form.Item
                  label={isEspeces ? 'Montant reçu' : 'Montant encaissé'}
                  required
                  validateStatus={errors.montant ? 'error' : ''}
                  help={errors.montant?.message}
                >
                  <InputNumber
                    {...field}
                    value={field.value}
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
                  {restantInfo && (
                    <Text type="secondary" style={{ display: 'block', marginTop: 4, fontSize: 12 }}>
                      Max : {formatMontantWithDevise(restantInfo.restant_a_payer)}
                    </Text>
                  )}
                </Form.Item>
              )}
            />
          </Col>

          {/* Référence de transaction */}
          <Col xs={24} md={12}>
            <Controller
              name="reference"
              control={control}
              render={({ field }) => (
                <Form.Item
                  label={
                    isModeNécessitantRef(modePaiement)
                      ? `Référence de transaction ${modePaiement === 'wave' ? 'Wave' : modePaiement === 'om' ? 'Orange Money' : ''}`
                      : 'Référence (optionnel)'
                  }
                  required={isModeNécessitantRef(modePaiement)}
                >
                  <Input
                    {...field}
                    prefix={getReferenceIcon(modePaiement)}
                    placeholder={getReferencePlaceholder(modePaiement)}
                    size="large"
                  />
                  {(modePaiement === 'wave' || modePaiement === 'om') && (
                    <Text type="secondary" style={{ fontSize: 11, marginTop: 4, display: 'block' }}>
                      Entrez le numéro de transaction visible dans le SMS de confirmation {modePaiement === 'wave' ? 'Wave' : 'Orange Money'}
                    </Text>
                  )}
                </Form.Item>
              )}
            />
          </Col>

          {/* Monnaie rendue — espèces seulement */}
          {isEspeces && montantRecu > 0 && restantInfo && (
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
            Enregistrer le paiement
          </Button>
          <Button size="large" onClick={onCancel}>
            Annuler
          </Button>
        </Space>
      </Card>
    </Form>
  )
}
