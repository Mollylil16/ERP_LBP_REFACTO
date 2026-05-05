import React, { useMemo, useState, useEffect } from "react";
import { Table, Card, Typography, Space, Tag, DatePicker, Select, Button, Avatar, Statistic, Row, Col, message } from "antd";
import {
    ArrowUpOutlined,
    BankOutlined,
    MobileOutlined,
    UserOutlined,
    HistoryOutlined,
    CalendarOutlined,
    FilePdfOutlined,
    FileExcelOutlined,
} from "@ant-design/icons";
import { apiService } from "@services/api.service";
import { formatMontant, formatDate } from "@utils/format";
import dayjs from "dayjs";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import { exportTableToExcel } from "@utils/export/excel";
import { fmtPdfNum, loadLogoBase64, drawLBPHeader, drawLBPFooters, LBP_TABLE_HEAD_STYLES, LBP_TABLE_ALT_ROW } from "@utils/pdfHelpers";
import { useCaisses } from "@hooks/useCaisse";

const { Title, Text } = Typography;
const { RangePicker } = DatePicker;

const WithdrawalTrackingPage: React.FC = () => {
    const [loading, setLoading] = useState(false);
    const [data, setData] = useState<any[]>([]);
    const [filters, setFilters] = useState<{ dates: dayjs.Dayjs[] | null; id_caisse: string | number | undefined }>({
        dates: [dayjs().startOf('month'), dayjs()],
        id_caisse: undefined,
    });
    const { data: caisses } = useCaisses();

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
            message.error("Chargement des retraits impossible");
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchData();
    }, [filters]);

    const exportPdf = async () => {
        if (!data.length) return;
        try {
            const logo = await loadLogoBase64();
            const doc = new jsPDF({ orientation: "landscape", unit: "mm", format: "a4" });

            const d0 = filters.dates?.[0]?.format("YYYY-MM-DD") ?? "";
            const d1 = filters.dates?.[1]?.format("YYYY-MM-DD") ?? "";
            const caisseLabel = (() => {
                if (!filters.id_caisse) return "Toutes les caisses";
                const c = caisses?.find((x: any) => Number(x.id) === Number(filters.id_caisse));
                return c?.libelle || (c as any)?.nom || c?.code || `Caisse #${filters.id_caisse}`;
            })();

            let y = drawLBPHeader(doc, {
                title: "Suivi des retraits",
                subtitle: `Période : ${d0} → ${d1}`,
                rightInfo: `Caisse : ${caisseLabel}`,
                logoBase64: logo,
            });

            const body = data.map((r: any, idx: number) => [
                String(idx + 1),
                formatDate(r.date_mouvement),
                r.libelle || "—",
                r.mode_retrait || "ESPECE",
                r.caisse?.nom || r.caisse?.libelle || "—",
                r.code_user || "—",
                fmtPdfNum(r.montant),
            ]);

            autoTable(doc, {
                startY: y,
                head: [["#", "Date", "Libellé", "Moyen", "Caisse", "Utilisateur", "Montant (FCFA)"]],
                body,
                styles: { fontSize: 8, cellPadding: 3 },
                headStyles: LBP_TABLE_HEAD_STYLES,
                alternateRowStyles: LBP_TABLE_ALT_ROW,
                columnStyles: {
                    0: { cellWidth: 10, halign: "center" },
                    6: { halign: "right", fontStyle: "bold" },
                },
            });

            drawLBPFooters(doc);
            doc.save(`retraits_${d0}_${d1}.pdf`);
            message.success("PDF généré");
        } catch {
            message.error("Export PDF impossible");
        }
    };

    const exportExcel = async () => {
        if (!data.length) return;
        try {
            const d0 = filters.dates?.[0]?.format("YYYY-MM-DD") ?? "";
            const d1 = filters.dates?.[1]?.format("YYYY-MM-DD") ?? "";
            const caisseLabel = (() => {
                if (!filters.id_caisse) return "Toutes";
                const c = caisses?.find((x: any) => Number(x.id) === Number(filters.id_caisse));
                return c?.libelle || (c as any)?.nom || c?.code || String(filters.id_caisse);
            })();

            await exportTableToExcel(
                {
                    headers: ["Date", "Libellé", "Montant", "Moyen", "Caisse", "Utilisateur"],
                    rows: data.map((r: any) => [
                        formatDate(r.date_mouvement),
                        r.libelle || "—",
                        Number(r.montant ?? 0),
                        r.mode_retrait || "ESPECE",
                        r.caisse?.nom || r.caisse?.libelle || "—",
                        r.code_user || "—",
                    ]),
                },
                `retraits_${d0}_${d1}`,
                {
                    title: `Retraits — ${d0} → ${d1} (Caisse: ${caisseLabel})`,
                    sheetName: "Retraits",
                },
            );
            message.success("Fichier Excel généré");
        } catch {
            message.error("Export Excel impossible");
        }
    };

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
    const canExport = data.length > 0;

    const caisseOptions = useMemo(() => {
        const rows = Array.isArray(caisses) ? caisses : [];
        return rows.map((c: any) => ({
            label: (c.libelle || c.nom || c.code || `Caisse #${c.id}`) + (c.id_agence ? "" : ""),
            value: c.id,
        }));
    }, [caisses]);

    return (
        <div style={{ padding: 24 }}>
            <Space direction="vertical" style={{ width: '100%' }} size="large">
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div>
                        <Title level={2}>Suivi des retraits & sorties de fonds</Title>
                        <Text type="secondary">Contrôlez qui retire, quand et par quel moyen.</Text>
                    </div>
                    <Space>
                        <Button icon={<HistoryOutlined />} onClick={fetchData}>Rafraîchir</Button>
                        <Button icon={<FilePdfOutlined />} onClick={exportPdf} disabled={!canExport}>
                            Exporter PDF
                        </Button>
                        <Button icon={<FileExcelOutlined />} onClick={exportExcel} disabled={!canExport}>
                            Exporter Excel
                        </Button>
                    </Space>
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
                            value={filters.id_caisse as any}
                            options={caisseOptions}
                            onChange={(val: any) => setFilters({ ...filters, id_caisse: val })}
                        >
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
