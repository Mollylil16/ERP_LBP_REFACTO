import React from 'react'
import { Card, Col, Row, Statistic, Tabs, Table, Tag, Typography } from 'antd'
import { useQuery } from '@tanstack/react-query'
import { groupeursService } from '@services/groupeurs.service'

const { Text } = Typography

export const GroupeursEspacePage: React.FC = () => {
  const { data: dash, isLoading: l0 } = useQuery({
    queryKey: ['groupeurs', 'espace', 'dashboard'],
    queryFn: () => groupeursService.getMyDashboard(),
    refetchInterval: 120_000,
  })
  const { data: devis, isLoading: l1 } = useQuery({
    queryKey: ['groupeurs', 'espace', 'devis'],
    queryFn: () => groupeursService.myDevis(),
  })
  const { data: exp, isLoading: l2 } = useQuery({
    queryKey: ['groupeurs', 'espace', 'expeditions'],
    queryFn: () => groupeursService.myExpeditions(),
  })
  const { data: fac, isLoading: l3 } = useQuery({
    queryKey: ['groupeurs', 'espace', 'factures'],
    queryFn: () => groupeursService.myFactures(),
  })
  const { data: docs, isLoading: l4 } = useQuery({
    queryKey: ['groupeurs', 'espace', 'documents'],
    queryFn: () => groupeursService.myDocuments(),
  })

  return (
    <div>
      <Card
        size="small"
        title="Mon espace groupeur"
        loading={l0}
        style={{ marginBottom: 16 }}
      >
        <Text type="secondary">
          {dash?.groupeur ? `${dash.groupeur.code} — ${dash.groupeur.raison_sociale}` : '—'}
        </Text>
        <Row gutter={[16, 16]} style={{ marginTop: 12 }}>
          <Col xs={24} sm={8}>
            <Card size="small">
              <Statistic title="Devis" value={dash?.kpis.devis_total ?? '—'} />
            </Card>
          </Col>
          <Col xs={24} sm={8}>
            <Card size="small">
              <Statistic title="Expéditions actives" value={dash?.kpis.expeditions_actives ?? '—'} />
            </Card>
          </Col>
          <Col xs={24} sm={8}>
            <Card size="small">
              <Statistic title="Factures impayées" value={dash?.kpis.factures_impayees ?? '—'} />
            </Card>
          </Col>
        </Row>
      </Card>

      <Tabs
        items={[
          {
            key: 'devis',
            label: `Devis (${(devis ?? []).length})`,
            children: (
              <Table
                size="small"
                loading={l1}
                rowKey={(_r: any, i: number) => `d-${i}`}
                dataSource={devis ?? []}
                columns={[
                  { title: 'Numéro', dataIndex: 'numero' },
                  { title: 'Client', dataIndex: 'client_nom' },
                  { title: 'Origine', dataIndex: 'origine' },
                  { title: 'Destination', dataIndex: 'destination' },
                  { title: 'Statut', dataIndex: 'statut', render: (s: string) => <Tag>{s}</Tag> },
                ]}
                pagination={{ pageSize: 10 }}
              />
            ),
          },
          {
            key: 'exp',
            label: `Expéditions (${(exp ?? []).length})`,
            children: (
              <Table
                size="small"
                loading={l2}
                rowKey={(_r: any, i: number) => `e-${i}`}
                dataSource={exp ?? []}
                columns={[
                  { title: 'Numéro', dataIndex: 'numero_expedition' },
                  { title: 'Client', dataIndex: 'client_nom' },
                  { title: 'Origine', dataIndex: 'origine' },
                  { title: 'Destination', dataIndex: 'destination' },
                  { title: 'Statut', dataIndex: 'statut', render: (s: string) => <Tag color={s === 'litige' ? 'red' : 'blue'}>{s}</Tag> },
                ]}
                pagination={{ pageSize: 10 }}
              />
            ),
          },
          {
            key: 'fac',
            label: `Factures (${(fac ?? []).length})`,
            children: (
              <Table
                size="small"
                loading={l3}
                rowKey={(_r: any, i: number) => `f-${i}`}
                dataSource={fac ?? []}
                columns={[
                  { title: 'Numéro', dataIndex: 'numero_facture' },
                  { title: 'Client', dataIndex: 'client_nom' },
                  { title: 'Total TTC', dataIndex: 'total_ttc' },
                  { title: 'Statut paiement', dataIndex: 'statut_paiement', render: (s: string) => <Tag>{s}</Tag> },
                ]}
                pagination={{ pageSize: 10 }}
              />
            ),
          },
          {
            key: 'docs',
            label: `Documents (${(docs ?? []).length})`,
            children: (
              <Table
                size="small"
                loading={l4}
                rowKey={(_r: any, i: number) => `doc-${i}`}
                dataSource={docs ?? []}
                columns={[
                  { title: 'Type', dataIndex: 'type_document' },
                  { title: 'Nom fichier', dataIndex: 'nom_fichier' },
                  { title: 'Statut', dataIndex: 'statut', render: (s: string) => <Tag>{s}</Tag> },
                ]}
                pagination={{ pageSize: 10 }}
              />
            ),
          },
        ]}
      />
    </div>
  )
}

