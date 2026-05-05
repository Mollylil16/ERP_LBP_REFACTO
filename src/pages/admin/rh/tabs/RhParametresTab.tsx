import React, { useState } from 'react'
import {
  Card, Form, InputNumber, Button, message, Table, Space,
  Divider, Alert, Typography, Row, Col,
} from 'antd'
import { SaveOutlined, SettingOutlined } from '@ant-design/icons'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { rhService, RhConfigPaie } from '@services/rh.service'
import { usePermissions } from '@hooks/usePermissions'
import { PERMISSIONS } from '@constants/permissions'
import dayjs from 'dayjs'

const { Title, Text } = Typography

const pct = (n: number) => `${(n * 100).toFixed(2)} %`
const fmt = (n: number) => Math.round(n).toLocaleString('fr-FR') + ' FCFA'

export const RhParametresTab: React.FC = () => {
  const { hasPermission } = usePermissions()
  const qc = useQueryClient()
  const canUpdate = hasPermission(PERMISSIONS.RH.PAIE_UPDATE)

  const [form] = Form.useForm()
  const [tranchesForm] = Form.useForm()
  const [editingTranches, setEditingTranches] = useState(false)

  const { data: config, isLoading } = useQuery<RhConfigPaie>({
    queryKey: ['rh-paie-config'],
    queryFn: () => rhService.getPaieConfig(),
    onSuccess: (data: RhConfigPaie) => form.setFieldsValue({
      smig_mensuel: data.smig_mensuel,
      cnps_retraite_salarial: data.cnps_retraite_salarial * 100,
      cnps_retraite_patronal: data.cnps_retraite_patronal * 100,
      cnps_retraite_plafond_annuel: data.cnps_retraite_plafond_annuel,
      cnps_at_patronal: data.cnps_at_patronal * 100,
      cnps_famille_patronal: data.cnps_famille_patronal * 100,
      cnps_famille_plafond_mensuel: data.cnps_famille_plafond_mensuel,
      cmu_salarial: data.cmu_salarial * 100,
      cmu_patronal: data.cmu_patronal * 100,
      cn_taux: data.cn_taux * 100,
    }),
  } as Parameters<typeof useQuery<RhConfigPaie>>[0])

  const updateMut = useMutation({
    mutationFn: (data: Partial<RhConfigPaie>) => rhService.upsertPaieConfig(data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['rh-paie-config'] })
      message.success('Configuration mise à jour')
    },
    onError: () => message.error('Erreur lors de la mise à jour'),
  })

  const handleSubmit = (vals: Record<string, number>) => {
    updateMut.mutate({
      annee_mois: config?.annee_mois ?? 'DEFAULT',
      smig_mensuel: vals.smig_mensuel,
      cnps_retraite_salarial: vals.cnps_retraite_salarial / 100,
      cnps_retraite_patronal: vals.cnps_retraite_patronal / 100,
      cnps_retraite_plafond_annuel: vals.cnps_retraite_plafond_annuel,
      cnps_at_patronal: vals.cnps_at_patronal / 100,
      cnps_famille_patronal: vals.cnps_famille_patronal / 100,
      cnps_famille_plafond_mensuel: vals.cnps_famille_plafond_mensuel,
      cmu_salarial: vals.cmu_salarial / 100,
      cmu_patronal: vals.cmu_patronal / 100,
      cn_taux: vals.cn_taux / 100,
    })
  }

  const tranches = config?.its_tranches ?? []

  const colonnesTranches = [
    {
      title: 'Tranche min (FCFA)',
      dataIndex: 'min',
      key: 'min',
      render: (v: number) => fmt(v),
    },
    {
      title: 'Tranche max (FCFA)',
      dataIndex: 'max',
      key: 'max',
      render: (v: number | null) => v === null ? '∞' : fmt(v),
    },
    {
      title: 'Taux ITS',
      dataIndex: 'taux',
      key: 'taux',
      render: (v: number) => `${(v * 100).toFixed(0)} %`,
    },
  ]

  return (
    <div>
      <Alert
        type="info"
        showIcon
        message="Ces paramètres s'appliquent au calcul automatique de la paie. Modifiez-les uniquement lors de changements réglementaires officiels."
        style={{ marginBottom: 16 }}
      />

      <Row gutter={[16, 16]}>
        <Col xs={24} lg={14}>
          <Card
            title={<span><SettingOutlined style={{ marginRight: 8 }} />Taux légaux (Code du Travail CI)</span>}
            loading={isLoading}
            extra={
              canUpdate && (
                <Button
                  type="primary"
                  icon={<SaveOutlined />}
                  loading={updateMut.isPending}
                  onClick={() => form.validateFields().then(handleSubmit)}
                >
                  Enregistrer
                </Button>
              )
            }
          >
            <Form form={form} layout="vertical" disabled={!canUpdate}>
              <Divider orientation="left" plain>SMIG (Art. 31 CDT)</Divider>
              <Form.Item name="smig_mensuel" label="SMIG mensuel (FCFA)" rules={[{ required: true }]}>
                <InputNumber min={0} style={{ width: '100%' }} formatter={(v: number | string | undefined) => String(v ?? '').replace(/\B(?=(\d{3})+(?!\d))/g, ' ')} />
              </Form.Item>

              <Divider orientation="left" plain>CNPS Retraite</Divider>
              <Row gutter={8}>
                <Col xs={8}>
                  <Form.Item name="cnps_retraite_salarial" label="Salarial (%)">
                    <InputNumber min={0} max={100} step={0.1} precision={2} style={{ width: '100%' }} />
                  </Form.Item>
                </Col>
                <Col xs={8}>
                  <Form.Item name="cnps_retraite_patronal" label="Patronal (%)">
                    <InputNumber min={0} max={100} step={0.1} precision={2} style={{ width: '100%' }} />
                  </Form.Item>
                </Col>
                <Col xs={8}>
                  <Form.Item name="cnps_retraite_plafond_annuel" label="Plafond annuel">
                    <InputNumber min={0} style={{ width: '100%' }} />
                  </Form.Item>
                </Col>
              </Row>

              <Divider orientation="left" plain>CNPS AT & Famille</Divider>
              <Row gutter={8}>
                <Col xs={8}>
                  <Form.Item name="cnps_at_patronal" label="AT patronal (%)">
                    <InputNumber min={0} max={100} step={0.5} precision={1} style={{ width: '100%' }} />
                  </Form.Item>
                </Col>
                <Col xs={8}>
                  <Form.Item name="cnps_famille_patronal" label="Famille patronal (%)">
                    <InputNumber min={0} max={100} step={0.25} precision={2} style={{ width: '100%' }} />
                  </Form.Item>
                </Col>
                <Col xs={8}>
                  <Form.Item name="cnps_famille_plafond_mensuel" label="Plafond mensuel famille">
                    <InputNumber min={0} style={{ width: '100%' }} />
                  </Form.Item>
                </Col>
              </Row>

              <Divider orientation="left" plain>CMU & Contribution Nationale</Divider>
              <Row gutter={8}>
                <Col xs={8}>
                  <Form.Item name="cmu_salarial" label="CMU salarial (%)">
                    <InputNumber min={0} max={100} step={0.5} precision={1} style={{ width: '100%' }} />
                  </Form.Item>
                </Col>
                <Col xs={8}>
                  <Form.Item name="cmu_patronal" label="CMU patronal (%)">
                    <InputNumber min={0} max={100} step={0.5} precision={1} style={{ width: '100%' }} />
                  </Form.Item>
                </Col>
                <Col xs={8}>
                  <Form.Item name="cn_taux" label="CN taux (%)">
                    <InputNumber min={0} max={100} step={0.5} precision={1} style={{ width: '100%' }} />
                  </Form.Item>
                </Col>
              </Row>
            </Form>
          </Card>
        </Col>

        <Col xs={24} lg={10}>
          {/* Récapitulatif */}
          <Card title="Paramètres actuels" size="small" loading={isLoading} style={{ marginBottom: 16 }}>
            {config && (
              <>
                <Row justify="space-between" style={{ marginBottom: 8 }}>
                  <Text strong>SMIG :</Text>
                  <Text>{fmt(config.smig_mensuel)}</Text>
                </Row>
                <Row justify="space-between" style={{ marginBottom: 8 }}>
                  <Text strong>CNPS Retraite sal. :</Text>
                  <Text>{pct(config.cnps_retraite_salarial)}</Text>
                </Row>
                <Row justify="space-between" style={{ marginBottom: 8 }}>
                  <Text strong>CNPS Retraite pat. :</Text>
                  <Text>{pct(config.cnps_retraite_patronal)}</Text>
                </Row>
                <Row justify="space-between" style={{ marginBottom: 8 }}>
                  <Text strong>CNPS AT pat. :</Text>
                  <Text>{pct(config.cnps_at_patronal)}</Text>
                </Row>
                <Row justify="space-between" style={{ marginBottom: 8 }}>
                  <Text strong>CNPS Famille pat. :</Text>
                  <Text>{pct(config.cnps_famille_patronal)}</Text>
                </Row>
                <Row justify="space-between" style={{ marginBottom: 8 }}>
                  <Text strong>CMU sal. / pat. :</Text>
                  <Text>{pct(config.cmu_salarial)} / {pct(config.cmu_patronal)}</Text>
                </Row>
                <Row justify="space-between">
                  <Text strong>CN taux :</Text>
                  <Text>{pct(config.cn_taux)}</Text>
                </Row>
              </>
            )}
          </Card>

          {/* Tranches ITS */}
          <Card title="Barème ITS (DGI) — Tranches progressives" size="small">
            <Table
              dataSource={tranches}
              columns={colonnesTranches}
              rowKey="min"
              size="small"
              pagination={false}
            />
            <div style={{ marginTop: 8, fontSize: 11, color: '#888' }}>
              Les tranches sont modifiables via l'API PATCH /rh/paie/config avec le champ its_tranches.
            </div>
          </Card>
        </Col>
      </Row>

    </div>
  )
}
