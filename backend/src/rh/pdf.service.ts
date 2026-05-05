import { Injectable } from '@nestjs/common';
// eslint-disable-next-line @typescript-eslint/no-require-imports
const PDFDocument = require('pdfkit') as typeof import('pdfkit');
import { RhPaieLigne } from './entities/rh-paie-ligne.entity';
import { RhEmploye } from './entities/rh-employe.entity';

const COULEUR_PRINCIPALE = '#1a237e';
const COULEUR_GRIS = '#f5f5f5';

@Injectable()
export class PdfService {
  private async toBuffer(doc: PDFKit.PDFDocument): Promise<Buffer> {
    return new Promise((resolve, reject) => {
      const chunks: Buffer[] = [];
      doc.on('data', (c: Buffer) => chunks.push(c));
      doc.on('end', () => resolve(Buffer.concat(chunks)));
      doc.on('error', reject);
      doc.end();
    });
  }

  // ── Bulletin de paie ───────────────────────────────────────────────────────

  async genererBulletinPaie(
    ligne: RhPaieLigne & { employe?: RhEmploye },
    periode: string,
    societe = 'LA BELLE PORTE',
  ): Promise<Buffer> {
    const doc = new PDFDocument({ size: 'A4', margin: 40 });
    const fmt = (n: number) => Math.round(n).toLocaleString('fr-FR') + ' FCFA';

    // En-tête société
    doc.rect(40, 40, 515, 60).fill(COULEUR_PRINCIPALE);
    doc.fillColor('white').fontSize(16).font('Helvetica-Bold')
      .text(societe, 50, 52);
    doc.fontSize(10).font('Helvetica')
      .text(`BULLETIN DE PAIE — ${periode}`, 50, 72);
    doc.fillColor('black');

    // Informations employé
    const emp = ligne.employe;
    doc.y = 120;
    doc.fontSize(11).font('Helvetica-Bold').text('INFORMATIONS EMPLOYÉ', 40);
    doc.moveTo(40, doc.y + 2).lineTo(555, doc.y + 2).stroke(COULEUR_PRINCIPALE);
    doc.y += 8;

    const infoCol = (label: string, val: string, x: number, y: number) => {
      doc.fontSize(9).font('Helvetica-Bold').text(label, x, y);
      doc.font('Helvetica').text(val, x + 120, y, { width: 150 });
    };

    const yBase = doc.y;
    infoCol('Matricule :', emp?.matricule ?? '—', 40, yBase);
    infoCol('Nom :', emp ? `${emp.nom} ${emp.prenoms}` : '—', 40, yBase + 16);
    infoCol('Poste :', emp?.intitule_poste ?? '—', 40, yBase + 32);
    infoCol('Catégorie :', emp?.categorie ?? '—', 40, yBase + 48);
    infoCol('Département :', emp?.departement ?? '—', 300, yBase);
    infoCol('Date embauche :', emp?.date_embauche ?? '—', 300, yBase + 16);
    infoCol('N° CNPS :', emp?.numero_cnps ?? '—', 300, yBase + 32);

    doc.y = yBase + 80;

    // Tableau gains
    doc.fontSize(11).font('Helvetica-Bold').text('ÉLÉMENTS DE RÉMUNÉRATION', 40);
    doc.moveTo(40, doc.y + 2).lineTo(555, doc.y + 2).stroke(COULEUR_PRINCIPALE);
    doc.y += 8;

    const ligneTableau = (label: string, montant: number, gris = false) => {
      const y = doc.y;
      if (gris) doc.rect(40, y - 2, 515, 16).fill(COULEUR_GRIS).stroke('white');
      doc.fillColor('black').fontSize(9).font('Helvetica').text(label, 50, y, { width: 350 });
      doc.text(fmt(montant), 400, y, { width: 155, align: 'right' });
      doc.y = y + 16;
    };

    ligneTableau('Salaire de base', ligne.salaire_base, true);
    if (ligne.prime_anciennete > 0) ligneTableau('Prime d\'ancienneté', ligne.prime_anciennete);
    if (ligne.prime_transport > 0) ligneTableau('Prime de transport', ligne.prime_transport, true);
    if (ligne.heures_sup_montant > 0) ligneTableau('Heures supplémentaires', ligne.heures_sup_montant);
    if (ligne.autres_primes > 0) ligneTableau('Autres primes', ligne.autres_primes, true);

    doc.rect(40, doc.y - 2, 515, 18).fill('#e8eaf6').stroke('white');
    doc.fillColor(COULEUR_PRINCIPALE).fontSize(10).font('Helvetica-Bold')
      .text('SALAIRE BRUT', 50, doc.y);
    doc.text(fmt(ligne.salaire_brut), 400, doc.y, { width: 155, align: 'right' });
    doc.fillColor('black');
    doc.y += 24;

    // Tableau déductions
    doc.fontSize(11).font('Helvetica-Bold').text('DÉDUCTIONS LÉGALES (SALARIALES)', 40);
    doc.moveTo(40, doc.y + 2).lineTo(555, doc.y + 2).stroke('#c62828');
    doc.y += 8;

    ligneTableau('CNPS Retraite (3,2%)', ligne.cnps_retraite_salarial, true);
    ligneTableau('CMU salariale (2%)', ligne.cmu_salarial);
    ligneTableau('ITS (barème progressif DGI)', ligne.its, true);
    ligneTableau('Contribution Nationale (1,5%)', ligne.cn);
    if (ligne.avances_deduites > 0) ligneTableau('Avances sur salaire', ligne.avances_deduites, true);
    if (ligne.absences_deduites > 0) ligneTableau('Retenues absences injustifiées', ligne.absences_deduites);

    doc.rect(40, doc.y - 2, 515, 18).fill('#ffebee').stroke('white');
    doc.fillColor('#c62828').fontSize(10).font('Helvetica-Bold')
      .text('TOTAL DÉDUCTIONS', 50, doc.y);
    doc.text(fmt(ligne.total_deductions_salariales), 400, doc.y, { width: 155, align: 'right' });
    doc.fillColor('black');
    doc.y += 28;

    // Salaire net
    doc.rect(40, doc.y - 2, 515, 24).fill(COULEUR_PRINCIPALE).stroke('white');
    doc.fillColor('white').fontSize(13).font('Helvetica-Bold')
      .text('NET À PAYER', 50, doc.y + 2);
    doc.text(fmt(ligne.salaire_net), 350, doc.y + 2, { width: 200, align: 'right' });
    doc.fillColor('black');
    doc.y += 36;

    // Charges patronales (informatif)
    doc.fontSize(9).font('Helvetica').fillColor('#666')
      .text(`Charges patronales totales : ${fmt(ligne.total_charges_patronales)}   |   Coût total employeur : ${fmt(ligne.cout_total_employeur)}`, 40, doc.y);
    doc.y += 16;

    if (ligne.alerte_smig) {
      doc.rect(40, doc.y, 515, 20).fill('#fff3e0').stroke('#fb8c00');
      doc.fillColor('#e65100').fontSize(9).font('Helvetica-Bold')
        .text('⚠ ALERTE : le salaire net est inférieur au SMIG en vigueur (Art. 31 CDT)', 50, doc.y + 5);
      doc.fillColor('black');
      doc.y += 28;
    }

    // Pied de page
    doc.y = 750;
    doc.fontSize(7).font('Helvetica').fillColor('#999')
      .text('Document généré automatiquement — Confidentiel — Conservation 10 ans minimum (Décret 2024-902)', 40, doc.y, { align: 'center' });

    return this.toBuffer(doc);
  }

