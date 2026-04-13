import React from 'react'
import { Link } from 'react-router-dom'
import { Card, Table, Typography, Tag, Statistic, Row, Col, Spin, Button, Space } from 'antd'
import { ShoppingOutlined, DollarOutlined, RiseOutlined, FallOutlined } from '@ant-design/icons'
import { useHistoriqueProduitsUtilisation } from '@hooks/useProduitsCatalogue'
import { usePermissions } from '@hooks/usePermissions'
import { ROUTE_ACCESS } from '@constants/routeAccess'
import { formatMontantWithDevise } from '@utils/format'

const { Title, Text } = Typography

export const HistoriqueProduitsPage: React.FC = () => {
    const { hasPermission } = usePermissions()
    const { data: historique = [], isLoading } = useHistoriqueProduitsUtilisation()

    const columns = [
        {
            title: 'Produit',
            dataIndex: 'nom_marchandise',
            key: 'nom_marchandise',
            width: '30%',
            render: (nom: string) => <Text strong>{nom}</Text>,
        },
        {
            title: 'Utilisations',
            dataIndex: 'nb_utilisations',
            key: 'nb_utilisations',
            align: 'center' as const,
            sorter: (a: any, b: any) => a.nb_utilisations - b.nb_utilisations,
            render: (nb: number) => (
                <Tag color="blue" style={{ fontSize: '14px', padding: '4px 12px' }}>
                    {nb}x
                </Tag>
            ),
        },
        {
            title: 'Prix Moyen',
            dataIndex: 'prix_moyen',
            key: 'prix_moyen',
            align: 'right' as const,
            sorter: (a: any, b: any) => parseFloat(a.prix_moyen) - parseFloat(b.prix_moyen),
            render: (prix: string) => (
                <Text strong style={{ color: '#1890ff' }}>
                    {formatMontantWithDevise(parseFloat(prix), 'XOF')}
                </Text>
            ),
        },
        {
            title: 'Prix Min',
            dataIndex: 'prix_min',
            key: 'prix_min',
            align: 'right' as const,
            render: (prix: string) => (
                <Text type="success">
                    <FallOutlined /> {formatMontantWithDevise(parseFloat(prix), 'XOF')}
                </Text>
            ),
        },
        {
            title: 'Prix Max',
            dataIndex: 'prix_max',
            key: 'prix_max',
            align: 'right' as const,
            render: (prix: string) => (
                <Text type="danger">
                    <RiseOutlined /> {formatMontantWithDevise(parseFloat(prix), 'XOF')}
                </Text>
            ),
        },
        {
            title: 'Clients',
            dataIndex: 'clients',
            key: 'clients',
            width: '25%',
            ellipsis: true,
            render: (clients: string) => <Text type="secondary">{clients || 'N/A'}</Text>,
        },
    ]

    // Calculer les statistiques globales
    const totalUtilisations = historique.reduce((sum, item) => sum + parseInt(item.nb_utilisations), 0)
    const produitsUniques = historique.length
    const prixMoyenGlobal = historique.length > 0
        ? historique.reduce((sum, item) => sum + parseFloat(item.prix_moyen), 0) / historique.length
        : 0

    return (
        <div style={{ padding: 24 }}>
            {hasPermission(ROUTE_ACCESS.settingsCatalogueProduits) && (
                <Space style={{ marginBottom: 16 }}>
                    <Link to="/settings/catalogue-produits">
                        <Button type="link">← Catalogue produits</Button>
                    </Link>
                </Space>
            )}
            <Title level={2}>
                <ShoppingOutlined /> Historique marchandises
            </Title>
            <Text type="secondary">
                Libellés de marchandise les plus utilisés sur les colis (agrégation par nom saisi), prix observés et
                échantillon de clients
            </Text>

            {/* Statistiques globales */}
            <Row gutter={16} style={{ marginTop: 24, marginBottom: 24 }}>
                <Col xs={24} sm={8}>
                    <Card>
                        <Statistic
                            title="Produits Différents"
                            value={produitsUniques}
                            prefix={<ShoppingOutlined />}
                            valueStyle={{ color: '#3f8600' }}
                        />
                    </Card>
                </Col>
                <Col xs={24} sm={8}>
                    <Card>
                        <Statistic
                            title="Total Utilisations"
                            value={totalUtilisations}
                            valueStyle={{ color: '#1890ff' }}
                        />
                    </Card>
                </Col>
                <Col xs={24} sm={8}>
                    <Card>
                        <Statistic
                            title="Prix Moyen Global"
                            value={prixMoyenGlobal.toFixed(0)}
                            suffix="XOF"
                            prefix={<DollarOutlined />}
                            valueStyle={{ color: '#cf1322' }}
                        />
                    </Card>
                </Col>
            </Row>

            {/* Tableau historique */}
            <Card>
                {isLoading ? (
                    <div style={{ textAlign: 'center', padding: '50px' }}>
                        <Spin size="large" tip="Chargement de l'historique..." />
                    </div>
                ) : (
                    <Table
                        columns={columns}
                        dataSource={historique}
                        rowKey="nom_marchandise"
                        pagination={{
                            pageSize: 20,
                            showSizeChanger: true,
                            showTotal: (total: number) => `Total: ${total} produits`,
                        }}
                        scroll={{ x: 1000 }}
                    />
                )}
            </Card>
        </div>
    )
}
