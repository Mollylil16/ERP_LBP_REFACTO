import {
  Body,
  Controller,
  Delete,
  Get,
  Param,
  ParseIntPipe,
  Patch,
  Post,
  Query,
  Request,
  UseGuards,
} from '@nestjs/common';
import { ApiBearerAuth, ApiOperation, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { AgencyRequiredGuard } from '../auth/guards/agency-required.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import { RhService } from './rh.service';
import { RhEncryptionService } from './encryption.service';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { RhEmploye } from './entities/rh-employe.entity';
import { CreateEmployeDto } from './dto/create-employe.dto';
import { CreateContratDto } from './dto/create-contrat.dto';
import { CreateCongeRequestDto, ValiderCongeDto } from './dto/conge.dto';
import { StatutEmploye, TypeContrat } from './entities/rh-employe.entity';
import { StatutContrat } from './entities/rh-contrat.entity';
import { StatutConge } from './entities/rh-conge-request.entity';

@ApiTags('rh')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard, AgencyRequiredGuard)
@Controller('rh')
export class RhController {
  constructor(
    private readonly rhService: RhService,
    private readonly encryptionService: RhEncryptionService,
    @InjectRepository(RhEmploye) private readonly employeRepo: Repository<RhEmploye>,
  ) {}

  // ── Dashboard ──────────────────────────────────────────────────────────────

  @Get('dashboard')
  @RequirePermission('rh.dashboard.read')
  @ApiOperation({ summary: 'KPIs et alertes RH' })
  getDashboard() {
    return this.rhService.getDashboard();
  }

  // ── Employés ───────────────────────────────────────────────────────────────

  @Get('employes')
  @RequirePermission('rh.employes.read')
  @ApiOperation({ summary: 'Liste des employés' })
  findEmployes(
    @Query('search') search?: string,
    @Query('statut') statut?: StatutEmploye,
  ) {
    return this.rhService.findAllEmployes(search, statut);
  }

  @Get('employes/:id')
  @RequirePermission('rh.employes.read')
  @ApiOperation({ summary: 'Fiche employé' })
  findEmploye(@Param('id', ParseIntPipe) id: number) {
    return this.rhService.findOneEmploye(id);
  }

  @Post('employes')
  @RequirePermission('rh.employes.create')
  @ApiOperation({ summary: 'Créer un employé' })
  createEmploye(@Body() dto: CreateEmployeDto) {
    return this.rhService.createEmploye(dto);
  }

  @Patch('employes/:id')
  @RequirePermission('rh.employes.update')
  @ApiOperation({ summary: 'Modifier un employé' })
  updateEmploye(@Param('id', ParseIntPipe) id: number, @Body() dto: Partial<CreateEmployeDto>) {
    return this.rhService.updateEmploye(id, dto);
  }

  @Patch('employes/:id/sortie')
  @RequirePermission('rh.employes.update')
  @ApiOperation({ summary: 'Enregistrer la sortie d\'un employé' })
  sortirEmploye(
    @Param('id', ParseIntPipe) id: number,
    @Body() body: { date_sortie: string; motif?: string },
  ) {
    return this.rhService.sortirEmploye(id, body.date_sortie, body.motif);
  }

  // ── Contrats ───────────────────────────────────────────────────────────────

  @Get('contrats')
  @RequirePermission('rh.contrats.read')
  @ApiOperation({ summary: 'Liste des contrats' })
  findContrats(
    @Query('statut') statut?: StatutContrat,
    @Query('type') type?: TypeContrat,
  ) {
    return this.rhService.findAllContrats(statut, type);
  }

  @Get('contrats/alertes/cdd')
  @RequirePermission('rh.contrats.read')
  @ApiOperation({ summary: 'CDD expirant bientôt' })
  getCddExpirants(@Query('jours') jours?: string) {
    return this.rhService.getCddExpirants(jours ? parseInt(jours, 10) : 30);
  }

  @Get('employes/:id/contrats')
  @RequirePermission('rh.contrats.read')
  @ApiOperation({ summary: 'Contrats d\'un employé' })
  findContratsEmploye(@Param('id', ParseIntPipe) id: number) {
    return this.rhService.findContratsEmploye(id);
  }

  @Post('contrats')
  @RequirePermission('rh.contrats.create')
  @ApiOperation({ summary: 'Créer un contrat' })
  createContrat(@Body() dto: CreateContratDto) {
    return this.rhService.createContrat(dto);
  }

  // ── Types de congé ─────────────────────────────────────────────────────────

