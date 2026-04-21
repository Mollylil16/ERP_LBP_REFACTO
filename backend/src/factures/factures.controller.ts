import {
  Controller,
  Get,
  Post,
  Param,
  Patch,
  Query,
  UseGuards,
  Request,
  Response,
} from '@nestjs/common';
import {
  ApiTags,
  ApiOperation,
  ApiResponse,
  ApiBearerAuth,
} from '@nestjs/swagger';
import { FacturesService } from './factures.service';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import { AgencyRequiredGuard } from '../auth/guards/agency-required.guard';

@ApiTags('factures')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard, AgencyRequiredGuard)
@Controller('factures')
export class FacturesController {
  constructor(private readonly facturesService: FacturesService) {}

  @Get()
  @RequirePermission('factures.read')
  @ApiOperation({ summary: 'Liste des factures' })
  findAll(@Request() req) {
    return this.facturesService.findAll(req.user);
  }

  @Post('generate/:colisId')
  @RequirePermission('factures.create')
  @ApiOperation({ summary: 'Générer une facture depuis un colis' })
  @ApiResponse({ status: 201, description: 'Facture générée avec succès' })
  @ApiResponse({ status: 404, description: 'Colis non trouvé' })
  async generateFromColis(@Param('colisId') colisId: string, @Request() req) {
    return this.facturesService.generateFromColis(+colisId, req.user.username);
  }

  @Get('colis/:ref')
  @RequirePermission('factures.read')
  @ApiOperation({ summary: "Récupérer la facture d'un colis par sa référence" })
  findByColis(@Param('ref') ref: string) {
    return this.facturesService.findByColisRef(ref);
  }

  @Get('num/:num')
  @RequirePermission('factures.read')
  @ApiOperation({ summary: 'Récupérer une facture par son numéro (FCO-…)' })
  findByNum(@Param('num') num: string, @Request() req) {
    return this.facturesService.findByNumFacture(num, req.user);
  }

  @Get('encaissement-lookup')
  @RequirePermission('factures.read')
  @ApiOperation({
    summary: 'Recherche facture pour encaissement (n°, ref colis, téléphone)',
  })
  encaissementLookup(@Query('q') q: string, @Request() req) {
    return this.facturesService.findForEncaissementLookup(q ?? '', req.user);
  }

  @Get(':id/pdf')
  @RequirePermission('factures.read', 'factures.print')
  @ApiOperation({ summary: "Générer le PDF d'une facture" })
  async getPDF(@Param('id') id: string, @Request() req, @Response() res) {
    const buffer = await this.facturesService.generatePDF(+id, req.user);
    res.set({
      'Content-Type': 'application/pdf',
      'Content-Disposition': `attachment; filename=facture-${id}.pdf`,
      'Content-Length': buffer.length,
    });
    res.end(buffer);
  }

  @Get(':id')
  @RequirePermission('factures.read')
  @ApiOperation({ summary: "Détails d'une facture" })
  findOne(@Param('id') id: string) {
    return this.facturesService.findOne(+id);
  }

  @Patch(':id/validate')
  @RequirePermission('factures.validate')
  @ApiOperation({ summary: 'Valider une facture proforma en définitive' })
  @ApiResponse({ status: 200, description: 'Facture validée avec succès' })
  validate(@Param('id') id: string, @Request() req) {
    return this.facturesService.validateProforma(+id, {
      id: req.user?.id,
      username: req.user?.username,
    });
  }

  @Patch(':id/cancel')
  @RequirePermission('factures.cancel')
  @ApiOperation({ summary: 'Annuler une facture' })
  cancel(@Param('id') id: string, @Request() req) {
    return this.facturesService.cancelFacture(+id, {
      id: req.user?.id,
      username: req.user?.username,
    });
  }
}
