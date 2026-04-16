import {
  Controller,
  Get,
  Post,
  Body,
  Query,
  UseGuards,
  Request,
  Param,
} from '@nestjs/common';
import { ApiTags, ApiOperation, ApiBearerAuth } from '@nestjs/swagger';
import { CaisseService } from './caisse.service';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import { MouvementType } from './entities/mouvement-caisse.entity';
import { effectiveRoleCode } from '../common/effective-role-code';

@ApiTags('caisse')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard)
@Controller('caisse')
export class CaisseController {
  constructor(private readonly caisseService: CaisseService) {}

  private reqRole(req: any): string {
    return effectiveRoleCode(req.user).toUpperCase();
  }

  @Post('appro')
  @RequirePermission('caisse.operations')
  @ApiOperation({ summary: 'Enregistrer un approvisionnement' })
  createAppro(@Body() data: any, @Request() req) {
    return this.caisseService.createMovement(
      data,
      MouvementType.APPRO,
      req.user.username,
      req.user.id_agence,
      this.reqRole(req),
    );
  }

  @Post('decaissement')
  @RequirePermission('caisse.operations')
  @ApiOperation({ summary: 'Enregistrer un décaissement' })
  createDecaissement(@Body() data: any, @Request() req) {
    return this.caisseService.createMovement(
      data,
      MouvementType.DECAISSEMENT,
      req.user.username,
      req.user.id_agence,
      this.reqRole(req),
    );
  }

  @Post('entree')
  @RequirePermission('caisse.operations')
  @ApiOperation({ summary: 'Enregistrer une entrée de caisse' })
  createEntree(@Body() data: any, @Request() req) {
    // Déterminer le type d'entrée selon le mode de règlement
    let type: MouvementType = MouvementType.ENTREE_ESPECE; // Par défaut
    if (data.mode_reglement === 'CHEQUE') {
      type = MouvementType.ENTREE_CHEQUE;
    } else if (data.mode_reglement === 'VIREMENT') {
      type = MouvementType.ENTREE_VIREMENT;
    } else if (data.type) {
      // Si le type est spécifié directement dans le body
      type = data.type as MouvementType;
    }
    return this.caisseService.createMovement(
      data,
      type,
      req.user.username,
      req.user.id_agence,
      this.reqRole(req),
    );
  }

  @Get('mouvements')
  @RequirePermission('caisse.view')
  @ApiOperation({ summary: 'Liste des mouvements de caisse' })
  getMouvements(@Query() query: any, @Request() req) {
    const rc = this.reqRole(req);
    const canSeeAll = ['ADMIN', 'DIRECTEUR', 'SUPER_ADMIN'].includes(rc);
    const agenceId =
      canSeeAll || rc === 'CAISSIER' ? undefined : req.user.id_agence;
    return this.caisseService.getMouvements(query, agenceId);
  }

  @Get('solde')
  @RequirePermission('caisse.view')
  @ApiOperation({ summary: 'Récupérer le solde actuel' })
  async getSolde(@Query('id_caisse') id_caisse?: string, @Request() req?) {
    let finalCaisseId = id_caisse ? +id_caisse : undefined;
    if (!finalCaisseId && req?.user) {
      const rc = this.reqRole(req);
      if (rc === 'CAISSIER') {
        finalCaisseId = await this.caisseService.resolveHubPrincipalCaisseId();
      } else if (req.user.id_agence) {
        const canSeeAll = ['ADMIN', 'DIRECTEUR', 'SUPER_ADMIN'].includes(rc);
        const agenceId = canSeeAll ? undefined : req.user.id_agence;
        const caisses = await this.caisseService.findAllCaisses(agenceId);
        finalCaisseId = caisses[0]?.id;
      }
    }
    if (!finalCaisseId) {
      finalCaisseId = await this.caisseService.resolveHubPrincipalCaisseId();
    }
    const solde = await this.caisseService.getSolde(finalCaisseId);
    return { solde };
  }

  @Get('point')
  @RequirePermission('caisse.view')
  @ApiOperation({ summary: 'Point de caisse journalier' })
  async getPoint(
    @Query('date') date?: string,
    @Query('id_caisse') id_caisse?: string,
    @Request() req?,
  ) {
    let id = id_caisse ? +id_caisse : undefined;
    if (!id && req?.user) {
      const rc = this.reqRole(req);
      if (rc === 'CAISSIER') {
        id = await this.caisseService.resolveHubPrincipalCaisseId();
      } else if (req.user.id_agence) {
        const canSeeAll = ['ADMIN', 'DIRECTEUR', 'SUPER_ADMIN'].includes(rc);
        const agenceId = canSeeAll ? undefined : req.user.id_agence;
        const caisses = await this.caisseService.findAllCaisses(agenceId);
        id = caisses[0]?.id;
      }
    }
    if (!id) {
      id = await this.caisseService.resolveHubPrincipalCaisseId();
    }
    return this.caisseService.getPointCaisse(date, id);
  }

  @Get('caisses')
  @RequirePermission('caisse.view')
  @ApiOperation({ summary: 'Liste des caisses' })
  async getCaisses(@Request() req) {
    const rc = this.reqRole(req);
    const canSeeAll = ['ADMIN', 'DIRECTEUR', 'SUPER_ADMIN'].includes(rc);
    const agenceId =
      canSeeAll || rc === 'CAISSIER' ? undefined : req.user.id_agence;
    const rows = await this.caisseService.findAllCaisses(agenceId);
    const hubId = await this.caisseService.resolveHubPrincipalCaisseId();
    return rows.map((c: any) => ({
      ...c,
      peut_operer:
        canSeeAll || rc !== 'CAISSIER' ? true : Number(c.id) === Number(hubId),
    }));
  }

