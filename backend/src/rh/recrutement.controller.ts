import {
  Body, Controller, Get, Param, ParseIntPipe,
  Patch, Post, Query, UseGuards,
} from '@nestjs/common';
import { ApiBearerAuth, ApiOperation, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import { RecrutementService } from './recrutement.service';
import { RhPoste, RhCandidature, StatutPoste, StatutCandidature } from './entities/rh-recrutement.entity';

@ApiTags('rh-recrutement')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard)
@Controller('rh/recrutement')
export class RecrutementController {
  constructor(private readonly recrutementService: RecrutementService) {}

  // ── Postes ────────────────────────────────────────────────────────────────

  @Get('postes')
  @RequirePermission('rh.recrutement.read')
  @ApiOperation({ summary: 'Liste des postes' })
  getPostes(@Query('statut') statut?: StatutPoste) {
    return this.recrutementService.getPostes(statut);
  }

  @Post('postes')
  @RequirePermission('rh.recrutement.create')
  @ApiOperation({ summary: 'Créer un poste' })
  createPoste(@Body() data: Partial<RhPoste>) {
    return this.recrutementService.createPoste(data);
  }

  @Patch('postes/:id')
  @RequirePermission('rh.recrutement.update')
  @ApiOperation({ summary: 'Modifier un poste' })
  updatePoste(
    @Param('id', ParseIntPipe) id: number,
    @Body() data: Partial<RhPoste>,
  ) {
    return this.recrutementService.updatePoste(id, data);
  }

  // ── Candidatures ──────────────────────────────────────────────────────────

  @Get('candidatures')
  @RequirePermission('rh.recrutement.read')
  @ApiOperation({ summary: 'Liste des candidatures' })
  getCandidatures(
    @Query('poste_id') posteId?: string,
    @Query('statut') statut?: StatutCandidature,
  ) {
    return this.recrutementService.getCandidatures(
      posteId ? parseInt(posteId, 10) : undefined,
      statut,
    );
  }

  @Post('candidatures')
  @RequirePermission('rh.recrutement.create')
  @ApiOperation({ summary: 'Enregistrer une candidature' })
  createCandidature(@Body() data: Partial<RhCandidature>) {
    return this.recrutementService.createCandidature(data);
  }

  @Patch('candidatures/:id/statut')
  @RequirePermission('rh.recrutement.update')
  @ApiOperation({ summary: 'Mettre a jour le statut d\'une candidature' })
  updateStatut(
    @Param('id', ParseIntPipe) id: number,
    @Body() body: {
      statut: StatutCandidature;
      notes?: string;
      note_entretien?: number;
      date_entretien?: string;
    },
  ) {
    return this.recrutementService.updateStatutCandidature(
      id, body.statut, body.notes, body.note_entretien, body.date_entretien,
    );
  }

  @Get('dashboard')
  @RequirePermission('rh.recrutement.read')
  @ApiOperation({ summary: 'Tableau de bord recrutement' })
  getDashboard() {
    return this.recrutementService.getDashboardRecrutement();
  }
}
