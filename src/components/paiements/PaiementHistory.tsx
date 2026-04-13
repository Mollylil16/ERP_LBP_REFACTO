import React from 'react';
import { Card, Table, Tag, Button, Space, Tooltip, Empty } from 'antd';
import { FileTextOutlined, CheckCircleOutlined, CloseCircleOutlined, DownloadOutlined } from '@ant-design/icons';
import { useQuery } from '@tanstack/react-query';
import { paiementsService } from '../../services/paiements.service';
import { Paiement } from '../../types';
import type { ColumnsType } from 'antd/es/table';
import { formatDate } from '@utils/format';

interface PaiementHistoryProps {
    factureId: number;
}


export const PaiementHistory: React.FC<PaiementHistoryProps> = ({ factureId }) => {
    const { data: paiements, isLoading } = useQuery({
        queryKey: ['paiements', 'facture', factureId],
        queryFn: () => paiementsService.getPaiementsByFacture(factureId),
    });

    const handleDownloadReceipt = async (paiementId: number) => {
        try {
            await paiementsService.downloadReceipt(paiementId);
        } catch (error) {
            console.error('Erreur téléchargement reçu:', error);
        }
    };

    const columns: ColumnsType<Paiement> = [
        {
            title: 'Date',
            dataIndex: 'date_paiement',
            key: 'date_paiement',
            render: (date: string) => formatDate(date),
            sorter: (a: Paiement, b: Paiement) => new Date(a.date_paiement).getTime() - new Date(b.date_paiement).getTime(),
        },
        {
            title: 'Montant',
            dataIndex: 'montant',
            key: 'montant',
            render: (montant: number) => (
                <strong style={{ color: '#52c41a' }}>{montant.toLocaleString()} FCFA</strong>
            ),
            sorter: (a: Paiement, b: Paiement) => a.montant - b.montant,
        },
        {
            title: 'Mode',
            dataIndex: 'mode_paiement',
            key: 'mode_paiement',
            render: (mode: string) => {
                const colors: Record<string, string> = {
                    ESPECE: 'green',
                    CHEQUE: 'blue',
                    VIREMENT: 'purple',
                    CARTE: 'cyan',
                };
                return <Tag color={colors[mode] || 'default'}>{mode}</Tag>;
            },
        },
        {
            title: 'Référence',
            dataIndex: 'reference_paiement',
            key: 'reference_paiement',
            render: (ref?: string) => ref || '-',
        },
        {
            title: 'Statut',
            dataIndex: 'etat_validation',
            key: 'etat_validation',
            render: (etat: number) =>
                etat === 1 ? (
                    <Tag icon={<CheckCircleOutlined />} color="success">
                        Validé
                    </Tag>
                ) : (
                    <Tag icon={<CloseCircleOutlined />} color="error">
                        Annulé
                    </Tag>
                ),
        },
        {
            title: 'Actions',
            key: 'actions',
            render: (_: any, record: Paiement) => (
                <Space>
                    <Tooltip title="Télécharger le reçu">
                        <Button
                            type="link"
                            icon={<DownloadOutlined />}
                            onClick={() => handleDownloadReceipt(record.id)}
                            disabled={record.etat_validation === 0}
                        >
                            Reçu
                        </Button>
                    </Tooltip>
                </Space>
            ),
        },
    ];

    const totalPaye = paiements?.reduce((sum: number, p: Paiement) => {
        return (p.etat_validation || 0) === 1 ? sum + Number(p.montant) : sum;
    }, 0) || 0;

    return (
        <Card
            title={
                <Space>
                    <FileTextOutlined />
                    <span>Historique des Paiements</span>
                </Space>
            }
            extra={
                <div>
                    Total payé:{' '}
                    <strong style={{ color: '#52c41a', fontSize: 16 }}>
                        {totalPaye.toLocaleString()} FCFA
                    </strong>
                </div>
            }
        >
            <Table
                dataSource={paiements || []}
                columns={columns}
                rowKey="id"
                loading={isLoading}
                pagination={{
                    pageSize: 10,
                    showSizeChanger: true,
                    showTotal: (total: number) => `Total: ${total} paiement(s)`,
                }}
                locale={{
                    emptyText: (
                        <Empty
                            description="Aucun paiement enregistré"
                            image={Empty.PRESENTED_IMAGE_SIMPLE}
                        />
                    ),
                }}
                summary={() => (
                    <Table.Summary fixed>
                        <Table.Summary.Row>
                            <Table.Summary.Cell index={0} colSpan={1}>
                                <strong>TOTAL</strong>
                            </Table.Summary.Cell>
                            <Table.Summary.Cell index={1}>
                                <strong style={{ color: '#52c41a' }}>
                                    {totalPaye.toLocaleString()} FCFA
                                </strong>
                            </Table.Summary.Cell>
                            <Table.Summary.Cell index={2} colSpan={4} />
                        </Table.Summary.Row>
                    </Table.Summary>
                )}
            />
        </Card>
    );
};
