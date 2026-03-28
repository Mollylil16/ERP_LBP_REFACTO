import {
  Controller,
  Get,
  Post,
  Body,
  Param,
  UseGuards,
  Request,
  HttpStatus,
} from '@nestjs/common';
import {
  ApiTags,
  ApiOperation,
  ApiBearerAuth,
  ApiResponse,
} from '@nestjs/swagger';
import { PaiementLienService } from './paiements-lien.service';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';

@ApiTags('paiements-liens')
@Controller('paiements-liens')
export class PaiementsLienController {
  constructor(private readonly paiementLienService: PaiementLienService) {}

  @ApiBearerAuth()
  @UseGuards(JwtAuthGuard, PermissionsGuard)
  @RequirePermission('paiements.create')
  @Post('generate/:factureId')
  @ApiOperation({ summary: 'Générer un lien de paiement pour une facture' })
  async generateLink(
    @Param('factureId') factureId: string,
    @Body('montant') montant?: number,
  ) {
    return this.paiementLienService.createLink(+factureId, montant);
  }

  // Endpoint public pour la page de paiement
  @Get('public/:token')
  @ApiOperation({
    summary: "Récupérer les détails d'un lien de paiement (Public)",
  })
  async getPublicDetails(@Param('token') token: string) {
    return this.paiementLienService.findByToken(token);
  }

  // Endpoint simulé pour le callback Orange Money / Wave
  @Post('public/:token/callback')
  @ApiOperation({ summary: 'Simuler le retour de paiement (Callback)' })
  async handleCallback(
    @Param('token') token: string,
    @Body()
    callbackData: {
      status: string;
      provider: string;
      transaction_id: string;
      customer_name?: string;
    },
  ) {
    return this.paiementLienService.handleCallback(token, callbackData);
  }
}