  // ── Fiche employé PDF ──────────────────────────────────────────────────────

  async genererFicheEmploye(employe: RhEmploye, societe = 'LA BELLE PORTE'): Promise<Buffer> {
    const doc = new PDFDocument({ size: 'A4', margin: 40 });

    doc.rect(40, 40, 515, 55).fill(COULEUR_PRINCIPALE);
    doc.fillColor('white').fontSize(15).font('Helvetica-Bold')
      .text(societe, 50, 50);
    doc.fontSize(10).font('Helvetica')
      .text(`FICHE EMPLOYÉ — ${employe.matricule}`, 50, 68);
    doc.fillColor('black');

    doc.y = 115;

    const section = (titre: string) => {
      doc.fontSize(11).font('Helvetica-Bold').fillColor(COULEUR_PRINCIPALE).text(titre, 40);
      doc.moveTo(40, doc.y + 2).lineTo(555, doc.y + 2).stroke(COULEUR_PRINCIPALE);
      doc.fillColor('black');
      doc.y += 8;
    };

    const champ = (label: string, val: string | null, x = 40, xVal = 180) => {
      const y = doc.y;
      doc.fontSize(9).font('Helvetica-Bold').text(label, x, y);
      doc.font('Helvetica').text(val ?? '—', xVal, y, { width: 200 });
      doc.y = y + 14;
    };

    section('IDENTITÉ');
    champ('Nom :', `${employe.nom} ${employe.prenoms}`);
    champ('Date naissance :', employe.date_naissance);
    champ('Lieu naissance :', employe.lieu_naissance);
    champ('Nationalité :', employe.nationalite);
    champ('Sexe :', employe.sexe);
    champ('Situation familiale :', employe.situation_familiale);
    champ('Nbre d\'enfants :', String(employe.nb_enfants));
    champ('N° CNI :', employe.numero_cni);
    champ('N° CNPS :', employe.numero_cnps);
    doc.y += 8;

    section('CONTACT');
    champ('Adresse :', employe.adresse);
    champ('Téléphone :', employe.telephone);
    champ('Email pro :', employe.email_pro);
    doc.y += 8;

    section('INFORMATIONS PROFESSIONNELLES');
    champ('Matricule :', employe.matricule);
    champ('Date embauche :', employe.date_embauche);
    champ('Poste :', employe.intitule_poste);
    champ('Catégorie :', employe.categorie);
    champ('Grade :', employe.grade);
    champ('Département :', employe.departement);
    champ('Service :', employe.service);
    champ('Type contrat :', employe.type_contrat_actuel);
    champ('Statut :', employe.statut);
    doc.y += 8;

    doc.y = 750;
    doc.fontSize(7).font('Helvetica').fillColor('#999')
      .text('Document confidentiel — Usage interne uniquement', 40, doc.y, { align: 'center' });

    return this.toBuffer(doc);
  }

