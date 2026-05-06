import React, { useState } from "react";
import {
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
  Grid,
  Modal,
  Upload,
} from "antd";
import {
  EditOutlined,
  DeleteOutlined,
  EyeOutlined,
  CheckCircleOutlined,
  SearchOutlined,
  PlusOutlined,
  ReloadOutlined,
  FilePdfOutlined,
  FileExcelOutlined,
  HomeOutlined,
  UploadOutlined,
} from "@ant-design/icons";
import type { ColumnsType } from "antd/es/table";
import { Colis } from "@types";
import {
  formatDate,
  formatMontantWithDevise,
  formatRefColis,
} from "@utils/format";
import './ColisList.css';
import {
  useColisList,
  useDeleteColis,
  useValidateColis,
  useReceiveAtHub,
} from "@hooks/useColis";
import { WithPermission } from "@components/common/WithPermission";
import { PERMISSIONS } from "@constants/permissions";
import { TableSkeleton } from "@components/common/SkeletonLoader";
import {
  EmptyListState,
  EmptySearchState,
} from "@components/common/EmptyState";
import { VirtualTable } from "@components/common/VirtualTable";
import { exportTableToPDF, exportTableToExcel } from "@utils/export";
import { message } from "antd";
import { useTranslation } from "@hooks/useTranslation";

// Helper pour obtenir les bonnes permissions selon le type d'envoi
const getPermissions = (formeEnvoi: "groupage" | "autres_envoi") => {
  return formeEnvoi === "groupage"
    ? PERMISSIONS.COLIS_GROUPAGE
    : PERMISSIONS.COLIS_AUTRES_ENVOIS;
};
import { usePermissions } from "@hooks/usePermissions";
import dayjs, { Dayjs } from "dayjs";
import { APP_CONFIG } from "@constants/application";
import { colisExtendedService } from "../../services/dashboard.service";


const { RangePicker } = DatePicker;
const { Option } = Select;

interface ColisListProps {
  formeEnvoi: "groupage" | "autres_envoi";
  onEdit?: (colis: Colis) => void;
  onView?: (colis: Colis) => void;
  onCreate?: () => void;
}

