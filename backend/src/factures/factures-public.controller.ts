import { Controller, Get, Param } from '@nestjs/common';
import { ApiTags, ApiOperation, ApiResponse } from '@nestjs/swagger';
import { FacturesService } from './factures.service';

@ApiTags('factures-public')
@Controller('factures-public')
export class FacturesPublicController {
  constructor(private readonly facturesService: FacturesService) {}

  @Get(':id')
  @ApiOperation({ summary: "Récupérer les détails d'une facture (Public)" })
  @ApiResponse({ status: 200, description: 'Détails de la facture récupérés' })
  @ApiResponse({ status: 404, description: 'Facture non trouvée' })
  async getPublicDetails(@Param('id') id: string) {
    return this.facturesService.getPublicFacture(+id);
  }
}
