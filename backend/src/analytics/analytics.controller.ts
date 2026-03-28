import { Controller, Get, Query, UseGuards } from '@nestjs/common';
import { AnalyticsService } from './analytics.service';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import { ApiTags, ApiOperation, ApiBearerAuth } from '@nestjs/swagger';

@ApiTags('analytics')
@Controller('analytics')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard)
export class AnalyticsController {
  constructor(private readonly analyticsService: AnalyticsService) {}

  @Get('recommendations')
  @RequirePermission('rapports.view')
  @ApiOperation({
    summary: "Obtenir des recommandations stratégiques basées sur l'IA",
  })
  getRecommendations() {
    return this.analyticsService.getStrategicRecommendations();
  }

  @Get('chart-data')
  @RequirePermission('rapports.view')
  @ApiOperation({ summary: 'Données pour les graphiques du dashboard' })
  getChartData() {
    return this.analyticsService.getChartData();
  }

  @Get('traffic-repartition')
  @RequirePermission('rapports.view')
  @ApiOperation({ summary: 'Répartition du trafic par type' })
  getTrafficRepartition() {
    return this.analyticsService.getTrafficRepartition();
  }

  @Get('monitoring')
  @RequirePermission('rapports.view')
  @ApiOperation({ summary: 'Monitoring IA V1 (métriques + drift)' })
  getModelMonitoring() {
    return this.analyticsService.getModelMonitoring();
  }

  @Get('profitability/real')
  @RequirePermission('rapports.view')
  @ApiOperation({
    summary: 'Rentabilité réelle (marge unitaire, P&L, impayés, cohortes)',
  })
  getRealProfitability(
    @Query('date_debut') dateDebut?: string,
    @Query('date_fin') dateFin?: string,
    @Query('agence_id') agenceId?: string,
  ) {
    return this.analyticsService.getRealProfitability({
      date_debut: dateDebut,
      date_fin: dateFin,
      agence_id: agenceId ? Number(agenceId) : undefined,
    });
  }

  @Get('profitability/scenario')
  @RequirePermission('rapports.view')
  @ApiOperation({
    summary: 'Simulateur tarifaire (scénarios prix/coût/volume)',
  })
  simulatePricingScenario(
    @Query('price_change_pct') priceChangePct?: string,
    @Query('cost_change_pct') costChangePct?: string,
    @Query('volume_change_pct') volumeChangePct?: string,
    @Query('date_debut') dateDebut?: string,
    @Query('date_fin') dateFin?: string,
    @Query('agence_id') agenceId?: string,
  ) {
    return this.analyticsService.simulatePricingScenario({
      price_change_pct: priceChangePct ? Number(priceChangePct) : 0,
      cost_change_pct: costChangePct ? Number(costChangePct) : 0,
      volume_change_pct: volumeChangePct ? Number(volumeChangePct) : 0,
      date_debut: dateDebut,
      date_fin: dateFin,
      agence_id: agenceId ? Number(agenceId) : undefined,
    });
  }
}
