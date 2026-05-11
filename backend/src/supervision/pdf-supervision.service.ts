import { Injectable } from '@nestjs/common';
import { SupervisionRapport } from './entities/supervision-rapport.entity';
import { SupervisionInsightsService } from './supervision-insights.service';
// eslint-disable-next-line @typescript-eslint/no-require-imports
const PDFDocument = require('pdfkit') as typeof import('pdfkit');

const COULEUR_PRINCIPALE = '#1a237e'; // Bleu nuit identique au module RH
const COULEUR_GRIS = '#f5f5f5';

@Injectable()
export class PdfSupervisionService {
  constructor(private readonly insightsService: SupervisionInsightsService) {}

  private async toBuffer(doc: PDFKit.PDFDocument): Promise<Buffer> {
    return new Promise((resolve, reject) => {
      const chunks: Buffer[] = [];
      doc.on('data', (c: Buffer) => chunks.push(c));
      doc.on('end', () => resolve(Buffer.concat(chunks)));
      doc.on('error', reject);
      doc.end();
    });
  }

  /**
   * Génère un magnifique document PDF consolidant le rapport de supervision
   * et les indicateurs clés réels de la période concernée.
   */
  async genererRapportPdf(rapport: SupervisionRapport, societe = 'LA BELLE PORTE'): Promise<Buffer> {
    const doc = new PDFDocument({ size: 'A4', margin: 40 });

    // Récupération auto des KPIs pour enrichir le document
    let kpis: any = null;
    try {
      kpis = await this.insightsService.getKpisRange(
        rapport.date_debut || undefined,
        rapport.date_fin || undefined,
      );
    } catch {
      // fallback if error
    }

    // -- ENTÊTE --
    doc.rect(40, 40, 515, 65).fill(COULEUR_PRINCIPALE);
    doc.fillColor('white').fontSize(16).font('Helvetica-Bold')
      .text(societe, 55, 55);
    doc.fontSize(11).font('Helvetica')
      .text(`RAPPORT DE SUPERVISION GÉNÉRALE — REF #${rapport.id}`, 55, 75);
    doc.fillColor('black');

    doc.y = 130;
    const dateSoumission = new Date(rapport.created_at).toLocaleDateString('fr-FR');

    // -- INFOS DU RAPPORT --
    this.dessinerSectionTitre(doc, 'INFORMATIONS DU DOCUMENT');
    
    const topY = doc.y;
    this.dessinerChamp(doc, 'Type :', rapport.type.toUpperCase(), 40, topY);
    this.dessinerChamp(doc, 'Période traitée :', rapport.periode, 40, topY + 18);
    this.dessinerChamp(doc, 'Périmètre :', rapport.agence?.nom || 'ENSEMBLE DU RÉSEAU', 40, topY + 36);

    this.dessinerChamp(doc, 'Date émission :', dateSoumission, 300, topY);
    this.dessinerChamp(doc, 'Émis par :', rapport.auteur?.nom_complet || rapport.auteur?.username || 'Système', 300, topY + 18);
    this.dessinerChamp(doc, 'Destinataire :', 'DIRECTION GÉNÉRALE', 300, topY + 36);

    doc.y = topY + 70;

    // -- BLOC KPI (SI DISPO) --
    if (kpis) {
      this.dessinerSectionTitre(doc, 'INDICATEURS D\'ACTIVITÉ SUR LA PÉRIODE');
      
      doc.rect(40, doc.y, 515, 50).fill('#e8eaf6');
      doc.fillColor(COULEUR_PRINCIPALE);

      const boxWidth = 515 / 3;
      const kpiY = doc.y + 12;

      doc.fontSize(8).font('Helvetica').text('COLIS CRÉÉS', 40, kpiY, { width: boxWidth, align: 'center' });
      doc.fontSize(14).font('Helvetica-Bold').text(String(kpis.colisCrees), 40, kpiY + 12, { width: boxWidth, align: 'center' });

      doc.fontSize(8).font('Helvetica').text('FACTURES ÉMISES', 40 + boxWidth, kpiY, { width: boxWidth, align: 'center' });
      doc.fontSize(14).font('Helvetica-Bold').text(String(kpis.facturesEmises), 40 + boxWidth, kpiY + 12, { width: boxWidth, align: 'center' });

      doc.fontSize(8).font('Helvetica').text('ENCAISSEMENTS (FCFA)', 40 + (boxWidth * 2), kpiY, { width: boxWidth, align: 'center' });
      const amountFmt = Math.round(kpis.encaissementsValides).toLocaleString('fr-FR');
      doc.fontSize(13).font('Helvetica-Bold').text(amountFmt, 40 + (boxWidth * 2), kpiY + 12, { width: boxWidth, align: 'center' });

      doc.fillColor('black');
      doc.y = kpiY + 45;
    }

    // -- COMMENTAIRES / ANALYSE --
    doc.moveDown(2);
    this.dessinerSectionTitre(doc, 'ANALYSE ET COMMENTAIRES DE LA SUPERVISION');
    
    doc.font('Helvetica').fontSize(10).fillColor('#333');
    if (rapport.commentaire && rapport.commentaire.trim()) {
      doc.text(rapport.commentaire, 45, doc.y, {
        width: 505,
        align: 'justify',
        lineGap: 3
      });
    } else {
      doc.font('Helvetica-Oblique').text("Aucun commentaire ou analyse manuscrite n'a été joint à ce rapport.", 45, doc.y);
    }

    // -- SIGNATURES --
    doc.y = 680;
    doc.moveTo(40, doc.y).lineTo(555, doc.y).stroke('#ddd');
    doc.y += 15;
    
    doc.fillColor('black').font('Helvetica-Bold').fontSize(9);
    doc.text('VISA SUPERVISEURE', 80, doc.y, { width: 200, align: 'center' });
    doc.text('DIRECTION GÉNÉRALE', 350, doc.y, { width: 200, align: 'center' });

    // -- FOOTER --
    doc.y = 760;
    doc.fontSize(7).font('Helvetica').fillColor('#999')
      .text(`Ce document constitue une pièce officielle de traçabilité générée par le système LBP le ${new Date().toLocaleString('fr-FR')}.`, 40, doc.y, { align: 'center' });

    return this.toBuffer(doc);
  }

  private dessinerSectionTitre(doc: PDFKit.PDFDocument, titre: string) {
    doc.y += 10;
    doc.fontSize(10).font('Helvetica-Bold').fillColor(COULEUR_PRINCIPALE).text(titre, 40);
    doc.moveTo(40, doc.y + 2).lineTo(555, doc.y + 2).stroke(COULEUR_PRINCIPALE);
    doc.fillColor('black');
    doc.y += 10;
  }

  private dessinerChamp(doc: PDFKit.PDFDocument, label: string, value: string, x: number, y: number) {
    doc.fontSize(9).font('Helvetica-Bold').text(label, x, y);
    doc.font('Helvetica').text(value || '—', x + 85, y, { width: 160 });
  }
}
