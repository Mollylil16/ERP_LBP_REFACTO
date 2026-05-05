import {
  Body, Controller, Delete, Get, Param, ParseIntPipe,
  Patch, Post, Query, Request, UseGuards,
} from '@nestjs/common';
import { ApiBearerAuth, ApiOperation, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import { PresenceService } from './presence.service';
import { RhPresence } from './entities/rh-presence.entity';
import { RhJourFerie } from './entities/rh-jour-ferie.entity';

@ApiTags('rh-presences')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard)
@Controller('rh/presences')
export class PresenceController {
  constructor(private readonly presenceService: PresenceService) {}

  @Get()
  @RequirePermission('rh.presences.read')
  @ApiOperation({ summary: 'Liste des présences' })
  getPresences(
    @Query('employe_id') employeId?: string,
    @Query('date_debut') dateDebut?: string,
    @Query('date_fin') dateFin?: string,
  ) {
    return this.presenceService.getPresences(
      employeId ? parseInt(employeId, 10) : undefined,
      dateDebut,
      dateFin,
    );
  }

  @Post()
  @RequirePermission('rh.presences.create')
  @ApiOperation({ summary: 'Saisir une présence' })
  saisirPresence(@Body() data: Partial<RhPresence>) {
    return this.presenceService.saisirPresence(data);
  }

  @Patch(':id/valider')
  @RequirePermission('rh.presences.update')
  @ApiOperation({ summary: 'Valider une présence' })
  validerPresence(
    @Param('id', ParseIntPipe) id: number,
    @Request() req: { user: { id: number } },
  ) {
    return this.presenceService.validerPresence(id, req.user.id);
  }

  @Get('stats/:employeId')
  @RequirePermission('rh.presences.read')
  @ApiOperation({ summary: 'Statistiques mensuelles d\'un employé' })
  getStatsMensuelles(
    @Param('employeId', ParseIntPipe) employeId: number,
    @Query('periode') periode: string,
  ) {
    return this.presenceService.getStatsMensuellesEmploye(employeId, periode);
  }

  // ── Jours fériés ──────────────────────────────────────────────────────────

  @Get('feries')
  @RequirePermission('rh.presences.read')
  @ApiOperation({ summary: 'Liste des jours fériés' })
  getJoursFeries(@Query('annee') annee: string) {
    return this.presenceService.getJoursFeries(parseInt(annee, 10));
  }

  @Post('feries')
  @RequirePermission('rh.presences.update')
  @ApiOperation({ summary: 'Ajouter un jour férié' })
  createJourFerie(@Body() data: Partial<RhJourFerie>) {
    return this.presenceService.createJourFerie(data);
  }

  @Delete('feries/:id')
  @RequirePermission('rh.presences.update')
  @ApiOperation({ summary: 'Supprimer un jour férié' })
  deleteJourFerie(@Param('id', ParseIntPipe) id: number) {
    return this.presenceService.deleteJourFerie(id);
  }

  @Post('feries/seed')
  @RequirePermission('rh.presences.update')
  @ApiOperation({ summary: 'Initialiser les jours fériés CI pour une année' })
  seedFeries(@Body() body: { annee: number }) {
    return this.presenceService.seedJoursFeriesCI(body.annee);
  }
}
