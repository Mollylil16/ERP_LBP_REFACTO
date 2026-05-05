import {
  Controller, Get, Patch, Param, ParseIntPipe, Query, Res, Request, Body, UseGuards,
} from '@nestjs/common';
import { ApiBearerAuth, ApiOperation, ApiTags } from '@nestjs/swagger';
import type { Response } from 'express';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import { RapportsService } from './rapports.service';
import { PdfService } from './pdf.service';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { RhEmploye } from './entities/rh-employe.entity';
import { RhPaieLigne } from './entities/rh-paie-ligne.entity';
import { RhContrat } from './entities/rh-contrat.entity';

@ApiTags('rh-rapports')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard)
@Controller('rh/rapports')
export class RapportsController {
  constructor(
    private readonly rapportsService: RapportsService,
    private readonly pdfService: PdfService,
    @InjectRepository(RhEmploye) private employeRepo: Repository<RhEmploye>,
    @InjectRepository(RhPaieLigne) private ligneRepo: Repository<RhPaieLigne>,
    @InjectRepository(RhContrat) private contratRepo: Repository<RhContrat>,
  ) {}

  // ── Bilan social ───────────────────────────────────────────────────────────

  @Get('bilan-social')
  @RequirePermission('rh.rapports.read')
  @ApiOperation({ summary: 'Bilan social annuel' })
  getBilanSocial(@Query('annee') annee: string) {
    return this.rapportsService.getBilanSocial(parseInt(annee ?? String(new Date().getFullYear()), 10));
  }

  // ── État CNPS ──────────────────────────────────────────────────────────────

  @Get('cnps')
  @RequirePermission('rh.rapports.read')
  @ApiOperation({ summary: 'État CNPS mensuel' })
  getEtatCnps(@Query('periode') periode: string) {
    return this.rapportsService.getEtatCnps(periode);
  }

  // ── État ITS/DGI ──────────────────────────────────────────────────────────

  @Get('its')
  @RequirePermission('rh.rapports.read')
  @ApiOperation({ summary: 'État ITS/DGI mensuel' })
  getEtatIts(@Query('periode') periode: string) {
    return this.rapportsService.getEtatIts(periode);
  }

  // ── Déclaration main-d'œuvre ──────────────────────────────────────────────

  @Get('declaration-mo')
  @RequirePermission('rh.rapports.read')
  @ApiOperation({ summary: 'Déclaration main-d\'oeuvre (Décret 2024-902 Art.6)' })
  getDeclarationMO(@Query('annee') annee: string) {
    return this.rapportsService.getDeclarationMainOeuvre(
      parseInt(annee ?? String(new Date().getFullYear()), 10),
    );
  }

  // ── Heures supplémentaires ────────────────────────────────────────────────

  @Get('heures-sup')
  @RequirePermission('rh.rapports.read')
  @ApiOperation({ summary: 'Rapport heures supplémentaires' })
  getHeuresSup(@Query('periode') periode: string) {
    return this.rapportsService.getRapportHeursSup(periode);
  }

  // ── Masse salariale 12 mois ────────────────────────────────────────────────

  @Get('masse-salariale')
  @RequirePermission('rh.rapports.read')
  @ApiOperation({ summary: 'Evolution masse salariale 12 mois' })
  getMasseSalariale() {
    return this.rapportsService.getMasseSalariale12Mois();
  }

  // ── PDF fiche employé ──────────────────────────────────────────────────────

  @Get('pdf/employe/:id')
  @RequirePermission('rh.employes.read')
  @ApiOperation({ summary: 'Export PDF fiche employé' })
  async exportFicheEmploye(
    @Param('id', ParseIntPipe) id: number,
    @Res() res: Response,
  ) {
    const employe = await this.employeRepo.findOne({ where: { id } });
    if (!employe) { res.status(404).json({ message: 'Introuvable' }); return; }
    const buffer = await this.pdfService.genererFicheEmploye(employe);
    res.setHeader('Content-Type', 'application/pdf');
    res.setHeader('Content-Disposition', `attachment; filename="fiche-${employe.matricule}.pdf"`);
    res.send(buffer);
  }

  // ── PDF attestation travail ────────────────────────────────────────────────

  @Get('pdf/attestation-travail/:id')
  @RequirePermission('rh.employes.read')
  @ApiOperation({ summary: 'Attestation de travail PDF' })
  async attestationTravail(
    @Param('id', ParseIntPipe) id: number,
    @Res() res: Response,
  ) {
    const employe = await this.employeRepo.findOne({ where: { id } });
    if (!employe) { res.status(404).json({ message: 'Introuvable' }); return; }
    const buffer = await this.pdfService.genererAttestationTravail(employe);
    res.setHeader('Content-Type', 'application/pdf');
    res.setHeader('Content-Disposition', `attachment; filename="attestation-travail-${employe.matricule}.pdf"`);
    res.send(buffer);
  }

  // ── PDF attestation salaire ────────────────────────────────────────────────

  @Get('pdf/attestation-salaire/:id')
  @RequirePermission('rh.paie.read')
  @ApiOperation({ summary: 'Attestation de salaire PDF' })
  async attestationSalaire(
    @Param('id', ParseIntPipe) id: number,
    @Res() res: Response,
  ) {
    const employe = await this.employeRepo.findOne({ where: { id } });
    if (!employe) { res.status(404).json({ message: 'Introuvable' }); return; }
    const dernierBulletin = await this.ligneRepo.findOne({
      where: { id_employe: id },
      order: { created_at: 'DESC' },
    });
    const salaire = Number(dernierBulletin?.salaire_net ?? 0);
    const buffer = await this.pdfService.genererAttestationSalaire(employe, salaire);
    res.setHeader('Content-Type', 'application/pdf');
    res.setHeader('Content-Disposition', `attachment; filename="attestation-salaire-${employe.matricule}.pdf"`);
    res.send(buffer);
  }

