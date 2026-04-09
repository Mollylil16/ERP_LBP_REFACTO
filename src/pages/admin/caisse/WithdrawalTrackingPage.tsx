import React, { useState, useEffect } from "react";
import { Table, Card, Typography, Space, Tag, DatePicker, Select, Button, Avatar, Statistic, Row, Col } from "antd";
import {
    ArrowUpOutlined,
    BankOutlined,
    MobileOutlined,
    UserOutlined,
    HistoryOutlined,
    CalendarOutlined,
} from "@ant-design/icons";
import { apiService } from "@services/api.service";
import { formatMontant, formatDate } from "@utils/format";
import dayjs from "dayjs";

const { Title, Text } = Typography;
const { RangePicker } = DatePicker;

const WithdrawalTrackingPage: React.FC = () => {
    const [loading, setLoading] = useState(false);
    const [data, setData] = useState<any[]>([]);
    const [filters, setFilters] = useState<{ dates: dayjs.Dayjs[] | null; id_caisse: string | number | undefined }>({
        dates: [dayjs().startOf('month'), dayjs()],
        id_caisse: undefined,
    });

    const fetchData = async () => {
        setLoading(true);
        try {
            const params: any = {};
            if (filters.dates) {
                params.date_debut = filters.dates[0].format('YYYY-MM-DD');
                params.date_fin = filters.dates[1].format('YYYY-MM-DD');
            }
            if (filters.id_caisse) {
                params.id_caisse = filters.id_caisse;
            }

            // Endpoint dédié: renvoie uniquement les décaissements (retraits/sorties).
            // apiService.get attend une config Axios -> { params }
            const response = await apiService.get<any[]>('/caisse/withdrawals', { params });
            setData(response);
        } catch (error) {
            console.error("Erreur lors du chargement des retraits:", error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchData();
    }, [filters]);

    const getModeIcon = (mode: string) => {
        if (!mode) return <BankOutlined />;
        const m = mode.toLowerCase();
        if (m.includes('wave') || m.includes('orange')) return <MobileOutlined style={{ color: '#faad14' }} />;
        if (m.includes('virement') || m.includes('banque')) return <BankOutlined style={{ color: '#1890ff' }} />;
        return <BankOutlined />;
    };

    const columns = [
        {
            title: "Date & Heure",
            dataIndex: "date_mouvement",
            key: "date_mouvement",
            render: (text: string) => (
                <Space>
                    <CalendarOutlined style={{ color: '#8c8c8c' }} />
                    {formatDate(text)}
                </Space>
            ),
            sorter: (a: any, b: any) => new Date(a.date_mouvement).getTime() - new Date(b.date_mouvement).getTime(),
        },
        {
            title: "Libellé",
            dataIndex: "libelle",
            key: "libelle",
            render: (text: string) => <Text strong>{text}</Text>,
        },
        {
            title: "Montant",
            dataIndex: "montant",
            key: "montant",
            render: (amount: number) => (
                <Text type="danger" strong style={{ fontSize: 16 }}>
                    {formatMontant(amount)}
                </Text>
            ),
            sorter: (a: any, b: any) => a.montant - b.montant,
        },
        {
            title: "Moyen",
            dataIndex: "mode_retrait",
            key: "mode_retrait",
            render: (mode: string) => (
                <Tag icon={getModeIcon(mode)} color={mode ? 'orange' : 'blue'}>
                    {mode || 'ESPECE'}
                </Tag>
            ),
        },
        {
            title: "Caisse",
            dataIndex: "caisse",
            key: "caisse",
            render: (caisse: any) => caisse?.nom || 'N/A',
        },
        {
            title: "Utilisateur",
            dataIndex: "code_user",
            key: "code_user",
            render: (user: string) => (
                <Space>
                    <Avatar size="small" icon={<UserOutlined />} backgroundColor="#f56a00" />
                    <Text>{user || 'Admin'}</Text>
                </Space>
            ),
        },
    ];

    const totalWithdrawals = data.reduce((sum, item) => sum + Number(item.montant), 0);

    return (
        <div style={{ padding: 24 }}>
            <Space direction="vertical" style={{ width: '100%' }} size="large">
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div>
                        <Title level={2}>💸 Suivi des Retraits & Sorties de Fonds</Title>
                        <Text type="secondary">Contrôlez qui retire, quand et par quel moyen.</Text>
                    </div>
                    <Button icon={<HistoryOutlined />} onClick={fetchData}>Rafraîchir</Button>
                </div>

                <Row gutter={16}>
                    <Col span={8}>
                        <Card>
                            <Statistic
                                title="Total Retraits (Période)"
                                value={totalWithdrawals}
                                precision={0}
                                valueStyle={{ color: '#cf1322' }}
                                prefix={<ArrowUpOutlined />}
                                suffix="FCFA"
                            />
                        </Card>
                    </Col>
                    <Col span={8}>
                        <Card>
                            <Statistic
                                title="Nombre d'opérations"
                                value={data.length}
                                prefix={<HistoryOutlined />}
                            />
                        </Card>
                    </Col>
                </Row>

                <Card>
                    <Space style={{ marginBottom: 16 }}>
                        <RangePicker
                            value={filters.dates as any}
                            onChange={(dates: any) => setFilters({ ...filters, dates: dates })}
                        />
                        <Select
                            placeholder="Filtrer par caisse"
                            style={{ width: 200 }}
                            allowClear
                            onChange={(val: any) => setFilters({ ...filters, id_caisse: val })}
                        >
                            <Select.Option value={1}>Caisse Principale</Select.Option>
                        </Select>
                    </Space>

                    <Table
                        columns={columns}
                        dataSource={data}
                        loading={loading}
                        rowKey="id"
                        pagination={{ pageSize: 10 }}
                    />
                </Card>
            </Space>
        </div>
    );
};

export default WithdrawalTrackingPage;
