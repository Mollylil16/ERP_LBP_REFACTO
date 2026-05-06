import {
  Body,
  Controller,
  Delete,
  ForbiddenException,
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

  private requireGroupeurId(req: any): string {
    const groupeurId = req?.user?.groupeurId;
    if (!groupeurId) {
      throw new ForbiddenException(
        "Accès réservé aux comptes Groupeur/Grossiste configurés.",
      );
    }
    return groupeurId;
  }

  @Get('dashboard')
  @RequirePermission('groupeurs.espace.read')
  async getMonDashboard(@Request() req: any) {
    const groupeurId = this.requireGroupeurId(req);
    const [devis, exp, fac, groupeur] = await Promise.all([
      this.devisService.getParGroupeur(groupeurId),
      this.expeditionsService.getParGroupeur(groupeurId),
      this.facturesService.getParGroupeur(groupeurId),
      this.groupeursService.getDetail(groupeurId).catch(() => null),
    ]);
    if (!groupeur) {
      // Réinitialiser groupeurId pour forcer un nouveau lookup au prochain appel
      if (req?.user) req.user.groupeurId = undefined;
      throw new ForbiddenException(
        'Compte groupeur introuvable — contactez votre administrateur.',
      );
    }
    const impayees = (fac as any[]).filter((f: any) =>
      ['en_attente', 'partiel', 'en_retard'].includes(f.statut_paiement),
    ).length;
    return {
      groupeur,
      kpis: {
        devis_total: (devis as any[]).length,
        expeditions_actives: (exp as any[]).filter((e: any) => e.statut !== 'livre')
          .length,
        factures_impayees: impayees,
      },
    };
  }

  @Get('devis')
  @RequirePermission('groupeurs.espace.read')
  getMesDevis(@Request() req: any) {
    return this.devisService.getParGroupeur(this.requireGroupeurId(req));
  }

  @Post('devis')
  @RequirePermission('groupeurs.espace.write')
  creerDevis(@Body() dto: CreateDevisDto, @Request() req: any) {
    return this.devisService.creer(dto, this.requireGroupeurId(req));
  }

  @Put('devis/:id')
  @RequirePermission('groupeurs.espace.write')
  modifierDevis(
    @Param('id') id: string,
    @Body() dto: any,
    @Request() req: any,
  ) {
    return this.devisService.modifier(id, dto, this.requireGroupeurId(req));
  }

  @Delete('devis/:id')
  @RequirePermission('groupeurs.espace.write')
  supprimerDevis(@Param('id') id: string, @Request() req: any) {
    return this.devisService.supprimer(id, this.requireGroupeurId(req));
  }

  @Get('expeditions')
  @RequirePermission('groupeurs.espace.read')
  getMesExpeditions(@Request() req: any) {
    return this.expeditionsService.getParGroupeur(this.requireGroupeurId(req));
  }

  @Post('expeditions')
  @RequirePermission('groupeurs.espace.write')
  creerExpedition(@Body() dto: CreateExpeditionDto, @Request() req: any) {
    return this.expeditionsService.creer(dto, this.requireGroupeurId(req));
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
      this.requireGroupeurId(req),
    );
  }

  @Get('factures')
  @RequirePermission('groupeurs.espace.read')
  getMesFactures(@Request() req: any) {
    return this.facturesService.getParGroupeur(this.requireGroupeurId(req));
  }

  @Post('factures')
  @RequirePermission('groupeurs.espace.write')
  creerFacture(@Body() dto: CreateFactureDto, @Request() req: any) {
    return this.facturesService.creer(dto, this.requireGroupeurId(req));
  }

  @Put('factures/:id')
  @RequirePermission('groupeurs.espace.write')
  modifierFacture(
    @Param('id') id: string,
    @Body() dto: any,
    @Request() req: any,
  ) {
    return this.facturesService.modifier(id, dto, this.requireGroupeurId(req));
  }

  @Get('documents')
  @RequirePermission('groupeurs.espace.read')
  getMesDocuments(@Request() req: any) {
    return this.documentsService.getParGroupeur(this.requireGroupeurId(req));
  }

  @Post('documents/upload')
  @RequirePermission('groupeurs.espace.write')
  uploadDocument(@Body() dto: any, @Request() req: any) {
    return this.documentsService.upload(
      dto,
      this.requireGroupeurId(req),
      req.user.sub ?? req.user.id,
    );
  }

  @Delete('documents/:id')
  @RequirePermission('groupeurs.espace.write')
  supprimerDocument(@Param('id') id: string, @Request() req: any) {
    return this.documentsService.supprimer(id, this.requireGroupeurId(req));
  }

  @Get('profil')
  @RequirePermission('groupeurs.espace.read')
  getMonProfil(@Request() req: any) {
    return this.groupeursService.getDetail(this.requireGroupeurId(req));
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
      this.requireGroupeurId(req),
      allowed,
      req.user.sub ?? req.user.id,
      req.user,
    );
  }
}