  @Get('rapport-grandes-lignes')
  @RequirePermission('caisse.view')
  @ApiOperation({ summary: 'Rapport grandes lignes de caisse' })
  getRapportGrandesLignes(@Query() query: any) {
    return this.caisseService.getRapportGrandesLignes(query);
  }

  @Get('withdrawals')
  @RequirePermission('caisse.view')
  @ApiOperation({ summary: 'Liste spécifique des retraits (décaissements)' })
  getWithdrawals(@Query() query: any, @Request() req) {
    const rc = this.reqRole(req);
    const canSeeAll = ['ADMIN', 'DIRECTEUR', 'SUPER_ADMIN'].includes(rc);
    const agenceId =
      canSeeAll || rc === 'CAISSIER' ? undefined : req.user.id_agence;
    return this.caisseService.getMouvements(
      {
        ...query,
        type: MouvementType.DECAISSEMENT,
      },
      agenceId,
    );
  }

  @Post('sessions/open')
  @RequirePermission('caisse.operations')
  @ApiOperation({
    summary: 'Ouvrir une session de caisse (obligatoire en début de journée)',
  })
  openSession(@Body() body: any, @Request() req) {
    return this.caisseService.openSession(
      Number(body.id_caisse),
      req.user.username,
      Number(body.solde_ouverture_reel ?? 0),
      body.note,
      this.reqRole(req),
      req.user.id_agence,
    );
  }

  @Post('sessions/:id/close')
  @RequirePermission('caisse.operations')
  @ApiOperation({ summary: 'Clôturer une session de caisse (fin de journée)' })
  closeSession(@Param('id') id: string, @Body() body: any, @Request() req) {
    return this.caisseService.closeSession(
      Number(id),
      req.user.username,
      Number(body.solde_fermeture_reel ?? 0),
      body.note,
      req.user?.id,
      this.reqRole(req),
      req.user.id_agence,
    );
  }

  @Get('sessions/active')
  @RequirePermission('caisse.view')
  @ApiOperation({ summary: 'Récupérer la session active de la caisse' })
  getActiveSession(@Query('id_caisse') idCaisse: string) {
    return this.caisseService.getActiveSession(Number(idCaisse));
  }

  @Get('sessions/history')
  @RequirePermission('caisse.view')
  @ApiOperation({ summary: 'Historique des sessions de caisse' })
  getSessionHistory(
    @Query('id_caisse') idCaisse: string,
    @Query('limit') limit?: string,
  ) {
    return this.caisseService.getSessionHistory(
      Number(idCaisse),
      limit ? Number(limit) : 20,
    );
  }

  @Post('mouvements/:id/submit')
  @RequirePermission('caisse.operations')
  @ApiOperation({ summary: 'Soumettre un mouvement pour validation' })
  submitMovement(@Param('id') id: string, @Request() req) {
    return this.caisseService.submitMouvement(
      Number(id),
      req.user.username,
      this.reqRole(req),
      req.user.id_agence,
    );
  }

  @Post('mouvements/:id/validate')
  @RequirePermission('caisse.operations')
  @ApiOperation({ summary: 'Valider ou rejeter un mouvement' })
  validateMovement(@Param('id') id: string, @Body() body: any, @Request() req) {
    return this.caisseService.validateMouvement(
      Number(id),
      req.user.username,
      this.reqRole(req),
      Boolean(body.approve),
      body.reason,
    );
  }

  @Get('mouvements/:id/workflow')
  @RequirePermission('caisse.view')
  @ApiOperation({ summary: 'Récupérer le workflow d’un mouvement' })
  getWorkflow(@Param('id') id: string) {
    return this.caisseService.getWorkflow(Number(id));
  }

  @Post('mouvements/:id/justificatif')
  @RequirePermission('caisse.operations')
  @ApiOperation({ summary: 'Attacher une pièce justificative à un mouvement' })
  attachJustificatif(
    @Param('id') id: string,
    @Body('justificatif_url') justificatifUrl: string,
    @Request() req,
  ) {
    return this.caisseService.attachJustificatif(
      Number(id),
      justificatifUrl,
      req.user.username,
      this.reqRole(req),
      req.user.id_agence,
    );
  }

  @Get('reconciliation')
  @RequirePermission('caisse.view')
  @ApiOperation({
    summary: 'Rapprochement automatique caisse / paiements / factures',
  })
  reconcile(
    @Query('date') date?: string,
    @Query('id_caisse') idCaisse?: string,
  ) {
    return this.caisseService.reconcileDaily(
      date || new Date().toISOString().slice(0, 10),
      idCaisse ? Number(idCaisse) : undefined,
    );
  }

  @Get('anomalies')
  @RequirePermission('caisse.view')
  @ApiOperation({
    summary: 'Détection anomalies (doublons, incohérences, trous de séquence)',
  })
  getAnomalies(
    @Query('date_debut') dateDebut?: string,
    @Query('date_fin') dateFin?: string,
  ) {
    return this.caisseService.detectAnomalies(dateDebut, dateFin);
  }
}
