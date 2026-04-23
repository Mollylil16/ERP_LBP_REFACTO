import React from 'react'
import { Card, Alert, Row, Col, Statistic, Table, Tag, Typography, Space } from 'antd'
import { useQuery } from '@tanstack/react-query'
import type { Dayjs } from 'dayjs'
import {
  supervisionService,
  type SupervisionAnomaliesPayload,
  type SupervisionAnomaliesUnavailable,
} from '@services/supervision.service'

const { Text, Paragraph } = Typography

function isUnavailable(
  d: SupervisionAnomaliesPayload | SupervisionAnomaliesUnavailable,
): d is SupervisionAnomaliesUnavailable {
  return 'donnees' in d && d.donnees === null
}

type Props = { range: [Dayjs, Dayjs] }

export const SupervisionAnomaliesTab: React.FC<Props> = ({ range }) => {
  const debut = range[0].format('YYYY-MM-DD')
  const fin = range[1].format('YYYY-MM-DD')
  const { data, isLoading, error } = useQuery({
    queryKey: ['supervision', 'anomalies', debut, fin],
    queryFn: () => supervisionService.getAnomalies(debut, fin),
    refetchInterval: 60_000,
  })

  if (error) {
    return <Alert type="warning" showIcon message="Anomalies indisponibles (caisse / données)." />
  }

  if (!data) {
    return (
      <Card size="small" title="Anomalies & contrôles" loading={isLoading}>
        <Text type="secondary">Chargement…</Text>
      </Card>
    )
  }

  if (isUnavailable(data)) {
    return (
      <Card size="small" title="Anomalies & contrôles">
        <Alert type="info" showIcon message={data.message} />
      </Card>
    )
  }

  const { summary, anomalies, range: rg } = data
  const dbl = anomalies.doublons_paiements ?? []
  const inc = anomalies.incoherences_montants_factures ?? []
  const trous = anomalies.trous_sequence_factures ?? []
  const rien =
    summary.doublons === 0 && summary.incoherences === 0 && summary.sequences_avec_trous === 0

  return (
    <Space direction="vertical" size="middle" style={{ width: '100%' }}>
      <Card size="small" title="Période d’analyse" loading={isLoading}>
        <Paragraph style={{ marginBottom: 0 }} type="secondary">
          Période d’analyse (période globale en tête de page) :{' '}
          <Text strong>
            {rg.date_debut} → {rg.date_fin}
          </Text>
        </Paragraph>
        {rien && (
          <Alert
            style={{ marginTop: 12 }}
            type="success"
            showIcon
            message="Aucune anomalie listée dans les contrôles automatisés sur cette période."
          />
        )}
      </Card>

      <Row gutter={[16, 16]}>
        <Col xs={24} sm={8}>
          <Card size="small">
            <Statistic title="Doublons de paiements (même facture / montant / jour)" value={summary.doublons} />
          </Card>
        </Col>
        <Col xs={24} sm={8}>
          <Card size="small">
            <Statistic title="Incohérences montants factures" value={summary.incoherences} />
          </Card>
        </Col>
        <Col xs={24} sm={8}>
          <Card size="small">
            <Statistic title="Préfixes de numérotation avec trous" value={summary.sequences_avec_trous} />
          </Card>
        </Col>
      </Row>

      <Card size="small" title="Doublons de paiements">
        <Table
          size="small"
          pagination={{ pageSize: 8 }}
          rowKey={(
            _r: SupervisionAnomaliesPayload['anomalies']['doublons_paiements'][number],
            i: number,
          ) => `d-${i}`}
          dataSource={dbl}
          columns={[
            { title: 'Facture', dataIndex: 'id_facture', width: 100 },
            { title: 'Montant', dataIndex: 'montant' },
            { title: 'Mode', dataIndex: 'mode_paiement', width: 120 },
            { title: 'Date paiement', dataIndex: 'date_paiement', width: 160 },
            { title: 'Occurrences', dataIndex: 'occurrences', width: 100 },
          ]}
        />
      </Card>

      <Card size="small" title="Incohérences montants (payé vs TTC)">
        <Table
          size="small"
          pagination={{ pageSize: 8 }}
          rowKey="id"
          dataSource={inc}
          columns={[
            { title: 'N° facture', dataIndex: 'num_facture' },
            {
              title: 'TTC (F)',
              dataIndex: 'montant_ttc',
              render: (v: number) => Number(v).toLocaleString('fr-FR'),
            },
            {
              title: 'Payé (F)',
              dataIndex: 'montant_paye',
              render: (v: number) => Number(v).toLocaleString('fr-FR'),
            },
            {
              title: 'Écart',
              key: 'ecart',
              render: (_: unknown, r: { montant_ttc: number; montant_paye: number }) => {
                const d = r.montant_paye - r.montant_ttc
                return <Tag color={d > 0 ? 'orange' : 'red'}>{d.toLocaleString('fr-FR')}</Tag>
              },
            },
          ]}
        />
      </Card>

      <Card size="small" title="Trous de séquence (numéros de facture)">
        <Text type="secondary" style={{ display: 'block', marginBottom: 12 }}>
          Pour chaque préfixe, numéros manquants entre le min et le max observé (plafonné côté serveur).
        </Text>
        <Table
          size="small"
          pagination={{ pageSize: 6 }}
          rowKey="prefix"
          dataSource={trous}
          columns={[
            { title: 'Préfixe', dataIndex: 'prefix' },
            {
              title: 'Manquants (aperçu)',
              dataIndex: 'missing',
              render: (m: number[]) => (
                <span style={{ fontSize: 12 }}>{(m ?? []).slice(0, 40).join(', ')}</span>
              ),
            },
            {
              title: 'Nb manquants',
              key: 'n',
              width: 120,
              render: (_: unknown, r: { missing: number[] }) => (r.missing?.length ?? 0),
            },
          ]}
        />
      </Card>
    </Space>
  )
}
