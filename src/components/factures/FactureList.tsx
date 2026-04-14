import React, { useState } from "react";
import {
  Table,
  Button,
  Space,
  Input,
  Select,
  Tag,
  Popconfirm,
  Tooltip,
  Card,
  Row,
  Col,
  DatePicker,
  Progress,
} from "antd";
import {
  EyeOutlined,
  FilePdfOutlined,
  CheckCircleOutlined,
  CloseCircleOutlined,
  SearchOutlined,
  ReloadOutlined,
  PrinterOutlined,
  GlobalOutlined,
  CopyOutlined,
  LinkOutlined,
  DollarOutlined,
  WhatsAppOutlined,
  CreditCardOutlined
} from "@ant-design/icons";
import { Modal, Typography as AntdTypography } from "antd";
import { paiementsLienService } from "@services/paiementsLien.service";
import type { ColumnsType } from "antd/es/table";
import dayjs, { Dayjs } from "dayjs";
import { FactureColis } from "@types";
import {
  formatDate,
  formatMontantWithDevise,
  formatRefColis,
} from "@utils/format";
import { facturesService } from "@services/factures.service";
import { useQuery } from "@tanstack/react-query";
import { PaginationParams } from "@types";
import { WithPermission } from "@components/common/WithPermission";
import { PERMISSIONS } from "@constants/permissions";
import { FactureListSkeleton } from "@components/common/SkeletonLoader";
import { EmptyFacturesList, EmptySearchState, EmptyErrorState } from "@components/common/EmptyState";
import { VirtualTable } from "@components/common/VirtualTable";
import toast from "react-hot-toast";

import { PaiementForm } from "@components/paiements/PaiementForm";
import { useCreatePaiement } from "@hooks/usePaiements";

const { RangePicker } = DatePicker;
const { Option } = Select;

interface FactureListProps {
  type?: "proforma" | "definitive";
  onView?: (facture: FactureColis) => void;
}

// ─── Helper: calcule le statut paiement d'une facture ───────────
const getPaymentStatus = (facture: FactureColis) => {
  const ttc = Number(facture.montant_ttc) || 0;
  const paye = Number(facture.montant_paye) || 0;
  const pct = ttc > 0 ? Math.round((paye / ttc) * 100) : 0;

  if (paye <= 0) return { label: 'Non payé', color: '#ff4d4f', tagColor: 'error', pct: 0 };
  if (paye >= ttc) return { label: 'Payé', color: '#52c41a', tagColor: 'success', pct: 100 };
  return { label: `Partiel (${pct}%)`, color: '#fa8c16', tagColor: 'warning', pct };
};


