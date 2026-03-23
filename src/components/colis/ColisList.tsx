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

  const columns: ColumnsType<Colis> = [
    {
      title: t("reference"),
      dataIndex: "ref_colis",
      key: "ref_colis",
      fixed: "left",
      width: 150,
      render: (text: string) => (
        <Tag color="blue" className="ref-tag">
          {formatRefColis(text)}
        </Tag>
      ),
    },
    {
      title: t("dateEnvoi"),
      dataIndex: "date_envoi",
      key: "date_envoi",
      width: 120,
      render: (date: string) => formatDate(date),
      sorter: true,
    },
    {
      title: t("trafic"),
      dataIndex: "trafic_envoi",
      key: "trafic_envoi",
      width: 150,
      render: (trafic: string) => <Tag>{trafic}</Tag>,
      filters: APP_CONFIG.traficEnvoi.map(trafic => ({ text: trafic, value: trafic })),
    },
    {
      title: "Statut Suivi",
      dataIndex: "statut_suivi",
      key: "statut_suivi",
      width: 130,
      render: (status: string) => (
        <Tag color={getStatusColor(status)}>
          {status || 'EMBALLE'}
        </Tag>
      ),
    },
    {
      title: t("expediteur"),
      key: "expediteur",
      width: 200,
      render: (_: unknown, record: Colis) => record.client_colis?.nom_exp || "-",
    },
    {
      title: t("destinataire"),
      dataIndex: "nom_destinataire",
      key: "nom_destinataire",
      width: 200,
    },
    {
      title: t("marchandise"),
      dataIndex: "nom_marchandise",
      key: "nom_marchandise",
      width: 200,
      ellipsis: true,
    },
    {
      title: t("poidsKg"),
      dataIndex: "poids_total",
      key: "poids_total",
      width: 100,
      render: (poids: number) => poids?.toFixed(2) || "0",
      sorter: true,
    },
    {
      title: t("montantTotal"),
      dataIndex: "total_montant",
      key: "total_montant",
      width: 150,
      render: (montant: number) => formatMontantWithDevise(montant),
      sorter: true,
    },
    {
      title: tCommon("actions"),
      key: "actions",
      fixed: "right",
      width: 150,
      render: (_: unknown, record: Colis) => (
        <Space size="small">
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

          {hasPermission(colisPermissions.VALIDATE) && (
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
    },
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
            <Space wrap style={{ width: '100%', justifyContent: 'flex-end' }}>
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
            scroll={{ x: 1200 }}
            totalLabel="colis"
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
    </div>
  );
};
