import React from 'react'
import { Alert, Button, Card, Col, Row, Space, Statistic, Table, Tag, Typography, message } from 'antd'
import { BulbOutlined } from '@ant-design/icons'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
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
  const qc = useQueryClient()

  const { data, isLoading, error } = useQuery({
    queryKey: ['supervision', 'anomalies', debut, fin],
    queryFn: () => supervisionService.getAnomalies(debut, fin),
    refetchInterval: 120_000,
  })

  const autoSignalerMut = useMutation({
    mutationFn: () => supervisionService.autoSignalerAnomalies(debut, fin),
    onSuccess: (res) => {
      if (res.signalements_crees > 0) {
        message.success(`${res.signalements_crees} signalement(s) créé(s) automatiquement`)
      } else {
        message.info('Aucun nouveau signalement — anomalies déjà signalées dans les 24 h')
      }
      qc.invalidateQueries({ queryKey: ['supervision', 'signalements'] })
    },
    onError: () => message.error('Erreur lors de l\'auto-signalement'),
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
  const totalAnomalies = summary.doublons + summary.incoherences + summary.sequences_avec_trous
  const rien = totalAnomalies === 0

  return (
    <Space direction="vertical" size="middle" style={{ width: '100%' }}>
      {/* Bannière critique si anomalies détectées */}
      {!rien && (
        <Alert
          type="error"
          showIcon
          message={`${totalAnomalies} anomalie(s) détectée(s) sur la période — vérification requise`}
          description={
            <Space wrap style={{ marginTop: 4 }}>
              {summary.doublons > 0 && (
                <Tag color="red">{summary.doublons} doublon(s) de paiement</Tag>
              )}
              {summary.incoherences > 0 && (
                <Tag color="orange">{summary.incoherences} incohérence(s) de montant</Tag>
              )}
              {summary.sequences_avec_trous > 0 && (
                <Tag color="purple">{summary.sequences_avec_trous} préfixe(s) avec trous de séquence</Tag>
              )}
            </Space>
          }
        />
      )}

      {/* Bouton auto-signalement */}
      <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
        <Button
          icon={<BulbOutlined />}
          loading={autoSignalerMut.isPending}
          onClick={() => autoSignalerMut.mutate()}
          disabled={rien}
          type={rien ? 'default' : 'primary'}
          danger={!rien}
        >
          Auto-signaler les anomalies critiques
        </Button>
      </div>

      {/* En-tête période */}
      <Card size="small" title="Période d'analyse" loading={isLoading}>
        <Paragraph style={{ marginBottom: 0 }} type="secondary">
          Données analysées :{' '}
          <Text strong>
            {rg.date_debut} → {rg.date_fin}
          </Text>
        </Paragraph>
        {rien && (
          <Alert
            style={{ marginTop: 12 }}
            type="success"
            showIcon
            message="Aucune anomalie détectée sur cette période — contrôles automatisés au vert."
          />
        )}
      </Card>

      {/* Compteurs */}
      <Row gutter={[16, 16]}>
        <Col xs={24} sm={8}>
          <Card size="small">
            <Statistic
              title="Doublons de paiements"
              value={summary.doublons}
              valueStyle={{ color: summary.doublons > 0 ? '#cf1322' : '#3f8600' }}
            />
            <Text type="secondary" style={{ fontSize: 11 }}>
              Même facture / montant / jour
            </Text>
          </Card>
        </Col>
        <Col xs={24} sm={8}>
          <Card size="small">
            <Statistic
              title="Incohérences montants"
              value={summary.incoherences}
              valueStyle={{ color: summary.incoherences > 0 ? '#d46b08' : '#3f8600' }}
            />
            <Text type="secondary" style={{ fontSize: 11 }}>
              Montant payé ≠ TTC facture
            </Text>
          </Card>
        </Col>
        <Col xs={24} sm={8}>
          <Card size="small">
            <Statistic
              title="Préfixes avec trous"
              value={summary.sequences_avec_trous}
              valueStyle={{ color: summary.sequences_avec_trous > 0 ? '#531dab' : '#3f8600' }}
            />
            <Text type="secondary" style={{ fontSize: 11 }}>
              Numérotation de factures
            </Text>
          </Card>
        </Col>
      </Row>

      {/* Doublons */}
      <Card
        size="small"
        title={
          <span>
            Doublons de paiements{' '}
            {summary.doublons > 0 && <Tag color="red">{summary.doublons}</Tag>}
          </span>
        }
      >
        <Table
          size="small"
          pagination={{ pageSize: 8 }}
          rowKey={(_r: SupervisionAnomaliesPayload['anomalies']['doublons_paiements'][number], i: number) => `d-${i}`}
          dataSource={dbl}
          rowClassName={() => (dbl.length > 0 ? 'lbp-row-danger' : '')}
          columns={[
            { title: 'Facture', dataIndex: 'id_facture', width: 100 },
            {
              title: 'Montant',
              dataIndex: 'montant',
              render: (v: string | number) =>
                v != null ? `${Number(v).toLocaleString('fr-FR')} F` : '—',
            },
            { title: 'Mode', dataIndex: 'mode_paiement', width: 120 },
            { title: 'Date paiement', dataIndex: 'date_paiement', width: 160 },
            {
              title: 'Occurrences',
              dataIndex: 'occurrences',
              width: 110,
              render: (v: string | number) => (
                <Tag color={Number(v) > 2 ? 'red' : 'orange'}>{v}</Tag>
              ),
            },
          ]}
        />
      </Card>

      {/* Incohérences montants */}
      <Card
        size="small"
        title={
          <span>
            Incohérences montants (payé vs TTC){' '}
            {summary.incoherences > 0 && <Tag color="orange">{summary.incoherences}</Tag>}
          </span>
        }
      >
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
                return (
                  <Tag color={d > 0 ? 'orange' : 'red'}>
                    {d > 0 ? '+' : ''}{d.toLocaleString('fr-FR')} F
                  </Tag>
                )
              },
            },
          ]}
        />
      </Card>

      {/* Trous de séquence */}
      <Card
        size="small"
        title={
          <span>
            Trous de séquence (numéros de facture){' '}
            {summary.sequences_avec_trous > 0 && (
              <Tag color="purple">{summary.sequences_avec_trous} préfixe(s)</Tag>
            )}
          </span>
        }
      >
        <Text type="secondary" style={{ display: 'block', marginBottom: 12 }}>
          Pour chaque préfixe, numéros manquants entre le min et le max observé (plafonné côté
          serveur).
        </Text>
        <Table
          size="small"
          pagination={{ pageSize: 6 }}
          rowKey="prefix"
          dataSource={trous}
          rowClassName={(r: { missing: number[] }) =>
            (r.missing?.length ?? 0) > 10 ? 'lbp-row-danger' : 'lbp-row-warning'
          }
          columns={[
            { title: 'Préfixe', dataIndex: 'prefix' },
            {
              title: 'Manquants (aperçu)',
              dataIndex: 'missing',
              render: (m: number[]) => (
                <Text style={{ fontSize: 11 }}>
                  {(m ?? []).slice(0, 30).join(', ')}
                  {(m ?? []).length > 30 ? ` … (+${(m ?? []).length - 30} autres)` : ''}
                </Text>
              ),
            },
            {
              title: 'Nb manquants',
              key: 'n',
              width: 120,
              sorter: (a: { missing: number[] }, b: { missing: number[] }) =>
                (a.missing?.length ?? 0) - (b.missing?.length ?? 0),
              render: (_: unknown, r: { missing: number[] }) => {
                const n = r.missing?.length ?? 0
                return <Tag color={n > 10 ? 'red' : 'orange'}>{n}</Tag>
              },
            },
          ]}
        />
      </Card>
    </Space>
  )
}
