import {
  Body, Controller, Get, Param, ParseIntPipe,
  Patch, Post, Query, Request, UseGuards,
} from '@nestjs/common';
import { ApiBearerAuth, ApiOperation, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import { PaieService } from './paie.service';
import { RhConfigPaie } from './entities/rh-config-paie.entity';

@ApiTags('rh-paie')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard)
@Controller('rh/paie')
export class PaieController {
  constructor(private readonly paieService: PaieService) {}

  // ── Configuration ──────────────────────────────────────────────────────────

  @Get('config')
  @RequirePermission('rh.paie.read')
  @ApiOperation({ summary: 'Lire la configuration de paie' })
  getConfig(@Query('periode') periode?: string) {
    return this.paieService.getConfig(periode);
  }

  @Post('config')
  @RequirePermission('rh.paie.update')
  @ApiOperation({ summary: 'Mettre à jour la configuration de paie (SMIG, taux légaux)' })
  upsertConfig(@Body() data: Partial<RhConfigPaie>) {
    return this.paieService.upsertConfig(data);
  }

  // ── Runs de paie ───────────────────────────────────────────────────────────

  @Get('runs')
  @RequirePermission('rh.paie.read')
  @ApiOperation({ summary: 'Liste des campagnes de paie' })
  getRuns() {
    return this.paieService.getRuns();
  }

  @Post('runs')
  @RequirePermission('rh.paie.create')
  @ApiOperation({ summary: 'Créer une nouvelle campagne de paie' })
  createRun(@Body() body: { periode: string }) {
    return this.paieService.createRun(body.periode);
  }

  @Post('runs/:id/calculer')
  @RequirePermission('rh.paie.create')
  @ApiOperation({ summary: 'Calculer toutes les lignes de paie' })
  calculerRun(@Param('id', ParseIntPipe) id: number) {
    return this.paieService.calculerRun(id);
  }

  @Patch('runs/:id/valider')
  @RequirePermission('rh.paie.update')
  @ApiOperation({ summary: 'Valider un run (RH ou DAF)' })
  validerRun(
    @Param('id', ParseIntPipe) id: number,
    @Query('role') role: 'rh' | 'daf',
    @Request() req: { user: { id: number } },
  ) {
    return this.paieService.validerRun(id, req.user.id, role ?? 'rh');
  }

  @Get('runs/:id')
  @RequirePermission('rh.paie.read')
  @ApiOperation({ summary: 'Détail d\'un run avec toutes les lignes' })
  getRunDetail(@Param('id', ParseIntPipe) id: number) {
    return this.paieService.getRunDetail(id);
  }

  // ── Bulletins par employé ──────────────────────────────────────────────────

  @Get('bulletins/:employeId')
  @RequirePermission('rh.paie.read')
  @ApiOperation({ summary: 'Historique bulletins d\'un employé' })
  getBulletinsEmploye(@Param('employeId', ParseIntPipe) id: number) {
    return this.paieService.getLignesEmploye(id);
  }

  // ── Avances sur salaire ────────────────────────────────────────────────────

  @Get('avances')
  @RequirePermission('rh.paie.read')
  @ApiOperation({ summary: 'Liste des avances sur salaire' })
  getAvances(@Query('employe_id') id?: string) {
    return this.paieService.getAvances(id ? parseInt(id, 10) : undefined);
  }

  @Post('avances')
  @RequirePermission('rh.paie.create')
  @ApiOperation({ summary: 'Enregistrer une avance sur salaire' })
  createAvance(@Body() body: { id_employe: number; montant: number; mois_deduction: string; motif?: string }) {
    return this.paieService.createAvance(body);
  }

  @Patch('avances/:id/approuver')
  @RequirePermission('rh.paie.update')
  @ApiOperation({ summary: 'Approuver ou refuser une avance' })
  approuverAvance(
    @Param('id', ParseIntPipe) id: number,
    @Query('approuve') approuve: string,
    @Request() req: { user: { id: number } },
  ) {
    return this.paieService.approuverAvance(id, req.user.id, approuve === 'true');
  }

  // ── Stats masse salariale ──────────────────────────────────────────────────

  @Get('masse-salariale')
  @RequirePermission('rh.rapports.read')
  @ApiOperation({ summary: 'Évolution masse salariale (12 derniers mois)' })
  getMasseSalariale() {
    return this.paieService.getMasseSalariale();
  }
}