  // ── Attestation de travail ─────────────────────────────────────────────────

  async genererAttestationTravail(employe: RhEmploye, societe = 'LA BELLE PORTE'): Promise<Buffer> {
    const doc = new PDFDocument({ size: 'A4', margin: 60 });
    const dateAujourdhui = new Date().toLocaleDateString('fr-FR', {
      day: '2-digit', month: 'long', year: 'numeric',
    });

    doc.fontSize(14).font('Helvetica-Bold').fillColor(COULEUR_PRINCIPALE)
      .text(societe, { align: 'center' });
    doc.moveDown(0.5);
    doc.fontSize(12).fillColor('black').font('Helvetica')
      .text('ATTESTATION DE TRAVAIL', { align: 'center', underline: true });
    doc.moveDown(2);

    const anciennete = (() => {
      const d = new Date(employe.date_embauche);
      const now = new Date();
      const ans = now.getFullYear() - d.getFullYear();
      return ans > 0 ? `${ans} an(s)` : 'moins d\'un an';
    })();

    doc.fontSize(11).font('Helvetica')
      .text('Nous soussignés, ', { continued: true })
      .font('Helvetica-Bold').text(societe, { continued: true })
      .font('Helvetica').text(', certifions que :', { paragraphGap: 10 });

    doc.moveDown(1);
    doc.font('Helvetica-Bold')
      .text(`${employe.nom} ${employe.prenoms}`.toUpperCase(), { align: 'center' });
    doc.moveDown(0.5);
    doc.font('Helvetica').text(`Matricule : ${employe.matricule}`, { align: 'center' });
    doc.moveDown(1);

    doc.text(
      `est employé(e) au sein de notre structure en qualité de ${employe.intitule_poste ?? 'collaborateur(trice)'},` +
      ` dans le département ${employe.departement ?? '—'}, sous contrat de type ${employe.type_contrat_actuel},` +
      ` depuis le ${new Date(employe.date_embauche).toLocaleDateString('fr-FR')} (ancienneté : ${anciennete}).`,
      { align: 'justify', paragraphGap: 16 },
    );

    doc.moveDown(1);
    doc.text(
      'Cette attestation est délivrée à l\'intéressé(e) à sa demande, pour servir et valoir ce que de droit.',
      { align: 'justify' },
    );

    doc.moveDown(3);
    doc.text(`Abidjan, le ${dateAujourdhui}`, { align: 'right' });
    doc.moveDown(1);
    doc.text('La Direction des Ressources Humaines', { align: 'right' });
    doc.moveDown(3);
    doc.font('Helvetica-Bold').text('Signature & Cachet', { align: 'right' });

    doc.y = 750;
    doc.fontSize(7).font('Helvetica').fillColor('#999')
      .text('Document généré automatiquement par le SIRH LBP', 40, doc.y, { align: 'center' });

    return this.toBuffer(doc);
  }

