import React from 'react'
import { Card, Spin, Table, Typography } from 'antd'
import { useQuery } from '@tanstack/react-query'
import { exploitationService } from '@services/exploitation.service'

const { Text } = Typography

/**
 * Bloc réutilisable : recettes du jour par devise (paiements validés).
 */
export const RecettesDuJourCard: React.FC = () => {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['credits', 'recettes-aujourdhui'],
    queryFn: () => exploitationService.getRecettesAujourdhui(),
    staleTime: 60_000,
  })

  if (isLoading) {
    return (
      <Card size="small" style={{ marginBottom: 16 }}>
        <Spin />
      </Card>
    )
  }
  if (isError || !data) {
    return null
  }

  return (
    <Card
      size="small"
      title="Recettes du jour (paiements validés)"
      style={{ marginBottom: 16 }}
    >
      <Text type="secondary" style={{ display: 'block', marginBottom: 8, fontSize: 12 }}>
        Le total global additionne des montants en devises différentes ; seul le détail par ligne
        est comparable devise par devise.
      </Text>
      <div style={{ marginBottom: 8 }}>
        <Text strong>Total (somme brute) : </Text>
        <Text>{data.recettesJour.toLocaleString('fr-FR', { maximumFractionDigits: 2 })}</Text>
      </div>
      {data.recettesJourParDevise.length > 0 ? (
        <Table<{
          devise: string
          total: number
        }>
          size="small"
          pagination={false}
          rowKey="devise"
          dataSource={data.recettesJourParDevise}
          columns={[
            { title: 'Devise', dataIndex: 'devise', width: 90 },
            {
              title: 'Montant',
              dataIndex: 'total',
              align: 'right' as const,
              render: (v: number) =>
                v.toLocaleString('fr-FR', { maximumFractionDigits: 2 }),
            },
          ]}
        />
      ) : (
        <Text type="secondary">Aucun paiement validé aujourd’hui</Text>
      )}
    </Card>
  )
}