export const ColisList: React.FC<ColisListProps> = ({
  formeEnvoi,
  onEdit,
  onView,
  onCreate,
}) => {
  const [pagination, setPagination] = useState({ page: 1, limit: 20 });
  const [searchTerm, setSearchTerm] = useState("");
  const [traficFilter, setTraficFilter] = useState<string | undefined>();
  const [dateRange, setDateRange] = useState<
    [Dayjs | null, Dayjs | null] | null
  >(null);
  const [exporting, setExporting] = useState(false);

  // NOUVEAUX ÉTATS POUR LES EN-LOT / MASSE
  const [selectedRowKeys, setSelectedRowKeys] = useState<React.Key[]>([]);
  const [isImportModalVisible, setIsImportModalVisible] = useState(false);
  const [importText, setImportText] = useState("");
  const [importLoading, setImportLoading] = useState(false);

  const screens = Grid.useBreakpoint();
  const tableCompact = screens.lg === false;

  const { hasPermission } = usePermissions();
  const { t } = useTranslation("colis");
  const { t: tCommon } = useTranslation("common");
  const colisPermissions = getPermissions(formeEnvoi);
  const { data, isLoading, refetch } = useColisList(formeEnvoi, {
    ...pagination,
    search: searchTerm || undefined,
  });

  const deleteMutation = useDeleteColis();
  const validateMutation = useValidateColis();
  const receiveAtHubMutation = useReceiveAtHub();

  // VALIDATION EN LOT
  const handleBatchValidate = async () => {
    if (selectedRowKeys.length === 0) return;
    try {
      const ids = selectedRowKeys.map((key) => Number(key));
      const res = await colisExtendedService.batchValidate(ids, "EXPEDIE");
      message.success(`${res.updated} colis validés et marqués EXPÉDIÉS !`);
      if (res.errors.length > 0) {
        message.warning(`${res.errors.length} erreurs lors de la validation.`);
      }
      setSelectedRowKeys([]);
      refetch();
    } catch (err) {
      message.error("Erreur de validation en lot");
    }
  };

  // IMPORT EN MASSE (Pasted Excel or CSV)
  const handleImportSubmit = async () => {
    if (!importText.trim()) {
      message.warning("Veuillez coller des données avant de soumettre");
      return;
    }
    setImportLoading(true);
    try {
      // Parsing des lignes d'un tableau Excel copié-collé (séparé par des tabulations \t et retours à la ligne \n)
      const lines = importText.trim().split("\n");
      const rows = lines.map((line) => {
        const parts = line.split("\t");
        return {
          nom_dest: parts[0] || "",
          lieu_dest: parts[1] || "",
          poids_total: Number(parts[2]) || 1,
          total_montant: Number(parts[3]) || 0,
          nom_marchandise: parts[4] || "Marchandise Excel",
        };
      });

      const res = await colisExtendedService.batchImport(rows);
      message.success(`Félicitations ! ${res.created} colis importés avec succès !`);
      if (res.errors.length > 0) {
        Modal.error({
          title: "Erreurs d'importation",
          content: (
            <div style={{ maxHeight: 200, overflowY: "auto" }}>
              {res.errors.map((err, idx) => (
                <div key={idx}>Ligne {err.row}: {err.message}</div>
              ))}
            </div>
          ),
        });
      }
      setIsImportModalVisible(false);
      setImportText("");
      refetch();
    } catch (err) {
      message.error("Erreur lors de l'importation en masse");
    } finally {
      setImportLoading(false);
    }
  };

  const handleSearch = (value: string) => {
    setSearchTerm(value);
    setPagination({ ...pagination, page: 1 });
  };

  const handleTableChange = (newPagination: { current?: number; pageSize?: number }) => {
    setPagination({
      page: newPagination.current || 1,
      limit: newPagination.pageSize || 20,
    });
  };

  const handleDelete = async (id: number) => {
    await deleteMutation.mutateAsync(id);
    refetch();
  };

  const handleExportPDF = () => {
    try {
      setExporting(true);
      if (!data?.data || data.data.length === 0) {
        message.warning("Aucune donnée à exporter");
        return;
      }

      const exportData = {
        headers: [
          "Référence",
          "Date Envoi",
          "Trafic",
          "Expéditeur",
          "Destinataire",
          "Marchandise",
          "Poids (Kg)",
          "Montant Total",
        ],
        rows: data.data.map((colis) => [
          formatRefColis(colis.ref_colis),
          formatDate(colis.date_envoi),
          colis.trafic_envoi || "-",
          colis.client_colis?.nom_exp || "-",
          colis.nom_destinataire || "-",
          colis.nom_marchandise || "-",
          colis.poids_total?.toFixed(2) || "0",
          formatMontantWithDevise(colis.total_montant),
        ]),
      };

      exportTableToPDF(exportData, `colis_${formeEnvoi}`, {
        title: `${t("title")} - ${formeEnvoi === "groupage" ? t("groupage") : t("autresEnvois")
          }`,
      });
      message.success(t("exportPdf"));
    } catch (error) {
      message.error(t("exportError"));
      console.error(error);
    } finally {
      setExporting(false);
    }
  };

  const handleExportExcel = async () => {
    try {
      setExporting(true);
      if (!data?.data || data.data.length === 0) {
        message.warning("Aucune donnée à exporter");
        return;
      }

      const exportData = {
        headers: [
          "Référence",
          "Date Envoi",
          "Trafic",
          "Expéditeur",
          "Destinataire",
          "Marchandise",
          "Poids (Kg)",
          "Montant Total",
        ],
        rows: data.data.map((colis) => [
          formatRefColis(colis.ref_colis),
          formatDate(colis.date_envoi),
          colis.trafic_envoi || "-",
          colis.client_colis?.nom_exp || "-",
          colis.nom_destinataire || "-",
          colis.nom_marchandise || "-",
          colis.poids_total?.toFixed(2) || "0",
          formatMontantWithDevise(colis.total_montant),
        ]),
      };

      await exportTableToExcel(exportData, `colis_${formeEnvoi}`, {
        title: `${t("title")} - ${formeEnvoi === "groupage" ? t("groupage") : t("autresEnvois")
          }`,
      });
      message.success(t("exportExcel"));
    } catch (error) {
      message.error(t("exportError"));
      console.error(error);
    } finally {
      setExporting(false);
    }
  };

  const handleValidate = async (id: number) => {
    await validateMutation.mutateAsync(id);
    refetch();
  };

  const handleReceiveAtHub = async (id: number) => {
    await receiveAtHubMutation.mutateAsync(id);
    refetch();
  };

  const getStatusColor = (status?: string) => {
    switch (status) {
      case 'EMBALLE': return 'default';
      case 'EXPEDIE': return 'orange';
      case 'REC_BOBIGNY': return 'blue';
      case 'EN_LIVRAISON': return 'purple';
      case 'LIVRE': return 'green';
      default: return 'default';
    }
  };

  const refCol: ColumnsType<Colis>[0] = {
    title: t("reference"),
    dataIndex: "ref_colis",
    key: "ref_colis",
    ...(tableCompact ? {} : { fixed: "left" as const }),
    width: 140,
    render: (text: string) => (
      <Tag color="blue" className="ref-tag">
        {formatRefColis(text)}
      </Tag>
    ),
  };
  const dateCol: ColumnsType<Colis>[0] = {
    title: t("dateEnvoi"),
    dataIndex: "date_envoi",
    key: "date_envoi",
    width: 108,
    render: (_date: string, record: Colis) =>
      formatDate(
        (record as any).date_envoi ??
          (record as any).date_enrg ??
          (record as any).created_at ??
          (record as any).createdAt,
      ),
    sorter: true,
  };
  const traficCol: ColumnsType<Colis>[0] = {
    title: t("trafic"),
    dataIndex: "trafic_envoi",
    key: "trafic_envoi",
    width: 130,
    render: (trafic: string) => <Tag>{trafic}</Tag>,
    filters: APP_CONFIG.traficEnvoi.map((trafic) => ({ text: trafic, value: trafic })),
  };
  const statutCol: ColumnsType<Colis>[0] = {
    title: "Statut Suivi",
    dataIndex: "statut_suivi",
    key: "statut_suivi",
    width: 118,
    render: (status: string) => (
      <Tag color={getStatusColor(status)}>{status || "EMBALLE"}</Tag>
    ),
  };
  const expediteurCol: ColumnsType<Colis>[0] = {
    title: t("expediteur"),
    key: "expediteur",
    width: tableCompact ? 160 : 200,
    ellipsis: tableCompact,
    render: (_: unknown, record: Colis) => record.client_colis?.nom_exp || "-",
  };
  const destCol: ColumnsType<Colis>[0] = {
    title: t("destinataire"),
    dataIndex: "nom_destinataire",
    key: "nom_destinataire",
    width: tableCompact ? 160 : 200,
    ellipsis: true,
  };
  const marchCol: ColumnsType<Colis>[0] = {
    title: t("marchandise"),
    dataIndex: "nom_marchandise",
    key: "nom_marchandise",
    width: tableCompact ? 140 : 200,
    ellipsis: true,
  };
  const poidsCol: ColumnsType<Colis>[0] = {
    title: t("poidsKg"),
    dataIndex: "poids_total",
    key: "poids_total",
    width: 88,
    render: (poids: number) => poids?.toFixed(2) || "0",
    sorter: true,
  };
  const montantCol: ColumnsType<Colis>[0] = {
    title: t("montantTotal"),
    dataIndex: "total_montant",
    key: "total_montant",
    width: 130,
    render: (montant: number) => formatMontantWithDevise(montant),
    sorter: true,
  };
  const actionsCol: ColumnsType<Colis>[0] = {
    title: tCommon("actions"),
    key: "actions",
    ...(tableCompact ? {} : { fixed: "right" as const }),
    width: tableCompact ? 168 : 150,
    render: (_: unknown, record: Colis) => (
        <Space size="small" wrap>
          {hasPermission(colisPermissions.UPDATE) && onEdit && (
            <Tooltip title={t("modifier")}>
              <Button
                type="primary"
                size="small"
                icon={<EditOutlined />}
                onClick={() => onEdit(record)}
              />
            </Tooltip>
          )}

          {onView && (
            <Tooltip title={t("voirDetails")}>
              <Button
                size="small"
                icon={<EyeOutlined />}
                onClick={() => onView(record)}
              />
            </Tooltip>
          )}

          {hasPermission(colisPermissions.VALIDATE) && record.etat_validation !== 1 && (
            <Tooltip title={t("valider")}>
              <Button
                type="default"
                size="small"
                icon={<CheckCircleOutlined />}
                onClick={() => handleValidate(record.id)}
                loading={validateMutation.isPending}
              />
            </Tooltip>
          )}

          {record.statut_suivi !== 'REC_BOBIGNY' && record.statut_suivi !== 'LIVRE' && (
            <Tooltip title="Marquer comme reçu à Bobigny">
              <Button
                type="default"
                size="small"
                icon={<HomeOutlined />}
                onClick={() => handleReceiveAtHub(record.id)}
                loading={receiveAtHubMutation.isPending}
                style={{ color: '#1890ff', borderColor: '#1890ff' }}
              />
            </Tooltip>
          )}

          {hasPermission(colisPermissions.DELETE) && (
            <Popconfirm
              title={t("deleteConfirm")}
              onConfirm={() => handleDelete(record.id)}
              okText={tCommon("yes")}
              cancelText={tCommon("no")}
            >
              <Tooltip title={t("supprimer")}>
                <Button
                  type="primary"
                  danger
                  size="small"
                  icon={<DeleteOutlined />}
                  loading={deleteMutation.isPending}
                />
              </Tooltip>
            </Popconfirm>
          )}
        </Space>
      ),
  };

  const columns: ColumnsType<Colis> = tableCompact
    ? [refCol, dateCol, traficCol, expediteurCol, destCol, marchCol, poidsCol, montantCol, actionsCol]
    : [
        refCol,
        dateCol,
        traficCol,
        statutCol,
        expediteurCol,
        destCol,
        marchCol,
        poidsCol,
        montantCol,
        actionsCol,
      ];

  return (
    <div>
      {/* BARRE DE FILTRES ET RECHERCHE */}
      <Card className="filter-card" style={{ marginBottom: 24 }}>
        <Row gutter={[16, 16]} align="middle">
          <Col xs={24} sm={12} md={8}>
            <Input
              placeholder={t("searchPlaceholder")}
              prefix={<SearchOutlined style={{ color: 'var(--premium-accent)' }} />}
              allowClear
              value={searchTerm}
              onChange={(e: React.ChangeEvent<HTMLInputElement>) => handleSearch(e.target.value)}
              onPressEnter={(e: React.KeyboardEvent<HTMLInputElement>) => handleSearch(e.currentTarget.value)}
              size="large"
            />
          </Col>

          <Col xs={24} sm={12} md={6}>
            <Select
              placeholder={t("traficFilter")}
              allowClear
              value={traficFilter}
              onChange={setTraficFilter}
              style={{ width: "100%" }}
              size="large"
            >
              {APP_CONFIG.traficEnvoi
                .filter((trafic) =>
                  formeEnvoi === 'groupage'
                    ? trafic.includes('Groupage')
                    : trafic.includes('Colis Rapide')
                )
                .map(trafic => (
                  <Option key={trafic} value={trafic}>{trafic}</Option>
                ))}
            </Select>
          </Col>

          <Col xs={24} sm={12} md={6}>
            <RangePicker
              style={{ width: "100%" }}
              size="large"
              value={dateRange}
              onChange={(dates: [Dayjs | null, Dayjs | null] | null) =>
                setDateRange(dates)
              }
              format="DD/MM/YYYY"
            />
          </Col>

          <Col xs={24} sm={24} md={4}>
            <Space wrap className="lbp-actions-stack" style={{ width: '100%', justifyContent: 'flex-end' }}>
              <Button
                icon={<ReloadOutlined />}
                onClick={() => refetch()}
                size="large"
                className="premium-action-btn"
              />

              <Button
                icon={<FilePdfOutlined />}
                onClick={handleExportPDF}
                loading={exporting}
                size="large"
                className="premium-action-btn"
              />

              {hasPermission(colisPermissions.CREATE) && (
                <Button
                  icon={<FileExcelOutlined />}
                  onClick={() => setIsImportModalVisible(true)}
                  size="large"
                  className="premium-action-btn"
                  style={{ color: "#52c41a", borderColor: "#52c41a" }}
                >
                  Importer Excel
                </Button>
              )}

              {hasPermission(colisPermissions.CREATE) && onCreate && (
                <Button
                  type="primary"
                  icon={<PlusOutlined />}
                  onClick={onCreate}
                  size="large"
                  className="premium-action-btn"
                  style={{ background: 'var(--premium-accent)', borderColor: 'var(--premium-accent)' }}
                >
                  {t("newColis")}
                </Button>
              )}
            </Space>
          </Col>
        </Row>
      </Card>

      {/* BANDEAU FLOTTANT DE SÉLECTION EN LOT */}
      {selectedRowKeys.length > 0 && (
        <Card
          style={{
            position: "fixed",
            bottom: 24,
            left: "50%",
            transform: "translateX(-50%)",
            zIndex: 1000,
            width: "auto",
            maxWidth: "90%",
            boxShadow: "0 8px 30px rgba(0,0,0,0.15)",
            background: "rgba(255,255,255,0.95)",
            backdropFilter: "blur(8px)",
            borderRadius: 16,
            border: "1px solid var(--premium-accent)",
          }}
          bodyStyle={{ padding: "12px 24px" }}
        >
          <Space size="large">
            <span style={{ fontWeight: 600 }}>
              ⚡ {selectedRowKeys.length} colis sélectionnés
            </span>
            <Button
              type="primary"
              icon={<CheckCircleOutlined />}
              onClick={handleBatchValidate}
              style={{ background: "var(--premium-accent)", border: "none" }}
            >
              Valider et Expédier
            </Button>
            <Button size="small" onClick={() => setSelectedRowKeys([])}>
              Annuler
            </Button>
          </Space>
        </Card>
      )}

      {/* TABLEAU */}
      <div className="premium-table-wrapper">
        {isLoading ? (
          <TableSkeleton rows={5} columns={8} />
        ) : !data?.data || data.data.length === 0 ? (
          searchTerm ? (
            <EmptySearchState
              searchTerm={searchTerm}
              onClearSearch={() => handleSearch("")}
            />
          ) : (
            <EmptyListState
              title={t("emptyState")}
              description={
                formeEnvoi === "groupage"
                  ? t("emptyDescriptionGroupage")
                  : t("emptyDescription")
              }
              onCreateClick={onCreate}
              createLabel={t("newColis")}
            />
          )
        ) : (
          <VirtualTable<Colis>
            columns={columns}
            dataSource={data.data}
            rowKey="id"
            className="premium-table"
            scroll={{ x: tableCompact ? 1320 : 1580 }}
            totalLabel="colis"
            rowSelection={{
              selectedRowKeys,
              onChange: (keys: React.Key[]) => setSelectedRowKeys(keys),
            }}
            pagination={{
              current: pagination.page,
              pageSize: pagination.limit,
              total: data?.total || 0,
              showSizeChanger: true,
              showTotal: (total: number) => t("totalColis", { total }),
              pageSizeOptions: ["10", "20", "50", "100"],
            }}
            onChange={handleTableChange}
          />
        )}
      </div>

      {/* MODAL D'IMPORT EN MASSE (EXCEL / COPY-PASTE) */}
      <Modal
        title={
          <div style={{ fontSize: 18, fontWeight: 700 }}>
            📥 Importation en Masse de Colis (Excel / CSV)
          </div>
        }
        visible={isImportModalVisible}
        onCancel={() => setIsImportModalVisible(false)}
        footer={[
          <Button key="cancel" onClick={() => setIsImportModalVisible(false)}>
            Fermer
          </Button>,
          <Button
            key="submit"
            type="primary"
            icon={<UploadOutlined />}
            loading={importLoading}
            onClick={handleImportSubmit}
            style={{ background: "var(--premium-accent)", border: "none" }}
          >
            Lancer l'importation
          </Button>,
        ]}
        width={720}
      >
        <div style={{ marginBottom: 16 }}>
          <p>
            Copiez-collez vos lignes depuis Excel ou Google Sheets ci-dessous. Les colonnes doivent être ordonnées comme suit :
          </p>
          <div style={{ background: "#f5f5f5", padding: "8px 12px", borderRadius: 8, fontFamily: "monospace", fontSize: 12, marginBottom: 16 }}>
            Nom Destinataire [TAB] Ville Destination [TAB] Poids (Kg) [TAB] Montant [TAB] Description Marchandise
          </div>
          <Input.TextArea
            rows={10}
            placeholder="Exemple :&#10;Konan Kouassi&#10;Abidjan&#10;12&#10;25000&#10;Poli en lot"
            value={importText}
            onChange={(e: any) => setImportText(e.target.value)}
            style={{ fontFamily: "monospace", fontSize: 12 }}
          />
        </div>
      </Modal>
    </div>
  );
};

