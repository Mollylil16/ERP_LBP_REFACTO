import {
  Body,
  Controller,
  Get,
  Param,
  Patch,
  Post,
  Query,
  Request,
  UseGuards,
} from '@nestjs/common';
import { ApiBearerAuth, ApiOperation, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import { PrestatairesService } from './prestataires.service';
import { CreatePrestataireDto } from './dto/create-prestataire.dto';
import { CreateFacturePrestataireDto } from './dto/create-facture-prestataire.dto';
import { CreateReglementPrestataireDto } from './dto/create-reglement-prestataire.dto';

@ApiTags('prestataires')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard)
@Controller('prestataires')
export class PrestatairesController {
  constructor(private readonly service: PrestatairesService) {}

  // ─────────────────────── Prestataires ───────────────────────

  @Post()
  @RequirePermission('exploitation.prestataires.create')
  @ApiOperation({ summary: 'Créer un prestataire' })
  createPrestataire(@Body() dto: CreatePrestataireDto) {
    return this.service.createPrestataire(dto);
  }

  @Get()
  @RequirePermission('exploitation.prestataires.read')
  @ApiOperation({ summary: 'Lister les prestataires' })
  listPrestataires(@Query() q: any) {
    return this.service.listPrestataires(q);
  }

  // ─────────────────────── Factures prestataires ───────────────────────

  @Post('factures')
  @RequirePermission('exploitation.prestataires_factures.create')
  @ApiOperation({ summary: 'Créer une facture prestataire' })
  createFacture(@Request() req: any, @Body() dto: CreateFacturePrestataireDto) {
    return this.service.createFacture(dto, req.user?.username);
  }

  @Get('factures')
  @RequirePermission('exploitation.prestataires_factures.read')
  @ApiOperation({ summary: 'Lister les factures prestataires' })
  listFactures(@Request() req: any, @Query() q: any) {
    return this.service.listFactures(req.user, q);
  }

  @Get('factures/:id')
  @RequirePermission('exploitation.prestataires_factures.read')
  @ApiOperation({ summary: 'Détail d’une facture prestataire' })
  getFacture(@Request() req: any, @Param('id') id: string) {
    return this.service.getFacture(Number(id), req.user);
  }

  @Post('factures/:id/reglements')
  @RequirePermission('exploitation.prestataires_reglements.create')
  @ApiOperation({ summary: 'Ajouter un règlement sur une facture prestataire' })
  addReglement(
    @Request() req: any,
    @Param('id') id: string,
    @Body() dto: CreateReglementPrestataireDto,
  ) {
    return this.service.addReglement(Number(id), dto, req.user);
  }

  // ─────────────────────── Retraits hub (caisse principale) ───────────────────────

  @Get('retraits-hub')
  @RequirePermission('exploitation.prestataires_retraits_hub.read')
  @ApiOperation({ summary: 'Liste des retraits hub (paiements espèces en agence)' })
  listRetraitsHub(@Request() req: any, @Query() q: any) {
    return this.service.listRetraitsHub(req.user, q);
  }

  @Patch('retraits-hub/:id/mark-retire')
  @RequirePermission('exploitation.prestataires_retraits_hub.update')
  @ApiOperation({ summary: 'Marquer un retrait hub comme effectué (trace)' })
  markRetrait(@Request() req: any, @Param('id') id: string) {
    return this.service.markRetraitEffectue(Number(id), req.user);
  }

  @Patch('retraits-hub/:id/request-approval')
  @RequirePermission('exploitation.prestataires_retraits_hub.request_approval')
  @ApiOperation({ summary: 'Demander approbation directeur (ASSISTANT_DG)' })
  requestApproval(@Request() req: any, @Param('id') id: string) {
    return this.service.requestRetraitApproval(Number(id), req.user);
  }

  @Patch('retraits-hub/:id/decide-approval')
  @RequirePermission('exploitation.prestataires_retraits_hub.approve')
  @ApiOperation({ summary: 'Approuver/rejeter une demande (DIRECTEUR)' })
  decideApproval(
    @Request() req: any,
    @Param('id') id: string,
    @Body() body: { approve: boolean; reason?: string },
  ) {
    return this.service.decideRetraitApproval(
      Number(id),
      req.user,
      Boolean(body.approve),
      body.reason,
    );
  }
}

