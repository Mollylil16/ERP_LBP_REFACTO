import {
  Body, Controller, Get, Param, ParseIntPipe,
  Patch, Post, Query, UseGuards,
} from '@nestjs/common';
import { ApiBearerAuth, ApiOperation, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import { FormationService } from './formation.service';
import { RhFormation, RhInscriptionFormation } from './entities/rh-formation.entity';

@ApiTags('rh-formation')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard)
@Controller('rh/formation')
export class FormationController {
  constructor(private readonly formationService: FormationService) {}

  // ── Formations ────────────────────────────────────────────────────────────

  @Get()
  @RequirePermission('rh.formation.read')
  @ApiOperation({ summary: 'Plan de formation' })
  getFormations(@Query('annee') annee?: string) {
    return this.formationService.getFormations(annee ? parseInt(annee, 10) : undefined);
  }

  @Post()
  @RequirePermission('rh.formation.create')
  @ApiOperation({ summary: 'Créer une formation' })
  createFormation(@Body() data: Partial<RhFormation>) {
    return this.formationService.createFormation(data);
  }

  @Patch(':id')
  @RequirePermission('rh.formation.update')
  @ApiOperation({ summary: 'Modifier une formation' })
  updateFormation(
    @Param('id', ParseIntPipe) id: number,
    @Body() data: Partial<RhFormation>,
  ) {
    return this.formationService.updateFormation(id, data);
  }

  // ── Inscriptions ──────────────────────────────────────────────────────────

  @Get('inscriptions')
  @RequirePermission('rh.formation.read')
  @ApiOperation({ summary: 'Liste des inscriptions' })
  getInscriptions(
    @Query('formation_id') formationId?: string,
    @Query('employe_id') employeId?: string,
  ) {
    return this.formationService.getInscriptions(
      formationId ? parseInt(formationId, 10) : undefined,
      employeId ? parseInt(employeId, 10) : undefined,
    );
  }

  @Post('inscriptions')
  @RequirePermission('rh.formation.create')
  @ApiOperation({ summary: 'Inscrire un employe a une formation' })
  inscrire(@Body() data: Partial<RhInscriptionFormation>) {
    return this.formationService.inscrire(data);
  }

  @Patch('inscriptions/:id')
  @RequirePermission('rh.formation.update')
  @ApiOperation({ summary: 'Modifier une inscription' })
  updateInscription(
    @Param('id', ParseIntPipe) id: number,
    @Body() data: Partial<RhInscriptionFormation>,
  ) {
    return this.formationService.updateInscription(id, data);
  }

  @Get('dashboard')
  @RequirePermission('rh.formation.read')
  @ApiOperation({ summary: 'Tableau de bord formation' })
  getDashboard() {
    return this.formationService.getDashboardFormation();
  }
}