  @Get('conge-types')
  @RequirePermission('rh.conges.read')
  @ApiOperation({ summary: 'Types de congés' })
  findCongeTypes() {
    return this.rhService.findCongeTypes();
  }

  // ── Demandes de congé ──────────────────────────────────────────────────────

  @Get('conges')
  @RequirePermission('rh.conges.read')
  @ApiOperation({ summary: 'Toutes les demandes de congé' })
  findConges(@Query('statut') statut?: StatutConge) {
    return this.rhService.findAllCongeRequests(statut);
  }

  @Get('employes/:id/conges')
  @RequirePermission('rh.conges.read')
  @ApiOperation({ summary: 'Congés d\'un employé' })
  findCongesEmploye(@Param('id', ParseIntPipe) id: number) {
    return this.rhService.findCongeRequestsEmploye(id);
  }

  @Get('employes/:id/soldes-conges')
  @RequirePermission('rh.conges.read')
  @ApiOperation({ summary: 'Soldes de congés d\'un employé' })
  getSoldesConges(@Param('id', ParseIntPipe) id: number, @Query('annee') annee?: string) {
    return this.rhService.getCongeBalancesEmploye(id, annee ? parseInt(annee, 10) : undefined);
  }

  @Post('conges')
  @RequirePermission('rh.conges.create')
  @ApiOperation({ summary: 'Soumettre une demande de congé' })
  createConge(@Body() dto: CreateCongeRequestDto) {
    return this.rhService.createCongeRequest(dto);
  }

  // ── Historique des postes ──────────────────────────────────────────────────

  @Get('employes/:id/historique-postes')
  @RequirePermission('rh.employes.read')
  @ApiOperation({ summary: 'Historique des postes d\'un employé' })
  getHistoriquePostes(@Param('id', ParseIntPipe) id: number) {
    return this.rhService.getHistoriquePostes(id);
  }

  // ── Archivage / Sortie d'un employé ───────────────────────────────────────

  @Delete('employes/:id')
  @RequirePermission('rh.employes.delete')
  @ApiOperation({ summary: 'Archiver (sortie définitive) un employé' })
  deleteEmploye(
    @Param('id', ParseIntPipe) id: number,
    @Body() body: { date_sortie?: string; motif?: string },
  ) {
    return this.rhService.sortirEmploye(
      id,
      body.date_sortie ?? new Date().toISOString().slice(0, 10),
      body.motif ?? 'Archivage administratif',
    );
  }

  @Patch('conges/:id/valider')
  @RequirePermission('rh.conges.validate')
  @ApiOperation({ summary: 'Valider / Refuser une demande de congé (RH)' })
  validerConge(
    @Param('id', ParseIntPipe) id: number,
    @Query('approuve') approuve: string,
    @Body() dto: ValiderCongeDto,
    @Request() req: { user: { id: number } },
  ) {
    return this.rhService.validerCongeRH(id, req.user.id, approuve === 'true', dto.commentaire);
  }

  // ── Données médicales sensibles (Art. 4 CDT CI) ───────────────────────────

  @Get('employes/:id/medical')
  @RequirePermission('rh.employes.update')
  @ApiOperation({ summary: 'Lire les données médicales chiffrées (RH autorisé)' })
  async getMedical(@Param('id', ParseIntPipe) id: number) {
    const emp = await this.employeRepo.createQueryBuilder('e')
      .addSelect('e.situation_medicale_enc')
      .where('e.id = :id', { id })
      .getOne();
    if (!emp) return { error: 'Employé introuvable' };
    if (!emp.situation_medicale_enc) return { id, donnees: null };
    const donnees = this.encryptionService.decryptJson(emp.situation_medicale_enc);
    return { id, donnees };
  }

  @Patch('employes/:id/medical')
  @RequirePermission('rh.employes.update')
  @ApiOperation({ summary: 'Mettre à jour les données médicales chiffrées (Art. 4 CDT CI)' })
  async updateMedical(
    @Param('id', ParseIntPipe) id: number,
    @Body() body: { statut_vih?: string; handicap_type?: string; taux_incapacite?: number },
  ) {
    const emp = await this.employeRepo.findOne({ where: { id } });
    if (!emp) return { error: 'Employé introuvable' };
    emp.situation_medicale_enc = this.encryptionService.encryptJson(body);
    await this.employeRepo.save(emp);
    return { ok: true, id, message: 'Données médicales mises à jour (chiffrées AES-256-GCM)' };
  }
}
