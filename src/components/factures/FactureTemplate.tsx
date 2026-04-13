/**
 * Template de facture avec en-tête et pied de page LBP
 * Utilise les images entete_lbp.png et footer_lbp.png
 */

import React from "react";
import {
  Card,
  Descriptions,
  Table,
  Divider,
  Typography,
  Space,
} from "antd";
import type { FactureColis } from "@/types";
import {
  formatDate,
  formatMontantWithDevise,
  formatRefColis,
} from "@utils/format";
import { APP_CONFIG } from "@constants/application";
import dayjs from "dayjs";
import "./FactureTemplate.css";

const { Title, Text } = Typography;

interface FactureTemplateProps {
  facture: FactureColis;
  colis?: {
    client_colis?: {
      nom_exp?: string;
      tel_exp?: string;
      email_exp?: string;
    };
    marchandises?: Array<{
      nom_marchandise?: string;
      nbre_colis?: number;
      poids_total?: number;
      prix_unit?: number;
      prix_emballage?: number;
      prix_assurance?: number;
      prix_agence?: number;
      total_montant?: number;
    }>;
    nom_destinataire?: string;
    lieu_dest?: string;
    tel_dest?: string;
    email_dest?: string;
    nom_recup?: string;
    adresse_recup?: string;
    tel_recup?: string;
    email_recup?: string;
    nom_marchandise?: string;
    nbre_colis?: number;
    nbre_articles?: number;
    poids_total?: number;
    prix_unit?: number;
    prix_emballage?: number;
    prix_assurance?: number;
    prix_agence?: number;
    total_montant?: number;
  };
  mode?: "preview" | "print" | "pdf";
}

