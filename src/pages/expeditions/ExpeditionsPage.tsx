
import React, { useState, useEffect } from 'react'
import { Table, Button, Card, Tag, Space, Modal, Form, Select, Input, message, Drawer, List, Checkbox } from 'antd'
import { PlusOutlined, TruckOutlined, FileTextOutlined, EyeOutlined } from '@ant-design/icons'
import { useAuth } from '@/hooks/useAuth'
import { expeditionsService } from '@/services/expeditions.service'
import { colisService } from '@/services/colis.service'
import { Expedition, Colis } from '@/types'
import { formatDate } from '@/utils/format'
import { ExpeditionsSkeleton } from '@components/common/SkeletonLoader'
import { EmptyExpeditionsList } from '@components/common/EmptyState'

const { Option } = Select

export const ExpeditionsPage: React.FC = () => {
    const { user } = useAuth()
    const [expeditions, setExpeditions] = useState<Expedition[]>([])
    const [loading, setLoading] = useState(false)
    const [isModalVisible, setIsModalVisible] = useState(false)
    const [isDrawerVisible, setIsDrawerVisible] = useState(false)
    const [selectedExpedition, setSelectedExpedition] = useState<Expedition | null>(null)
    const [availableColis, setAvailableColis] = useState<Colis[]>([])
    const [selectedColisIds, setSelectedColisIds] = useState<number[]>([])
    const [form] = Form.useForm()

    const fetchExpeditions = async () => {
        setLoading(true)
        try {
            const data = await expeditionsService.getAll()
            setExpeditions(data)
        } catch (error) {
            message.error('Erreur lors du chargement des expéditions')
        } finally {
            setLoading(false)
        }
    }

    useEffect(() => {
        fetchExpeditions()
    }, [])

    const handleCreate = async (values: any) => {
        try {
            await expeditionsService.create(values)
            message.success('Expédition créée avec succès')
            setIsModalVisible(false)
            form.resetFields()
            fetchExpeditions()
        } catch (error) {
            message.error('Erreur lors de la création')
        }
    }

    const handleOpenDrawer = async (expedition: Expedition) => {
        setSelectedExpedition(expedition)
        setIsDrawerVisible(true)
        // Charger les colis disponibles (validés, non assignés à une expédition)
        try {
            const response = await colisService.getAll({
                statut: 'valide',
                sans_expedition: true,
                agence_id: user?.agency?.id,
            })
            // Sécurisation : si le backend ne supporte pas encore les filtres,
            // on filtre côté client en fallback
            const data = Array.isArray(response) ? response : (response as any)?.data || []
            const filtered = data.filter(
                (c: any) =>
                    !c.expedition_id &&
                    !c.manifeste_id &&
                    (!user?.agency?.id || c.agence?.id === user.agency.id)
            )
            setAvailableColis(filtered)
        } catch (error) {
            console.error('Erreur chargement colis disponibles:', error)
            setAvailableColis([])
        }
    }

    const handleAddColis = async () => {
        if (!selectedExpedition || selectedColisIds.length === 0) return
        try {
            await expeditionsService.addColis(selectedExpedition.id, selectedColisIds)
            message.success('Colis ajoutés au manifeste')
            setSelectedColisIds([])
            setIsDrawerVisible(false)
            fetchExpeditions()
        } catch (error) {
            message.error('Erreur lors de l\'ajout des colis')
        }
    }

    const columns = [
        {
            title: 'Référence',
            dataIndex: 'ref_expedition',
            key: 'ref_expedition',
            render: (text: string) => <Tag color="blue">{text}</Tag>,
        },
        {
            title: 'Type',
            dataIndex: 'type',
            key: 'type',
            render: (type: string) => (type === 'AERIEN' ? <Tag color="cyan">AÉRIEN</Tag> : <Tag color="blue">MARITIME</Tag>),
        },
        {
            title: 'Départ',
            dataIndex: ['agence_depart', 'ville'],
            key: 'depart',
        },
        {
            title: 'Destination',
            dataIndex: ['agence_destination', 'ville'],
            key: 'destination',
        },
        {
            title: 'Container / Vol',
            dataIndex: 'numero_container',
            key: 'container',
            render: (text: string) => text || '-',
        },
        {
            title: 'Statut',
            dataIndex: 'statut',
            key: 'statut',
            render: (statut: string) => {
                let color = 'default'
                if (statut === 'EN_TRANSIT') color = 'processing'
                if (statut === 'ARRIVE') color = 'success'
                return <Tag color={color}>{statut}</Tag>
            },
        },
        {
            title: 'Date Création',
            dataIndex: 'created_at',
            key: 'created_at',
            render: (date: string) => formatDate(date),
        },
        {
            title: 'Actions',
            key: 'actions',
            render: (_: any, record: Expedition) => (
                <Space>
                    <Button icon={<EyeOutlined />} onClick={() => handleOpenDrawer(record)}>
                        Détails
                    </Button>
                </Space>
            ),
        },
    ]

    if (loading && expeditions.length === 0) {
        return (
            <div style={{ padding: 24 }}>
                <h2>Gestion des Manifestes Internationaux</h2>
                <ExpeditionsSkeleton />
            </div>
        )
    }

    return (
        <div style={{ padding: 24 }}>
            <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between' }}>
                <h2>Gestion des Manifestes Internationaux</h2>
                <Button type="primary" icon={<PlusOutlined />} onClick={() => setIsModalVisible(true)}>
                    Nouvelle Expédition
                </Button>
            </div>

            <Table
                columns={columns}
                dataSource={expeditions}
                rowKey="id"
                loading={loading}
                locale={{
                    emptyText: <EmptyExpeditionsList onCreateClick={() => setIsModalVisible(true)} />,
                }}
            />

            <Modal
                title="Créer une nouvelle expédition"
                open={isModalVisible} // 'visible' is deprecated in older antd, checking compat. Using open for v5, visible for v4. user seems to use v5 (ConfigProvider usually). Let's use visible/open depending. Antd v4 uses visible.
                visible={isModalVisible}
                onCancel={() => setIsModalVisible(false)}
                footer={null}
            >
                <Form form={form} onFinish={handleCreate} layout="vertical">
                    <Form.Item name="type" label="Type de transport" rules={[{ required: true }]}>
                        <Select>
                            <Option value="AERIEN">Aérien</Option>
                            <Option value="MARITIME">Maritime</Option>
                        </Select>
                    </Form.Item>
                    <Form.Item name="id_agence_destination" label="Destination" rules={[{ required: true }]}>
                        <Select>
                            {/* Hardcoded for now, should fetch from API */}
                            <Option value={1}>Abidjan (Siège)</Option>
                            <Option value={2}>Paris (France)</Option>
                            <Option value={3}>Dakar (Sénégal)</Option>
                        </Select>
                    </Form.Item>
                    <Form.Item name="numero_container" label="Numéro Container / Vol">
                        <Input placeholder="ex: AF1234 ou MSCU1234567" />
                    </Form.Item>
                    <Form.Item name="compagnie_transport" label="Compagnie">
                        <Input placeholder="ex: Air France, MSC" />
                    </Form.Item>
                    <Button type="primary" htmlType="submit" block>
                        Créer
                    </Button>
                </Form>
            </Modal>

            <Drawer
                title={`Détails Expédition ${selectedExpedition?.ref_expedition}`}
                width={720}
                onClose={() => setIsDrawerVisible(false)}
                open={isDrawerVisible} // Antd v5
                visible={isDrawerVisible} // Antd v4 fallback
            >
                {selectedExpedition && (
                    <div>
                        <Card title="Colis dans ce manifeste">
                            <List
                                dataSource={selectedExpedition.colis}
                                renderItem={(item: Colis) => (
                                    <List.Item
                                        actions={[
                                            <Button danger size="small" onClick={() => {
                                                expeditionsService.removeColis(selectedExpedition.id, item.id)
                                                    .then(() => {
                                                        message.success('Colis retiré')
                                                        // Refresh logic needed here, maybe just update local state
                                                        fetchExpeditions()
                                                        setIsDrawerVisible(false) // Simple refresh force
                                                    })
                                            }}>Retirer</Button>
                                        ]}
                                    >
                                        <List.Item.Meta
                                            title={`${item.ref_colis} - ${item.nom_destinataire}`}
                                            description={`${item.nbre_colis} colis | ${item.poids_total} kg`}
                                        />
                                    </List.Item>
                                )}
                            />
                        </Card>

                        <Card title="Ajouter des colis au manifeste" style={{ marginTop: 16 }}>
                            <div style={{ marginBottom: 16 }}>
                                <Select
                                    mode="multiple"
                                    style={{ width: '100%' }}
                                    placeholder="Sélectionner des colis à ajouter"
                                    onChange={setSelectedColisIds}
                                    value={selectedColisIds}
                                    options={availableColis.map(c => ({ label: `${c.ref_colis} - ${c.nom_destinataire}`, value: c.id }))}
                                />
                            </div>
                            <Button type="primary" onClick={handleAddColis} disabled={selectedColisIds.length === 0}>
                                Ajouter sélectionnés
                            </Button>
                        </Card>
                    </div>
                )}
            </Drawer>
        </div>
    )
}
