/**
 * Rapport "Grandes Lignes" avec totaux
 */

/**
 * Rapport "Grandes Lignes" avec totaux
 */

/**
 * Rapport "Grandes Lignes" avec totaux
 */

import React from "react";
import {
  Card,
  Row,
  Col,
  DatePicker,
  Button,
  Space,
  Table,
  Statistic,
  Tag,
  Dropdown,
} from "antd";
import type { GetProps } from 'antd';
type RangePickerProps = any;
import { ReloadOutlined, DownloadOutlined, FilePdfOutlined, FileExcelOutlined } from "@ant-design/icons";
import dayjs from "dayjs";
import { formatMontantWithDevise } from "@utils/format";
import type { RapportGrandesLignes as RapportGrandesLignesType } from "@types";
import { getRapportGrandesLignes } from "@services/caisse.service";
import type { ColumnsType } from "antd/es/table";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import ExcelJS from "exceljs";
import { saveAs } from "file-saver";

const { RangePicker } = DatePicker;

interface RapportGrandesLignesProps {
  idCaisse?: number;
}

export const RapportGrandesLignes: React.FC<RapportGrandesLignesProps> = ({
  idCaisse,
}) => {
  const [rapport, setRapport] = React.useState<RapportGrandesLignesType | null>(
    null
  );
  const [loading, setLoading] = React.useState(false);
  const [dateRange, setDateRange] = React.useState<[dayjs.Dayjs, dayjs.Dayjs]>([
    dayjs().startOf("month"),
    dayjs().endOf("month"),
  ]);

  const loadRapport = React.useCallback(async () => {
    try {
      setLoading(true);

      const params = {
        date_debut: dateRange[0].format("YYYY-MM-DD"),
        date_fin: dateRange[1].format("YYYY-MM-DD"),
        ...(idCaisse && { id_caisse: idCaisse }),
      };

      const data = await getRapportGrandesLignes(params);
      setRapport(data);
    } catch (error) {
      console.error("Erreur lors du chargement du rapport:", error);
    } finally {
      setLoading(false);
    }
  }, [dateRange, idCaisse]);

  React.useEffect(() => {
    loadRapport();
  }, [loadRapport]);

  const handleExportPDF = () => {
    if (!rapport) return;
    const doc = new jsPDF();
    const periode = `${dateRange[0].format("DD/MM/YYYY")} - ${dateRange[1].format("DD/MM/YYYY")}`;

    doc.setFontSize(16);
    doc.text("Rapport Grandes Lignes - Caisse", 14, 18);
    doc.setFontSize(11);
    doc.text(`Période : ${periode}`, 14, 28);

    const rows = [
      ["Approvisionnement", rapport.total_appro],
      ["Décaissement", rapport.total_decaissement],
      ["Entrées Chèque", rapport.total_entrees_cheque],
      ["Entrées Espèce", rapport.total_entrees_espece],
      ["Entrées Virement", rapport.total_entrees_virement],
      ["TOTAL ENTRÉES", rapport.total_entrees],
    ].map(([label, montant]) => [label, `${Number(montant).toLocaleString("fr-FR")} FCFA`]);

    autoTable(doc, {
      head: [["Type", "Montant"]],
      body: rows,
      startY: 35,
      styles: { fontSize: 10 },
      headStyles: { fillColor: [30, 136, 229] },
    });

    doc.save(`rapport-caisse-${dateRange[0].format("YYYYMMDD")}.pdf`);
  };

  const handleExportExcel = async () => {
    if (!rapport) return;
    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet("Rapport Caisse");
    const periode = `${dateRange[0].format("DD/MM/YYYY")} - ${dateRange[1].format("DD/MM/YYYY")}`;

    sheet.mergeCells("A1:B1");
    sheet.getCell("A1").value = `Rapport Grandes Lignes - Période : ${periode}`;
    sheet.getCell("A1").font = { bold: true, size: 13 };
    sheet.addRow([]);

    sheet.addRow(["Type", "Montant (FCFA)"]);
    sheet.getRow(3).font = { bold: true };
    sheet.getRow(3).fill = { type: "pattern", pattern: "solid", fgColor: { argb: "FF1E88E5" } };

    const data = [
      ["Approvisionnement", rapport.total_appro],
      ["Décaissement", rapport.total_decaissement],
      ["Entrées Chèque", rapport.total_entrees_cheque],
      ["Entrées Espèce", rapport.total_entrees_espece],
      ["Entrées Virement", rapport.total_entrees_virement],
      ["TOTAL ENTRÉES", rapport.total_entrees],
    ];

    data.forEach(([label, montant]) => {
      const row = sheet.addRow([label, montant]);
      if (label === "TOTAL ENTRÉES") row.font = { bold: true };
    });

    sheet.getColumn(1).width = 35;
    sheet.getColumn(2).width = 22;

    const buffer = await workbook.xlsx.writeBuffer();
    saveAs(new Blob([buffer]), `rapport-caisse-${dateRange[0].format("YYYYMMDD")}.xlsx`);
  };

  if (!rapport) {
    return (
      <Card>
        <Space direction="vertical" style={{ width: "100%" }} size="middle">
          <Space>
            <RangePicker
              value={dateRange}
              onChange={(dates: RangePickerProps["value"]) =>
                setDateRange(dates as [dayjs.Dayjs, dayjs.Dayjs])
              }
              format="DD/MM/YYYY"
              placeholder={["Date début", "Date fin"]}
            />
            <Button
              icon={<ReloadOutlined />}
              onClick={loadRapport}
              loading={loading}
            >
              Générer le rapport
            </Button>
          </Space>
        </Space>
      </Card>
    );
  }

  const tableData = [
    {
      key: "appro",
      type: "APPRO (Approvisionnement)",
      montant: rapport.total_appro,
      color: "green",
    },
    {
      key: "decaissement",
      type: "DÉCAISSEMENT",
      montant: rapport.total_decaissement,
      color: "red",
    },
    {
      key: "entree_cheque",
      type: "ENTRÉE CAISSE - Chèque",
      montant: rapport.total_entrees_cheque,
      color: "blue",
    },
    {
      key: "entree_espece",
      type: "ENTRÉE CAISSE - Espèce",
      montant: rapport.total_entrees_espece,
      color: "cyan",
    },
    {
      key: "entree_virement",
      type: "ENTRÉE CAISSE - Virement",
      montant: rapport.total_entrees_virement,
      color: "purple",
    },
    {
      key: "total_entrees",
      type: "TOTAL ENTREES",
      montant: rapport.total_entrees,
      color: "green",
      bold: true,
    },
  ];

  const columns: ColumnsType<(typeof tableData)[0]> = [
    {
      title: "Type",
      dataIndex: "type",
      key: "type",
      render: (text: string, record: any) => (
        <span style={{ fontWeight: record.bold ? "bold" : "normal" }}>
          {text}
        </span>
      ),
    },
    {
      title: "Montant Total",
      dataIndex: "montant",
      key: "montant",
      align: "right",
      render: (montant: number, record: any) => (
        <Tag
          color={record.color}
          style={{
            fontSize: record.bold ? 14 : 12,
            fontWeight: record.bold ? "bold" : "normal",
          }}
        >
          {formatMontantWithDevise(montant)}
        </Tag>
      ),
    },
  ];

  return (
    <Card>
      <Space direction="vertical" style={{ width: "100%" }} size="large">
        <Space wrap>
          <RangePicker
            value={dateRange}
            onChange={(dates: RangePickerProps["value"]) =>
              setDateRange(dates as [dayjs.Dayjs, dayjs.Dayjs])
            }
            format="DD/MM/YYYY"
            placeholder={["Date début", "Date fin"]}
          />
          <Button
            icon={<ReloadOutlined />}
            onClick={loadRapport}
            loading={loading}
          >
            Actualiser
          </Button>
          <Dropdown
            menu={{
              items: [
                {
                  key: "pdf",
                  label: "Exporter en PDF",
                  icon: <FilePdfOutlined />,
                  onClick: handleExportPDF,
                },
                {
                  key: "excel",
                  label: "Exporter en Excel",
                  icon: <FileExcelOutlined />,
                  onClick: handleExportExcel,
                },
              ],
            }}
          >
            <Button icon={<DownloadOutlined />}>Exporter</Button>
          </Dropdown>
        </Space>

        <div style={{ textAlign: "center", marginBottom: 24 }}>
          <h2>RAPPORT GRANDES LIGNES</h2>
          <p>
            Période: {dateRange[0].format("DD/MM/YYYY")} -{" "}
            {dateRange[1].format("DD/MM/YYYY")}
          </p>
        </div>

        <Row gutter={16} style={{ marginBottom: 24 }}>
          <Col xs={24} sm={8}>
            <Card>
              <Statistic
                title="Total APPRO"
                value={rapport.total_appro}
                prefix="+"
                valueStyle={{ color: "#52c41a" }}
                formatter={(value: any) => formatMontantWithDevise(Number(value))}
              />
            </Card>
          </Col>
          <Col xs={24} sm={8}>
            <Card>
              <Statistic
                title="Total DÉCAISSEMENT"
                value={rapport.total_decaissement}
                prefix="-"
                valueStyle={{ color: "#ff4d4f" }}
                formatter={(value: any) => formatMontantWithDevise(Number(value))}
              />
            </Card>
          </Col>
          <Col xs={24} sm={8}>
            <Card>
              <Statistic
                title="Total ENTREES"
                value={rapport.total_entrees}
                prefix="+"
                valueStyle={{ color: "#1890ff" }}
                formatter={(value: any) => formatMontantWithDevise(Number(value))}
              />
            </Card>
          </Col>
        </Row>

        <Row gutter={16} style={{ marginBottom: 24 }}>
          <Col xs={24} sm={12}>
            <Card>
              <Statistic
                title="Solde Initial"
                value={rapport.solde_initial}
                valueStyle={{ color: "#666" }}
                formatter={(value: any) => formatMontantWithDevise(Number(value))}
              />
            </Card>
          </Col>
          <Col xs={24} sm={12}>
            <Card>
              <Statistic
                title="Solde Final"
                value={rapport.solde_final}
                valueStyle={{
                  color: "#1890ff",
                  fontWeight: "bold",
                  fontSize: 20,
                }}
                formatter={(value: any) => formatMontantWithDevise(Number(value))}
              />
            </Card>
          </Col>
        </Row>

        <Table
          columns={columns}
          dataSource={tableData}
          pagination={false}
          bordered
          summary={() => (
            <Table.Summary fixed>
              <Table.Summary.Row>
                <Table.Summary.Cell index={0}>
                  <strong style={{ textAlign: "right", display: "block" }}>
                    SOLDE NET:
                  </strong>
                </Table.Summary.Cell>
                <Table.Summary.Cell index={1}>
                  <Tag
                    color="blue"
                    style={{ fontSize: 14, fontWeight: "bold" }}
                  >
                    {formatMontantWithDevise(rapport.solde_final)}
                  </Tag>
                </Table.Summary.Cell>
              </Table.Summary.Row>
            </Table.Summary>
          )}
        />
      </Space>
    </Card>
  );
};
