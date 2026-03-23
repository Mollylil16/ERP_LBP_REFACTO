import React, { useState } from 'react';
import { Card, Descriptions, Tag, Divider, Table, Button, Space, Modal } from 'antd';
import { PrinterOutlined, EditOutlined, DeleteOutlined, FileTextOutlined, ArrowLeftOutlined } from '@ant-design/icons';
import { useQuery, useMutation } from '@tanstack/react-query';
import { colisService } from '@services/colis.service';
import { facturesService } from '@services/factures.service';
import { useNavigate } from 'react-router-dom';
import { FactureTemplate } from '../factures/FactureTemplate';

interface ColisDetailsProps {
    colisId: number;
    onClose?: () => void;
    onEdit?: () => void;
    onDelete?: () => void;
}

export const ColisDetails: React.FC<ColisDetailsProps> = ({ colisId, onClose, onEdit, onDelete }) => {
    const navigate = useNavigate();
    const [generatedFacture, setGeneratedFacture] = useState<any>(null); // Assuming a type for facture
    const [currentStep, setCurrentStep] = useState(1); // Assuming a step management

    const { data: colis, isLoading: loadingColis } = useQuery({
        queryKey: ['colis', colisId],
        queryFn: () => colisService.getColisById(colisId),
    });

    const { data: existingFacture } = useQuery({
        queryKey: ['facture', 'colis', colisId],
        queryFn: () => facturesService.getFactureByColis(colis?.ref_colis || ''),
        enabled: !!colis?.ref_colis,
    });

    const generateMutation = useMutation({
        mutationFn: () => facturesService.createFactureProforma(colis?.id || 0),
        onSuccess: (facture) => {
            setGeneratedFacture(facture);
            setCurrentStep(2);
            // Téléchargement automatique
            facturesService.downloadPDF(facture.id, `facture-${colis?.ref_colis}.pdf`);
        },
        onError: (error: any) => {
            // Si la facture existe déjà, on essaie de la récupérer
            if (error?.message?.includes('déjà') || error?.status === 400) {
                if (existingFacture) {
                    setGeneratedFacture(existingFacture);
                    setCurrentStep(2);
                }
            }
        }
    });

    if (loadingColis) {
        return <Card loading />;
    }

    if (!colis) {
        return <Card>Colis non trouvé</Card>;
    }

    const marchandisesColumns = [
        {
            title: 'Marchandise',
            dataIndex: 'nom_marchandise',
            key: 'nom_marchandise',
        },
        {
            title: 'Nbre Colis',
            dataIndex: 'nbre_colis',
            key: 'nbre_colis',
        },
        {
            title: 'Nbre Articles',
            dataIndex: 'nbre_articles',
            key: 'nbre_articles',
        },
        {
            title: 'Prix Unit.',
            dataIndex: 'prix_unit',
            key: 'prix_unit',
            render: (val: number) => `${Number(val || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".")} FCFA`,
        },
        {
            title: 'Emballage',
            dataIndex: 'prix_emballage',
            key: 'prix_emballage',
            render: (val: number) => `${Number(val || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".")} FCFA`,
        },
        {
            title: 'Assurance',
            dataIndex: 'prix_assurance',
            key: 'prix_assurance',
            render: (val: number) => `${Number(val || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".")} FCFA`,
        },
        {
            title: 'Total',
            key: 'total',
            render: (_: any, record: any) => {
                const total =
                    Number(record.prix_unit) * Number(record.poids_total) +
                    Number(record.prix_emballage || 0) +
                    Number(record.prix_assurance || 0) +
                    Number(record.prix_agence || 0);
                return `${total.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".")} FCFA`;
            },
        },
    ];

    const handleGenerateFacture = () => {
        // navigate(`/admin/factures/generate/${colisId}`); // Old logic
        generateMutation.mutate(); // New logic using mutation
    };

    if (currentStep === 2 && generatedFacture) {
        return (
            <div>
                <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between' }}>
                    <Button icon={<ArrowLeftOutlined />} onClick={() => setCurrentStep(1)}>
                        Retour aux détails
                    </Button>
                    <Space>
                        <Button
                            icon={<PrinterOutlined />}
                            onClick={() => facturesService.printFacture(generatedFacture.id)}
                        >
                            Imprimer
                        </Button>
                        <Button
                            type="primary"
                            icon={<FileTextOutlined />}
                            onClick={() => facturesService.downloadPDF(generatedFacture.id)}
                        >
                            Télécharger PDF
                        </Button>
                    </Space>
                </div>
                <FactureTemplate
                    facture={generatedFacture}
                    colis={colis}
                    mode="preview"
                />
            </div>
        );
    }

    return (
        <Card
            title={
                <Space>
                    <span>Détails du Colis</span>
                    <Tag color={colis.etat_validation === 1 ? 'green' : 'orange'}>
                        {colis.etat_validation === 1 ? 'Validé' : 'Brouillon'}
                    </Tag>
                </Space>
            }
            extra={
                <Space>
                    {onEdit && (
                        <Button icon={<EditOutlined />} onClick={onEdit}>
                            Modifier
                        </Button>
                    )}
                    <Button
                        icon={<FileTextOutlined />}
                        type="primary"
                        onClick={handleGenerateFacture}
                        loading={generateMutation.isPending}
                        disabled={generateMutation.isPending}
                    >
                        Générer Facture
                    </Button>
                    {onDelete && colis.etat_validation === 0 && (
                        <Button icon={<DeleteOutlined />} danger onClick={onDelete}>
                            Supprimer
                        </Button>
                    )}
                    {onClose && <Button onClick={onClose}>Fermer</Button>}
                </Space>
            }
        >
            <Descriptions bordered column={2}>
                <Descriptions.Item label="Référence" span={2}>
                    <strong>{colis.ref_colis}</strong>
                </Descriptions.Item>
                <Descriptions.Item label="Trafic">{colis.trafic_envoi}</Descriptions.Item>
                <Descriptions.Item label="Forme">{colis.forme_envoi}</Descriptions.Item>
                <Descriptions.Item label="Mode">{colis.mode_envoi || 'N/A'}</Descriptions.Item>
                <Descriptions.Item label="Date d'envoi">
                    {new Date(colis.date_envoi).toLocaleDateString('fr-FR')}
                </Descriptions.Item>
            </Descriptions>

            <Divider orientation="left">Expéditeur</Divider>
            <Descriptions bordered column={2}>
                <Descriptions.Item label="Nom">{colis.client?.nom_exp || colis.client_colis?.nom_exp || 'N/A'}</Descriptions.Item>
                <Descriptions.Item label="Téléphone">{colis.client?.tel_exp || colis.client_colis?.tel_exp || 'N/A'}</Descriptions.Item>
                <Descriptions.Item label="Type Pièce">{colis.client?.type_piece_exp || colis.client_colis?.type_piece_exp || 'N/A'}</Descriptions.Item>
                <Descriptions.Item label="N° Pièce">{colis.client?.num_piece_exp || colis.client_colis?.num_piece_exp || 'N/A'}</Descriptions.Item>
                {(colis.client?.email_exp || colis.client_colis?.email_exp) && (
                    <Descriptions.Item label="Email" span={2}>
                        {colis.client?.email_exp || colis.client_colis?.email_exp}
                    </Descriptions.Item>
                )}
            </Descriptions>

            <Divider orientation="left">Destinataire</Divider>
            <Descriptions bordered column={2}>
                <Descriptions.Item label="Nom">{colis.nom_dest || colis.nom_destinataire}</Descriptions.Item>
                <Descriptions.Item label="Lieu">{colis.lieu_dest || 'N/A'}</Descriptions.Item>
                <Descriptions.Item label="Téléphone">{colis.tel_dest || 'N/A'}</Descriptions.Item>
                <Descriptions.Item label="Email">{colis.email_dest || 'N/A'}</Descriptions.Item>
            </Descriptions>

            {(colis.nom_recup) && (
                <>
                    <Divider orientation="left">Récupérateur</Divider>
                    <Descriptions bordered column={2}>
                        <Descriptions.Item label="Nom">{colis.nom_recup}</Descriptions.Item>
                        <Descriptions.Item label="Téléphone">{colis.tel_recup || 'N/A'}</Descriptions.Item>
                        <Descriptions.Item label="Adresse" span={2}>
                            {colis.adresse_recup || 'N/A'}
                        </Descriptions.Item>
                    </Descriptions>
                </>
            )}

            <Divider orientation="left">Marchandises</Divider>
            <Table
                dataSource={colis.marchandises || []}
                columns={marchandisesColumns}
                rowKey="id"
                pagination={false}
                summary={(pageData: any[]) => {
                    let totalGeneral = 0;
                    pageData.forEach((record: any) => {
                        totalGeneral +=
                            Number(record.prix_unit) * Number(record.poids_total) +
                            Number(record.prix_emballage || 0) +
                            Number(record.prix_assurance || 0) +
                            Number(record.prix_agence || 0);
                    });

                    return (
                        <Table.Summary.Row>
                            <Table.Summary.Cell index={0} colSpan={6} align="right">
                                <strong>TOTAL GÉNÉRAL</strong>
                            </Table.Summary.Cell>
                            <Table.Summary.Cell index={1}>
                                <strong>{totalGeneral.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".")} FCFA</strong>
                            </Table.Summary.Cell>
                        </Table.Summary.Row>
                    );
                }}
            />
        </Card>
    );
};
