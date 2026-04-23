import {
  Body,
  Controller,
  Get,
  Param,
  ParseIntPipe,
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
import { SupervisionService } from './supervision.service';
import { SupervisionInsightsService } from './supervision-insights.service';
import { SoumettreRapportDto } from './dto/rapport.dto';
import { SignalementDto } from './dto/signalement.dto';
import { DemanderJustificationDto } from './dto/justification.dto';
import { AnnotationDto } from './dto/annotation.dto';

@ApiTags('supervision')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard, AgencyRequiredGuard)
@Controller('supervision')
export class SupervisionController {
  constructor(
    private readonly supervisionService: SupervisionService,
    private readonly supervisionInsights: SupervisionInsightsService,
  ) {}

  @Get('kpis')
  @RequirePermission('supervision.dashboard.read')
  @ApiOperation({ summary: 'Indicateurs consolidés (réseau, jour en cours)' })
  getKpis() {
    return this.supervisionService.getKpisConsolides();
  }

  @Get('agences')
  @RequirePermission('supervision.dashboard.read')
  @ApiOperation({ summary: 'État des agences (colis du jour, caisse)' })
  getAgences() {
    return this.supervisionService.getEtatAgences();
  }

  @Get('agences/:id')
  @RequirePermission('supervision.dashboard.read')
  @ApiOperation({ summary: 'Détail agence' })
  getAgence(@Param('id', ParseIntPipe) id: number) {
    return this.supervisionService.getDetailAgence(id);
  }

  @Get('agences/:id/transactions')
  @RequirePermission('supervision.dashboard.read')
  @ApiOperation({ summary: 'Mouvements de caisse sur la période' })
  getTransactions(
    @Param('id', ParseIntPipe) agenceId: number,
    @Query('debut') debut?: string,
    @Query('fin') fin?: string,
  ) {
    return this.supervisionService.getTransactionsAgence(agenceId, debut, fin);
  }

  @Get('performance-agents')
  @RequirePermission('supervision.dashboard.read')
  @ApiOperation({ summary: 'Effectifs par agence et rôle' })
  getPerformanceAgents() {
    return this.supervisionService.getPerformanceAgents();
  }

  @Get('anomalies')
  @RequirePermission('supervision.dashboard.read')
  @ApiOperation({ summary: 'Détection doublons paiements, incohérences, trous de numérotation factures' })
  getAnomalies(
    @Query('debut') debut?: string,
    @Query('fin') fin?: string,
  ) {
    return this.supervisionService.getAnomalies(debut, fin);
  }

  @Get('insights/kpis')
  @RequirePermission('supervision.dashboard.read')
  @ApiOperation({ summary: 'KPI période (colis, factures, encaissements, clients, agences)' })
  getInsightsKpis(
    @Query('debut') debut?: string,
    @Query('fin') fin?: string,
  ) {
    return this.supervisionInsights.getKpisRange(debut, fin);
  }

  @Get('insights/activity')
  @RequirePermission('supervision.dashboard.read')
  @ApiOperation({ summary: 'Série activité (colis / factures) par jour ou par mois' })
  getInsightsActivity(
    @Query('debut') debut?: string,
    @Query('fin') fin?: string,
    @Query('bucket') bucket: 'day' | 'month' = 'day',
  ) {
    return this.supervisionInsights.getActivitySeries(
      debut,
      fin,
      bucket === 'month' ? 'month' : 'day',
    );
  }

  @Get('insights/revenue-years')
  @RequirePermission('supervision.dashboard.read')
  getRevenueYears(
    @Query('from') from?: string,
    @Query('to') to?: string,
  ) {
    const y = new Date().getFullYear();
    const f = from ? parseInt(from, 10) : y - 4;
    const t = to ? parseInt(to, 10) : y + 1;
    return this.supervisionInsights.getRevenueByYear(f, t);
  }

  @Get('insights/compare-years')
  @RequirePermission('supervision.dashboard.read')
  getCompareYears(
    @Query('a1') a1: string,
    @Query('a2') a2: string,
  ) {
    return this.supervisionInsights.getCompareYears(
      parseInt(a1, 10),
      parseInt(a2, 10),
    );
  }

  @Get('insights/projection')
  @RequirePermission('supervision.dashboard.read')
  getProjection() {
    return this.supervisionInsights.getProjectionIndicative();
  }

  @Get('insights/user-productivity')
  @RequirePermission('supervision.dashboard.read')
  getUserProductivity(
    @Query('debut') debut?: string,
    @Query('fin') fin?: string,
  ) {
    return this.supervisionInsights.getUserProductivity(debut, fin);
  }

  @Get('insights/caisse-reseau')
  @RequirePermission('supervision.dashboard.read')
  getCaisseReseau(
    @Query('debut') debut?: string,
    @Query('fin') fin?: string,
  ) {
    return this.supervisionInsights.getCaisseReseauSynthese(debut, fin);
  }

  @Get('rapports')
  @RequirePermission('supervision.rapport.read')
  @ApiOperation({ summary: 'Historique des rapports soumis' })
  getRapports() {
    return this.supervisionService.getHistoriqueRapports();
  }

  @Post('rapports')
  @RequirePermission('supervision.rapport.create')
  @ApiOperation({ summary: 'Soumettre un rapport (direction)' })
  postRapport(
    @Body() dto: SoumettreRapportDto,
    @Request() req: { user: { id: number; username: string } },
  ) {
    return this.supervisionService.soumettreRapport(dto, {
      id: req.user.id,
      username: req.user.username,
    });
  }

  @Post('signalements')
  @RequirePermission('supervision.signalement.create')
  @ApiOperation({ summary: 'Signaler une anomalie' })
  postSignalement(
    @Body() dto: SignalementDto,
    @Request() req: { user: { id: number } },
  ) {
    return this.supervisionService.signalerAnomalie(dto, { id: req.user.id });
  }

  @Post('demandes-justification')
  @RequirePermission('supervision.justification.create')
  @ApiOperation({ summary: 'Demander une justification' })
  postDj(
    @Body() dto: DemanderJustificationDto,
    @Request() req: { user: { id: number } },
  ) {
    return this.supervisionService.demanderJustification(dto, {
      id: req.user.id,
    });
  }

  @Post('annotations')
  @RequirePermission('supervision.annotation.create')
  @ApiOperation({ summary: 'Annoter une cible (traçabilité)' })
  postAnnotation(
    @Body() dto: AnnotationDto,
    @Request() req: { user: { id: number } },
  ) {
    return this.supervisionService.annoter(dto, { id: req.user.id });
  }
}
