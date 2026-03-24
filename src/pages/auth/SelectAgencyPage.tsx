import React, { useState } from 'react';
import { Typography, Card, Row, Col, Button, message, Spin } from 'antd';
import { ShopOutlined, CheckCircleOutlined, LogoutOutlined } from '@ant-design/icons';
import { useAuth } from '@hooks/useAuth';
import { usersService } from '@services/users.service';
import { apiService } from '@services/api.service';
import { Agency } from '@types';
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import '../public/LoginPage.css';

const { Title, Text } = Typography;

export const SelectAgencyPage: React.FC = () => {
    const { user, refreshUser, logout } = useAuth();
    const navigate = useNavigate();
    const [selectingId, setSelectingId] = useState<number | null>(null);

    const { data: agences, isLoading } = useQuery<Agency[]>({
        queryKey: ['agences'],
        queryFn: () => apiService.get('/agences'),
    });

    const handleSelect = async (agenceId: number) => {
        if (!user) return;
        setSelectingId(agenceId);
        try {
            await usersService.selectAgence(user.id, agenceId);
            message.success("Agence configurée avec succès !");
            await refreshUser();
            navigate('/dashboard');
        } catch (error: any) {
            message.error("Erreur lors de la sélection de l'agence");
        } finally {
            setSelectingId(null);
        }
    };

    if (isLoading) return <div style={{ height: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}><Spin size="large" /></div>;

    return (
        <div className="premium-login-container light">
            <div className="animated-background">
                <div className="gradient-blob blob-3"></div>
                <div className="grid-pattern"></div>
            </div>

            <div className="premium-login-content" style={{ flexDirection: 'column', padding: '40px 20px' }}>
                <div style={{ textAlign: 'center', marginBottom: 40, animation: 'fadeInDown 0.8s ease' }}>
                    <Title level={1} className="brand-title-premium">Choisissez votre Agence</Title>
                    <Text className="brand-subtitle-premium">
                        Bienvenue {user?.nom_complet}. Veuillez sélectionner votre agence de rattachement pour commencer.
                    </Text>
                </div>

                <div style={{ maxWidth: 1000, width: '100%', margin: '0 auto' }}>
                    <Row gutter={[24, 24]}>
                        {agences?.map((agence) => (
                            <Col xs={24} sm={12} md={8} key={agence.id}>
                                <Card
                                    hoverable
                                    className="glass-form-container"
                                    style={{
                                        height: '100%',
                                        padding: 24,
                                        display: 'flex',
                                        flexDirection: 'column',
                                        transition: 'all 0.3s'
                                    }}
                                    onClick={() => handleSelect(agence.id)}
                                >
                                    <div style={{ textAlign: 'center', marginBottom: 20 }}>
                                        <div className="feature-icon-wrapper" style={{ margin: '0 auto 16px' }}>
                                            <ShopOutlined className="feature-icon-premium" />
                                        </div>
                                        <Title level={4} style={{ margin: 0 }}>
                                            {agence.name || (agence as any).nom || agence.code}
                                        </Title>
                                        <Text type="secondary">
                                            {agence.address || (agence as any).adresse || 'Adresse non renseignée'}
                                        </Text>
                                    </div>

                                    <div style={{ marginTop: 'auto', textAlign: 'center' }}>
                                        <Button
                                            type="primary"
                                            block
                                            className="glass-button"
                                            loading={selectingId === agence.id}
                                            icon={<CheckCircleOutlined />}
                                        >
                                            Sélectionner
                                        </Button>
                                    </div>
                                </Card>
                            </Col>
                        ))}
                    </Row>

                    <div style={{ textAlign: 'center', marginTop: 40 }}>
                        <Button type="link" onClick={logout} icon={<LogoutOutlined />}>
                            Changer de compte
                        </Button>
                    </div>
                </div>
            </div>

            <style>{`
                @keyframes fadeInDown {
                    from { opacity: 0; transform: translateY(-30px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .glass-form-container .ant-card-body { padding: 0; }
                .glass-form-container:hover { transform: translateY(-8px); border-color: #3b82f6; }
            `}</style>
        </div>
    );
};
