import {
  Controller,
  Get,
  Post,
  Body,
  Param,
  Patch,
  UseGuards,
  Request,
  Res,
} from '@nestjs/common';
import {
  ApiTags,
  ApiOperation,
  ApiBearerAuth,
  ApiResponse,
} from '@nestjs/swagger';
import { PaiementsService } from './paiements.service';
import { CreatePaiementDto } from './dto/create-paiement.dto';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import type { Response } from 'express';

@ApiTags('paiements')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard)
@Controller('paiements')
export class PaiementsController {
  constructor(private readonly paiementsService: PaiementsService) {}

  @Post()
  @RequirePermission('paiements.create')
  @ApiOperation({ summary: 'Enregistrer un nouveau paiement' })
  create(@Body() createPaiementDto: CreatePaiementDto, @Request() req) {
    return this.paiementsService.create(createPaiementDto, req.user.username);
  }

  @Get()
  @RequirePermission('paiements.read')
  @ApiOperation({ summary: 'Liste de tous les paiements' })
  findAll() {
    return this.paiementsService.findAll();
  }

  @Get('suivi')
  @RequirePermission('paiements.read')
  @ApiOperation({
    summary: 'Suivi consolidé des paiements (Payé/Partiel/Impayé)',
  })
  getSuivi(@Request() req) {
    return this.paiementsService.getSuiviPaiements(req.query, req.user);
  }

  // ── Routes with specific prefixes MUST be declared BEFORE :id ──

  @Get('facture/:id')
  @RequirePermission('paiements.read')
  @ApiOperation({ summary: "Historique des paiements d'une facture" })
  findByFacture(@Param('id') id: string) {
    return this.paiementsService.findByFacture(+id);
  }

  @Get('calculate/:refColis')
  @RequirePermission('paiements.read')
  @ApiOperation({ summary: 'Calculer le restant à payer pour un colis' })
  calculateRestant(@Param('refColis') refColis: string) {
    return this.paiementsService.calculateRestantAPayer(refColis);
  }

  @Get('colis/:refColis')
  @RequirePermission('paiements.read')
  @ApiOperation({ summary: "Historique des paiements d'un colis" })
  findByColis(@Param('refColis') refColis: string) {
    return this.paiementsService.findByColis(refColis);
  }

  @Get(':id/receipt')
  @RequirePermission('paiements.read')
  @ApiOperation({ summary: "Télécharger le reçu PDF d'un paiement" })
  async getReceipt(@Param('id') id: string, @Res() res: Response) {
    const buffer = await this.paiementsService.generateReceipt(+id);
    res.set({
      'Content-Type': 'application/pdf',
      'Content-Disposition': `attachment; filename=recu-paiement-${id}.pdf`,
      'Content-Length': buffer.length,
    });
    res.end(buffer);
  }

  @Get(':id')
  @RequirePermission('paiements.read')
  @ApiOperation({ summary: "Détails d'un paiement" })
  findOne(@Param('id') id: string) {
    return this.paiementsService.findOne(+id);
  }

  @Patch(':id/cancel')
  @RequirePermission('paiements.cancel')
  @ApiOperation({ summary: 'Annuler un paiement' })
  @ApiResponse({ status: 200, description: 'Paiement annulé avec succès' })
  cancel(@Param('id') id: string) {
    return this.paiementsService.cancel(+id);
  }

  @Patch(':id/validate')
  @RequirePermission('paiements.validate')
  @ApiOperation({ summary: 'Valider un paiement' })
  @ApiResponse({ status: 200, description: 'Paiement validé avec succès' })
  validate(@Param('id') id: string) {
    return this.paiementsService.validate(+id);
  }
}
