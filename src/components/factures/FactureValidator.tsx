import React, { useState } from 'react';
import { Card, Descriptions, Alert, Space, Modal, Form, Input, Typography } from 'antd';
import { CheckCircleOutlined, ExclamationCircleOutlined } from '@ant-design/icons';
import { useMutation, useQueryClient, useQuery } from '@tanstack/react-query';
import { facturesService } from '../../services/factures.service';
import { paiementsService } from '../../services/paiements.service';
import { confirmValidation } from '../common/ConfirmDialogs';
import { TracedActionButton } from '../audit/TracedActionButton';

const { Text } = Typography;

interface FactureValidatorProps {
    facture: any;
    onSuccess?: () => void;
}

export const FactureValidator: React.FC<FactureValidatorProps> = ({ facture, onSuccess }) => {
    const [showValidationModal, setShowValidationModal] = useState(false);
    const queryClient = useQueryClient();
    const { data: paiements, isLoading } = useQuery({
        queryKey: ['paiements', 'facture', facture.id],
        queryFn: () => paiementsService.getPaiementsByFacture(facture.id),
    });
    const [form] = Form.useForm();

    const validateMutation = useMutation({
        mutationFn: () => facturesService.validateFacture(facture.id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['factures'] });
            queryClient.invalidateQueries({ queryKey: ['factures', facture.id] });
            setShowValidationModal(false);
            onSuccess?.();
        },
    });

    const handleValidate = () => {
        confirmValidation(`la facture ${facture.num_facture}`, () => {
            setShowValidationModal(true);
        });
    };

    const handleSubmitValidation = async () => {
        const values = await form.validateFields();
        validateMutation.mutate(values);
    };

    const canValidate = facture.etat === 0; // Proforma uniquement
    const isValidated = facture.etat === 1;

    // Vérifications avant validation
    const checks = {
        hasMarchandises: facture.colis?.marchandises?.length > 0,
        hasClient: !!facture.colis?.client,
        hasValidAmount: Number(facture.montant_ttc) > 0,
    };

    const allChecksPass = Object.values(checks).every(Boolean);

    return (
        <>
            <Card
                title={
                    <Space>
                        {isValidated ? (
                            <CheckCircleOutlined style={{ color: '#52c41a' }} />
                        ) : (
                            <ExclamationCircleOutlined style={{ color: '#faad14' }} />
                        )}
                        <span>Validation de la Facture</span>
                    </Space>
                }
            >
                {isValidated ? (
                    <Alert
                        message="Facture Validée"
                        description="Cette facture a été validée et ne peut plus être modifiée."
                        type="success"
                        showIcon
                    />
                ) : (
                    <>
                        <Alert
                            message="Facture Proforma"
                            description="Cette facture est en mode proforma. Vérifiez les informations avant de la valider."
                            type="warning"
                            showIcon
                            style={{ marginBottom: 16 }}
                        />

                        <Descriptions bordered column={1} size="small">
                            <Descriptions.Item
                                label="Marchandises"
                                labelStyle={{ width: '40%' }}
                            >
                                {checks.hasMarchandises ? (
                                    <span style={{ color: '#52c41a' }}>
                                        ✓ {facture.colis?.marchandises?.length} marchandise(s)
                                    </span>
                                ) : (
                                    <span style={{ color: '#ff4d4f' }}>✗ Aucune marchandise</span>
                                )}
                            </Descriptions.Item>
                            <Descriptions.Item label="Client">
                                {checks.hasClient ? (
                                    <span style={{ color: '#52c41a' }}>
                                        ✓ {facture.colis?.client?.nom_exp}
                                    </span>
                                ) : (
                                    <span style={{ color: '#ff4d4f' }}>✗ Client manquant</span>
                                )}
                            </Descriptions.Item>
                            <Descriptions.Item label="Montant">
                                {checks.hasValidAmount ? (
                                    <span style={{ color: '#52c41a' }}>
                                        ✓ {facture.montant_ttc?.toLocaleString()} FCFA
                                    </span>
                                ) : (
                                    <span style={{ color: '#ff4d4f' }}>✗ Montant invalide</span>
                                )}
                            </Descriptions.Item>
                        </Descriptions>

                        {!allChecksPass && (
                            <Alert
                                message="Validation Impossible"
                                description="Certaines vérifications ont échoué. Veuillez corriger les problèmes avant de valider."
                                type="error"
                                showIcon
                                style={{ marginTop: 16 }}
                            />
                        )}

                        <div style={{ marginTop: 16, textAlign: 'right' }}>
                            <TracedActionButton
                                type="primary"
                                icon={<CheckCircleOutlined />}
                                onClick={handleValidate}
                                disabled={!allChecksPass || !canValidate}
                                loading={validateMutation.isPending}
                                traceHint="La validation définitive est enregistrée dans le journal d’audit (facture, utilisateur, horodatage)."
                            >
                                Valider la Facture
                            </TracedActionButton>
                        </div>
                    </>
                )}
            </Card>

            <Modal
                title="Validation de la Facture"
                open={showValidationModal}
                onOk={handleSubmitValidation}
                onCancel={() => setShowValidationModal(false)}
                okText="Valider"
                cancelText="Annuler"
                confirmLoading={validateMutation.isPending}
            >
                <Alert
                    message="Attention"
                    description="Une fois validée, la facture ne pourra plus être modifiée."
                    type="warning"
                    showIcon
                    style={{ marginBottom: 16 }}
                />

                <Text type="secondary" style={{ display: 'block', marginBottom: 12, fontSize: 13 }}>
                    Action tracée : une entrée d’audit métier est créée lorsque vous confirmez.
                </Text>
                <Form form={form} layout="vertical">
                    <Form.Item
                        label="Notes de validation (optionnel)"
                        name="notes"
                    >
                        <Input.TextArea
                            rows={3}
                            placeholder="Ajoutez des notes si nécessaire..."
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </>
    );
};
