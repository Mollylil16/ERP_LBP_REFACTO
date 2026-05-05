import {
  Body, Controller, Get, Param, ParseIntPipe,
  Patch, Post, Query, UseGuards,
} from '@nestjs/common';
import { ApiBearerAuth, ApiOperation, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import { EvaluationService } from './evaluation.service';
import { RhEvaluation, StatutEvaluation } from './entities/rh-evaluation.entity';

@ApiTags('rh-evaluations')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard)
@Controller('rh/evaluations')
export class EvaluationController {
  constructor(private readonly evaluationService: EvaluationService) {}

  @Get()
  @RequirePermission('rh.evaluations.read')
  @ApiOperation({ summary: 'Liste des évaluations' })
  getEvaluations(
    @Query('employe_id') employeId?: string,
    @Query('statut') statut?: StatutEvaluation,
  ) {
    return this.evaluationService.getEvaluations(
      employeId ? parseInt(employeId, 10) : undefined,
      statut,
    );
  }

  @Post()
  @RequirePermission('rh.evaluations.create')
  @ApiOperation({ summary: 'Créer une évaluation' })
  createEvaluation(@Body() data: Partial<RhEvaluation>) {
    return this.evaluationService.createEvaluation(data);
  }

  @Patch(':id')
  @RequirePermission('rh.evaluations.update')
  @ApiOperation({ summary: 'Modifier une évaluation' })
  updateEvaluation(
    @Param('id', ParseIntPipe) id: number,
    @Body() data: Partial<RhEvaluation>,
  ) {
    return this.evaluationService.updateEvaluation(id, data);
  }

  @Patch(':id/valider')
  @RequirePermission('rh.evaluations.update')
  @ApiOperation({ summary: 'Valider une étape de l\'évaluation' })
  validerEvaluation(
    @Param('id', ParseIntPipe) id: number,
    @Query('etape') etape: 'evalue' | 'evaluateur' | 'rh',
  ) {
    return this.evaluationService.validerEvaluation(id, etape);
  }

  @Get('dashboard')
  @RequirePermission('rh.evaluations.read')
  @ApiOperation({ summary: 'Tableau de bord des évaluations' })
  getDashboard() {
    return this.evaluationService.getDashboardEval();
  }
}
