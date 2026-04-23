import {
  Body,
  Controller,
  Delete,
  Get,
  Param,
  Post,
  Put,
  Query,
  Request,
  UseGuards,
} from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../../auth/guards/permissions.guard';
import { RequirePermission } from '../../auth/decorators/permissions.decorator';
import { GroupeursService } from '../services/groupeurs.service';
import { CreateGroupeurDto } from '../dto/create-groupeur.dto';
import { UpdateGroupeurDto } from '../dto/update-groupeur.dto';
import { DevisService } from '../services/devis.service';
import { ExpeditionsService } from '../services/expeditions.service';
import { FacturesService } from '../services/factures.service';
import { DocumentsService } from '../services/documents.service';
import { RapportsGroupeurService } from '../services/rapports-groupeur.service';
import { SoumettreRapportGroupeurDto } from '../dto/soumettre-rapport.dto';

@ApiTags('groupeurs')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard)
@Controller('groupeurs/admin')
export class GroupeursAdminController {
  constructor(
    private readonly groupeursService: GroupeursService,
    private readonly devisService: DevisService,
    private readonly expeditionsService: ExpeditionsService,
    private readonly facturesService: FacturesService,
    private readonly documentsService: DocumentsService,
    private readonly rapportsService: RapportsGroupeurService,
  ) {}

  @Get()
  @RequirePermission('groupeurs.admin.read')
  listerTousGroupeurs(@Query() filters: any) {
    return this.groupeursService.listerTous(filters);
  }

  @Get('stats')
  @RequirePermission('groupeurs.admin.read')
  getStatsGlobales() {
    return this.groupeursService.getStatsGlobales();
  }

  @Get(':id')
  @RequirePermission('groupeurs.admin.read')
  getDetailGroupeur(@Param('id') id: string) {
    return this.groupeursService.getDetail(id);
  }

  @Get(':id/compte')
  @RequirePermission('groupeurs.admin.read')
  getCompteGroupeur(@Param('id') id: string) {
    return this.groupeursService.getCompteInfo(id);
  }

  @Get(':id/activite')
  @RequirePermission('groupeurs.admin.read')
  async getActiviteGroupeur(@Param('id') id: string) {
    return {
      devis: await this.devisService.getParGroupeur(id),
      expeditions: await this.expeditionsService.getParGroupeur(id),
      factures: await this.facturesService.getParGroupeur(id),
      documents: await this.documentsService.getParGroupeur(id),
    };
  }

  @Post()
  @RequirePermission('groupeurs.admin.write')
  creerGroupeur(@Body() dto: CreateGroupeurDto, @Request() req: any) {
    return this.groupeursService.creer(dto, req.user.sub ?? req.user.id);
  }

  @Put(':id')
  @RequirePermission('groupeurs.admin.write')
  modifierGroupeur(
    @Param('id') id: string,
    @Body() dto: UpdateGroupeurDto,
    @Request() req: any,
  ) {
    return this.groupeursService.modifier(
      id,
      dto,
      req.user.sub ?? req.user.id,
      req.user,
    );
  }

  @Put(':id/statut')
  @RequirePermission('groupeurs.admin.write')
  changerStatut(
    @Param('id') id: string,
    @Body() body: { statut: 'actif' | 'suspendu' | 'archive'; motif?: string },
    @Request() req: any,
  ) {
    return this.groupeursService.changerStatut(
      id,
      body,
      req.user.sub ?? req.user.id,
      req.user,
    );
  }

  @Delete(':id')
  @RequirePermission('groupeurs.admin.write')
  supprimerGroupeur(@Param('id') id: string, @Request() req: any) {
    return this.groupeursService.archiver(
      id,
      req.user.sub ?? req.user.id,
      req.user,
    );
  }

  @Get(':id/devis')
  @RequirePermission('groupeurs.admin.read')
  getDevisGroupeur(@Param('id') id: string) {
    return this.devisService.getParGroupeur(id);
  }

  @Get(':id/expeditions')
  @RequirePermission('groupeurs.admin.read')
  getExpeditionsGroupeur(@Param('id') id: string) {
    return this.expeditionsService.getParGroupeur(id);
  }

  @Get(':id/factures')
  @RequirePermission('groupeurs.admin.read')
  getFacturesGroupeur(@Param('id') id: string) {
    return this.facturesService.getParGroupeur(id);
  }

  @Get(':id/documents')
  @RequirePermission('groupeurs.admin.read')
  getDocumentsGroupeur(@Param('id') id: string) {
    return this.documentsService.getParGroupeur(id);
  }

  @Post('rapports/soumettre')
  @RequirePermission('groupeurs.rapports.create')
  soumettreRapport(
    @Body() dto: SoumettreRapportGroupeurDto,
    @Request() req: any,
  ) {
    return this.rapportsService.soumettre(dto, {
      id: req.user.sub ?? req.user.id,
      username: req.user.username,
    });
  }

  @Get('rapports/historique')
  @RequirePermission('groupeurs.rapports.read')
  getHistoriqueRapports() {
    return this.rapportsService.getHistorique();
  }

  @Get('audit/log')
  @RequirePermission('groupeurs.audit.read')
  getAuditLog(@Query() filters: any) {
    return this.groupeursService.getAuditLog(filters);
  }
}
