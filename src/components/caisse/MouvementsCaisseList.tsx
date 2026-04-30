/**
 * Liste des mouvements de caisse avec filtres
 */

import React from "react";
import {
  Tag,
  Space,
  Button,
  DatePicker,
  Select,
  Input,
  Card,
  Modal,
  message,
} from "antd";
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
import {
  getMouvementsCaisse,
  submitMouvement,
  validateMouvement,
} from "@services/caisse.service";
import { useAuth } from "@hooks/useAuth";
import { usePermissions } from "@hooks/usePermissions";
import { PERMISSIONS } from "@constants/permissions";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import { fmtPdf, loadLogoBase64, drawLBPHeader, drawLBPFooters, LBP_TABLE_HEAD_STYLES, LBP_TABLE_ALT_ROW } from "@utils/pdfHelpers";

const { RangePicker } = DatePicker;
const { Option } = Select;

const WORKFLOW_LABELS: Record<string, string> = {
  DRAFT: "Brouillon",
  SUBMITTED: "Soumis",
  VALIDATED: "Validé",
  REJECTED: "Rejeté",
};

const WORKFLOW_TAG_COLOR: Record<string, string> = {
  DRAFT: "default",
  SUBMITTED: "processing",
  VALIDATED: "success",
  REJECTED: "error",
};

const VALIDATE_MOVEMENT_ROLES = new Set([
  "ADMIN",
  "DIRECTEUR",
  "SUPER_ADMIN",
  "MANAGER",
]);

interface MouvementsCaisseListProps {
  type?: MouvementCaisse["type"];
  idCaisse?: number;
  /** Si faux : consultation seule (ex. caissière sur caisse d’agence). */
  operationsEnabled?: boolean;
  onEdit?: (mouvement: MouvementCaisse) => void;
  onDelete?: (id: number) => void;
}

