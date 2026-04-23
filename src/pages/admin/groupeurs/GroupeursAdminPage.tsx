import React, { useMemo, useState } from 'react'
import { Alert, Button, Card, Col, Drawer, Form, Input, Row, Select, Space, Statistic, Table, Tag, Typography, message } from 'antd'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { groupeursService, type Groupeur } from '@services/groupeurs.service'
import { usePermissions } from '@hooks/usePermissions'
import { PERMISSIONS } from '@constants/permissions'

const { Text } = Typography

const STATUT_COLORS: Record<string, string> = {
  actif: 'green',
  suspendu: 'orange',
  archive: 'default',
}

export const GroupeursAdminPage: React.FC = () => {
  const qc = useQueryClient()
  const { hasPermission } = usePermissions()
  const canWrite = hasPermission(PERMISSIONS.GROUPEURS.ADMIN_WRITE)

  const [filters, setFilters] = useState<{ statut?: string; type?: string; q?: string }>({})
  const [selected, setSelected] = useState<Groupeur | null>(null)

  const { data: compte } = useQuery({
    queryKey: ['groupeurs', 'admin', 'compte', selected?.id],
    queryFn: () => groupeursService.getCompte(String(selected?.id)),
    enabled: !!selected?.id,
    staleTime: 30_000,
  })

  const { data: stats } = useQuery({
    queryKey: ['groupeurs', 'stats'],
    queryFn: () => groupeursService.getStats(),
  })
  const { data, isLoading, error } = useQuery({
    queryKey: ['groupeurs', 'admin', filters],
    queryFn: () => groupeursService.listAdmin(filters),
  })

  const mutCreate = useMutation({
    mutationFn: (dto: Partial<Groupeur>) => groupeursService.create(dto),
    onSuccess: async () => {
      message.success('Groupeur créé')
      await qc.invalidateQueries({ queryKey: ['groupeurs'] })
    },
  })

  const mutStatut = useMutation({
    mutationFn: (p: { id: string; statut: string; motif?: string }) =>
      groupeursService.changeStatut(p.id, { statut: p.statut, motif: p.motif }),
    onSuccess: async () => {
      message.success('Statut mis à jour')
      await qc.invalidateQueries({ queryKey: ['groupeurs'] })
    },
  })

  const columns = useMemo(
    () => [
      { title: 'Code', dataIndex: 'code', width: 110 },
      { title: 'Raison sociale', dataIndex: 'raison_sociale' },
      {
        title: 'Username',
        key: 'username',
        width: 160,
        render: (_: unknown, r: Groupeur) => r.user?.username ?? '—',
      },
      { title: 'Ville', dataIndex: 'ville', width: 140 },
      { title: 'Type', dataIndex: 'type', width: 120 },
      {
        title: 'Statut',
        dataIndex: 'statut',
        width: 120,
        render: (s: string) => <Tag color={STATUT_COLORS[s] ?? 'default'}>{s}</Tag>,
      },
      {
        title: 'Actions',
        key: 'actions',
        width: 220,
        render: (_: unknown, r: Groupeur) => (
          <Space>
            <Button size="small" onClick={() => setSelected(r)}>
              Détail
            </Button>
            {canWrite && (
              <Select
                size="small"
                style={{ width: 120 }}
                value={r.statut}
                  onChange={(v: string) => mutStatut.mutate({ id: r.id, statut: v })}
                options={[
                  { value: 'actif', label: 'Actif' },
                  { value: 'suspendu', label: 'Suspendu' },
                  { value: 'archive', label: 'Archivé' },
                ]}
              />
            )}
          </Space>
        ),
      },
    ],
    [canWrite, mutStatut],
  )

  return (
    <div>
      <Row gutter={[16, 16]}>
        <Col xs={24} md={12} lg={6}>
          <Card size="small">
            <Statistic title="Groupeurs actifs" value={stats?.groupeurs_actifs ?? '—'} />
          </Card>
        </Col>
        <Col xs={24} md={12} lg={6}>
          <Card size="small">
            <Statistic title="Groupeurs (total)" value={stats?.groupeurs_total ?? '—'} />
          </Card>
        </Col>
      </Row>

      <Card
        size="small"
        title="Administration des groupeurs"
        style={{ marginTop: 16 }}
        extra={
          <Space wrap>
            <Input.Search
              allowClear
              placeholder="Recherche (code / raison sociale)"
              style={{ width: 260 }}
              onSearch={(q: string) => setFilters((f) => ({ ...f, q: q || undefined }))}
            />
            <Select
              allowClear
              placeholder="Statut"
              style={{ width: 160 }}
              onChange={(v: string) => setFilters((f) => ({ ...f, statut: v || undefined }))}
              options={[
                { value: 'actif', label: 'Actif' },
                { value: 'suspendu', label: 'Suspendu' },
                { value: 'archive', label: 'Archivé' },
              ]}
            />
          </Space>
        }
      >
        {error ? (
          <Alert type="error" showIcon message="Impossible de charger la liste des groupeurs." />
        ) : (
          <Table<Groupeur>
            size="small"
            loading={isLoading}
            rowKey="id"
            dataSource={data ?? []}
            columns={columns as any}
            pagination={{ pageSize: 12 }}
          />
        )}
      </Card>

      {canWrite && (
        <Card size="small" title="Créer un groupeur" style={{ marginTop: 16 }}>
          <Form
            layout="vertical"
            onFinish={(v: Record<string, unknown>) => mutCreate.mutate(v as any)}
            initialValues={{ type: 'groupeur' }}
          >
            <Row gutter={16}>
              <Col xs={24} md={6}>
                <Form.Item name="code" label="Code" rules={[{ required: true }]}>
                  <Input placeholder="GRP-001" />
                </Form.Item>
              </Col>
              <Col xs={24} md={10}>
                <Form.Item name="raison_sociale" label="Raison sociale" rules={[{ required: true }]}>
                  <Input />
                </Form.Item>
              </Col>
              <Col xs={24} md={8}>
                <Form.Item name="type" label="Type">
                  <Select
                    options={[
                      { value: 'groupeur', label: 'Groupeur' },
                      { value: 'grossiste', label: 'Grossiste' },
                      { value: 'mixte', label: 'Mixte' },
                    ]}
                  />
                </Form.Item>
              </Col>
              <Col xs={24} md={8}>
                <Form.Item name="ville" label="Ville">
                  <Input />
                </Form.Item>
              </Col>
              <Col xs={24} md={8}>
                <Form.Item name="telephone" label="Téléphone">
                  <Input />
                </Form.Item>
              </Col>
              <Col xs={24} md={8}>
                <Form.Item name="email_contact" label="Email contact">
                  <Input />
                </Form.Item>
              </Col>
            </Row>
            <Button type="primary" htmlType="submit" loading={mutCreate.isPending}>
              Créer
            </Button>
          </Form>
          <Text type="secondary" style={{ display: 'block', marginTop: 8 }}>
            Note : la création du compte de connexion groupeur (user) sera câblée dans une passe suivante si besoin.
          </Text>
        </Card>
      )}

      <Drawer
        open={!!selected}
        width={620}
        onClose={() => setSelected(null)}
        title={selected ? `${selected.code} — ${selected.raison_sociale}` : 'Détail'}
      >
        {selected && (
          <Card size="small">
            <p>
              <Text strong>Compte :</Text> {compte?.username ?? selected.user?.username ?? '—'}{' '}
              {compte?.password_changed === true && <Tag color="green">Mdp déjà changé</Tag>}
              {compte?.password_changed === false && <Tag color="orange">Mdp temporaire actif</Tag>}
            </p>
            <p>
              <Text strong>Statut :</Text> <Tag color={STATUT_COLORS[selected.statut] ?? 'default'}>{selected.statut}</Tag>
            </p>
            <p>
              <Text strong>Contact :</Text> {selected.telephone ?? '—'} / {selected.email_contact ?? '—'}
            </p>
            <p>
              <Text strong>Adresse :</Text> {selected.adresse ?? '—'}
            </p>
          </Card>
        )}
      </Drawer>
    </div>
  )
}

