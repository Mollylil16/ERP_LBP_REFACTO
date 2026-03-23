import React, { useState, useEffect } from 'react'
import { rapportsService } from '../../../services/rapports.service'
import { Card, Table, Statistic, Row, Col, Typography, Tag, Space, Spin, Alert } from 'antd'
import { DollarOutlined, LineChartOutlined, PercentageOutlined, WalletOutlined } from '@ant-design/icons'

const { Title, Text } = Typography

const RentabiliteTarifPage: React.FC = () => {
    const [loading, setLoading] = useState(true)
    const [data, setData] = useState<any[]>([])
    const [error, setError] = useState<string | null>(null)

    useEffect(() => {
        fetchData()
    }, [])

    const fetchData = async () => {
        setLoading(true)
        setError(null)
        try {
            const response = await rapportsService.getFinancesParTarif()
            setData(response)
        } catch (err: any) {
            console.error('Erreur lors de la récupération des données de rentabilité:', err)
            setError('Impossible de charger les données de rentabilité. Veuillez réessayer plus tard.')
        } finally {
            setLoading(false)
        }
    }

    const totals = (data || []).reduce(
        (acc, curr) => ({
            revenu: acc.revenu + (curr.revenu_total || 0),
            cout: acc.cout + (curr.cout_total || 0),
            charges: acc.charges + (curr.charges_totales || 0),
            benefice: acc.benefice + (curr.benefice_total || 0),
            poids: acc.poids + (curr.poids_total || 0),
        }),
        { revenu: 0, cout: 0, charges: 0, benefice: 0, poids: 0 }
    )

    const columns = [
        {
            title: 'Tarif / Prix Unit.',
            dataIndex: 'nom_tarif',
            key: 'nom_tarif',
            render: (text: string, record: any) => (
                <Space direction="vertical" size={0}>
                    <Text strong>{text || '(Sans Nom)'}</Text>
                    <Text type="secondary" style={{ fontSize: '12px' }}>
                        Base: {(record.tarif || 0).toLocaleString()} FCFA
                    </Text>
                </Space>
            ),
        },
        {
            title: 'Poids Total',
            dataIndex: 'poids_total',
            key: 'poids_total',
            render: (val: number) => `${(val || 0).toLocaleString()} kg`,
        },
        {
            title: 'Montant Encaissé',
            dataIndex: 'revenu_total',
            key: 'revenu_total',
            render: (val: number) => <Text strong>{(val || 0).toLocaleString()} FCFA</Text>,
        },
        {
            title: 'Dû Compagnie',
            dataIndex: 'cout_total',
            key: 'cout_total',
            render: (val: number) => `${(val || 0).toLocaleString()} FCFA`,
        },
        {
            title: 'Charges',
            dataIndex: 'charges_totales',
            key: 'charges_totales',
            render: (val: number) => `${(val || 0).toLocaleString()} FCFA`,
        },
        {
            title: 'Bénéfice Net',
            dataIndex: 'benefice_total',
            key: 'benefice_total',
            render: (val: number) => {
                const safeVal = val || 0
                const color = safeVal > 0 ? 'green' : safeVal < 0 ? 'red' : 'default'
                return <Tag color={color} style={{ fontWeight: 'bold' }}>{safeVal.toLocaleString()} FCFA</Tag>
            },
        },
        {
            title: 'Marge',
            key: 'marge',
            render: (_: any, record: any) => {
                const marge = record.revenu_total > 0 ? (record.benefice_total / record.revenu_total) * 100 : 0
                const color = marge > 40 ? 'success' : marge > 20 ? 'warning' : 'error'
                return <Statistic value={marge} precision={1} suffix="%" valueStyle={{ fontSize: '14px', color: `var(--ant-${color}-color)` }} />
            },
        },
    ]

    if (error) {
        return <Alert message="Erreur" description={error} type="error" showIcon style={{ margin: '24px' }} />
    }

    return (
        <div style={{ padding: '24px' }}>
            <Title level={2} style={{ marginBottom: '24px' }}>
                <LineChartOutlined /> Analyse de la Rentabilité par Tarif
            </Title>

            <Spin spinning={loading}>
                <Row gutter={[16, 16]} style={{ marginBottom: '24px' }}>
                    <Col xs={24} sm={12} md={6}>
                        <Card bordered={false} className="stat-card">
                            <Statistic
                                title="Revenu Total"
                                value={totals.revenu}
                                prefix={<DollarOutlined />}
                                suffix="FCFA"
                                valueStyle={{ color: '#1890ff' }}
                            />
                        </Card>
                    </Col>
                    <Col xs={24} sm={12} md={6}>
                        <Card bordered={false} className="stat-card">
                            <Statistic
                                title="Dû Compagnies"
                                value={totals.cout}
                                prefix={<WalletOutlined />}
                                suffix="FCFA"
                                valueStyle={{ color: '#fa8c16' }}
                            />
                        </Card>
                    </Col>
                    <Col xs={24} sm={12} md={6}>
                        <Card bordered={false} className="stat-card">
                            <Statistic
                                title="Bénéfice Net Global"
                                value={totals.benefice}
                                prefix={<PercentageOutlined />}
                                suffix="FCFA"
                                valueStyle={{ color: totals.benefice >= 0 ? '#52c41a' : '#f5222d' }}
                            />
                        </Card>
                    </Col>
                    <Col xs={24} sm={12} md={6}>
                        <Card bordered={false} className="stat-card">
                            <Statistic
                                title="Marge Globale"
                                value={totals.revenu > 0 ? (totals.benefice / totals.revenu) * 100 : 0}
                                precision={1}
                                suffix="%"
                                valueStyle={{ color: '#722ed1' }}
                            />
                        </Card>
                    </Col>
                </Row>

                <Card title="Détails par Palier de Tarif" bordered={false}>
                    <Table
                        dataSource={data}
                        columns={columns}
                        rowKey={(record: any) => `${record.tarif}-${record.nom_tarif}`}
                        pagination={false}
                    />
                </Card>
            </Spin>
        </div>
    )
}

export default RentabiliteTarifPage
