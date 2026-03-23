import { Injectable, NotFoundException, BadRequestException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, DataSource } from 'typeorm';
import { Facture } from './entities/facture.entity';
import { Colis } from '../colis/entities/colis.entity';
import { LienPaiement, LienPaiementStatut } from '../paiements/entities/lien-paiement.entity';
import * as crypto from 'crypto';

@Injectable()
export class FacturesService {
    constructor(
        @InjectRepository(Facture)
        private factureRepository: Repository<Facture>,
        @InjectRepository(Colis)
        private colisRepository: Repository<Colis>,
        @InjectRepository(LienPaiement)
        private lienRepository: Repository<LienPaiement>,
        private dataSource: DataSource,
    ) { }

    async createProforma(colis: Colis, userId: string): Promise<Facture> {
        const numFacture = await this.generateReference();

        // Calcul des montants basés sur les marchandises du colis
        let montantHT = 0;
        if (colis.marchandises) {
            montantHT = colis.marchandises.reduce((acc, m) => {
                // Formule LBP : Poids × Prix Unitaire
                const totalLigne =
                    Number(m.prix_unit) * Number(m.poids_total || 0) +
                    Number(m.prix_emballage || 0) +
                    Number(m.prix_assurance || 0) +
                    Number(m.prix_agence || 0);
                return acc + totalLigne;
            }, 0);
        }

        const agencyCurrency = colis.agence?.devise || 'XOF';

        const facture = this.factureRepository.create({
            num_facture: numFacture,
            colis: colis,
            montant_ht: montantHT,
            montant_ttc: montantHT,
            montant_paye: 0,
            etat: 0,
            devise: agencyCurrency,
            taux_change: 1, // Par défaut 1, sera ajustable par l'IA ou admin
            date_facture: new Date(),
            code_user: userId,
        });

        return await this.factureRepository.save(facture);
    }

    private async generateReference(): Promise<string> {
        const now = new Date();
        const mm = String(now.getMonth() + 1).padStart(2, '0');
        const yy = String(now.getFullYear()).slice(-2);
        const datePart = `${mm}${yy}`;

        const lastFacture = await this.factureRepository
            .createQueryBuilder('facture')
            .where('facture.num_facture LIKE :pattern', { pattern: `FCO-${datePart}-%` })
            .orderBy('facture.id', 'DESC')
            .getOne();

        let nextNumber = 1;
        if (lastFacture) {
            const lastRef = lastFacture.num_facture;
            const parts = lastRef.split('-');
            const lastNum = parseInt(parts[2], 10);
            nextNumber = lastNum + 1;
        }

        const numPart = String(nextNumber).padStart(3, '0');
        return `FCO-${datePart}-${numPart}`;
    }

    async findAll(user: any): Promise<Facture[]> {
        const where: any = {};

        const canSeeAll = ['ADMIN', 'DIRECTEUR'].includes(user.role);

        if (!canSeeAll && user.id_agence) {
            where.colis = { agence: { id: user.id_agence } };
        }
        return this.factureRepository.find({
            where,
            relations: ['colis', 'colis.client'],
            order: { created_at: 'DESC' },
        });
    }

    async findOne(id: number): Promise<Facture> {
        const facture = await this.factureRepository.findOne({
            where: { id },
            relations: ['colis', 'colis.client'],
        });
        if (!facture) {
            throw new NotFoundException(`Facture #${id} introuvable`);
        }
        return facture;
    }

    /**
     * ✅ AJOUT: Récupérer les détails d'une facture publiquement par son ID
     * (Uniquement les informations non sensibles)
     */
    async getPublicFacture(id: number) {
        const facture = await this.factureRepository.findOne({
            where: { id },
            relations: ['colis', 'colis.client'],
        });

        if (!facture) {
            throw new NotFoundException(`Facture #${id} introuvable`);
        }

        // Retourner uniquement ce qui est nécessaire pour la page de paiement
        return {
            id: facture.id,
            num_facture: facture.num_facture,
            montant_ttc: Number(facture.montant_ttc),
            montant_paye: Number(facture.montant_paye),
            payment_status: facture.payment_status,
            devise: facture.devise,
            date_facture: facture.date_facture,
            colis: {
                ref_colis: facture.colis.ref_colis,
                client: {
                    nom_exp: facture.colis.client.nom_exp,
                }
            }
        };
    }

