import React from 'react'
import { Card, List, Typography, Button, Tag, Space, Alert, Skeleton } from 'antd'
import {
    RobotOutlined,
    ArrowRightOutlined,
    AlertOutlined,
    CheckCircleOutlined,
    InfoCircleOutlined,
    BulbOutlined
} from '@ant-design/icons'
import { EmptyRecommandations } from '@components/common/EmptyState'

const { Title, Text, Paragraph } = Typography

interface AIRecommendation {
    type: 'error' | 'warning' | 'success' | 'info'
    title: string
    description: string
    cause?: string
    action: string
    model?: string
    explanation?: {
        factors?: string[]
        confidence?: number
    }
    metrics?: {
        value?: number
        threshold?: number
        status?: string
        unpaid_amount?: number
    }
    drift?: {
        status?: string
        score?: number
    }
    actions?: Array<{
        code: string
        label: string
        route?: string
    }>
}

interface AIIntelligencePanelProps {
    recommendations: AIRecommendation[]
    loading?: boolean
    onActionClick?: (action: { code: string; label: string; route?: string }) => void
    monitoring?: {
        summary?: {
            alerts_count?: number
            avg_drift_score?: number
            high_priority_count?: number
        }
    } | null
}

export const AIIntelligencePanel: React.FC<AIIntelligencePanelProps> = ({
    recommendations,
    loading,
    onActionClick,
    monitoring
}) => {
    if (loading) {
        return (
            <Card title={<Space><RobotOutlined /> Intelligence IA</Space>}>
                <Skeleton active avatar paragraph={{ rows: 3 }} />
            </Card>
        )
    }

    return (
        <Card
            title={
                <Space>
                    <RobotOutlined style={{ color: '#1890ff' }} />
                    <span>Analyses & Recommandations IA</span>
                </Space>
            }
            className="ai-intelligence-panel"
            bodyStyle={{ padding: '0 24px 24px' }}
        >
            {monitoring?.summary ? (
                <Space size={8} style={{ marginBottom: 12 }}>
                    <Tag color={Number(monitoring.summary.alerts_count || 0) > 0 ? 'red' : 'green'}>
                        Alertes: {Number(monitoring.summary.alerts_count || 0)}
                    </Tag>
                    <Tag color={Number(monitoring.summary.high_priority_count || 0) > 0 ? 'orange' : 'blue'}>
                        Priorité haute: {Number(monitoring.summary.high_priority_count || 0)}
                    </Tag>
                    <Tag color={Number(monitoring.summary.avg_drift_score || 0) > 0.3 ? 'orange' : 'green'}>
                        Drift moyen: {(Number(monitoring.summary.avg_drift_score || 0) * 100).toFixed(1)}%
                    </Tag>
                </Space>
            ) : null}
            {recommendations.length === 0 ? (
                <EmptyRecommandations />
            ) : (
                <List
                    itemLayout="vertical"
                    dataSource={recommendations}
                    renderItem={(item: AIRecommendation) => (
                        <List.Item className="ai-reco-item">
                            <Alert
                                message={
                                    <Space>
                                        <Title level={5} style={{ margin: 0, color: 'inherit' }}>{item.title}</Title>
                                        <Tag color={item.type === 'error' ? 'red' : item.type === 'warning' ? 'orange' : 'blue'}>
                                            {item.type.toUpperCase()}
                                        </Tag>
                                    </Space>
                                }
                                description={
                                    <div style={{ marginTop: 10 }}>
                                        <Paragraph strong>{item.description}</Paragraph>

                                        {item.cause && (
                                            <div style={{ marginBottom: 16 }}>
                                                <Text type="secondary"><InfoCircleOutlined /> Cause identifiée :</Text>
                                                <Paragraph style={{ marginTop: 4 }}>{item.cause}</Paragraph>
                                            </div>
                                        )}

                                        <div style={{ backgroundColor: '#f6ffed', padding: '12px', border: '1px border #b7eb8f', borderRadius: '4px' }}>
                                            <Text strong style={{ color: '#389e0d' }}><BulbOutlined /> Action recommandée :</Text>
                                            <Paragraph style={{ marginTop: 4, marginBottom: 8 }}>{item.action}</Paragraph>
                                            <Space wrap>
                                                {(item.actions && item.actions.length > 0 ? item.actions : [{ code: 'DEFAULT', label: 'Appliquer maintenant' }]).map((actionItem) => (
                                                    <Button
                                                        key={actionItem.code}
                                                        type="primary"
                                                        size="small"
                                                        icon={<ArrowRightOutlined />}
                                                        onClick={() => onActionClick?.(actionItem)}
                                                    >
                                                        {actionItem.label}
                                                    </Button>
                                                ))}
                                            </Space>
                                            {item.explanation?.factors?.length ? (
                                                <div style={{ marginTop: 8 }}>
                                                    <Text type="secondary">
                                                        Explication: {item.explanation.factors.slice(0, 3).join(' | ')}
                                                    </Text>
                                                </div>
                                            ) : null}
                                            <Space size={8} style={{ marginTop: 8 }}>
                                                {typeof item.explanation?.confidence === 'number' ? (
                                                    <Tag color="blue">Confiance {(item.explanation.confidence * 100).toFixed(0)}%</Tag>
                                                ) : null}
                                                {typeof item.drift?.score === 'number' ? (
                                                    <Tag color={item.drift.score > 0.3 ? 'orange' : 'green'}>
                                                        Drift {(item.drift.score * 100).toFixed(1)}%
                                                    </Tag>
                                                ) : null}
                                            </Space>
                                        </div>
                                    </div>
                                }
                                type={item.type}
                                showIcon
                                icon={item.type === 'error' ? <AlertOutlined /> : item.type === 'success' ? <CheckCircleOutlined /> : <RobotOutlined />}
                                style={{ marginBottom: 16, border: 'none' }}
                            />
                        </List.Item>
                    )}
                />
            )}
        </Card>
    )
}
