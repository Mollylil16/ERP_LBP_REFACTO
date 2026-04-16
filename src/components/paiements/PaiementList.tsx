import React, { useState } from "react";
import {
  Table,
  Button,
  Space,
  Input,
  Tag,
  Popconfirm,
  Tooltip,
  Card,
  Row,
  Col,
  DatePicker,
} from "antd";
type RangePickerProps = any;
import type { ChangeEvent, KeyboardEvent } from "react";
import {
  SearchOutlined,
  ReloadOutlined,
  CloseCircleOutlined,
  WalletOutlined,
  MobileOutlined,
  BankOutlined,
  FileTextOutlined,
  CalendarOutlined,
  DollarOutlined,
} from "@ant-design/icons";
import type { ColumnsType } from "antd/es/table";
import dayjs, { Dayjs } from "dayjs";
import { Paiement } from "@types";
import { formatDate, formatMontantWithDevise } from "@utils/format";
import { usePaiementsList, useCancelPaiement } from "@hooks/usePaiements";
import { WithPermission } from "@components/common/WithPermission";
import { PERMISSIONS } from "@constants/permissions";
import { EmptyPaiementsList, EmptySearchState } from "@components/common/EmptyState";
import { VirtualTable } from "@components/common/VirtualTable";
import { APP_CONFIG } from "@constants/application";

const { RangePicker } = DatePicker;

interface PaiementListProps {
  refColis?: string;
}

export const PaiementList: React.FC<PaiementListProps> = ({ refColis }) => {
  const [pagination, setPagination] = useState({ page: 1, limit: 20 });
  const [searchTerm, setSearchTerm] = useState("");
  const [dateRange, setDateRange] = useState<
    [Dayjs | null, Dayjs | null] | null
  >(null);

  const { data, isLoading, refetch } = usePaiementsList({
    ...pagination,
    search: searchTerm || undefined,
  });

  const cancelMutation = useCancelPaiement();

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

  const handleCancel = async (id: number) => {
    await cancelMutation.mutateAsync(id);
    refetch();
  };

  const getModeIcon = (value: string) => {
    switch (value) {
      case 'wave':
      case 'om': return <MobileOutlined />;
      case 'especes': return <WalletOutlined />;
      case 'cheque': return <FileTextOutlined />;
      case 'virement': return <BankOutlined />;
      case '30j':
      case '45j':
      case '60j':
      case '90j': return <CalendarOutlined />;
      default: return <DollarOutlined />;
    }
  };

  const getModeTag = (mode: string) => {
    const config = APP_CONFIG.modesPaiement.find(
      (m) => m.label.toLowerCase() === mode?.toLowerCase() || m.value === mode?.toLowerCase()
    );
    const iconKey = config?.value ?? mode?.toLowerCase() ?? '';
    return (
      <Tag color={config?.color || 'default'} icon={getModeIcon(iconKey)}>
        {config?.label ?? mode ?? '-'}
      </Tag>
    );
  };

  const columns: ColumnsType<Paiement> = [
    {
      title: "Réf. Colis",
      key: "ref_colis",
      width: 150,
      render: (_: unknown, record: Paiement) => {
        const ref =
          (record as any).facture?.colis?.ref_colis ?? record.ref_colis ?? record.reference
        return (
          <span style={{ fontFamily: 'monospace', fontWeight: 600 }}>{ref || "—"}</span>
        )
      },
    },
    {
      title: "Date & Heure",
      key: "date_paiement",
      width: 150,
      render: (_: unknown, record: Paiement) => {
        const dateSrc = record.date_paiement || record.created_at
        return (
          <div>
            <div>{formatDate(dateSrc)}</div>
            {record.created_at && (
              <div style={{ fontSize: 11, color: '#8c8c8c' }}>
                {new Date(record.created_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
              </div>
            )}
          </div>
        )
      },
      sorter: true,
    },
    {
      title: "Montant",
      dataIndex: "montant",
      key: "montant",
      width: 160,
      render: (montant: number) => (
        <strong style={{ color: "#52c41a", fontSize: 15 }}>
          {formatMontantWithDevise(montant)}
        </strong>
      ),
      sorter: true,
    },
    {
      title: "Mode",
      dataIndex: "mode_paiement",
      key: "mode_paiement",
      width: 160,
      render: (mode: string) => getModeTag(mode),
      filters: APP_CONFIG.modesPaiement.map((m) => ({ text: m.label, value: m.label })),
    },
    {
      title: "Réf. Transaction",
      key: "reference_paiement",
      width: 180,
      render: (_: unknown, record: Paiement) => {
        const ref = record.reference_paiement || (record as any).encaissement_ref
        return ref ? (
          <span style={{ fontFamily: 'monospace', fontSize: 12 }}>{ref}</span>
        ) : (
          <span style={{ color: '#bfbfbf' }}>—</span>
        )
      },
    },
    {
      title: "Enregistré par",
      dataIndex: "code_user",
      key: "code_user",
      width: 130,
      render: (code: string) => code || '-',
    },
    {
      title: "Statut",
      dataIndex: "etat_validation",
      key: "etat_validation",
      width: 100,
      render: (etat: number) => (
        <Tag color={etat === 1 ? 'green' : etat === 0 ? 'red' : 'gold'}>
          {etat === 1 ? 'Validé' : etat === 0 ? 'Annulé' : 'En attente'}
        </Tag>
      ),
    },
    {
      title: "Actions",
      key: "actions",
      fixed: "right",
      width: 100,
      render: (_: any, record: Paiement) => (
        <WithPermission permission={PERMISSIONS.PAIEMENTS.CANCEL}>
          <Popconfirm
            title="Annuler ce paiement ?"
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
                loading={cancelMutation.isPending}
              />
            </Tooltip>
          </Popconfirm>
        </WithPermission>
      ),
    },
  ];

  return (
    <div>
      {/* BARRE DE RECHERCHE */}
      <Card style={{ marginBottom: 16 }}>
        <Row gutter={16} align="middle">
          <Col xs={24} sm={12} md={8}>
            <Input
              placeholder="Rechercher par référence..."
              prefix={<SearchOutlined />}
              allowClear
              value={searchTerm}
              onChange={(e: ChangeEvent<HTMLInputElement>) => handleSearch(e.target.value)}
              onPressEnter={(e: KeyboardEvent<HTMLInputElement>) => handleSearch(e.currentTarget.value)}
              size="large"
            />
          </Col>

          <Col xs={24} sm={12} md={8}>
            <RangePicker
              style={{ width: "100%" }}
              size="large"
              value={dateRange}
              onChange={(dates: RangePickerProps["value"]) =>
                setDateRange(dates as [Dayjs | null, Dayjs | null])
              }
              format="DD/MM/YYYY"
            />
          </Col>

          <Col xs={24} sm={12} md={8} style={{ textAlign: "right" }}>
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
        <VirtualTable<Paiement>
          columns={columns}
          dataSource={data?.data || []}
          loading={isLoading}
          rowKey="id"
          scroll={{ x: 1100 }}
          totalLabel="paiements"
          locale={{
            emptyText: searchTerm
              ? <EmptySearchState searchTerm={searchTerm} onClearSearch={() => { setSearchTerm(''); setPagination({ ...pagination, page: 1 }); }} />
              : <EmptyPaiementsList />,
          }}
          pagination={{
            current: pagination.page,
            pageSize: pagination.limit,
            total: data?.total || 0,
            showSizeChanger: true,
            showTotal: (total: number) => `Total : ${total} paiements`,
            pageSizeOptions: ["10", "20", "50", "100"],
          }}
          onChange={handleTableChange}
        />
      </Card>
    </div>
  );
};