    async validateProforma(id: number): Promise<Facture> {
        const facture = await this.findOne(id);
        facture.etat = 1; // Définitive
        return await this.factureRepository.save(facture);
    }

    async findByColisRef(refColis: string): Promise<Facture | null> {
        return this.factureRepository.findOne({
            where: { colis: { ref_colis: refColis } },
            relations: ['colis', 'colis.client'],
        });
    }

    async cancelFacture(id: number): Promise<void> {
        const facture = await this.findOne(id);
        facture.etat = 2; // Annulée
        await this.factureRepository.save(facture);
    }

    /**
     * ✅ AJOUT: Générer une facture depuis un colis
     */
    async generateFromColis(colisId: number, userId: string): Promise<Facture> {
        // Vérifier que le colis existe
        const colis = await this.colisRepository.findOne({
            where: { id: colisId },
            relations: ['client', 'marchandises'],
        });

        if (!colis) {
            throw new NotFoundException(`Colis #${colisId} not found`);
        }

        // Vérifier qu'il n'existe pas déjà une facture pour ce colis
        const existingFacture = await this.factureRepository.findOne({
            where: { colis: { id: colisId } },
        });

        if (existingFacture) {
            throw new BadRequestException(
                `Une facture existe déjà pour ce colis (${existingFacture.num_facture})`
            );
        }

        // Créer la facture proforma
        return await this.createProforma(colis, userId);
    }

