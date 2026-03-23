/**
 * Liste des mouvements de caisse avec filtres
 */

import React from "react";
import {
  Table,
  Tag,
  Space,
  Button,
  DatePicker,
  Select,
  Input,
  Card,
} from "antd";
import type { GetProps } from 'antd';
type RangePickerProps = GetProps<typeof DatePicker.RangePicker>;
import type { ChangeEvent } from "react";
import { EmptyCaisseList } from "@components/common/EmptyState";
import {
  EditOutlined,
  DeleteOutlined,
  ReloadOutlined,
} from "@ant-design/icons";
import type { ColumnsType } from "antd/es/table";
import dayjs from "dayjs";
import { formatMontantWithDevise } from "@utils/format";
import type { MouvementCaisse } from "@types";
import { VirtualTable } from "@components/common/VirtualTable";
import { getMouvementsCaisse } from "@services/caisse.service";

const { RangePicker } = DatePicker;
const { Option } = Select;

interface MouvementsCaisseListProps {
  type?: MouvementCaisse["type"];
  idCaisse?: number;
  onEdit?: (mouvement: MouvementCaisse) => void;
  onDelete?: (id: number) => void;
}

export const MouvementsCaisseList: React.FC<MouvementsCaisseListProps> = ({
  type,
  idCaisse,
  onEdit,
  onDelete,
}) => {
  const [mouvements, setMouvements] = React.useState<MouvementCaisse[]>([]);
  const [loading, setLoading] = React.useState(false);
  const [dateRange, setDateRange] = React.useState<
    [dayjs.Dayjs, dayjs.Dayjs] | null
  >(null);
  const [searchText, setSearchText] = React.useState("");

  const loadMouvements = React.useCallback(async () => {
    try {
      setLoading(true);

      const params: any = {};
      if (type) params.type = type;
      if (idCaisse) params.id_caisse = idCaisse;
      if (dateRange) {
        params.date_debut = dateRange[0].format("YYYY-MM-DD");
        params.date_fin = dateRange[1].format("YYYY-MM-DD");
      }

      const data = await getMouvementsCaisse(params);
      setMouvements(data);
    } catch (error) {
      console.error("Erreur lors du chargement des mouvements:", error);
    } finally {
      setLoading(false);
    }
  }, [type, idCaisse, dateRange]);

  React.useEffect(() => {
    loadMouvements();
  }, [loadMouvements]);

  const columns: ColumnsType<MouvementCaisse> = [
    {
      title: "Date",
      dataIndex: "date",
      key: "date",
      width: 120,
      render: (date: string) => dayjs(date).format("DD/MM/YYYY"),
      sorter: (a: MouvementCaisse, b: MouvementCaisse) => dayjs(a.date).unix() - dayjs(b.date).unix(),
    },
    {
      title: "Type",
      dataIndex: "type",
      key: "type",
      width: 150,
      render: (type: string) => {
        const colors: Record<string, string> = {
          APPRO: "green",
          DECAISSEMENT: "red",
          ENTREE_CHEQUE: "blue",
          ENTREE_ESPECE: "cyan",
          ENTREE_VIREMENT: "purple",
        };
        const labels: Record<string, string> = {
          APPRO: "Approvisionnement",
          DECAISSEMENT: "Décaissement",
          ENTREE_CHEQUE: "Entrée - Chèque",
          ENTREE_ESPECE: "Entrée - Espèce",
          ENTREE_VIREMENT: "Entrée - Virement",
        };
        return (
          <Tag color={colors[type] || "default"}>{labels[type] || type}</Tag>
        );
      },
      filters: [
        { text: "Approvisionnement", value: "APPRO" },
        { text: "Décaissement", value: "DECAISSEMENT" },
        { text: "Entrée - Chèque", value: "ENTREE_CHEQUE" },
        { text: "Entrée - Espèce", value: "ENTREE_ESPECE" },
        { text: "Entrée - Virement", value: "ENTREE_VIREMENT" },
      ],
      onFilter: (value: any, record: MouvementCaisse) => record.type === value,
    },
    {
      title: "Libellé",
      dataIndex: "libelle",
      key: "libelle",
      ellipsis: true,
    },
    {
      title: "Numéro dossier",
      dataIndex: "numero_dossier",
      key: "numero_dossier",
      width: 150,
    },
    {
      title: "Nom client/Demandeur",
      dataIndex: type === "DECAISSEMENT" ? "nom_demandeur" : "nom_client",
      key: "nom",
      width: 200,
    },
    {
      title: "Montant",
      dataIndex: "montant",
      key: "montant",
      width: 150,
      align: "right",
      render: (montant: number, record: MouvementCaisse) => (
        <span
          style={{
            color: record.type === "DECAISSEMENT" ? "#ff4d4f" : "#52c41a",
            fontWeight: "bold",
          }}
        >
          {record.type === "DECAISSEMENT" ? "-" : "+"}
          {formatMontantWithDevise(montant)}
        </span>
      ),
      sorter: (a: MouvementCaisse, b: MouvementCaisse) => a.montant - b.montant,
    },
    {
      title: "Solde",
      dataIndex: "solde",
      key: "solde",
      width: 150,
      align: "right",
      render: (solde: number) => (
        <span style={{ fontWeight: "bold" }}>
          {formatMontantWithDevise(solde || 0)}
        </span>
      ),
      sorter: (a: MouvementCaisse, b: MouvementCaisse) => (a.solde || 0) - (b.solde || 0),
    },
    {
      title: "Actions",
      key: "actions",
      width: 120,
      fixed: "right",
      render: (_: any, record: MouvementCaisse) => (
        <Space>
          {onEdit && (
            <Button
              type="link"
              icon={<EditOutlined />}
              onClick={() => onEdit(record)}
              size="small"
            />
          )}
          {onDelete && (
            <Button
              type="link"
              danger
              icon={<DeleteOutlined />}
              onClick={() => record.id && onDelete(record.id)}
              size="small"
            />
          )}
        </Space>
      ),
    },
  ];

  // Filtrer les mouvements par recherche
  const filteredMouvements = React.useMemo(() => {
    if (!searchText) return mouvements;

    const searchLower = searchText.toLowerCase();
    return mouvements.filter(
      (m) =>
        m.libelle?.toLowerCase().includes(searchLower) ||
        m.numero_dossier?.toLowerCase().includes(searchLower) ||
        m.nom_client?.toLowerCase().includes(searchLower) ||
        m.nom_demandeur?.toLowerCase().includes(searchLower)
    );
  }, [mouvements, searchText]);

  // Calculer les totaux
  const totals = React.useMemo(() => {
    return filteredMouvements.reduce(
      (acc, m) => {
        if (m.type === "APPRO" || m.type?.startsWith("ENTREE_")) {
          acc.entrees += m.montant;
        } else if (m.type === "DECAISSEMENT") {
          acc.sorties += m.montant;
        }
        return acc;
      },
      { entrees: 0, sorties: 0 }
    );
  }, [filteredMouvements]);

  return (
    <Card>
      <Space direction="vertical" style={{ width: "100%" }} size="middle">
        <Space wrap>
          <RangePicker
            value={dateRange}
            onChange={(dates: RangePickerProps["value"]) =>
              setDateRange(dates as [dayjs.Dayjs, dayjs.Dayjs] | null)
            }
            format="DD/MM/YYYY"
            placeholder={["Date début", "Date fin"]}
          />

          <Input
            placeholder="Rechercher..."
            value={searchText}
            onChange={(e: ChangeEvent<HTMLInputElement>) => setSearchText(e.target.value)}
            style={{ width: 200 }}
            allowClear
          />

          <Button
            icon={<ReloadOutlined />}
            onClick={loadMouvements}
            loading={loading}
          >
            Actualiser
          </Button>
        </Space>

        {(totals.entrees > 0 || totals.sorties > 0) && (
          <div
            style={{
              marginBottom: 16,
              padding: 12,
              backgroundColor: "#f5f5f5",
              borderRadius: 4,
            }}
          >
            <Space>
              <strong>Total Entrées:</strong>
              <span style={{ color: "#52c41a", fontWeight: "bold" }}>
                {formatMontantWithDevise(totals.entrees)}
              </span>
              <strong style={{ marginLeft: 16 }}>Total Sorties:</strong>
              <span style={{ color: "#ff4d4f", fontWeight: "bold" }}>
                {formatMontantWithDevise(totals.sorties)}
              </span>
              <strong style={{ marginLeft: 16 }}>Solde Net:</strong>
              <span style={{ fontWeight: "bold" }}>
                {formatMontantWithDevise(totals.entrees - totals.sorties)}
              </span>
            </Space>
          </div>
        )}

        <VirtualTable<MouvementCaisse>
          columns={columns}
          dataSource={filteredMouvements}
          rowKey="id"
          loading={loading}
          totalLabel="mouvements"
          locale={{
            emptyText: <EmptyCaisseList />,
          }}
          pagination={{
            pageSize: 20,
            showSizeChanger: true,
            showTotal: (total: number) => `Total : ${total} mouvements`,
          }}
          scroll={{ x: 1200 }}
        />
      </Space>
    </Card>
  );
};
