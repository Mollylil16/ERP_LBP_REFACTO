import {
  Body,
  Controller,
  Delete,
  Get,
  Param,
  Post,
  Put,
  Request,
  UseGuards,
} from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../../auth/guards/permissions.guard';
import { RequirePermission } from '../../auth/decorators/permissions.decorator';
import { GroupeurOwnerGuard } from '../guards/groupeur-owner.guard';
import { GroupeursService } from '../services/groupeurs.service';
import { DevisService } from '../services/devis.service';
import { ExpeditionsService } from '../services/expeditions.service';
import { FacturesService } from '../services/factures.service';
import { DocumentsService } from '../services/documents.service';
import { CreateDevisDto } from '../dto/create-devis.dto';
import { CreateExpeditionDto } from '../dto/create-expedition.dto';
import { CreateFactureDto } from '../dto/create-facture.dto';

@ApiTags('groupeurs')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard, GroupeurOwnerGuard)
@Controller('groupeurs/espace')
export class GroupeursEspaceController {
  constructor(
    private readonly groupeursService: GroupeursService,
    private readonly devisService: DevisService,
    private readonly expeditionsService: ExpeditionsService,
    private readonly facturesService: FacturesService,
    private readonly documentsService: DocumentsService,
  ) {}

  @Get('dashboard')
  @RequirePermission('groupeurs.espace.read')
  async getMonDashboard(@Request() req: any) {
    const groupeurId = req.user.groupeurId;
    const devis = await this.devisService.getParGroupeur(groupeurId);
    const exp = await this.expeditionsService.getParGroupeur(groupeurId);
    const fac = await this.facturesService.getParGroupeur(groupeurId);
    const impayees = fac.filter((f: any) =>
      ['en_attente', 'partiel', 'en_retard'].includes(f.statut_paiement),
    ).length;
    return {
      groupeur: await this.groupeursService.getDetail(groupeurId),
      kpis: {
        devis_total: devis.length,
        expeditions_actives: exp.filter((e: any) => e.statut !== 'livre')
          .length,
        factures_impayees: impayees,
      },
    };
  }

  @Get('devis')
  @RequirePermission('groupeurs.espace.read')
  getMesDevis(@Request() req: any) {
    return this.devisService.getParGroupeur(req.user.groupeurId);
  }

  @Post('devis')
  @RequirePermission('groupeurs.espace.write')
  creerDevis(@Body() dto: CreateDevisDto, @Request() req: any) {
    return this.devisService.creer(dto, req.user.groupeurId);
  }

  @Put('devis/:id')
  @RequirePermission('groupeurs.espace.write')
  modifierDevis(
    @Param('id') id: string,
    @Body() dto: any,
    @Request() req: any,
  ) {
    return this.devisService.modifier(id, dto, req.user.groupeurId);
  }

  @Delete('devis/:id')
  @RequirePermission('groupeurs.espace.write')
  supprimerDevis(@Param('id') id: string, @Request() req: any) {
    return this.devisService.supprimer(id, req.user.groupeurId);
  }

  @Get('expeditions')
  @RequirePermission('groupeurs.espace.read')
  getMesExpeditions(@Request() req: any) {
    return this.expeditionsService.getParGroupeur(req.user.groupeurId);
  }

  @Post('expeditions')
  @RequirePermission('groupeurs.espace.write')
  creerExpedition(@Body() dto: CreateExpeditionDto, @Request() req: any) {
    return this.expeditionsService.creer(dto, req.user.groupeurId);
  }

  @Put('expeditions/:id/statut')
  @RequirePermission('groupeurs.espace.write')
  mettreAJourStatutExpedition(
    @Param('id') id: string,
    @Body() body: { statut: string; notes?: string },
    @Request() req: any,
  ) {
    return this.expeditionsService.mettreAJourStatut(
      id,
      body,
      req.user.groupeurId,
    );
  }

  @Get('factures')
  @RequirePermission('groupeurs.espace.read')
  getMesFactures(@Request() req: any) {
    return this.facturesService.getParGroupeur(req.user.groupeurId);
  }

  @Post('factures')
  @RequirePermission('groupeurs.espace.write')
  creerFacture(@Body() dto: CreateFactureDto, @Request() req: any) {
    return this.facturesService.creer(dto, req.user.groupeurId);
  }

  @Put('factures/:id')
  @RequirePermission('groupeurs.espace.write')
  modifierFacture(
    @Param('id') id: string,
    @Body() dto: any,
    @Request() req: any,
  ) {
    return this.facturesService.modifier(id, dto, req.user.groupeurId);
  }

  @Get('documents')
  @RequirePermission('groupeurs.espace.read')
  getMesDocuments(@Request() req: any) {
    return this.documentsService.getParGroupeur(req.user.groupeurId);
  }

  @Post('documents/upload')
  @RequirePermission('groupeurs.espace.write')
  uploadDocument(@Body() dto: any, @Request() req: any) {
    return this.documentsService.upload(
      dto,
      req.user.groupeurId,
      req.user.sub ?? req.user.id,
    );
  }

  @Delete('documents/:id')
  @RequirePermission('groupeurs.espace.write')
  supprimerDocument(@Param('id') id: string, @Request() req: any) {
    return this.documentsService.supprimer(id, req.user.groupeurId);
  }

  @Get('profil')
  @RequirePermission('groupeurs.espace.read')
  getMonProfil(@Request() req: any) {
    return this.groupeursService.getDetail(req.user.groupeurId);
  }

  @Put('profil')
  @RequirePermission('groupeurs.espace.write')
  modifierMonProfil(@Body() dto: any, @Request() req: any) {
    // champs non sensibles uniquement
    const allowed = (({
      telephone,
      email_contact,
      adresse,
      ville,
      pays,
    }: any) => ({
      telephone,
      email_contact,
      adresse,
      ville,
      pays,
    }))(dto ?? {});
    return this.groupeursService.modifier(
      req.user.groupeurId,
      allowed,
      req.user.sub ?? req.user.id,
      req.user,
    );
  }
}
