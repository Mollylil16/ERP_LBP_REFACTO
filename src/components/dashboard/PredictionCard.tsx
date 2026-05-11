import React from 'react';
import { Card, Statistic, Row, Col, Typography, Tag, Space, Tooltip } from 'antd';
import { RiseOutlined, FallOutlined, LineOutlined, InfoCircleOutlined, RobotOutlined } from '@ant-design/icons';
import { predictFutureVolume } from '@utils/ai';

const { Title, Text, Paragraph } = Typography;

interface PredictionCardProps {
    data: number[];
    loading?: boolean;
}

export const PredictionCard: React.FC<PredictionCardProps> = ({ data, loading }) => {
    const { prediction, trend, confidence } = predictFutureVolume(data);

    const getTrendIcon = () => {
        switch (trend) {
            case 'up': return <RiseOutlined style={{ color: '#52c41a' }} />;
            case 'down': return <FallOutlined style={{ color: '#f5222d' }} />;
            default: return <LineOutlined style={{ color: '#faad14' }} />;
        }
    };

    const getTrendText = () => {
        switch (trend) {
            case 'up': return 'En Hausse';
            case 'down': return 'En Baisse';
            default: return 'Stable';
        }
    };

    const getConfidenceColor = () => {
        if (confidence > 0.7) return 'success';
        if (confidence > 0.4) return 'warning';
        return 'default';
    };

    return (
        <Card
            title={
                <Space>
                    <RobotOutlined style={{ color: '#1890ff' }} />
                    <span>Prévisions IA (Mois Suivant)</span>
                </Space>
            }
            extra={
                <Tooltip title="Prévisions générées par IA basées sur vos tendances récentes">
                    <InfoCircleOutlined style={{ color: '#8c8c8c' }} />
                </Tooltip>
            }
            className="prediction-card"
            loading={loading}
        >
            <Row gutter={16} align="middle">
                <Col xs={24} sm={12}>
                    <Statistic
                        title="Volume Estimé"
                        value={prediction}
                        suffix="colis"
                        valueStyle={{ fontWeight: 'bold' }}
                    />
                </Col>
                <Col xs={24} sm={12}>
                    <div style={{ textAlign: 'right' }}>
                        <Text type="secondary" block style={{ marginBottom: 4 }}>Tendance</Text>
                        <Space>
                            {getTrendIcon()}
                            <Text strong>{getTrendText()}</Text>
                        </Space>
                    </div>
                </Col>
            </Row>

            <div style={{ marginTop: 16, paddingTop: 16, borderTop: '1px solid #f0f0f0' }}>
                <Row justify="space-between" align="middle">
                    <Text type="secondary" size="small">Indice de confiance :</Text>
                    <Tag color={getConfidenceColor()}>
                        {Math.round(confidence * 100)}%
                    </Tag>
                </Row>
                <Paragraph style={{ color: '#8c8c8c', fontSize: '11px', marginTop: 8, marginBottom: 0 }}>
                    * Ces données sont des projections statistiques et peuvent varier selon les conditions réelles.
                </Paragraph>
            </div>
        </Card>
    );
};
