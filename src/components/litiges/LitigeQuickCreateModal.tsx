import React, { useEffect } from 'react'
import { Form, Input, InputNumber, Modal, Select, message } from 'antd'
import { useMutation } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { litigesService, type CreateLitigePayload } from '@services/litiges.service'
import { useAuth } from '@hooks/useAuth'

const TYPE_OPTIONS = [
  { value: 'SERVICE_CLIENT', label: 'Service client' },
  { value: 'RETARD_LIVRAISON', label: 'Retard livraison' },
  { value: 'COLIS_ENDOMMAGE', label: 'Colis endommagé' },
  { value: 'COLIS_PERDU', label: 'Colis perdu' },
  { value: 'MONTANT_INCORRECT', label: 'Montant incorrect' },
  { value: 'AUTRE', label: 'Autre' },
]

interface LitigeQuickCreateModalProps {
  open: boolean
  onClose: () => void
  defaultClientId?: number
  defaultPhone?: string
  defaultFactureId?: number
  defaultColisRef?: string
}

type FormVals = Pick<CreateLitigePayload, 'type' | 'objet' | 'description' | 'contact_telephone'> & {
  id_client_manual?: number
}

export const LitigeQuickCreateModal: React.FC<LitigeQuickCreateModalProps> = ({
  open,
  onClose,
  defaultClientId,
  defaultPhone,
  defaultFactureId,
  defaultColisRef,
}) => {
  const [form] = Form.useForm()
  const { user } = useAuth()
  const navigate = useNavigate()

  const agenceId = user?.agency?.id ?? user?.agency_id

  const mutation = useMutation({
    mutationFn: (payload: CreateLitigePayload) => litigesService.create(payload),
    onSuccess: (res: { id?: number }) => {
      message.success('Litige créé.')
      form.resetFields()
      onClose()
      const lid = (res as { id?: number })?.id
      if (lid) navigate(`/litiges/${lid}`)
      else navigate('/litiges')
    },
    onError: () => message.error('Création impossible.'),
  })

  useEffect(() => {
    if (!open) return
    const descParts: string[] = []
    if (defaultColisRef) descParts.push(`Réf. colis : ${defaultColisRef}.`)
    if (defaultFactureId) descParts.push(`Facture #${defaultFactureId}.`)
    if (defaultPhone) descParts.push(`Contact : ${defaultPhone}.`)
    form.setFieldsValue({
      type: 'SERVICE_CLIENT',
      objet: defaultColisRef
        ? `Réclamation — colis ${defaultColisRef}`
        : 'Demande relation client',
      description:
        descParts.length > 0
          ? `${descParts.join(' ')}\n\n`
          : 'Décrivez la situation en quelques phrases (minimum 10 caractères).',
      contact_telephone: defaultPhone,
    })
  }, [open, defaultPhone, defaultFactureId, defaultColisRef, form])

  const handleOk = async () => {
    try {
      const v = await form.validateFields()
      const idClient = defaultClientId ?? v.id_client_manual
      if (!idClient) {
        message.error('Indiquez le n° client ou ouvrez la création depuis le call center.')
        return
      }
      if (!agenceId) {
        message.error('Votre profil n’est pas rattaché à une agence.')
        return
      }
      const { id_client_manual: _m, ...rest } = v
      const payload: CreateLitigePayload = {
        ...rest,
        id_client: idClient,
        id_agence: agenceId,
        ...(defaultFactureId ? { id_facture: defaultFactureId } : {}),
      }
      mutation.mutate(payload)
    } catch {
      // validation
    }
  }

  return (
    <Modal
      title="Nouveau litige"
      open={open}
      onCancel={onClose}
      onOk={() => void handleOk()}
      confirmLoading={mutation.isPending}
      width={560}
      destroyOnClose
    >
      <Form form={form} layout="vertical">
        {!defaultClientId ? (
          <Form.Item
            name="id_client_manual"
            label="N° client"
            rules={[{ required: true, message: 'Obligatoire' }]}
          >
            <InputNumber min={1} style={{ width: '100%' }} placeholder="Identifiant client LBP" />
          </Form.Item>
        ) : null}
        <Form.Item name="type" label="Type" rules={[{ required: true }]}>
          <Select options={TYPE_OPTIONS} />
        </Form.Item>
        <Form.Item name="objet" label="Objet" rules={[{ required: true, min: 5 }]}>
          <Input maxLength={200} />
        </Form.Item>
        <Form.Item
          name="description"
          label="Description"
          rules={[{ required: true, min: 10 }]}
        >
          <Input.TextArea rows={5} maxLength={4000} />
        </Form.Item>
        <Form.Item name="contact_telephone" label="Téléphone du contact">
          <Input />
        </Form.Item>
      </Form>
    </Modal>
  )
}
