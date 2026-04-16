import React, { useState } from 'react';
import { Form, Input, Button, Typography, Card, message } from 'antd';
import { LockOutlined, ThunderboltOutlined, LogoutOutlined } from '@ant-design/icons';
import { useAuth } from '@hooks/useAuth';
import { usersService } from '@services/users.service';
import { useNavigate } from 'react-router-dom';
import '../public/LoginPage.css'; // Réutilisation des styles premium
import { shouldSkipAgencySelection } from '@utils/agencyGate';

const { Title, Text } = Typography;

export const ChangePasswordPage: React.FC = () => {
    const [loading, setLoading] = useState(false);
    const { user, logout, refreshUser } = useAuth();
    const navigate = useNavigate();

    const onFinish = async (values: any) => {
        if (!user) return;
        if (values.newPassword !== values.confirmPassword) {
            return message.error("Les mots de passe ne correspondent pas");
        }

        setLoading(true);
        try {
            await usersService.changePassword(user.id, values.oldPassword, values.newPassword);
            message.success("Mot de passe mis à jour avec succès !");

            // Rafraîchir l'utilisateur pour mettre à jour le flag must_change_password
            const updated = await refreshUser();
            const permsRaw =
                sessionStorage.getItem('lbp_permissions') ?? localStorage.getItem('lbp_permissions');
            let perms: string[] = [];
            try {
                const p = permsRaw ? JSON.parse(permsRaw) : [];
                perms = Array.isArray(p) ? p : [];
            } catch {
                perms = [];
            }

            // Suite du flux : sélection d'agence obligatoire pour les profils « agence »
            if (updated && !shouldSkipAgencySelection(updated, perms) && !updated.agence_selected) {
                navigate('/auth/select-agency', { replace: true });
            } else {
                navigate('/', { replace: true });
            }
        } catch (error: any) {
            message.error(error.response?.data?.message || "Erreur lors du changement de mot de passe");
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="premium-login-container light">
            <div className="animated-background">
                <div className="gradient-blob blob-1"></div>
                <div className="gradient-blob blob-2"></div>
                <div className="grid-pattern"></div>
            </div>

            <div className="premium-login-content" style={{ justifyContent: 'center', alignItems: 'center' }}>
                <div className="glass-form-container" style={{ animation: 'fadeInUp 0.8s ease-out' }}>
                    <div className="form-header-premium">
                        <div className="logo-glass-container" style={{ width: 80, height: 80, marginBottom: 16 }}>
                            <img src="/logo_lbp.png" alt="LBP" style={{ width: '100%' }} />
                        </div>
                        <Title level={2} className="form-title-premium">Sécurité</Title>
                        <Text className="form-subtitle-premium">
                            Par mesure de sécurité, vous devez changer votre mot de passe temporaire pour continuer.
                        </Text>
                    </div>

                    <Form layout="vertical" onFinish={onFinish} size="large">
                        <Form.Item
                            name="oldPassword"
                            label={<Text strong>Mot de passe actuel (temporaire)</Text>}
                            rules={[{ required: true, message: 'Requis' }]}
                        >
                            <Input.Password prefix={<LockOutlined />} className="glass-input" />
                        </Form.Item>

                        <Form.Item
                            name="newPassword"
                            label={<Text strong>Nouveau mot de passe</Text>}
                            rules={[{ required: true, min: 6, message: 'Minimum 6 caractères' }]}
                        >
                            <Input.Password prefix={<LockOutlined />} className="glass-input" />
                        </Form.Item>

                        <Form.Item
                            name="confirmPassword"
                            label={<Text strong>Confirmer le nouveau mot de passe</Text>}
                            rules={[{ required: true, message: 'Veuillez confirmer' }]}
                        >
                            <Input.Password prefix={<LockOutlined />} className="glass-input" />
                        </Form.Item>

                        <Button
                            type="primary"
                            htmlType="submit"
                            loading={loading}
                            block
                            className="glass-button"
                            icon={<ThunderboltOutlined />}
                        >
                            Mettre à jour et continuer
                        </Button>

                        <Button
                            type="link"
                            onClick={logout}
                            block
                            style={{ marginTop: 16 }}
                            icon={<LogoutOutlined />}
                        >
                            Se déconnecter
                        </Button>
                    </Form>
                </div>
            </div>

            <style>{`
                @keyframes fadeInUp {
                    from { opacity: 0; transform: translateY(40px); }
                    to { opacity: 1; transform: translateY(0); }
                }
            `}</style>
        </div>
    );
};