export const FactureTemplate: React.FC<FactureTemplateProps> = ({
  facture,
  colis,
  mode = "preview",
}) => {
  const isProforma = facture.etat === 0;

  // Chemins des images (à utiliser côté backend pour PDF)
  const headerImagePath = "/images/entete_lbp.png";
  const footerImagePath = "/images/footer_lbp.png";

  // Calcul des montants
  const montantHT = colis?.total_montant || facture.montant_ttc || 0;
  const tva = 0; // Pas de TVA pour l'instant
  const montantTTC = facture.montant_ttc;

  const items =
    colis?.marchandises && colis.marchandises.length > 0
      ? colis.marchandises
      : colis
        ? [
          {
            nom_marchandise: colis.nom_marchandise,
            nbre_colis: colis.nbre_colis,
            poids_total: colis.poids_total,
            prix_unit: colis.prix_unit,
            prix_emballage: colis.prix_emballage,
            prix_assurance: colis.prix_assurance,
            prix_agence: colis.prix_agence,
            total_montant: colis.total_montant,
          },
        ]
        : [];

  return (
    <div
      className={`facture-template-container ${mode === "print" || mode === "pdf" ? "print-mode" : ""
        }`}
    >
      {/* En-tête : Logo LBP haut-gauche + bloc titre */}
      <div className="facture-header-row">
        <img
          src="/images/WhatsApp Image 2026-02-20 at 12.20.22.jpeg"
          alt="Logo LBP"
          className="facture-logo"
          onError={(e) => {
            (e.target as HTMLImageElement).style.display = 'none';
          }}
        />
        <div className="facture-title-box">
          <span className="facture-title-box-label">IMPRIMÉ SPÉCIFIQUE</span>
          <span className="facture-title-box-main">Facture &amp; Colisage</span>
        </div>
        <div className="facture-tag-area">
          {isProforma && (
            <span className="facture-type-tag proforma">FACTURE PROFORMA</span>
          )}
          {!isProforma && (
            <span className="facture-type-tag definitive">FACTURE DÉFINITIVE</span>
          )}
        </div>
      </div>

      {/* Bande avertissement */}
      <div className="facture-warning-band">
        VOUS NE DISPOSEZ QUE DE TROIS JOURS POUR LA RÉCUPÉRATION DE VOTRE COLIS À COMPTER DE LA DATE
        DE NOTIFICATION. PASSÉ CE DÉLAI, NOUS DÉCLINONS TOUTE RESPONSABILITÉ VIS-À-VIS DU COLIS.
      </div>

      {/* Ligne agence + service client */}
      <div className="facture-agence-bar">
        <span className="facture-agence-left">
          SERVICES CLIENT : {APP_CONFIG.company.callCenterPhones}
        </span>
      </div>

      {/* === GRAND TITRE DETAILS COLIS === */}
      <div className="facture-colis-title-block">
        <div className="facture-colis-title-text">
          DETAILS COLIS&nbsp;{formatRefColis(facture.ref_colis || facture.colis?.ref_colis)}
        </div>
      </div>

      {/* Informations facture */}
      <Space
        direction="vertical"
        style={{ width: "100%", marginBottom: 32 }}
        size="large"
      >
        <div className="facture-info-section">
          <Descriptions bordered column={{ xs: 1, sm: 2, md: 3 }} size="small">
            <Descriptions.Item label="N° Facture" span={1}>
              <Text strong style={{ fontSize: 16, color: "#1890ff" }}>
                {facture.num_facture}
              </Text>
            </Descriptions.Item>
            <Descriptions.Item label="Date" span={1}>
              <Text>{formatDate(facture.date_facture)}</Text>
            </Descriptions.Item>
            <Descriptions.Item label="Référence Colis" span={1}>
              <Text>{formatRefColis(facture.ref_colis || facture.colis?.ref_colis)}</Text>
            </Descriptions.Item>
          </Descriptions>
        </div>

        {/* Informations expéditeur et destinataire */}
        <div className="facture-addresses">
          {/* Expéditeur */}
          {colis?.client_colis && (
            <Card
              title="Expéditeur"
              size="small"
              className="facture-address-card"
            >
              <Space direction="vertical" size="small">
                <Text strong>{colis.client_colis.nom_exp || "-"}</Text>
                {colis.client_colis.tel_exp && (
                  <Text>{colis.client_colis.tel_exp}</Text>
                )}
                {colis.client_colis.email_exp && (
                  <Text type="secondary">{colis.client_colis.email_exp}</Text>
                )}
              </Space>
            </Card>
          )}

          {/* Destinataire */}
          {colis && (
            <Card
              title="Destinataire"
              size="small"
              className="facture-address-card"
            >
              <Space direction="vertical" size="small">
                <Text strong>{colis.nom_destinataire || "-"}</Text>
                {colis.lieu_dest && <Text>{colis.lieu_dest}</Text>}
                {colis.tel_dest && <Text>{colis.tel_dest}</Text>}
                {colis.email_dest && (
                  <Text type="secondary">{colis.email_dest}</Text>
                )}
              </Space>
            </Card>
          )}

          {/* Récupérateur */}
          {colis?.nom_recup && (
            <Card
              title="Récupérateur"
              size="small"
              className="facture-address-card"
            >
              <Space direction="vertical" size="small">
                <Text strong>{colis.nom_recup}</Text>
                {colis.adresse_recup && <Text>{colis.adresse_recup}</Text>}
                {colis.tel_recup && <Text>{colis.tel_recup}</Text>}
                {colis.email_recup && (
                  <Text type="secondary">{colis.email_recup}</Text>
                )}
              </Space>
            </Card>
          )}
        </div>

        {/* Détails de la marchandise */}
        {colis && (
          <div className="facture-items-table">
            <Table
              dataSource={items.map((m, idx) => ({
                key: String(idx + 1),
                description: m.nom_marchandise || "Marchandise",
                quantite: m.nbre_colis || 1,
                unite: "Colis",
                poids: `${m.poids_total || 0} Kg`,
                prix_unitaire: m.prix_unit || 0,
                prix_emballage: m.prix_emballage || 0,
                prix_assurance: m.prix_assurance || 0,
                prix_agence: m.prix_agence || 0,
                montant: m.total_montant || 0,
              }))}
              columns={[
                {
                  title: "Description",
                  dataIndex: "description",
                  key: "description",
                  width: "30%",
                },
                {
                  title: "Quantité",
                  dataIndex: "quantite",
                  key: "quantite",
                  align: "center",
                  width: "10%",
                },
                {
                  title: "Unité",
                  dataIndex: "unite",
                  key: "unite",
                  align: "center",
                  width: "8%",
                },
                {
                  title: "Poids",
                  dataIndex: "poids",
                  key: "poids",
                  align: "center",
                  width: "10%",
                },
                {
                  title: "Prix unitaire",
                  dataIndex: "prix_unitaire",
                  key: "prix_unitaire",
                  align: "right",
                  width: "12%",
                  render: (value: number) => formatMontantWithDevise(value),
                },
                {
                  title: "Montant",
                  dataIndex: "montant",
                  key: "montant",
                  align: "right",
                  width: "15%",
                  render: (value: number) => (
                    <Text strong style={{ fontSize: 14 }}>
                      {formatMontantWithDevise(value)}
                    </Text>
                  ),
                },
              ]}
              pagination={false}
              size="small"
              bordered
              summary={() => (
                <Table.Summary>
                  <Table.Summary.Row>
                    <Table.Summary.Cell index={0} colSpan={5} align="right">
                      <Text strong>Montant HT:</Text>
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={1} align="right">
                      <Text strong>{formatMontantWithDevise(montantHT)}</Text>
                    </Table.Summary.Cell>
                  </Table.Summary.Row>
                  {tva > 0 && (
                    <Table.Summary.Row>
                      <Table.Summary.Cell index={0} colSpan={5} align="right">
                        <Text>TVA (18%):</Text>
                      </Table.Summary.Cell>
                      <Table.Summary.Cell index={1} align="right">
                        <Text>{formatMontantWithDevise(tva)}</Text>
                      </Table.Summary.Cell>
                    </Table.Summary.Row>
                  )}
                  <Table.Summary.Row>
                    <Table.Summary.Cell index={0} colSpan={5} align="right">
                      <Text strong style={{ fontSize: 16, color: "#1890ff" }}>
                        Montant TTC:
                      </Text>
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={1} align="right">
                      <Text strong style={{ fontSize: 16, color: "#1890ff" }}>
                        {formatMontantWithDevise(montantTTC)}
                      </Text>
                    </Table.Summary.Cell>
                  </Table.Summary.Row>
                </Table.Summary>
              )}
            />
          </div>
        )}

        {/* Montant total si pas de détails colis */}
        {!colis && (
          <Card
            style={{ backgroundColor: "#f0f2f5", border: "2px solid #1890ff" }}
          >
            <Space
              direction="vertical"
              style={{ width: "100%" }}
              align="end"
              size="small"
            >
              <Text type="secondary">Montant TTC</Text>
              <Text strong style={{ fontSize: 24, color: "#1890ff" }}>
                {formatMontantWithDevise(montantTTC)}
              </Text>
            </Space>
          </Card>
        )}
      </Space>

      <Divider />

      {/* Notes et conditions */}
      <div className="facture-notes">
        <Text type="secondary">
          <strong>Conditions de paiement:</strong> Paiement à réception de la
          facture
        </Text>
        <br />
        <Text type="secondary">
          <strong>Validité:</strong> Cette facture est valable 30 jours à
          compter de la date d'émission
        </Text>
      </div>

      {/* Pied de page avec image */}
      <div className="facture-footer">
        <img
          src={footerImagePath}
          alt="Pied de page LBP"
          style={{
            maxWidth: "100%",
            height: "auto",
          }}
          onError={(e) => {
            // Fallback si l'image n'existe pas
            const target = e.target as HTMLImageElement;
            target.style.display = "none";
          }}
        />
      </div>

      {/* Informations supplémentaires au pied de page */}
      <div className="facture-footer-info">
        <Text type="secondary">
          Facture générée le {dayjs().format("DD/MM/YYYY à HH:mm")} par{" "}
          {facture.code_user || "Système"}
        </Text>
      </div>
    </div>
  );
};

/**
 * Configuration pour le backend - Chemins des images à utiliser dans le PDF
 */
export const FACTURE_IMAGES_CONFIG = {
  header: "/images/entete_lbp.png",
  footer: "/images/footer_lbp.png",
  // Chemins absolus pour le backend (à adapter selon l'environnement)
  headerAbsolute: import.meta.env.BASE_URL
    ? `${import.meta.env.BASE_URL}images/entete_lbp.png`
    : "/images/entete_lbp.png",
  footerAbsolute: import.meta.env.BASE_URL
    ? `${import.meta.env.BASE_URL}images/footer_lbp.png`
    : "/images/footer_lbp.png",
} as const;