export const FactureList: React.FC<FactureListProps> = ({ type, onView }) => {
  const [pagination, setPagination] = useState<PaginationParams>({
    page: 1,
    limit: 20,
  });
  const [searchTerm, setSearchTerm] = useState("");
  const [typeFilter, setTypeFilter] = useState<
    "proforma" | "definitive" | undefined
  >(type);
  const [dateRange, setDateRange] = useState<
    [Dayjs | null, Dayjs | null] | null
  >(null);
  const [isLinkModalVisible, setIsLinkModalVisible] = useState(false);
  const [generatedLink, setGeneratedLink] = useState("");
  const [currentFacture, setCurrentFacture] = useState<FactureColis | null>(null);
  const [isGenerating, setIsGenerating] = useState(false);
  const [isPaymentModalVisible, setIsPaymentModalVisible] = useState(false);

  const createPaymentMutation = useCreatePaiement();

  const { data, isLoading, refetch } = useQuery({
    queryKey: ["factures", typeFilter, pagination, searchTerm],
    queryFn: () => facturesService.getFactures(typeFilter, pagination),
  });

  const handleSearch = (value: string) => {
    setSearchTerm(value);
    setPagination({ ...pagination, page: 1 });
  };

  const handleTableChange = (newPagination: any) => {
    setPagination({
      page: newPagination.current || 1,
      limit: newPagination.pageSize || 20,
    });
  };

  const handlePrint = async (facture: FactureColis) => {
    try {
      await facturesService.printFacture(facture.id);
    } catch (error) {
      toast.error("Erreur lors de l'impression");
    }
  };

  const handleDownload = async (facture: FactureColis) => {
    try {
      await facturesService.downloadPDF(facture.id);
      toast.success("Facture téléchargée avec succès");
    } catch (error) {
      toast.error("Erreur lors du téléchargement");
    }
  };

  const handleValidate = async (id: number) => {
    try {
      await facturesService.validateFacture(id);
      toast.success("Facture validée avec succès");
      refetch();
    } catch (error) {
      toast.error("Erreur lors de la validation");
    }
  };

  const handleCancel = async (id: number) => {
    try {
      await facturesService.cancelFacture(id);
      toast.success("Facture annulée");
      refetch();
    } catch (error) {
      toast.error("Erreur lors de l'annulation");
    }
  };

  const handleGenerateLink = async (facture: FactureColis) => {
    try {
      setIsGenerating(true);
      setCurrentFacture(facture);
      const data = await paiementsLienService.generateLink(facture.id);
      const publicUrl = `${window.location.origin}/pay/${data.token}`;
      setGeneratedLink(publicUrl);
      setIsLinkModalVisible(true);
    } catch (error: any) {
      toast.error(error.response?.data?.message || "Erreur lors de la génération du lien");
    } finally {
      setIsGenerating(false);
    }
  };

  const copyToClipboard = () => {
    navigator.clipboard.writeText(generatedLink);
    toast.success("Lien copié dans le presse-papier");
  };

  const shareViaWhatsApp = () => {
    const message = `Bonjour, voici le lien de paiement pour votre facture ${currentFacture?.num_facture} : ${generatedLink}`;
    window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, "_blank");
  };

  const handleOpenPaymentModal = (facture: FactureColis) => {
    setCurrentFacture(facture);
    setIsPaymentModalVisible(true);
  };

  const handlePaymentSubmit = async (data: any) => {
    try {
      await createPaymentMutation.mutateAsync(data);
      setIsPaymentModalVisible(false);
      refetch();
      toast.success("Paiement enregistré avec succès");
    } catch (error) {
      // toast déjà géré par le hook ou l'intercepteur ? 
      // On laisse le hook gérer le feedback d'erreur
    }
  };

  const columns: ColumnsType<FactureColis> = [
    {
      title: "N° Facture",
      dataIndex: "num_facture",
      key: "num_facture",
      fixed: "left",
      width: 150,
      render: (text: string) => (
        <Tag color="blue" style={{ fontWeight: "bold" }}>
          {text}
        </Tag>
      ),
    },
    {
      title: "Référence Colis",
      dataIndex: "ref_colis",
      key: "ref_colis",
      width: 150,
      render: (text: string) => formatRefColis(text),
    },
    {
      title: "Date",
      dataIndex: "date_facture",
      key: "date_facture",
      width: 120,
      render: (_date: string, record: FactureColis) =>
        formatDate(
          (record as any).date_facture ??
            (record as any).created_at ??
            (record as any).date_facture ??
            (record as any).createdAt,
        ),
      sorter: true,
    },
    {
      title: "Montant TTC",
      dataIndex: "montant_ttc",
      key: "montant_ttc",
      width: 150,
      render: (montant: number) => formatMontantWithDevise(montant),
      sorter: true,
    },
    {
      title: "Paiement",
      key: "paiement",
      width: 160,
      render: (_: any, record: FactureColis) => {
        const status = getPaymentStatus(record);
        const paye = Number(record.montant_paye) || 0;
        const ttc = Number(record.montant_ttc) || 0;
        return (
          <Tooltip title={`${paye.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.')} / ${ttc.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.')} FCFA`}>
            <div style={{ minWidth: 130 }}>
              <Tag color={status.tagColor} style={{ marginBottom: 4, fontWeight: 600, fontSize: 11 }}>
                {status.label}
              </Tag>
              {status.pct > 0 && status.pct < 100 && (
                <Progress
                  percent={status.pct}
                  size="small"
                  strokeColor={status.color}
                  showInfo={false}
                  style={{ margin: 0 }}
                />
              )}
            </div>
          </Tooltip>
        );
      },
      filters: [
        { text: 'Payé', value: 'paid' },
        { text: 'Partiel', value: 'partial' },
        { text: 'Non payé', value: 'unpaid' },
      ],
      onFilter: (value: any, record: FactureColis) => {
        const paye = Number(record.montant_paye) || 0;
        const ttc = Number(record.montant_ttc) || 0;
        if (value === 'paid') return paye >= ttc;
        if (value === 'partial') return paye > 0 && paye < ttc;
        return paye <= 0;
      },
    },
    {
      title: "Statut",
      key: "etat",
      width: 120,
      render: (_: any, record: FactureColis) => (
        <Tag color={record.etat === 1 ? "success" : record.etat === 2 ? "error" : "warning"}>
          {record.etat === 1 ? "Validée" : record.etat === 2 ? "Annulée" : "Proforma"}
        </Tag>
      ),
      filters: [
        { text: "Validée", value: 1 },
        { text: "Proforma", value: 0 },
        { text: "Annulée", value: 2 },
      ],
    },
    {
      title: "Actions",
      key: "actions",
      fixed: "right",
      width: 200,
      render: (_: any, record: FactureColis) => (
        <Space size="small">
          {onView && (
            <Tooltip title="Voir détails">
              <Button
                size="small"
                icon={<EyeOutlined />}
                onClick={() => onView(record)}
              />
            </Tooltip>
          )}

          <Tooltip title="Imprimer">
            <Button
              size="small"
              icon={<PrinterOutlined />}
              onClick={() => handlePrint(record)}
            />
          </Tooltip>

          <Tooltip title="Télécharger PDF">
            <Button
              size="small"
              icon={<FilePdfOutlined />}
              onClick={() => handleDownload(record)}
            />
          </Tooltip>

          <Tooltip title="Lien Paiement Mobile Money">
            <Button
              size="small"
              icon={<LinkOutlined />}
              onClick={() => handleGenerateLink(record)}
              loading={isGenerating && currentFacture?.id === record.id}
              style={{ color: '#ff7900' }}
            />
          </Tooltip>

          <WithPermission permission={PERMISSIONS.PAIEMENTS.CREATE}>
            {Number(record.montant_paye) < Number(record.montant_ttc) && record.etat !== 2 && (
              <Tooltip title="Enregistrer un paiement manuel">
                <Button
                  size="small"
                  type="primary"
                  ghost
                  icon={<DollarOutlined />}
                  onClick={() => handleOpenPaymentModal(record)}
                  style={{ color: '#52c41a', borderColor: '#52c41a' }}
                />
              </Tooltip>
            )}
          </WithPermission>

          {record.etat === 0 && (
            <WithPermission permission={PERMISSIONS.FACTURES.VALIDATE}>
              <Tooltip title="Valider">
                <Popconfirm
                  title="Valider cette facture proforma ?"
                  onConfirm={() => handleValidate(record.id)}
                  okText="Oui"
                  cancelText="Non"
                >
                  <Button
                    type="primary"
                    size="small"
                    icon={<CheckCircleOutlined />}
                  />
                </Popconfirm>
              </Tooltip>
            </WithPermission>
          )}

          {record.etat === 0 && (
            <WithPermission permission={PERMISSIONS.FACTURES.CANCEL}>
              <Popconfirm
                title="Annuler cette facture ?"
                onConfirm={() => handleCancel(record.id)}
                okText="Oui"
                cancelText="Non"
              >
                <Tooltip title="Annuler">
                  <Button
                    type="primary"
                    danger
                    size="small"
                    icon={<CloseCircleOutlined />}
                  />
                </Tooltip>
              </Popconfirm>
            </WithPermission>
          )}
        </Space>
      ),
    },
  ];

  if (isLoading && !data) {
    return <FactureListSkeleton />
  }

  return (
    <div>
      {/* BARRE DE FILTRES ET RECHERCHE */}
      <Card style={{ marginBottom: 16 }}>
        <Row gutter={16} align="middle">
          <Col xs={24} sm={12} md={8}>
            <Input
              placeholder="Rechercher par numéro facture, référence colis..."
              prefix={<SearchOutlined />}
              allowClear
              value={searchTerm}
              onChange={(e: any) => handleSearch(e.target.value)}
              onPressEnter={(e: any) => handleSearch(e.currentTarget.value)}
              size="large"
            />
          </Col>

          <Col xs={24} sm={12} md={6}>
            <Select
              placeholder="Type de facture"
              allowClear
              value={typeFilter}
              onChange={setTypeFilter}
              style={{ width: "100%" }}
              size="large"
            >
              <Option value="proforma">Proforma</Option>
              <Option value="definitive">Définitive</Option>
            </Select>
          </Col>

          <Col xs={24} sm={12} md={6}>
            <RangePicker
              style={{ width: "100%" }}
              size="large"
              value={dateRange}
              onChange={(dates: any) =>
                setDateRange(dates as [Dayjs | null, Dayjs | null])
              }
              format="DD/MM/YYYY"
            />
          </Col>

          <Col xs={24} sm={12} md={4}>
            <Button
              icon={<ReloadOutlined />}
              onClick={() => refetch()}
              size="large"
            >
              Actualiser
            </Button>
          </Col>
        </Row>
      </Card>

      {/* TABLEAU */}
      <Card>
        <VirtualTable<FactureColis>
          columns={columns}
          dataSource={data?.data || []}
          loading={isLoading}
          rowKey="id"
          scroll={{ x: 1200 }}
          totalLabel="factures"
          locale={{
            emptyText: searchTerm
              ? <EmptySearchState searchTerm={searchTerm} onClearSearch={() => { setSearchTerm(''); setPagination({ ...pagination, page: 1 }); }} />
              : <EmptyFacturesList />,
          }}
          pagination={{
            current: pagination.page,
            pageSize: pagination.limit,
            total: data?.total || 0,
            showSizeChanger: true,
            showTotal: (total: number) => `Total : ${total} factures`,
            pageSizeOptions: ["10", "20", "50", "100"],
          }}
          onChange={handleTableChange}
        />
      </Card>

      {/* MODAL LIEN DE PAIEMENT */}
      <Modal
        title="Lien de Paiement Mobile Money"
        open={isLinkModalVisible}
        onCancel={() => setIsLinkModalVisible(false)}
        footer={[
          <Button key="close" onClick={() => setIsLinkModalVisible(false)}>
            Fermer
          </Button>,
          <Button
            key="whatsapp"
            type="primary"
            icon={<WhatsAppOutlined />}
            onClick={shareViaWhatsApp}
            style={{ background: "#25D366", borderColor: "#25D366" }}
          >
            WhatsApp
          </Button>,
          <Button
            key="copy"
            type="primary"
            icon={<CopyOutlined />}
            onClick={copyToClipboard}
          >
            Copier le lien
          </Button>,
        ]}
      >
        <Space direction="vertical" style={{ width: "100%" }} size="middle">
          <AntdTypography.Text type="secondary">
            Partagez ce lien avec votre client pour qu'il puisse payer via
            Orange Money ou Wave.
          </AntdTypography.Text>
          <Input
            value={generatedLink}
            readOnly
            addonBefore={<GlobalOutlined />}
            size="large"
          />
          <Card size="small" style={{ background: "#f5f5f5" }}>
            <AntdTypography.Paragraph style={{ marginBottom: 0 }}>
              <strong>Facture:</strong> {currentFacture?.num_facture}
              <br />
              <strong>Montant:</strong>{" "}
              {currentFacture ? formatMontantWithDevise(currentFacture.montant_ttc) : ""}
            </AntdTypography.Paragraph>
          </Card>
        </Space>
      </Modal>

      {/* MODAL ENREGISTRER PAIEMENT */}
      <Modal
        title={`Enregistrer un paiement — Facture ${currentFacture?.num_facture}`}
        open={isPaymentModalVisible}
        onCancel={() => setIsPaymentModalVisible(false)}
        footer={null}
        width="60%"
        destroyOnClose
      >
        {currentFacture?.colis?.ref_colis && (
          <PaiementForm
            refColis={currentFacture.colis.ref_colis}
            onSubmit={handlePaymentSubmit}
            onCancel={() => setIsPaymentModalVisible(false)}
            loading={createPaymentMutation.isPending}
          />
        )}
      </Modal>
    </div>
  );
};