  // ── Registre employeur — Fascicule A (personnel) ──────────────────────────

  async genererFasciculeA(data: Awaited<ReturnType<import('./rapports.service').RapportsService['getFasciculeA']>>, societe = 'LA BELLE PORTE'): Promise<Buffer> {
    const doc = new PDFDocument({ size: 'A4', margin: 30 });
    this.entete(doc, societe, `REGISTRE DU PERSONNEL — ${data.annee}`, 'Fascicule A (Décret 2024-902 Art.9)');

    const cols = [50, 30, 130, 40, 90, 80, 80, 60];
    const headers = ['Mat.', 'S.', 'Nom & Prénoms', 'Cat.', 'Poste', 'Embauche', 'Sortie', 'Contrat'];
    this.tableHeader(doc, cols, headers);

    for (const e of data.employes) {
      const row = [
        e.matricule ?? '',
        e.sexe ?? '',
        `${e.nom} ${e.prenoms}`.slice(0, 28),
        e.categorie ?? '',
        (e.intitule_poste ?? '').slice(0, 18),
        e.date_embauche ? String(e.date_embauche).slice(0, 10) : '',
        e.date_sortie ? String(e.date_sortie).slice(0, 10) : '',
        e.type_contrat_actuel ?? '',
      ];
      this.tableRow(doc, cols, row);
    }

    this.pied(doc, `${data.employes.length} employé(s) — Conservez ce registre 5 ans minimum (Décret 2024-902)`);
    return this.toBuffer(doc);
  }

  // ── Registre employeur — Fascicule B (congés) ─────────────────────────────

  async genererFasciculeB(data: Awaited<ReturnType<import('./rapports.service').RapportsService['getFasciculeB']>>, societe = 'LA BELLE PORTE'): Promise<Buffer> {
    const doc = new PDFDocument({ size: 'A4', margin: 30 });
    this.entete(doc, societe, `REGISTRE DES CONGÉS PAYÉS — ${data.annee}`, 'Fascicule B (Décret 2024-902 Art.9)');

    const cols = [50, 150, 70, 80, 80, 40, 80];
    const headers = ['Mat.', 'Nom & Prénoms', 'Type', 'Début', 'Fin', 'Jours', 'Statut'];
    this.tableHeader(doc, cols, headers);

    for (const c of data.conges) {
      const row = [
        c.matricule ?? '',
        `${c.nom} ${c.prenoms}`.slice(0, 26),
        c.type_code ?? '',
        c.date_debut ? String(c.date_debut).slice(0, 10) : '',
        c.date_fin ? String(c.date_fin).slice(0, 10) : '',
        String(c.nb_jours_ouvrables ?? 0),
        c.statut ?? '',
      ];
      this.tableRow(doc, cols, row);
    }

    this.pied(doc, `${data.conges.length} demande(s) — Art. 27 Code du Travail CI`);
    return this.toBuffer(doc);
  }

  // ── Registre employeur — Fascicule C (AT/maladies) ────────────────────────

  async genererFasciculeC(data: Awaited<ReturnType<import('./rapports.service').RapportsService['getFasciculeC']>>, societe = 'LA BELLE PORTE'): Promise<Buffer> {
    const doc = new PDFDocument({ size: 'A4', margin: 30 });
    this.entete(doc, societe, `REGISTRE DES ACCIDENTS & MALADIES — ${data.annee}`, 'Fascicule C (Décret 2024-902 Art.9)');

    const cols = [50, 150, 90, 100, 150];
    const headers = ['Mat.', 'Nom & Prénoms', 'Date', 'Type', 'Commentaire'];
    this.tableHeader(doc, cols, headers);

    for (const a of data.absences) {
      const row = [
        a.matricule ?? '',
        `${a.nom} ${a.prenoms}`.slice(0, 26),
        a.date ? String(a.date).slice(0, 10) : '',
        a.statut ?? '',
        (a.commentaire ?? '').slice(0, 30),
      ];
      this.tableRow(doc, cols, row);
    }

    this.pied(doc, `${data.absences.length} absence(s) enregistrée(s) — CNPS CI`);
    return this.toBuffer(doc);
  }

  // ── Helpers layout communs ─────────────────────────────────────────────────

