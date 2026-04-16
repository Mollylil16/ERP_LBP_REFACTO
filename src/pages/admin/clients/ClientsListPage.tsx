import React, { useState } from 'react'
import { Modal, Typography, Descriptions } from 'antd'
import { useSearchParams } from 'react-router-dom'
import { ClientList } from '@components/clients/ClientList'
import { ClientForm } from '@components/clients/ClientForm'
import { ClientColis, CreateClientDto, UpdateClientDto } from '@services/clients.service'
import { useCreateClient, useUpdateClient, useClientHistory } from '@hooks/useClients'
import { formatDate, formatPhone } from '@utils/format'

const { Title } = Typography

export const ClientsListPage: React.FC = () => {
  const [searchParams] = useSearchParams()
  const initialSearch = searchParams.get('search') ?? undefined
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [selectedClient, setSelectedClient] = useState<ClientColis | null>(null)
  const [isViewMode, setIsViewMode] = useState(false)

  const createMutation = useCreateClient()
  const updateMutation = useUpdateClient()
  const { data: clientHistory } = useClientHistory(selectedClient?.id || null)

  const handleCreate = () => {
    setSelectedClient(null)
    setIsViewMode(false)
    setIsModalOpen(true)
  }

  const handleEdit = (client: ClientColis) => {
    setSelectedClient(client)
    setIsViewMode(false)
    setIsModalOpen(true)
  }

  const handleView = (client: ClientColis) => {
    setSelectedClient(client)
    setIsViewMode(true)
    setIsModalOpen(true)
  }

  const handleSubmit = async (data: CreateClientDto | UpdateClientDto) => {
    try {
      if (selectedClient) {
        await updateMutation.mutateAsync({
          id: selectedClient.id,
          data: data as UpdateClientDto,
        })
      } else {
        await createMutation.mutateAsync(data as CreateClientDto)
      }
      setIsModalOpen(false)
      setSelectedClient(null)
    } catch (error) {
      console.error('Error submitting form:', error)
    }
  }

  const handleCancel = () => {
    setIsModalOpen(false)
    setSelectedClient(null)
    setIsViewMode(false)
  }

  return (
    <div>
      <Title level={2}>Gestion des Clients Expéditeurs</Title>

      <ClientList
        onCreate={handleCreate}
        onEdit={handleEdit}
        onView={handleView}
        initialSearch={initialSearch}
      />

      <Modal
        title={
          isViewMode
            ? `Détails Client - ${selectedClient?.nom_exp || ''}`
            : selectedClient
            ? 'Modifier Client'
            : 'Nouveau Client Expéditeur'
        }
        open={isModalOpen}
        onCancel={handleCancel}
        footer={null}
        width={isViewMode ? '80%' : '70%'}
        style={{ top: 20 }}
      >
        {isViewMode && selectedClient ? (
          <div>
            <Descriptions title="Informations Client" bordered column={2} style={{ marginBottom: 24 }}>
              <Descriptions.Item label="Nom complet">{selectedClient.nom_exp}</Descriptions.Item>
              <Descriptions.Item label="Type pièce">{selectedClient.type_piece_exp}</Descriptions.Item>
              <Descriptions.Item label="N° Pièce d'identité">{selectedClient.num_piece_exp}</Descriptions.Item>
              <Descriptions.Item label="Téléphone">{formatPhone(selectedClient.tel_exp)}</Descriptions.Item>
              <Descriptions.Item label="Email">{selectedClient.email_exp || '-'}</Descriptions.Item>
              <Descriptions.Item label="Date enregistrement">{formatDate(selectedClient.date_enrg)}</Descriptions.Item>
            </Descriptions>

            {clientHistory && clientHistory.length > 0 && (
              <div>
                <Title level={4}>Historique des Colis</Title>
                <p>Historique à implémenter - Liste des colis du client</p>
              </div>
            )}
          </div>
        ) : (
          <ClientForm
            onSubmit={handleSubmit}
            initialData={selectedClient || undefined}
            loading={createMutation.isPending || updateMutation.isPending}
          />
        )}
      </Modal>
    </div>
  )
}
