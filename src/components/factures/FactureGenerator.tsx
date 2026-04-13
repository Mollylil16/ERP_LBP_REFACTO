import React, { useState } from 'react';
import { apiService } from '../../services/api.service';
import { Card, Button, Steps, Result, Spin, Alert, Descriptions, Table } from 'antd';
import { CheckCircleOutlined, LoadingOutlined, FilePdfOutlined } from '@ant-design/icons';
import { useMutation, useQuery } from '@tanstack/react-query';
import { facturesService } from '../../services/factures.service';
import { colisService } from '../../services/colis.service';
import { useNavigate } from 'react-router-dom';
import { formatDate } from '@utils/format';

interface FactureGeneratorProps {
    colisId: number;
    onSuccess?: (facture: any) => void;
    onCancel?: () => void;
}

export const FactureGenerator: React.FC<FactureGeneratorProps> = ({ colisId, onSuccess, onCancel }) => {
    const [currentStep, setCurrentStep] = useState(0);
    const [generatedFacture, setGeneratedFacture] = useState<any>(null);
    const navigate = useNavigate();

    // Récupérer les détails du colis
    const { data: colis, isLoading: loadingColis } = useQuery({
        queryKey: ['colis', colisId],
        queryFn: () => colisService.getColisById(colisId),
    });

    // Mutation pour générer la facture
    const generateMutation = useMutation({
        mutationFn: () => facturesService.createFactureProforma(colisId),
        onSuccess: (facture) => {
            setGeneratedFacture(facture);
            setCurrentStep(2);
            onSuccess?.(facture);
        },
    });

    const handleGenerate = () => {
        setCurrentStep(1);
        generateMutation.mutate();
    };

    const handleViewFacture = () => {
        navigate(`/admin/factures/${generatedFacture.id}`);
    };

    const handleDownloadPDF = async () => {
        try {
            await facturesService.downloadPDF(generatedFacture.id, `facture-${generatedFacture.num_facture}.pdf`);
        } catch (error) {
            console.error('Erreur téléchargement PDF:', error);
        }
    };

    if (loadingColis) {
        return (
            <Card>
                <Spin tip="Chargement des informations du colis..." />
            </Card>
        );
    }

    if (!colis) {
        return (
            <Card>
                <Alert message="Colis non trouvé" type="error" />
            </Card>
        );
    }

    const steps = [
        {
            title: 'Vérification',
            description: 'Vérification des données du colis',
        },
        {
            title: 'Génération',
            description: 'Génération de la facture',
        },
        {
            title: 'Terminé',
            description: 'Facture générée avec succès',
        },
    ];

    const marchandisesColumns = [
        {
            title: 'Marchandise',
            dataIndex: 'nom_marchandise',
            key: 'nom_marchandise',
        },
        {
            title: 'Qté',
            dataIndex: 'nbre_colis',
            key: 'nbre_colis',
        },
        {
            title: 'Prix Unit.',
            dataIndex: 'prix_unit',
            key: 'prix_unit',
            render: (val: number) => `${val?.toLocaleString()} FCFA`,
        },
        {
            title: 'Total',
            key: 'total',
            render: (_: any, record: any) => {
                const total =
                    Number(record.prix_unit) * Number(record.nbre_colis) +
                    Number(record.prix_emballage || 0) +
                    Number(record.prix_assurance || 0) +
                    Number(record.prix_agence || 0);
                return `${total.toLocaleString()} FCFA`;
            },
        },
    ];

    return (
        <Card title="Générateur de Facture">
            <Steps current={currentStep} items={steps} style={{ marginBottom: 24 }} />

            {currentStep === 0 && (
                <>
                    <Alert
                        message="Vérifiez les informations avant de générer la facture"
                        type="info"
                        showIcon
                        style={{ marginBottom: 16 }}
                    />

                    <Descriptions bordered column={2} style={{ marginBottom: 16 }}>
                        <Descriptions.Item label="Référence Colis" span={2}>
                            <strong>{colis.ref_colis}</strong>
                        </Descriptions.Item>
                        <Descriptions.Item label="Client">{colis.client?.nom_exp}</Descriptions.Item>
                        <Descriptions.Item label="Téléphone">{colis.client?.tel_exp}</Descriptions.Item>
                        <Descriptions.Item label="Destination">{colis.nom_dest}</Descriptions.Item>
                        <Descriptions.Item label="Date d'envoi">
                            {formatDate(colis.date_envoi)}
                        </Descriptions.Item>
                    </Descriptions>

                    <Table
                        dataSource={colis.marchandises || []}
                        columns={marchandisesColumns}
                        rowKey="id"
                        pagination={false}
                        size="small"
                        summary={(pageData: any[]) => {
                            let totalGeneral = 0;
                            pageData.forEach((record: any) => {
                                totalGeneral +=
                                    Number(record.prix_unit) * Number(record.nbre_colis) +
                                    Number(record.prix_emballage || 0) +
                                    Number(record.prix_assurance || 0) +
                                    Number(record.prix_agence || 0);
                            });

                            return (
                                <Table.Summary.Row>
                                    <Table.Summary.Cell index={0} colSpan={3} align="right">
                                        <strong>TOTAL</strong>
                                    </Table.Summary.Cell>
                                    <Table.Summary.Cell index={1}>
                                        <strong>{totalGeneral.toLocaleString()} FCFA</strong>
                                    </Table.Summary.Cell>
                                </Table.Summary.Row>
                            );
                        }}
                    />

                    <div style={{ marginTop: 24, textAlign: 'right' }}>
                        {onCancel && (
                            <Button onClick={onCancel} style={{ marginRight: 8 }}>
                                Annuler
                            </Button>
                        )}
                        <Button type="primary" onClick={handleGenerate}>
                            Générer la Facture
                        </Button>
                    </div>
                </>
            )}

            {currentStep === 1 && (
                <div style={{ textAlign: 'center', padding: '40px 0' }}>
                    <Spin
                        indicator={<LoadingOutlined style={{ fontSize: 48 }} spin />}
                        tip="Génération de la facture en cours..."
                    />
                </div>
            )}

            {currentStep === 2 && generatedFacture && (
                <Result
                    status="success"
                    title="Facture générée avec succès !"
                    subTitle={`Numéro de facture: ${generatedFacture.num_facture}`}
                    icon={<CheckCircleOutlined style={{ color: '#52c41a' }} />}
                    extra={[
                        <Button key="view" type="primary" onClick={handleViewFacture}>
                            Voir la Facture
                        </Button>,
                        <Button key="pdf" icon={<FilePdfOutlined />} onClick={handleDownloadPDF}>
                            Télécharger PDF
                        </Button>,
                        onCancel && (
                            <Button key="close" onClick={onCancel}>
                                Fermer
                            </Button>
                        ),
                    ]}
                >
                    <Descriptions bordered column={2}>
                        <Descriptions.Item label="Montant HT">
                            {generatedFacture.montant_ht?.toLocaleString()} FCFA
                        </Descriptions.Item>
                        <Descriptions.Item label="Montant TTC">
                            {generatedFacture.montant_ttc?.toLocaleString()} FCFA
                        </Descriptions.Item>
                        <Descriptions.Item label="Statut" span={2}>
                            {generatedFacture.etat === 0 ? 'Proforma' : 'Définitive'}
                        </Descriptions.Item>
                    </Descriptions>
                </Result>
            )}

            {generateMutation.isError && (
                <Alert
                    message="Erreur"
                    description={
                        generateMutation.error?.message || 'Impossible de générer la facture'
                    }
                    type="error"
                    showIcon
                    style={{ marginTop: 16 }}
                />
            )}
        </Card>
    );
};