  private entete(doc: PDFKit.PDFDocument, societe: string, titre: string, sous: string) {
    doc.rect(30, 30, 535, 50).fill(COULEUR_PRINCIPALE);
    doc.fillColor('white').fontSize(13).font('Helvetica-Bold').text(societe, 40, 38);
    doc.fontSize(10).font('Helvetica').text(titre, 40, 53);
    doc.fontSize(8).text(sous, 40, 67);
    doc.fillColor('black');
    doc.y = 92;
  }

  private tableHeader(doc: PDFKit.PDFDocument, cols: number[], headers: string[]) {
    let x = 30;
    doc.rect(30, doc.y, cols.reduce((a, b) => a + b, 0), 14).fill('#e8eaf6');
    headers.forEach((h, i) => {
      doc.fillColor(COULEUR_PRINCIPALE).fontSize(7).font('Helvetica-Bold').text(h, x + 2, doc.y + 3, { width: cols[i] - 4, lineBreak: false });
      x += cols[i];
    });
    doc.fillColor('black');
    doc.y += 14;
  }

  private tableRow(doc: PDFKit.PDFDocument, cols: number[], values: string[]) {
    if (doc.y > 760) { doc.addPage(); doc.y = 30; }
    let x = 30;
    values.forEach((v, i) => {
      doc.fontSize(7).font('Helvetica').text(v, x + 2, doc.y + 2, { width: cols[i] - 4, lineBreak: false });
      x += cols[i];
    });
    doc.moveTo(30, doc.y + 12).lineTo(30 + cols.reduce((a, b) => a + b, 0), doc.y + 12).stroke('#e0e0e0');
    doc.y += 14;
  }

  private pied(doc: PDFKit.PDFDocument, note: string) {
    doc.y = 780;
    doc.fontSize(7).font('Helvetica').fillColor('#999')
      .text(`${note} — Généré le ${new Date().toLocaleDateString('fr-FR')} — Confidentiel`, 30, doc.y, { align: 'center' });
  }

  // ── Attestation de salaire ─────────────────────────────────────────────────

  async genererAttestationSalaire(
    employe: RhEmploye,
    salaireMensuelNet: number,
    societe = 'LA BELLE PORTE',
  ): Promise<Buffer> {
    const doc = new PDFDocument({ size: 'A4', margin: 60 });
    const dateAujourdhui = new Date().toLocaleDateString('fr-FR', {
      day: '2-digit', month: 'long', year: 'numeric',
    });
    const fmt = (n: number) => Math.round(n).toLocaleString('fr-FR') + ' FCFA';

    doc.fontSize(14).font('Helvetica-Bold').fillColor(COULEUR_PRINCIPALE)
      .text(societe, { align: 'center' });
    doc.moveDown(0.5);
    doc.fontSize(12).fillColor('black').font('Helvetica')
      .text('ATTESTATION DE SALAIRE', { align: 'center', underline: true });
    doc.moveDown(2);

    doc.fontSize(11).font('Helvetica-Bold')
      .text(`${employe.nom} ${employe.prenoms}`.toUpperCase(), { align: 'center' });
    doc.font('Helvetica').text(`Matricule : ${employe.matricule}`, { align: 'center' });
    doc.moveDown(1.5);

    doc.text(
      `Nous certifions que ${employe.nom} ${employe.prenoms}, employé(e) en qualité de ` +
      `${employe.intitule_poste ?? 'collaborateur(trice)'}, perçoit un salaire mensuel net de :`,
      { align: 'justify', paragraphGap: 20 },
    );

    doc.moveDown(1);
    doc.rect(doc.x, doc.y, 435, 40).fill('#e8eaf6').stroke('white');
    doc.fillColor(COULEUR_PRINCIPALE).fontSize(18).font('Helvetica-Bold')
      .text(fmt(salaireMensuelNet), 40, doc.y + 10, { align: 'center' });
    doc.fillColor('black');
    doc.y += 56;

    doc.fontSize(11).font('Helvetica').text(
      'Cette attestation est délivrée à l\'intéressé(e) à sa demande, pour servir et valoir ce que de droit.',
      { align: 'justify' },
    );

    doc.moveDown(3);
    doc.text(`Abidjan, le ${dateAujourdhui}`, { align: 'right' });
    doc.moveDown(4);
    doc.font('Helvetica-Bold').text('La Direction des Ressources Humaines', { align: 'right' });

    doc.y = 750;
    doc.fontSize(7).font('Helvetica').fillColor('#999')
      .text('Document confidentiel — Validité 3 mois', 40, doc.y, { align: 'center' });

    return this.toBuffer(doc);
  }
}