  // ── PDF bulletin de paie ───────────────────────────────────────────────────

  @Get('pdf/bulletin/:ligneId')
  @RequirePermission('rh.paie.read')
  @ApiOperation({ summary: 'Bulletin de paie PDF' })
  async bulletinPaie(
    @Param('ligneId', ParseIntPipe) ligneId: number,
    @Query('periode') periode: string,
    @Res() res: Response,
  ) {
    const ligne = await this.ligneRepo.findOne({
      where: { id: ligneId },
      relations: ['employe', 'run'],
    });
    if (!ligne) { res.status(404).json({ message: 'Introuvable' }); return; }
    const buffer = await this.pdfService.genererBulletinPaie(
      ligne as Parameters<PdfService['genererBulletinPaie']>[0],
      periode ?? (ligne as any).run?.periode ?? '',
    );
    res.setHeader('Content-Type', 'application/pdf');
    res.setHeader('Content-Disposition', `attachment; filename="bulletin-${ligne.id_employe}-${periode}.pdf"`);
    res.send(buffer);
  }

  // ── Registre employeur 3 fascicules JSON ──────────────────────────────────

  @Get('registre/a')
  @RequirePermission('rh.rapports.read')
  @ApiOperation({ summary: 'Fascicule A — Registre du personnel (JSON)' })
  getFasciculeA(@Query('annee') annee: string) {
    return this.rapportsService.getFasciculeA(parseInt(annee ?? String(new Date().getFullYear()), 10));
  }

  @Get('registre/b')
  @RequirePermission('rh.rapports.read')
  @ApiOperation({ summary: 'Fascicule B — Registre des congés (JSON)' })
  getFasciculeB(@Query('annee') annee: string) {
    return this.rapportsService.getFasciculeB(parseInt(annee ?? String(new Date().getFullYear()), 10));
  }

  @Get('registre/c')
  @RequirePermission('rh.rapports.read')
  @ApiOperation({ summary: 'Fascicule C — Registre AT/Maladies (JSON)' })
  getFasciculeC(@Query('annee') annee: string) {
    return this.rapportsService.getFasciculeC(parseInt(annee ?? String(new Date().getFullYear()), 10));
  }

  // ── Registre employeur 3 fascicules PDF (Décret 2024-902 Art.9) ──────────

  @Get('registre/a/pdf')
  @RequirePermission('rh.rapports.read')
  @ApiOperation({ summary: 'Fascicule A PDF — Registre du personnel' })
  async fasciculeAPdf(@Query('annee') annee: string, @Res() res: Response) {
    const data = await this.rapportsService.getFasciculeA(parseInt(annee ?? String(new Date().getFullYear()), 10));
    const buf = await this.pdfService.genererFasciculeA(data);
    res.setHeader('Content-Type', 'application/pdf');
    res.setHeader('Content-Disposition', `attachment; filename="registre-personnel-${data.annee}.pdf"`);
    res.send(buf);
  }

  @Get('registre/b/pdf')
  @RequirePermission('rh.rapports.read')
  @ApiOperation({ summary: 'Fascicule B PDF — Registre des congés' })
  async fasciculeBPdf(@Query('annee') annee: string, @Res() res: Response) {
    const data = await this.rapportsService.getFasciculeB(parseInt(annee ?? String(new Date().getFullYear()), 10));
    const buf = await this.pdfService.genererFasciculeB(data);
    res.setHeader('Content-Type', 'application/pdf');
    res.setHeader('Content-Disposition', `attachment; filename="registre-conges-${data.annee}.pdf"`);
    res.send(buf);
  }

  @Get('registre/c/pdf')
  @RequirePermission('rh.rapports.read')
  @ApiOperation({ summary: 'Fascicule C PDF — Registre AT/Maladies' })
  async fasciculeCPdf(@Query('annee') annee: string, @Res() res: Response) {
    const data = await this.rapportsService.getFasciculeC(parseInt(annee ?? String(new Date().getFullYear()), 10));
    const buf = await this.pdfService.genererFasciculeC(data);
    res.setHeader('Content-Type', 'application/pdf');
    res.setHeader('Content-Disposition', `attachment; filename="registre-at-maladies-${data.annee}.pdf"`);
    res.send(buf);
  }

  // ── Signature électronique contrat (CDC §6.3) ─────────────────────────────

  @Patch('contrats/:id/signer')
  @RequirePermission('rh.contrats.update')
  @ApiOperation({ summary: 'Signer électroniquement un contrat (salarié ou RH)' })
  async signerContrat(
    @Param('id', ParseIntPipe) id: number,
    @Body() body: { role: 'SALARIE' | 'RH'; document_url?: string },
    @Request() req,
  ) {
    const contrat = await this.contratRepo.findOne({ where: { id } });
    if (!contrat) return { error: 'Contrat introuvable' };

    const now = new Date();
    if (body.role === 'SALARIE') {
      contrat.signe_salarie_at = now;
      contrat.signature_mode = 'ELECTRONIQUE';
    } else {
      contrat.signe_rh_at = now;
      contrat.signe_rh_user_id = req.user.id;
      contrat.signature_mode = 'ELECTRONIQUE';
    }
    if (body.document_url) contrat.document_signe_url = body.document_url;

    await this.contratRepo.save(contrat);
    return { ok: true, contrat_id: id, role: body.role, signed_at: now };
  }
}