    /**
     * ✅ REFONTE COMPLÈTE: Génération PDF façon "Facture & Colisage" LBP
     * - Logo LBP en haut à gauche
     * - QR code de suivi en haut à droite
     * - Tableau détaillé par marchandise (N°, Nbre Colis, Nbre Art., Nature, Poids, Prix/Kg, Prix Total)
     * - Sous-total, Emballage, Assurance, Montant Total (FCFA + EUR)
     * - Montant Reçu / Montant Restant
     * - Zone signatures CLIENT / SOCIÉTÉ
     * - Adresse en pied de page
     */
    async generatePDF(id: number, user?: any): Promise<Buffer> {
        const facture = await this.findOne(id);
        const PDFDocument = require('pdfkit');
        const QRCode = require('qrcode');
        const path = require('path');
        const fs = require('fs');
        const { PaiementLienService } = require('../paiements/paiements-lien.service');

        return new Promise(async (resolve, reject) => {
            try {
                // ── COULEURS (palette épurée) ──────────────────────────
                const NAVY = '#1A2B5B';
                const GOLD = '#B8900A';
                const WHITE = '#FFFFFF';
                const LIGHT = '#F4F6FA';   // fond lignes paires (très discret)
                const DARK = '#1C1C1C';   // texte principal
                const MEDIUM = '#555555';   // texte secondaire
                const BORDER = '#C8CDD8';   // bordures légères

                // ── DOCUMENT ─────────────────────────────────────────────
                const doc = new PDFDocument({ margin: 0, size: 'A4' });
                const chunks: Buffer[] = [];
                doc.on('data', (c: Buffer) => chunks.push(c));
                doc.on('end', () => resolve(Buffer.concat(chunks)));
                doc.on('error', reject);

                const W = doc.page.width;   // 595.28
                const H = doc.page.height;  // 841.89
                const M = 30;               // marge latérale
                const CONTENT_W = W - 2 * M;

                const publicDir = path.join(process.cwd(), '..', 'public');
                const logoPath = path.join(publicDir, 'images', 'WhatsApp Image 2026-02-20 at 12.20.22.jpeg');
                const footerImgPath = path.join(publicDir, 'images', 'footer_lbp.png');

                const currency = facture.devise || 'XOF';
                // URL de l'application frontend (à configurer dans .env avec FRONTEND_URL)
                const frontendBase = process.env.FRONTEND_URL || 'https://labelleporte.net';
                const trackingUrl = `${frontendBase}/#/track/${facture.colis.ref_colis}`;

                // ── QR CODE ───────────────────────────────────────────────
                const qrBuffer = await QRCode.toBuffer(trackingUrl, {
                    errorCorrectionLevel: 'M', margin: 1, width: 200,
                    color: { dark: NAVY, light: WHITE }
                });
                // ✅ AJOUT: QR Code de Paiement (ouvre la page publique de paiement)
                let paymentQrBuffer: Buffer | null = null;
                try {
                    // URL de paiement directe (Phase 1 : /#/invoice/:id/pay)
                    const paymentUrl = `${frontendBase}/#/invoice/${facture.id}/pay`;
                    paymentQrBuffer = await QRCode.toBuffer(paymentUrl, {
                        errorCorrectionLevel: 'H', margin: 1, width: 210,
                        color: { dark: NAVY, light: WHITE }
                    });
                } catch (e) {
                    console.error('Erreur génération QR paiement:', e);
                }


                // ─────────────────────────────────────────────────────────
                // EN-TÊTE compact
                // ─────────────────────────────────────────────────────────
                let y = 0;

                // Fine bande marine en haut
                doc.rect(0, 0, W, 5).fill(NAVY);
                doc.rect(0, 5, W, 2).fill(GOLD);
                y = 10;

                // Logo
                const LOGO_H = 55;
                const logoOk = fs.existsSync(logoPath);
                if (logoOk) {
                    doc.image(logoPath, M, y + 2, { height: LOGO_H });
                } else {
                    doc.fontSize(18).font('Helvetica-Bold').fillColor(NAVY).text('LBP-CI', M, y + 14);
                    doc.fontSize(8).font('Helvetica').fillColor(GOLD).text('LA BELLE PORTE', M, y + 36);
                }

                // Bloc titre centré sur la page
                const TBW = 230;
                const TBX = (W - TBW) / 2;
                doc.roundedRect(TBX, y + 5, TBW, 50, 3).fill(NAVY);
                doc.fontSize(7).font('Helvetica').fillColor(GOLD)
                    .text('IMPRIMÉ SPÉCIFIQUE', TBX, y + 11, { width: TBW, align: 'center' });
                doc.fontSize(13).font('Helvetica-Bold').fillColor(WHITE)
                    .text('Facture & Colisage', TBX, y + 23, { width: TBW, align: 'center' });

                // QR code – haut droit
                const QR_SIZE = 56;
                doc.image(qrBuffer, W - M - QR_SIZE, y + 2, { width: QR_SIZE });
                doc.fontSize(5.5).font('Helvetica').fillColor(MEDIUM)
                    .text('Suivi colis', W - M - QR_SIZE, y + QR_SIZE + 3, { width: QR_SIZE, align: 'center' });

                y += LOGO_H + 14;

                // Bandeau avertissement (compact, fond très léger)
                doc.rect(M, y, CONTENT_W, 14).fill('#FFF8E1');
                doc.rect(M, y, 2, 14).fill(GOLD);
                doc.fontSize(6).font('Helvetica').fillColor('#7D5A00')
                    .text(
                        'VOUS DISPOSEZ DE 3 JOURS POUR RÉCUPÉRER VOTRE COLIS À COMPTER DE LA DATE DE NOTIFICATION. PASSÉ CE DÉLAI, NOUS DÉCLINONS TOUTE RESPONSABILITÉ.',
                        M + 5, y + 4, { width: CONTENT_W - 10 }
                    );
                y += 18;

                // Ligne agence + service client
                doc.moveTo(M, y).lineTo(W - M, y).lineWidth(0.4).strokeColor(BORDER).stroke();
                y += 4;
                const agence = facture.colis.agence;
                const agencesInfo = agence
                    ? `${agence.nom || ''} (${agence.ville || ''}) — Tel: ${agence.telephone || ''}`
                    : 'LBP Logistics — Siège Social';

                doc.fontSize(7.5).font('Helvetica-Bold').fillColor(NAVY).text('Agence: ', M, y, { continued: true });
                doc.font('Helvetica').fillColor(MEDIUM).text(agencesInfo);

                doc.font('Helvetica-Bold').fillColor(NAVY)
                    .text('SERVICE CLIENT CI : +225 05 08 00 36 35', 0, y, { align: 'right', width: W - M });
                y += 14;

                // ─────────────────────────────────────────────────────────
                // TITRE DÉTAILS COLIS
                // ─────────────────────────────────────────────────────────
                const colisRef = facture.colis.ref_colis || '—';
                doc.rect(M, y, CONTENT_W, 36).fill(NAVY);
                doc.rect(M, y, CONTENT_W, 2).fill(GOLD);
                doc.fontSize(18).font('Helvetica-Bold').fillColor(WHITE)
                    .text(`DÉTAILS COLIS  ${colisRef}`, M, y + 10, { width: CONTENT_W, align: 'center' });
                y += 36;
                doc.rect(M, y, CONTENT_W, 2).fill(GOLD);
                y += 4;

                const nbreColis = (facture.colis.marchandises || []).reduce((s: number, m: any) => s + Number(m.nbre_colis || 0), 0);
                doc.fontSize(8).font('Helvetica').fillColor(DARK)
                    .text(`Nombre total de colis : ${nbreColis}`, M, y + 2, { width: CONTENT_W, align: 'center' });
                y += 16;

                // ─────────────────────────────────────────────────────────
                // INFOS EXPÉDITEUR / DESTINATAIRE (en 2 colonnes)
                // ─────────────────────────────────────────────────────────
                const INFO_W = CONTENT_W / 2 - 4;
                const leftX = M;
                const rightX = M + INFO_W + 8;

                const drawRow = (label: string, value: string, x: number, iy: number) => {
                    doc.fontSize(7.5).font('Helvetica-Bold').fillColor(NAVY).text(label, x, iy, { width: 100, continued: false });
                    doc.fontSize(7.5).font('Helvetica').fillColor(DARK).text(value, x + 95, iy, { width: INFO_W - 95 });
                };

                const expediteur = facture.colis.client;
                drawRow('Code Colis :', colisRef, leftX, y);
                drawRow("Date d'envoi :", new Date(facture.colis.date_envoi || facture.date_facture).toLocaleDateString('fr-FR'), rightX, y);
                y += 12;
                drawRow('EXPÉDITEUR :', (expediteur?.nom_exp || '—').toUpperCase(), leftX, y);
                drawRow('DESTINATION :', (facture.colis.lieu_dest || '—').toUpperCase(), rightX, y);
                y += 12;
                drawRow('TÉL EXP. :', expediteur?.tel_exp || '—', leftX, y);
                drawRow('DESTINATAIRE :', (facture.colis.nom_dest || '—').toUpperCase(), rightX, y);
                y += 12;
                drawRow('TRAFIC :', facture.colis.trafic_envoi || '—', leftX, y);
                drawRow('TÉL DEST. :', facture.colis.tel_dest || '—', rightX, y);
                y += 12;

                y += 4;



                // ─────────────────────────────────────────────────────────
                // TABLEAU MARCHANDISES  (CONTENT_W = 535 pt)
                // Répartition : N°(25) | Nbre(30) | Description(155) | Emb.(65) | Poids(50) | Prix/Kg(80) | Total(130)
                // ─────────────────────────────────────────────────────────
                const CW = { num: 20, ncolis: 30, desc: 140, emb: 60, qtemb: 30, poids: 50, prix: 75, total: 130 };
                // Positions X calculées dynamiquement depuis M
                let _cx = M;
                const COL: Record<string, { x: number; w: number }> = {};
                for (const [k, w] of Object.entries(CW)) { COL[k] = { x: _cx, w }; _cx += w; }

                const ROW_H = 18;
                const TH = 20;

                // En-têtes — fond marine, texte blanc, police 7, une seule ligne
                doc.rect(M, y, CONTENT_W, TH).fill(NAVY);
                doc.fontSize(7).font('Helvetica-Bold').fillColor(WHITE);
                const headers = [
                    { label: 'N°', key: 'num', align: 'center' },
                    { label: 'Nbre\nColis', key: 'ncolis', align: 'center' },
                    { label: 'Description', key: 'desc', align: 'left' },
                    { label: 'Emballage', key: 'emb', align: 'center' },
                    { label: 'Qté\nEmb.', key: 'qtemb', align: 'center' },
                    { label: 'Poids (kg)', key: 'poids', align: 'center' },
                    { label: 'Prix / Kg', key: 'prix', align: 'center' },
                    { label: 'Total', key: 'total', align: 'center' },
                ] as const;
                headers.forEach(h => {
                    doc.text(h.label, COL[h.key].x + 3, y + 6, {
                        width: COL[h.key].w - 6,
                        align: h.align,
                        lineBreak: false,
                    });
                });
                y += TH;

                // ── Lignes de données ─────────────────────────────────────
                let sousTotal = 0;
                let totalEmb = 0;
                let totalAss = 0;
                let totalAgence = 0;

                // Formatage nombres : séparateur de milliers = point
                const formatNum = (num: number) =>
                    Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');

                // Fonction pour tronquer proprement le texte au max de caractères
                const truncate = (str: string, maxChars: number) =>
                    str.length > maxChars ? str.slice(0, maxChars - 1) + '…' : str;

                (facture.colis.marchandises || []).forEach((m: any, i: number) => {
                    const isEven = i % 2 === 0;
                    doc.rect(M, y, CONTENT_W, ROW_H).fill(isEven ? LIGHT : WHITE);

                    const poids = Number(m.poids_total || 0);
                    const prixKg = Number(m.prix_unit || 0);
                    const emb = Number(m.prix_emballage || 0);
                    const ass = Number(m.prix_assurance || 0);
                    const agence = Number(m.prix_agence || 0);

                    // Correction: Le total de la ligne ne doit montrer que ce qui est visible (Poids * Prix/Kg)
                    // Les frais annexes (emb, ass, agence) seront détaillés dans les totaux globaux
                    const totalLg = prixKg * poids;

                    sousTotal += totalLg;
                    totalEmb += emb;
                    totalAss += ass;
                    totalAgence += agence;

                    // Textes — tronqués selon la taille de la colonne
                    const descTxt = truncate((m.nom_marchandise || m.designation || m.description || '—').toUpperCase(), 26);
                    const embTxt = truncate((m.type_emballage || '—').toLowerCase(), 10);

                    const rowData = [
                        { val: String(i + 1), key: 'num', align: 'center', bold: false },
                        { val: String(m.nbre_colis || 0), key: 'ncolis', align: 'center', bold: false },
                        { val: descTxt, key: 'desc', align: 'left', bold: false },
                        { val: embTxt, key: 'emb', align: 'center', bold: false },
                        { val: String(m.nbre_emballage || 1), key: 'qtemb', align: 'center', bold: false },
                        { val: String(poids), key: 'poids', align: 'center', bold: false },
                        { val: `${formatNum(prixKg)} F`, key: 'prix', align: 'right', bold: false },
                        { val: `${formatNum(totalLg)} F`, key: 'total', align: 'right', bold: true },
                    ] as const;

                    doc.fontSize(7.5).fillColor(DARK);
                    rowData.forEach(r => {
                        doc.font(r.bold ? 'Helvetica-Bold' : 'Helvetica')
                            .text(r.val, COL[r.key].x + 3, y + 5, {
                                width: COL[r.key].w - 6,
                                align: r.align,
                                lineBreak: false,   // une seule ligne, jamais de chevauchement
                            });
                    });

                    // Ligne de séparation légère
                    doc.moveTo(M, y + ROW_H).lineTo(W - M, y + ROW_H).lineWidth(0.25).strokeColor(BORDER).stroke();
                    y += ROW_H;

                    // Ajout de page si nécessaire
                    if (y > H - 200) {
                        doc.addPage();
                        y = 40;
                    }
                });

                doc.moveTo(M, y).lineTo(W - M, y).lineWidth(0.8).strokeColor(NAVY).stroke();
                y += 8;

                // ─────────────────────────────────────────────────────────
                // TOTAUX
                // ─────────────────────────────────────────────────────────
                const LABEL_X = M + 20;
                const VALUE_X = W - M - 155;
                const VALUE_W = 150;
                const TROW_H = 14;

                const tauxChange = 655.957;
                const montantTTC = Number(facture.montant_ttc);
                const montantEUR = (montantTTC / tauxChange).toFixed(2);
                const payeAmt = Number(facture.montant_paye || 0);
                const resteAmt = montantTTC - payeAmt;
                // Paiement partiel = l'expéditeur a versé quelque chose mais pas la totalité
                const isPaiementPartiel = payeAmt > 0 && payeAmt < montantTTC;

                const totalsRows: Array<{ label: string; value: string; hl?: boolean; bold?: boolean; isApprox?: boolean }> = [
                    { label: 'SOUS-TOTAL', value: `${formatNum(sousTotal)} FCFA` },
                    ...(totalEmb > 0 ? [{ label: 'EMBALLAGE', value: `${formatNum(totalEmb)} FCFA` }] : []),
                    ...(totalAss > 0 ? [{ label: 'ASSURANCE', value: `${formatNum(totalAss)} FCFA` }] : []),
                    ...(totalAgence > 0 ? [{ label: 'FRAIS AGENCE', value: `${formatNum(totalAgence)} FCFA`, bold: true }] : []),
                    { label: 'MONTANT TOTAL', value: `${formatNum(montantTTC)} FCFA`, hl: true, bold: true },
                    { label: '', value: `${montantEUR} €`, hl: true, isApprox: true },
                    ...(isPaiementPartiel ? [
                        { label: 'Montant Reçu', value: `${formatNum(payeAmt)} FCFA` },
                        { label: 'Montant Restant à Payer', value: `${formatNum(resteAmt)} FCFA`, bold: true },
                    ] : []),
                ];

                totalsRows.forEach(row => {
                    if (row.hl) {
                        doc.rect(VALUE_X - 4, y, VALUE_W + 4, TROW_H).fill('#E8ECF8');
                    }
                    if (row.label) {
                        doc.fontSize(8)
                            .font(row.bold ? 'Helvetica-Bold' : 'Helvetica')
                            .fillColor(row.bold ? NAVY : MEDIUM)
                            .text(row.label, LABEL_X, y + 2);
                    }
                    doc.fontSize(row.hl ? 8.5 : 7.5)
                        .font((row.bold || row.hl) ? 'Helvetica-Bold' : 'Helvetica')
                        .fillColor(row.hl ? NAVY : DARK);

                    if (row.isApprox) {
                        // Utilisation de la police Symbol pour le signe ≈ (environ) afin d'éviter les bugs d'encodage
                        const textW = doc.widthOfString(row.value);
                        const symX = VALUE_X + VALUE_W - textW - 12;
                        doc.font('Symbol').text('\xbb', symX, y + 2.5);
                        doc.font('Helvetica-Bold').text(row.value, VALUE_X, y + 2, { width: VALUE_W, align: 'right' });
                    } else {
                        doc.text(row.value, VALUE_X, y + 2, { width: VALUE_W, align: 'right' });
                    }
                    y += TROW_H;
                });

                y += 10;
                doc.fontSize(7).font('Helvetica').fillColor(MEDIUM)
                    .text('Les frais de transaction sont à la charge du client.', LABEL_X, y);

                const bottomY = y + 15;

                // ✅ AFFICHAGE DU QR CODE DE PAIEMENT (Mieux positionné)
                if (paymentQrBuffer) {
                    const PAY_QR_SIZE = 85;
                    const payQrX = W - M - PAY_QR_SIZE;
                    const payQrY = bottomY + 5; // Un peu plus bas

                    // Petit cadre élégant
                    doc.rect(payQrX - 4, payQrY - 4, PAY_QR_SIZE + 8, PAY_QR_SIZE + 24)
                        .lineWidth(0.5).strokeColor('#F0F0F0').stroke();

                    doc.image(paymentQrBuffer, payQrX, payQrY, { width: PAY_QR_SIZE });

                    doc.fontSize(7).font('Helvetica-Bold').fillColor(NAVY)
                        .text('SCANNEZ POUR PAYER', payQrX, payQrY + PAY_QR_SIZE + 3, { width: PAY_QR_SIZE, align: 'center' });
                    doc.fontSize(6).font('Helvetica').fillColor(MEDIUM)
                        .text('(Wave / Orange Money)', payQrX, payQrY + PAY_QR_SIZE + 12, { width: PAY_QR_SIZE, align: 'center' });

                    y = Math.max(y, payQrY + PAY_QR_SIZE + 35);
                } else {
                    y = bottomY + 10;
                }

                y += 20;

                // ── MENTION LIVRAISON (si applicable) ───────────────────
                if (facture.colis.livraison) {
                    doc.fontSize(10).font('Helvetica-Bold').fillColor(NAVY)
                        .text('LIVRAISON : OUI', M, y, { width: CONTENT_W, align: 'left' });
                    y += 20;
                } else {
                    y += 10;
                }

                // ─────────────────────────────────────────────────────────
                // SIGNATURES
                // ─────────────────────────────────────────────────────────
                const SIG_H = 40;
                const SIG_W = CONTENT_W / 2 - 5;
                doc.roundedRect(M, y, SIG_W, SIG_H, 2).lineWidth(0.6).strokeColor(BORDER).stroke();
                doc.fontSize(7.5).font('Helvetica-Bold').fillColor(NAVY)
                    .text('CLIENT (date et visa)', M + 6, y + 5);

                doc.roundedRect(M + SIG_W + 10, y, SIG_W, SIG_H, 2).lineWidth(0.6).strokeColor(BORDER).stroke();
                doc.text('SOCIÉTÉ (date et visa)', M + SIG_W + 16, y + 5);
                y += SIG_H + 8;

                // ─────────────────────────────────────────────────────────
                // PIED DE PAGE
                // ─────────────────────────────────────────────────────────
                // On positionne les infos textuelles juste au dessus du trait final
                const FOOTER_LINE_Y = H - 45;
                const FTR_TEXT_Y = FOOTER_LINE_Y - 55;

                // ── Ligne 1 : Adresse
                doc.fontSize(8).font('Helvetica-Bold').fillColor(NAVY)
                    .text('ADRESSE : PARIS 17 CHEMIN DES VIGNES 93000 BOBIGNY', M, FTR_TEXT_Y, { width: CONTENT_W, align: 'center' });

                // ── Ligne 2 : Contacts
                doc.fontSize(7.5).font('Helvetica').fillColor(DARK)
                    .text('Tél : +33 7 75 73 27 97  /  +33 7 51 19 83 82  /  +33 7 45 93 56 92', M, FTR_TEXT_Y + 12, { width: CONTENT_W, align: 'center' });

                // ── Ligne 3 : Horaires sur 2 colonnes
                const HOR_Y = FTR_TEXT_Y + 23;
                const HALF = CONTENT_W / 2 - 2;
                doc.fontSize(6.5).font('Helvetica-Bold').fillColor(NAVY)
                    .text('ABIDJAN', M, HOR_Y, { width: HALF, align: 'center' });
                doc.text('PARIS', M + HALF + 4, HOR_Y, { width: HALF, align: 'center' });

                doc.fontSize(6).font('Helvetica').fillColor(MEDIUM)
                    .text('Lun–Ven : 08h–17h  |  Sam–Dim : 08h–14h30', M, HOR_Y + 8, { width: HALF, align: 'center' });
                doc.text('Lun–Sam : 10h30–18h  |  Dim : 10h–14h', M + HALF + 4, HOR_Y + 8, { width: HALF, align: 'center' });

                // ── Ligne 4 : Édité par + Réf (maintenant au-dessus du trait)
                const now = new Date();
                const dateEdit = now.toLocaleDateString('fr-FR');
                const timeEdit = now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
                const editorName = user
                    ? (`${user.prenom || ''} ${user.nom || user.username || ''}`.trim())
                    : (facture.code_user || '—');

                doc.fontSize(6.5).font('Helvetica').fillColor(MEDIUM)
                    .text(`Édité par ${editorName} le ${dateEdit} à ${timeEdit}`, M, HOR_Y + 18);
                doc.text(`Réf. ${facture.num_facture}`, 0, HOR_Y + 18, { width: W - M, align: 'right' });

                // TRAIT FINAL
                doc.moveTo(M, FOOTER_LINE_Y).lineTo(W - M, FOOTER_LINE_Y).lineWidth(0.6).strokeColor(NAVY).stroke();

                // Image Pied de Page LBP (seul en dessous du trait)
                const footerOk = fs.existsSync(footerImgPath);
                if (footerOk) {
                    const FTR_IMG_H = 35;
                    doc.image(footerImgPath, 0, H - FTR_IMG_H, { width: W, height: FTR_IMG_H });
                } else {
                    // Bandes décoratives basses (fallback)
                    doc.rect(0, H - 8, W, 5).fill(NAVY);
                    doc.rect(0, H - 13, W, 4).fill(GOLD);
                }

                doc.end();
            } catch (error) {
                reject(error);
            }
        });
    }
}
