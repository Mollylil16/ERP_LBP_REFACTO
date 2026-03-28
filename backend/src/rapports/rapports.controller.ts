import { Controller, Get, Query, UseGuards, Response } from '@nestjs/common';
import { ApiTags, ApiOperation, ApiBearerAuth } from '@nestjs/swagger';
import { RapportsService } from './rapports.service';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';

@ApiTags('rapports')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard)
@Controller('rapports')
export class RapportsController {
  constructor(private readonly rapportsService: RapportsService) {}

  @Get('colis')
  @RequirePermission('rapports.view')
  @ApiOperation({ summary: 'Générer un rapport de colis' })
  getColisReport(@Query() query: any) {
    return this.rapportsService.generateRapportColis(query);
  }

  @Get('export/excel')
  @RequirePermission('rapports.export', 'rapports.view')
  @ApiOperation({ summary: 'Exporter le rapport en Excel' })
  async exportExcel(@Query() query: any, @Response() res) {
    const buffer = await this.rapportsService.exportExcel(query);
    res.set({
      'Content-Type':
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'Content-Disposition': `attachment; filename=rapport-${query.start_date || 'export'}.xlsx`,
      'Content-Length': buffer.length,
    });
    res.end(buffer);
  }

  @Get('export/pdf')
  @RequirePermission('rapports.export', 'rapports.view')
  @ApiOperation({ summary: 'Exporter le rapport en PDF' })
  async exportPDF(@Query() query: any, @Response() res) {
    const buffer = await this.rapportsService.exportPDF(query);
    res.set({
      'Content-Type': 'application/pdf',
      'Content-Disposition': `attachment; filename=rapport-${query.start_date || 'export'}.pdf`,
      'Content-Length': buffer.length,
    });
    res.end(buffer);
  }

  @Get('finances-tarif')
  @RequirePermission('rapports.view')
  @ApiOperation({ summary: 'Obtenir les finances groupées par tarif' })
  async getFinancesParTarif() {
    return this.rapportsService.getFinancesParTarif();
  }
}