export const MouvementsCaisseList: React.FC<MouvementsCaisseListProps> = ({
  type,
  idCaisse,
  operationsEnabled = true,
  onEdit,
  onDelete,
}) => {
  const { user } = useAuth();
  const { hasPermission } = usePermissions();
  const canCaisseOps = hasPermission(PERMISSIONS.CAISSE.OPERATIONS);
  const roleCode = (user?.role?.code || "").toUpperCase();
  const canValidateMouvement =
    canCaisseOps && VALIDATE_MOVEMENT_ROLES.has(roleCode);

  const [mouvements, setMouvements] = React.useState<MouvementCaisse[]>([]);
  const [loading, setLoading] = React.useState(false);
  const [rejectModalId, setRejectModalId] = React.useState<number | null>(null);
  const [rejectReason, setRejectReason] = React.useState("");
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

  const columns: ColumnsType<MouvementCaisse> = React.useMemo(() => [
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
      title: "Validation",
      dataIndex: "workflow_status",
      key: "workflow_status",
      width: 120,
      render: (st: string | null | undefined) => {
        if (st == null || st === "") return <span>—</span>;
        return (
          <Tag color={WORKFLOW_TAG_COLOR[st] || "default"}>
            {WORKFLOW_LABELS[st] || st}
          </Tag>
        );
      },
    },
    {
      title: "Motif rejet",
      dataIndex: "rejection_reason",
      key: "rejection_reason",
      width: 160,
      ellipsis: true,
      render: (t: string | null | undefined) =>
        t ? <span title={t}>{t}</span> : <span>—</span>,
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
      width: 280,
      fixed: "right",
      render: (_: any, record: MouvementCaisse) => {
        const mid = record.id;
        const st = record.workflow_status;
        const canSubmit =
          operationsEnabled &&
          canCaisseOps &&
          mid != null &&
          (st === "DRAFT" || st === "REJECTED");
        const canReview =
          operationsEnabled &&
          canValidateMouvement &&
          mid != null &&
          (st === "SUBMITTED" || st === "REJECTED");
        return (
          <Space wrap size="small">
            {canSubmit ? (
              <Button
                type="link"
                size="small"
                onClick={async () => {
                  try {
                    await submitMouvement(mid);
                    message.success("Mouvement soumis pour validation");
                    void loadMouvements();
                  } catch (e: unknown) {
                    const msg =
                      e &&
                      typeof e === "object" &&
                      "message" in e &&
                      typeof (e as { message?: string }).message === "string"
                        ? (e as { message: string }).message
                        : "Soumission impossible";
                    message.error(msg);
                  }
                }}
              >
                Soumettre
              </Button>
            ) : null}
            {canReview ? (
              <>
                <Button
                  type="link"
                  size="small"
                  onClick={async () => {
                    try {
                      await validateMouvement(mid, { approve: true });
                      message.success("Mouvement validé");
                      void loadMouvements();
                    } catch (e: unknown) {
                      const msg =
                        e &&
                        typeof e === "object" &&
                        "message" in e &&
                        typeof (e as { message?: string }).message === "string"
                          ? (e as { message: string }).message
                          : "Validation impossible";
                      message.error(msg);
                    }
                  }}
                >
                  Valider
                </Button>
                <Button
                  type="link"
                  danger
                  size="small"
                  onClick={() => {
                    setRejectReason("");
                    setRejectModalId(mid);
                  }}
                >
                  Rejeter
                </Button>
              </>
            ) : null}
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
        );
      },
    },
  ], [operationsEnabled, canCaisseOps, canValidateMouvement, loadMouvements, onEdit, onDelete]);

  // Filtrer les mouvements par recherche
  const filteredMouvements = React.useMemo(() => {
    if (!searchText) return mouvements;

    const searchLower = searchText.toLowerCase();
    return mouvements.filter(
      (m) =>
        m.libelle?.toLowerCase().includes(searchLower) ||
        m.numero_dossier?.toLowerCase().includes(searchLower) ||
        m.nom_client?.toLowerCase().includes(searchLower) ||
        m.nom_demandeur?.toLowerCase().includes(searchLower) ||
        (m.workflow_status &&
          m.workflow_status.toLowerCase().includes(searchLower)) ||
        (m.rejection_reason &&
          m.rejection_reason.toLowerCase().includes(searchLower))
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

  const exportPdf = React.useCallback(async () => {
    try {
      const logo = await loadLogoBase64();
      const doc  = new jsPDF({ orientation: "landscape", unit: "mm", format: "a4" });

      const subtitleParts: string[] = [];
      if (type)      subtitleParts.push(`Type: ${type}`);
      if (idCaisse)  subtitleParts.push(`Caisse: #${idCaisse}`);
      if (dateRange) subtitleParts.push(`Periode: ${dateRange[0].format("DD/MM/YYYY")} - ${dateRange[1].format("DD/MM/YYYY")}`);
      if (searchText.trim()) subtitleParts.push(`Recherche: "${searchText.trim()}"`);

      let y = drawLBPHeader(doc, {
        title:     "Mouvements de caisse",
        subtitle:  subtitleParts.join("  |  ") || undefined,
        logoBase64: logo,
      });

      const body = filteredMouvements.map((m) => [
        m.date ? dayjs(m.date).format("DD/MM/YYYY") : "—",
        m.type ?? "—",
        m.libelle ?? "—",
        m.numero_dossier ?? "—",
        (type === "DECAISSEMENT" ? m.nom_demandeur : m.nom_client) ?? "—",
        fmtPdf(Number(m.montant || 0)),
        m.workflow_status ?? "—",
        m.rejection_reason ?? "—",
      ]);

      autoTable(doc, {
        startY: y,
        head: [["Date", "Type", "Libellé", "N° dossier", "Client/Demandeur", "Montant", "Workflow", "Motif rejet"]],
        body,
        styles: { fontSize: 8, cellPadding: 3, overflow: "linebreak" },
        headStyles: LBP_TABLE_HEAD_STYLES,
        alternateRowStyles: LBP_TABLE_ALT_ROW,
        columnStyles: { 5: { halign: "right", fontStyle: "bold" } },
      });

      drawLBPFooters(doc);
      doc.save(`mouvements_caisse_${idCaisse ?? "all"}_${new Date().toISOString().slice(0, 10)}.pdf`);
    } catch {
      message.error("Export PDF impossible");
    }
  }, [filteredMouvements, type, idCaisse, dateRange, searchText]);

  return (
    <Card>
      <Space direction="vertical" style={{ width: "100%" }} size="middle">
        <Space wrap>
          <RangePicker
            value={dateRange}
            onChange={(dates: [dayjs.Dayjs | null, dayjs.Dayjs | null] | null) =>
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
          <Button onClick={exportPdf} disabled={filteredMouvements.length === 0}>
            Exporter PDF
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
          scroll={{ x: 1480 }}
        />

        <Modal
          title="Motif du rejet"
          open={rejectModalId != null}
          onCancel={() => {
            setRejectModalId(null);
            setRejectReason("");
          }}
          onOk={() => {
            if (rejectModalId == null) {
              return Promise.resolve();
            }
            if (!rejectReason.trim()) {
              message.warning("Motif obligatoire");
              return Promise.reject(new Error("missing reason"));
            }
            return validateMouvement(rejectModalId, {
              approve: false,
              reason: rejectReason.trim(),
            })
              .then(() => {
                message.success("Mouvement rejeté");
                setRejectModalId(null);
                setRejectReason("");
                void loadMouvements();
              })
              .catch((e: unknown) => {
                const msg =
                  e &&
                  typeof e === "object" &&
                  "message" in e &&
                  typeof (e as { message?: string }).message === "string"
                    ? (e as { message: string }).message
                    : "Rejet impossible";
                message.error(msg);
                return Promise.reject(e);
              });
          }}
        >
          <Input.TextArea
            rows={3}
            value={rejectReason}
            onChange={(e: ChangeEvent<HTMLTextAreaElement>) =>
              setRejectReason(e.target.value)
            }
            placeholder="Précisez la raison du rejet…"
          />
        </Modal>
      </Space>
    </Card>
  );
};
