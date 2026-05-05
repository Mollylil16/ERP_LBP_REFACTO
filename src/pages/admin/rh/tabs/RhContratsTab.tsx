import React, { useState } from 'react'
import {
  Table,
  Button,
  Select,
  Tag,
  Space,
  Modal,
  Form,
  DatePicker,
  InputNumber,
  Input,
  Row,
  Col,
  message,
  Alert,
} from 'antd'
import { PlusOutlined, WarningOutlined } from '@ant-design/icons'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { rhService, RhContrat } from '@services/rh.service'
import { usePermissions } from '@hooks/usePermissions'
import { PERMISSIONS } from '@constants/permissions'
import dayjs from 'dayjs'

const { Option } = Select

const STATUT_COLOR: Record<string, string> = {
  actif: 'green',
  termine: 'default',
  resilie: 'red',
  essai: 'blue',
}

export const RhContratsTab: React.FC = () => {
  const qc = useQueryClient()
  const { hasPermission } = usePermissions()
  const canCreate = hasPermission(PERMISSIONS.RH.CONTRATS_CREATE)

  const [statutFilter, setStatutFilter] = useState<string | undefined>()
  const [showForm, setShowForm] = useState(false)
  const [form] = Form.useForm()

  const { data: contrats = [], isLoading } = useQuery<RhContrat[]>({
    queryKey: ['rh-contrats', statutFilter],
    queryFn: () => rhService.getContrats(statutFilter),
    staleTime: 30_000,
  })

  const { data: alertesCdd = [] } = useQuery<RhContrat[]>({
    queryKey: ['rh-cdd-expirants'],
    queryFn: () => rhService.getCddExpirants(30),
    staleTime: 60_000,
    refetchInterval: 120_000,
  })

  const createMut = useMutation({
    mutationFn: (data: Partial<RhContrat>) => rhService.createContrat(data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['rh-contrats'] })
      qc.invalidateQueries({ queryKey: ['rh-cdd-expirants'] })
      qc.invalidateQueries({ queryKey: ['rh-dashboard'] })
      message.success('Contrat créé avec succès')
      setShowForm(false)
      form.resetFields()
    },
    onError: (e: { message?: string }) => message.error(e.message ?? 'Erreur lors de la création'),
  })

  const columns = [
    {
      title: 'Employé',
      key: 'employe',
      render: (_: unknown, r: RhContrat) =>
        r.employe ? `${r.employe.matricule} — ${r.employe.nom} ${r.employe.prenoms}` : r.id_employe,
      width: 240,
    },
    {
      title: 'Type',
      dataIndex: 'type_contrat',
      key: 'type',
      render: (v: string) => <Tag>{v}</Tag>,
      width: 90,
    },
    {
      title: 'Début',
      dataIndex: 'date_debut',
      key: 'debut',
      render: (v: string) => dayjs(v).format('DD/MM/YYYY'),
      width: 110,
    },
    {
      title: 'Fin',
      dataIndex: 'date_fin',
      key: 'fin',
      render: (v: string | null) => {
        if (!v) return <Tag color="blue">CDI / illimité</Tag>
        const jours = dayjs(v).diff(dayjs(), 'day')
        return (
          <Space>
            {dayjs(v).format('DD/MM/YYYY')}
            {jours <= 30 && (
              <Tag color={jours <= 7 ? 'red' : jours <= 15 ? 'orange' : 'gold'}>
                <WarningOutlined /> J-{jours}
              </Tag>
            )}
          </Space>
        )
      },
      width: 180,
    },
    {
      title: 'Salaire base',
      dataIndex: 'salaire_base',
      key: 'salaire',
      render: (v: number | null) =>
        v != null ? `${Number(v).toLocaleString('fr-FR')} FCFA` : '—',
      width: 150,
    },
    {
      title: 'Statut',
      dataIndex: 'statut',
      key: 'statut',
      render: (v: string) => <Tag color={STATUT_COLOR[v] ?? 'default'}>{v}</Tag>,
      width: 90,
    },
    {
      title: 'Signatures',
      key: 'signatures',
      width: 160,
      render: (_: unknown, r: RhContrat & { signe_salarie_at?: string | null; signe_rh_at?: string | null }) => (
        <Space size={4}>
          <Tag color={r.signe_salarie_at ? 'green' : 'default'} title={r.signe_salarie_at ? `Salarié le ${dayjs(r.signe_salarie_at).format('DD/MM/YYYY')}` : 'Non signé par salarié'}>
            Sal.{r.signe_salarie_at ? ' ✓' : ' ○'}
          </Tag>
          <Tag color={r.signe_rh_at ? 'green' : 'default'} title={r.signe_rh_at ? `RH le ${dayjs(r.signe_rh_at).format('DD/MM/YYYY')}` : 'Non signé par RH'}>
            RH{r.signe_rh_at ? ' ✓' : ' ○'}
          </Tag>
          {!r.signe_salarie_at && (
            <Button size="small" type="dashed"
              onClick={async () => {
                try {
                  const { apiService } = await import('@services/api.service')
                  await apiService.patch(`/rh/rapports/contrats/${r.id}/signer`, { role: 'SALARIE' })
                  message.success('Signature salarié enregistrée')
                  qc.invalidateQueries({ queryKey: ['rh-contrats'] })
                } catch { message.error('Erreur signature') }
              }}>Signer (sal.)</Button>
          )}
          {!r.signe_rh_at && (
            <Button size="small" type="primary"
              onClick={async () => {
                try {
                  const { apiService } = await import('@services/api.service')
                  await apiService.patch(`/rh/rapports/contrats/${r.id}/signer`, { role: 'RH' })
                  message.success('Signature RH enregistrée')
                  qc.invalidateQueries({ queryKey: ['rh-contrats'] })
                } catch { message.error('Erreur signature') }
              }}>Signer (RH)</Button>
          )}
        </Space>
      ),
    },
  ]

  return (
    <div>
      {alertesCdd.length > 0 && (
        <Alert
          type="warning"
          showIcon
          icon={<WarningOutlined />}
          message={`${alertesCdd.length} CDD expire(nt) dans moins de 30 jours`}
          style={{ marginBottom: 16 }}
        />
      )}

      <Row gutter={12} style={{ marginBottom: 16 }} align="middle">
        <Col>
          <Select
            placeholder="Statut contrat"
            allowClear
            style={{ width: 160 }}
            onChange={setStatutFilter}
          >
            <Option value="actif">Actif</Option>
            <Option value="essai">Période d'essai</Option>
            <Option value="termine">Terminé</Option>
            <Option value="resilie">Résilié</Option>
          </Select>
        </Col>
        {canCreate && (
          <Col>
            <Button type="primary" icon={<PlusOutlined />} onClick={() => setShowForm(true)}>
              Nouveau contrat
            </Button>
          </Col>
        )}
      </Row>

      <Table
        dataSource={contrats}
        columns={columns}
        rowKey="id"
        loading={isLoading}
        size="small"
        pagination={{ pageSize: 20 }}
        scroll={{ x: 900 }}
      />

      <Modal
        title="Nouveau contrat"
        open={showForm}
        onCancel={() => { setShowForm(false); form.resetFields() }}
        onOk={() => form.validateFields().then((vals: Record<string, unknown> & { date_debut?: { format: (f: string) => string }; date_fin?: { format: (f: string) => string }; periode_essai_debut?: { format: (f: string) => string }; periode_essai_fin?: { format: (f: string) => string } }) => {
          createMut.mutate({
            ...vals,
            date_debut: vals.date_debut?.format('YYYY-MM-DD'),
            date_fin: vals.date_fin?.format('YYYY-MM-DD') ?? null,
            periode_essai_debut: vals.periode_essai_debut?.format('YYYY-MM-DD') ?? null,
            periode_essai_fin: vals.periode_essai_fin?.format('YYYY-MM-DD') ?? null,
          })
        })}
        confirmLoading={createMut.isPending}
        width={680}
      >
        <Form form={form} layout="vertical" size="small">
          <Row gutter={12}>
            <Col span={12}>
              <Form.Item name="id_employe" label="ID employé" rules={[{ required: true }]}>
                <InputNumber style={{ width: '100%' }} min={1} />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="type_contrat" label="Type de contrat" rules={[{ required: true }]}>
                <Select>
                  <Option value="CDI">CDI</Option>
                  <Option value="CDD">CDD</Option>
                  <Option value="STAGE">Stage</Option>
                  <Option value="INTERIM">Intérim</Option>
                </Select>
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="date_debut" label="Date de début" rules={[{ required: true }]}>
                <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="date_fin" label="Date de fin (CDD)">
                <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="salaire_base" label="Salaire de base (FCFA)">
                <InputNumber style={{ width: '100%' }} min={0} step={1000} />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="intitule_poste" label="Intitulé du poste">
                <Input />
              </Form.Item>
            </Col>
            <Col span={24}>
              <Form.Item name="notes" label="Notes">
                <Input.TextArea rows={2} />
              </Form.Item>
            </Col>
          </Row>
        </Form>
      </Modal>
    </div>
  )
}
