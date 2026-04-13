import React, { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  Card,
  Table,
  Typography,
  Button,
  Space,
  Modal,
  Form,
  Input,
  Select,
  InputNumber,
  Switch,
  message,
  Tag,
  Segmented,
} from 'antd'
import { EditOutlined, ReloadOutlined } from '@ant-design/icons'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { usePermissions } from '@hooks/usePermissions'
import { ROUTE_ACCESS } from '@constants/routeAccess'
import {
  produitsCatalogueService,
  type ProduitCatalogue,
} from '@services/produits-catalogue.service'

const { Title, Text } = Typography

const CATEGORIE_LABELS: Record<ProduitCatalogue['categorie'], string> = {
  DENREE: 'Denrée',
  HUILE_ET_KARITE: 'Huile & karité',
  DIVERS: 'Divers',
  COLIS_RAPIDE_EXPORT: 'Colis rapide export',
}

const NATURE_LABELS: Record<string, string> = {
  PRIX_UNITAIRE: 'Prix au kg',
  PRIX_FORFAITAIRE: 'Forfait',
}

type FiltreActif = 'tous' | 'actifs' | 'inactifs'

export const CatalogueProduitsPage: React.FC = () => {
  const { hasPermission } = usePermissions()
  const queryClient = useQueryClient()
  const [filtreActif, setFiltreActif] = useState<FiltreActif>('actifs')
  const [editing, setEditing] = useState<ProduitCatalogue | null>(null)
  const [saving, setSaving] = useState(false)
  const [form] = Form.useForm()

  const { data: rows = [], isLoading, refetch } = useQuery({
    queryKey: ['produits-catalogue-gestion'],
    queryFn: () => produitsCatalogueService.getAllForManagement(),
  })

  const filtered = useMemo(() => {
    if (filtreActif === 'actifs') return rows.filter((r) => r.actif)
    if (filtreActif === 'inactifs') return rows.filter((r) => !r.actif)
    return rows
  }, [rows, filtreActif])

  const openEdit = (record: ProduitCatalogue) => {
    setEditing(record)
    form.setFieldsValue({
      nom: record.nom,
      categorie: record.categorie,
      nature: record.nature ?? undefined,
      prix_unitaire: record.prix_unitaire != null ? Number(record.prix_unitaire) : undefined,
      prix_forfaitaire: record.prix_forfaitaire != null ? Number(record.prix_forfaitaire) : undefined,
      poids_min: record.poids_min != null ? Number(record.poids_min) : undefined,
      poids_max: record.poids_max != null ? Number(record.poids_max) : undefined,
      unite: record.unite ?? undefined,
      description: record.description ?? undefined,
      actif: record.actif,
    })
  }

  const closeModal = () => {
    setEditing(null)
    form.resetFields()
  }

  const onSave = async () => {
    if (!editing) return
    try {
      const values = await form.validateFields()
      const min = values.poids_min
      const max = values.poids_max
      if (min != null && max != null && Number(min) > Number(max)) {
        message.error('Le poids minimum ne peut pas dépasser le poids maximum.')
        return
      }
      setSaving(true)
      await produitsCatalogueService.update(editing.id, {
        nom: values.nom?.trim(),
        categorie: values.categorie,
        nature: values.nature,
        prix_unitaire: values.prix_unitaire,
        prix_forfaitaire: values.prix_forfaitaire,
        poids_min: values.poids_min,
        poids_max: values.poids_max,
        unite: values.unite?.trim() || undefined,
        description: values.description?.trim() || undefined,
        actif: values.actif,
      })
      message.success('Produit catalogue mis à jour')
      closeModal()
      await queryClient.invalidateQueries({ queryKey: ['produits-catalogue-gestion'] })
      await queryClient.invalidateQueries({ queryKey: ['produits-catalogue'] })
    } catch (e: any) {
      if (e?.errorFields) return
      message.error(e?.message || 'Enregistrement impossible')
    } finally {
      setSaving(false)
    }
  }

  const columns = [
    {
      title: 'Nom',
      dataIndex: 'nom',
      key: 'nom',
      ellipsis: true,
      render: (nom: string, r: ProduitCatalogue) => (
        <Space direction="vertical" size={0}>
          <Text strong>{nom}</Text>
          {!r.actif && <Tag color="default">Inactif</Tag>}
        </Space>
      ),
    },
    {
      title: 'Catégorie',
      dataIndex: 'categorie',
      key: 'categorie',
      width: 160,
      render: (c: ProduitCatalogue['categorie']) => CATEGORIE_LABELS[c] ?? c,
    },
    {
      title: 'Nature',
      dataIndex: 'nature',
      key: 'nature',
      width: 120,
      render: (n: string | null) => (n ? NATURE_LABELS[n] ?? n : '—'),
    },
    {
      title: 'Prix kg',
      dataIndex: 'prix_unitaire',
      key: 'prix_unitaire',
      width: 100,
      align: 'right' as const,
      render: (v: number | null) =>
        v != null && Number(v) > 0 ? `${Number(v).toLocaleString()} F` : '—',
    },
    {
      title: 'Forfait',
      key: 'prix_forfait',
      width: 140,
      align: 'right' as const,
      render: (_: unknown, r: ProduitCatalogue) => {
        const pf = r.prix_forfaitaire != null ? Number(r.prix_forfaitaire) : 0
        if (!(pf > 0)) return '—'
        const a = r.poids_min != null ? Number(r.poids_min) : null
        const b = r.poids_max != null ? Number(r.poids_max) : null
        const tranche =
          a != null || b != null
            ? ` (${[a, b].filter((x) => x != null).join('–')} kg)`
            : ''
        return `${pf.toLocaleString()} F${tranche}`
      },
    },
    {
      title: 'Unité',
      dataIndex: 'unite',
      key: 'unite',
      width: 90,
      render: (u: string | null) => u || '—',
    },
    {
      title: '',
      key: 'actions',
      width: 100,
      render: (_: unknown, r: ProduitCatalogue) => (
        <Button type="link" icon={<EditOutlined />} onClick={() => openEdit(r)}>
          Modifier
        </Button>
      ),
    },
  ]

  return (
    <div style={{ padding: 24 }}>
      <Card>
        <Space direction="vertical" size="large" style={{ width: '100%' }}>
          <Space wrap align="center" style={{ justifyContent: 'space-between', width: '100%' }}>
            <Title level={3} style={{ margin: 0 }}>
              Catalogue produits
            </Title>
            <Space wrap>
              {hasPermission(ROUTE_ACCESS.settingsProduitsHistorique) && (
                <Link to="/settings/produits-historique">
                  <Button type="link">Historique marchandises</Button>
                </Link>
              )}
              <Segmented
                options={[
                  { label: 'Actifs', value: 'actifs' },
                  { label: 'Inactifs', value: 'inactifs' },
                  { label: 'Tous', value: 'tous' },
                ]}
                value={filtreActif}
                onChange={(v: FiltreActif) => setFiltreActif(v)}
              />
              <Button icon={<ReloadOutlined />} onClick={() => void refetch()}>
                Actualiser
              </Button>
            </Space>
          </Space>
          <Text type="secondary">
            Corriger une fiche, désactiver un doublon ou ajuster un tarif. Les produits inactifs ne
            sont plus proposés à la saisie colis.
          </Text>
          <Table
            rowKey="id"
            loading={isLoading}
            dataSource={filtered}
            columns={columns}
            pagination={{ pageSize: 25, showSizeChanger: true }}
            scroll={{ x: 900 }}
          />
        </Space>
      </Card>

      <Modal
        title={editing ? `Modifier — ${editing.nom}` : 'Produit'}
        open={editing !== null}
        onCancel={closeModal}
        onOk={() => void onSave()}
        confirmLoading={saving}
        destroyOnClose
        width={560}
        okText="Enregistrer"
      >
        <Form form={form} layout="vertical" style={{ marginTop: 16 }}>
          <Form.Item name="nom" label="Nom" rules={[{ required: true, message: 'Nom requis' }]}>
            <Input />
          </Form.Item>
          <Form.Item
            name="categorie"
            label="Catégorie"
            rules={[{ required: true, message: 'Catégorie requise' }]}
          >
            <Select
              options={Object.entries(CATEGORIE_LABELS).map(([value, label]) => ({
                value,
                label,
              }))}
            />
          </Form.Item>
          <Form.Item name="nature" label="Type de prix (fiche)">
            <Select
              allowClear
              placeholder="Auto selon les montants"
              options={[
                { value: 'PRIX_UNITAIRE', label: NATURE_LABELS.PRIX_UNITAIRE },
                { value: 'PRIX_FORFAITAIRE', label: NATURE_LABELS.PRIX_FORFAITAIRE },
              ]}
            />
          </Form.Item>
          <Form.Item name="prix_unitaire" label="Prix au kg (FCFA)">
            <InputNumber min={0} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item name="prix_forfaitaire" label="Forfait (FCFA)">
            <InputNumber min={0} style={{ width: '100%' }} />
          </Form.Item>
          <Space style={{ width: '100%' }} size="middle">
            <Form.Item name="poids_min" label="Poids min. (kg)" style={{ flex: 1 }}>
              <InputNumber min={0} style={{ width: '100%' }} />
            </Form.Item>
            <Form.Item name="poids_max" label="Poids max. (kg)" style={{ flex: 1 }}>
              <InputNumber min={0} style={{ width: '100%' }} />
            </Form.Item>
          </Space>
          <Form.Item name="unite" label="Unité">
            <Input placeholder="kg, carton…" />
          </Form.Item>
          <Form.Item name="description" label="Description">
            <Input.TextArea rows={2} />
          </Form.Item>
          <Form.Item name="actif" label="Actif" valuePropName="checked">
            <Switch />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  )
}
