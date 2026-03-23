import { Controller, Get, Query, UseGuards } from '@nestjs/common';
import { ApiTags, ApiOperation, ApiBearerAuth } from '@nestjs/swagger';
import { DashboardService } from './dashboard.service';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';

@ApiTags('dashboard')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('dashboard')
export class DashboardController {
    constructor(private readonly dashboardService: DashboardService) { }

    @Get('stats')
    @ApiOperation({ summary: 'Récupérer les statistiques globales' })
    getStats() {
        return this.dashboardService.getStats();
    }

    @Get('activities')
    @ApiOperation({ summary: 'Activités récentes' })
    getActivities(@Query('limit') limit?: string) {
        return this.dashboardService.getRecentActivities(limit ? +limit : 10);
    }

    @Get('caisse')
    @ApiOperation({ summary: 'Point caisse du jour' })
    getPointCaisse(@Query('date') date?: string) {
        return this.dashboardService.getPointCaisse(date);
    }

    @Get('agencies-performances')
    @ApiOperation({ summary: 'Performance journalière par agence (Directeur)' })
    getAgenciesPerformances(@Query('date') date?: string) {
        return this.dashboardService.getAgenciesPerformances(date);
    }
}
