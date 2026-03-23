import React from 'react'
import { Card, Row, Col, Statistic, Empty, Skeleton, Space, Typography } from 'antd'
import { ShopOutlined, ArrowUpOutlined, ArrowDownOutlined, WalletOutlined } from '@ant-design/icons'
import { formatMontantWithDevise } from '@utils/format'

const { Text } = Typography

interface AgencyPerformance {
    agenceId: number
    agenceNom: string
    agenceCode: string
    entrees: number
    sorties: number
    solde: number
    date: string
}

interface AgenciesPerformanceGridProps {
    data: AgencyPerformance[]
    loading: boolean
}

export const AgenciesPerformanceGrid: React.FC<AgenciesPerformanceGridProps> = ({ data, loading }) => {
    if (loading) {
        return <Skeleton active paragraph={{ rows: 4 }} />
    }

    if (!data || data.length === 0) {
        return <Empty description="Aucune donnée de performance d'agence disponible" />
    }

    return (
        <div className="agencies-performance-section" style={{ marginBottom: 24 }}>
            <Typography.Title level={4} style={{ marginBottom: 16 }}>
                <ShopOutlined /> Performance des Agences (Aujourd'hui)
            </Typography.Title>

            <Row gutter={[16, 16]}>
                {data.map((agency) => (
                    <Col xs={24} sm={12} lg={8} xl={6} key={agency.agenceId}>
                        <Card
                            hoverable
                            className="agency-perf-card"
                            title={
                                <Space>
                                    <ShopOutlined style={{ color: '#1890ff' }} />
                                    <span>{agency.agenceNom}</span>
                                    <Text type="secondary" size="small">({agency.agenceCode})</Text>
                                </Space>
                            }
                            bodyStyle={{ padding: '12px 24px' }}
                        >
                            <Space direction="vertical" style={{ width: '100%' }} size="small">
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <Text type="secondary">Entrées</Text>
                                    <Text type="success" strong>
                                        <ArrowUpOutlined /> {formatMontantWithDevise(agency.entrees)}
                                    </Text>
                                </div>

                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <Text type="secondary">Sorties</Text>
                                    <Text type="danger" strong>
                                        <ArrowDownOutlined /> {formatMontantWithDevise(agency.sorties)}
                                    </Text>
                                </div>

                                <div style={{
                                    display: 'flex',
                                    justifyContent: 'space-between',
                                    alignItems: 'center',
                                    marginTop: 8,
                                    paddingTop: 8,
                                    borderTop: '1px solid #f0f0f0'
                                }}>
                                    <Text strong>Solde Net</Text>
                                    <Text strong style={{ color: agency.solde >= 0 ? '#52c41a' : '#ff4d4f', fontSize: '1.1em' }}>
                                        <WalletOutlined /> {formatMontantWithDevise(agency.solde)}
                                    </Text>
                                </div>
                            </Space>
                        </Card>
                    </Col>
                ))}
            </Row>
        </div>
    )
}
