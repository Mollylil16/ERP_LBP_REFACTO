import {
  Injectable,
  NotFoundException,
  OnApplicationBootstrap,
  BadRequestException,
  ForbiddenException,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, Between, In } from 'typeorm';
import { Caisse } from './entities/caisse.entity';
import {
  MouvementCaisse,
  MouvementType,
} from './entities/mouvement-caisse.entity';
import { Agence } from '../agences/entities/agence.entity';
import {
  CaisseSession,
  CaisseSessionStatus,
} from './entities/caisse-session.entity';
import {
  CaisseMouvementWorkflow,
  WorkflowStatus,
} from './entities/caisse-mouvement-workflow.entity';
import { CaisseAuditLog } from './entities/caisse-audit-log.entity';
import { Paiement } from '../paiements/entities/paiement.entity';
import { Facture } from '../factures/entities/facture.entity';
import { BusinessAuditService } from '../audit/business-audit.service';

@Injectable()
export class CaisseService implements OnApplicationBootstrap {
  constructor(
    @InjectRepository(Caisse)
    private caisseRepository: Repository<Caisse>,
    @InjectRepository(MouvementCaisse)
    private mouvementRepository: Repository<MouvementCaisse>,
    @InjectRepository(Agence)
    private agenceRepository: Repository<Agence>,
    @InjectRepository(CaisseSession)
    private sessionRepository: Repository<CaisseSession>,
    @InjectRepository(CaisseMouvementWorkflow)
    private workflowRepository: Repository<CaisseMouvementWorkflow>,
    @InjectRepository(CaisseAuditLog)
    private caisseAuditRepository: Repository<CaisseAuditLog>,
    @InjectRepository(Paiement)
    private paiementRepository: Repository<Paiement>,
    @InjectRepository(Facture)
    private factureRepository: Repository<Facture>,
    private readonly businessAudit: BusinessAuditService,
  ) {}

  async onApplicationBootstrap() {
    // S'assurer qu'il existe une caisse pour chaque agence existante
    const agences = await this.agenceRepository.find();
    for (const agence of agences) {
      const existing = await this.caisseRepository.findOne({
        where: { agence: { id: agence.id } },
      });
      if (!existing) {
        await this.caisseRepository.save({
          nom: `Caisse - ${agence.nom}`,
          solde_initial: 0,
          agence: agence,
        });
        console.log(`Cash register created for agency: ${agence.nom}`);
      }
    }
  }

  /**
   * Caisse « principale » (centralisation siège) : nom contenant « principal »,
   * sinon première caisse par id (rétrocompatibilité).
   */
  async resolveHubPrincipalCaisseId(): Promise<number> {
    const envIdRaw = process.env.HUB_CAISSE_ID;
    const envId = envIdRaw ? Number(envIdRaw) : NaN;
    if (envIdRaw && !Number.isNaN(envId) && envId > 0) {
      return envId;
    }

    const agenceCode = (process.env.HUB_AGENCE_CODE || '').trim();
    if (agenceCode) {
      const agence = await this.agenceRepository.findOne({
        where: { code: agenceCode },
      });
      if (agence) {
        const caisse = await this.caisseRepository.findOne({
          where: { agence: { id: agence.id } },
        });
        if (caisse) return caisse.id;
      }
    }

    const byName = await this.caisseRepository
      .createQueryBuilder('c')
      .where('LOWER(c.nom) LIKE :p', { p: '%principal%' })
      .orderBy('c.id', 'ASC')
      .getOne();
    if (byName) return byName.id;
    const first = await this.caisseRepository.find({
      order: { id: 'ASC' },
      take: 1,
    });
    if (!first.length) {
      throw new NotFoundException('Aucune caisse configurée');
    }
    return first[0].id;
  }

  /**
   * Règles de périmètre caisse selon rôle :
   * - CAISSIER (principal) : n’opère que sur la caisse hub
   * - CAISSIER_AGENCE : n’opère que sur la caisse de son agence (id_agence)
   */
  async assertCaisseOperationScope(params: {
    roleCode: string | undefined;
    idCaisse: number;
    agenceId?: number;
    caisseAgenceId?: number | null;
  }): Promise<void> {
    const rc = (params.roleCode || '').toUpperCase();
    const idCaisse = Number(params.idCaisse);
    if (!idCaisse) return;

    if (rc === 'CAISSIER') {
      const hub = await this.resolveHubPrincipalCaisseId();
      const isHub = Number(idCaisse) === Number(hub);
      // Le caissier principal (Abobo) peut aussi opérer sur la caisse de son agence
      // (ex: caisse Abobo), en plus du hub.
      const isOwnAgency =
        params.agenceId != null &&
        params.caisseAgenceId != null &&
        Number(params.caisseAgenceId) === Number(params.agenceId);

      if (!isHub && !isOwnAgency) {
        throw new ForbiddenException(
          'Les opérations caisse (sessions, mouvements, encaissements) sont réservées à la caisse principale et à la caisse de votre agence.',
        );
      }
      return;
    }

    if (rc === 'CAISSIER_AGENCE') {
      const agenceId = params.agenceId != null ? Number(params.agenceId) : NaN;
      if (!agenceId || Number.isNaN(agenceId)) {
        throw new ForbiddenException(
          "Agence de l'utilisateur manquante : accès caisse d'agence impossible.",
        );
      }

      let caisseAgenceId =
        params.caisseAgenceId != null ? Number(params.caisseAgenceId) : NaN;
      if (!caisseAgenceId || Number.isNaN(caisseAgenceId)) {
        const caisse = await this.caisseRepository.findOne({
          where: { id: idCaisse },
          relations: ['agence'],
        });
        if (!caisse) {
          throw new NotFoundException(`Caisse #${idCaisse} not found`);
        }
        caisseAgenceId = caisse.agence?.id ?? NaN;
      }

      if (Number(caisseAgenceId) !== Number(agenceId)) {
        throw new ForbiddenException(
          "Vous ne pouvez opérer que sur la caisse de votre agence.",
        );
      }
    }
  }

  async createMovement(
    data: any,
    type: MouvementType,
    userId: string,
    agenceId?: number,
    operatorRole?: string,
  ): Promise<MouvementCaisse> {
    let caisseId = data.id_caisse;

    if (!caisseId && agenceId) {
      const cAg = await this.caisseRepository.findOne({
        where: { agence: { id: agenceId } },
      });
      caisseId = cAg?.id;
    }
    if (!caisseId) {
      caisseId = await this.resolveHubPrincipalCaisseId();
    }

    const caisse = await this.caisseRepository.findOne({
      where: { id: caisseId },
      relations: ['agence'],
    });

    if (!caisse) {
      throw new NotFoundException(`Caisse #${caisseId} not found`);
    }

    await this.assertCaisseOperationScope({
      roleCode: operatorRole,
      idCaisse: caisse.id,
      agenceId,
      caisseAgenceId: caisse.agence?.id ?? null,
    });

    const activeSession = await this.getActiveSession(caisse.id);
    if (!activeSession) {
      throw new BadRequestException(
        'Aucune session de caisse ouverte. Ouvrez la caisse avant tout mouvement.',
      );
    }

    const mouvement = this.mouvementRepository.create({
      ...data,
      type,
      caisse,
      code_user: userId,
      date_mouvement: data.date_mouvement
        ? new Date(data.date_mouvement)
        : new Date(),
    } as MouvementCaisse);
    const saved = await this.mouvementRepository.save(mouvement);

    const requiredLevel = this.getValidationLevelRequired(
      type,
      Number(saved.montant),
    );
    const initialStatus =
      data?.status === WorkflowStatus.DRAFT
        ? WorkflowStatus.DRAFT
        : WorkflowStatus.SUBMITTED;
    const justificatifRequired = this.isJustificatifRequired(
      type,
      Number(saved.montant),
    );
    const justificatifUrl = data?.justificatif_url ?? null;

    if (
      justificatifRequired &&
      initialStatus !== WorkflowStatus.DRAFT &&
      !justificatifUrl
    ) {
      throw new BadRequestException(
        `Pièce justificative obligatoire pour ce mouvement (seuil ${this.getJustificatifThreshold().toLocaleString('fr-FR')} FCFA).`,
      );
    }

    await this.workflowRepository.save(
      this.workflowRepository.create({
        mouvement_id: saved.id,
        mouvement_type: type,
        status: initialStatus,
        validation_level_required: requiredLevel,
        validation_level_current:
          initialStatus === WorkflowStatus.SUBMITTED ? 0 : 0,
        submitted_by:
          initialStatus === WorkflowStatus.SUBMITTED ? userId : null,
        submitted_at:
          initialStatus === WorkflowStatus.SUBMITTED ? new Date() : null,
        justificatif_url: justificatifUrl,
      }),
    );

    await this.auditCaisse(
      'MOUVEMENT_CREATE',
      saved.id,
      activeSession.id,
      userId,
      null,
      {
        type,
        montant: saved.montant,
        libelle: saved.libelle,
        status: initialStatus,
        validation_level_required: requiredLevel,
      },
    );

    return saved;
  }

  async getMouvements(
    params: any,
    agenceId?: number,
  ): Promise<MouvementCaisse[]> {
    const { id_caisse, date_debut, date_fin, type } = params;
    const where: any = {};

    if (id_caisse) {
      where.caisse = { id: id_caisse };
    } else if (agenceId) {
      where.caisse = { agence: { id: agenceId } };
    }

    if (type) where.type = type;
    if (date_debut && date_fin) {
      where.date_mouvement = Between(new Date(date_debut), new Date(date_fin));
    }

    const rows = await this.mouvementRepository.find({
      where,
      order: { created_at: 'DESC' },
      relations: ['caisse'],
    });

    const ids = rows.map((r) => r.id);
    const workflows = ids.length
      ? await this.workflowRepository.find({ where: { mouvement_id: In(ids) } })
      : [];
    const wfMap = new Map<number, CaisseMouvementWorkflow>();
    workflows.forEach((w) => wfMap.set(w.mouvement_id, w));

    return rows.map((r: any) => {
      const wf = wfMap.get(r.id);
      return {
        ...r,
        workflow_status: wf?.status ?? null,
        validation_level_required: wf?.validation_level_required ?? 1,
        validation_level_current: wf?.validation_level_current ?? 0,
        justificatif_url: wf?.justificatif_url ?? null,
        rejection_reason: wf?.rejection_reason ?? null,
      };
    });
  }

  async openSession(
    idCaisse: number,
    openedBy: string,
    soldeOuvertureReel: number,
    note?: string,
    operatorRole?: string,
    agenceId?: number,
  ) {
    const caisse = await this.caisseRepository.findOne({
      where: { id: idCaisse },
      relations: ['agence'],
    });
    if (!caisse) throw new NotFoundException(`Caisse #${idCaisse} not found`);

    await this.assertCaisseOperationScope({
      roleCode: operatorRole,
      idCaisse,
      agenceId,
      caisseAgenceId: caisse.agence?.id ?? null,
    });

    const existing = await this.getActiveSession(idCaisse);
    if (existing)
      throw new BadRequestException(
        'Une session est déjà ouverte pour cette caisse.',
      );

    const soldeTheorique = await this.getSolde(idCaisse);
    const ecart = Number(soldeOuvertureReel) - Number(soldeTheorique);

    const today = new Date();
    const dateJournee = new Date(
      today.getFullYear(),
      today.getMonth(),
      today.getDate(),
    );

    const session = await this.sessionRepository.save(
      this.sessionRepository.create({
        caisse,
        status: CaisseSessionStatus.OPEN,
        date_journee: dateJournee,
        solde_ouverture_theorique: soldeTheorique,
        solde_ouverture_reel: soldeOuvertureReel,
        ecart_ouverture: ecart,
        opened_by: openedBy,
        note_ouverture: note ?? null,
      }),
    );

    await this.auditCaisse(
      'SESSION_OPEN',
      null,
      session.id,
      openedBy,
      null,
      session,
    );
    return session;
  }

  async closeSession(
    sessionId: number,
    closedBy: string,
    soldeFermetureReel: number,
    note?: string,
    closedByUserId?: number,
    operatorRole?: string,
    agenceId?: number,
  ) {
    const session = await this.sessionRepository.findOne({
      where: { id: sessionId },
      relations: ['caisse', 'caisse.agence'],
    });
    if (!session) throw new NotFoundException('Session de caisse introuvable');
    await this.assertCaisseOperationScope({
      roleCode: operatorRole,
      idCaisse: session.caisse?.id ?? 0,
      agenceId,
      caisseAgenceId: session.caisse?.agence?.id ?? null,
    });
    if (session.status !== CaisseSessionStatus.OPEN) {
      throw new BadRequestException('Cette session est déjà clôturée.');
    }

    const soldeFermetureTheorique = await this.getSolde(session.caisse.id);
    const ecartFermeture =
      Number(soldeFermetureReel) - Number(soldeFermetureTheorique);
    const before = { ...session };

    session.status = CaisseSessionStatus.CLOSED;
    session.solde_fermeture_theorique = soldeFermetureTheorique;
    session.solde_fermeture_reel = soldeFermetureReel;
    session.ecart_fermeture = ecartFermeture;
    session.closed_by = closedBy;
    session.note_fermeture = note ?? null;

    const saved = await this.sessionRepository.save(session);
    await this.auditCaisse(
      'SESSION_CLOSE',
      null,
      session.id,
      closedBy,
      before,
      saved,
    );
    this.businessAudit.logEvent({
      action: 'caisse.session_closed',
      entity: 'caisse_session',
      entityId: String(sessionId),
      userId: closedByUserId,
      username: closedBy,
      details: {
        id_caisse: session.caisse?.id,
        solde_fermeture_reel: saved.solde_fermeture_reel,
        solde_fermeture_theorique: saved.solde_fermeture_theorique,
        ecart_fermeture: saved.ecart_fermeture,
      },
    });
    return saved;
  }

  async submitMouvement(
    mouvementId: number,
    username: string,
    operatorRole?: string,
    agenceId?: number,
  ) {
    const workflow = await this.workflowRepository.findOne({
      where: { mouvement_id: mouvementId },
    });
    if (!workflow)
      throw new NotFoundException('Workflow de mouvement introuvable');
    if (workflow.status === WorkflowStatus.VALIDATED) {
      throw new BadRequestException('Ce mouvement est déjà validé.');
    }
    const mouvement = await this.mouvementRepository.findOne({
      where: { id: mouvementId },
      relations: ['caisse', 'caisse.agence'],
    });
    if (!mouvement) throw new NotFoundException('Mouvement introuvable');
    await this.assertCaisseOperationScope({
      roleCode: operatorRole,
      idCaisse: mouvement.caisse?.id ?? 0,
      agenceId,
      caisseAgenceId: mouvement.caisse?.agence?.id ?? null,
    });
    if (
      this.isJustificatifRequired(mouvement.type, Number(mouvement.montant)) &&
      !workflow.justificatif_url
    ) {
      throw new BadRequestException(
        'Pièce justificative obligatoire avant soumission.',
      );
    }

    const before = { ...workflow };
    workflow.status = WorkflowStatus.SUBMITTED;
    workflow.submitted_by = username;
    workflow.submitted_at = new Date();
    workflow.rejection_reason = null;
    const saved = await this.workflowRepository.save(workflow);
    await this.auditCaisse(
      'MOUVEMENT_SUBMIT',
      mouvementId,
      null,
      username,
      before,
      saved,
    );
    return saved;
  }

  async attachJustificatif(
    mouvementId: number,
    justificatifUrl: string,
    username: string,
    operatorRole?: string,
    agenceId?: number,
  ) {
    if (!justificatifUrl || !justificatifUrl.trim()) {
      throw new BadRequestException('URL ou chemin du justificatif requis.');
    }
    const mouvementForCaisse = await this.mouvementRepository.findOne({
      where: { id: mouvementId },
      relations: ['caisse', 'caisse.agence'],
    });
    if (!mouvementForCaisse)
      throw new NotFoundException('Mouvement introuvable');
    await this.assertCaisseOperationScope({
      roleCode: operatorRole,
      idCaisse: mouvementForCaisse.caisse?.id ?? 0,
      agenceId,
      caisseAgenceId: mouvementForCaisse.caisse?.agence?.id ?? null,
    });

    const workflow = await this.workflowRepository.findOne({
      where: { mouvement_id: mouvementId },
    });
    if (!workflow)
      throw new NotFoundException('Workflow de mouvement introuvable');

    const before = { ...workflow };
    workflow.justificatif_url = justificatifUrl.trim();
    const saved = await this.workflowRepository.save(workflow);
    await this.auditCaisse(
      'MOUVEMENT_ATTACH_JUSTIFICATIF',
      mouvementId,
      null,
      username,
      before,
      saved,
    );
    return saved;
  }

  async validateMouvement(
    mouvementId: number,
    username: string,
    role: string,
    approve: boolean,
    reason?: string,
  ) {
    const workflow = await this.workflowRepository.findOne({
      where: { mouvement_id: mouvementId },
    });
    if (!workflow)
      throw new NotFoundException('Workflow de mouvement introuvable');
    if (
      ![WorkflowStatus.SUBMITTED, WorkflowStatus.REJECTED].includes(
        workflow.status,
      )
    ) {
      throw new BadRequestException('Mouvement non soumis pour validation.');
    }

    const normalizedRole = String(role || '').toUpperCase();
    const canValidate = [
      'ADMIN',
      'DIRECTEUR',
      'SUPER_ADMIN',
      'MANAGER',
    ].includes(normalizedRole);
    if (!canValidate) {
      throw new ForbiddenException(
        'Vous n’avez pas le droit de valider ce mouvement.',
      );
    }

    const before = { ...workflow };
    if (!approve) {
      workflow.status = WorkflowStatus.REJECTED;
      workflow.rejection_reason = reason || 'Rejeté sans motif';
      const rejected = await this.workflowRepository.save(workflow);
      await this.auditCaisse(
        'MOUVEMENT_REJECT',
        mouvementId,
        null,
        username,
        before,
        rejected,
      );
      return rejected;
    }

    workflow.validation_level_current += 1;
    if (
      workflow.validation_level_current >= workflow.validation_level_required
    ) {
      workflow.status = WorkflowStatus.VALIDATED;
      workflow.approved_by = username;
      workflow.approved_at = new Date();
    } else {
      workflow.status = WorkflowStatus.SUBMITTED;
    }
    const saved = await this.workflowRepository.save(workflow);
    await this.auditCaisse(
      'MOUVEMENT_VALIDATE',
      mouvementId,
      null,
      username,
      before,
      saved,
    );
    return saved;
  }

  async getWorkflow(mouvementId: number) {
    const workflow = await this.workflowRepository.findOne({
      where: { mouvement_id: mouvementId },
    });
    if (!workflow)
      throw new NotFoundException('Workflow de mouvement introuvable');
    return workflow;
  }

  async getActiveSession(idCaisse: number) {
    return this.sessionRepository.findOne({
      where: {
        caisse: { id: idCaisse },
        status: CaisseSessionStatus.OPEN,
      },
      relations: ['caisse'],
      order: { created_at: 'DESC' },
    });
  }

  async getSessionHistory(idCaisse: number, limit = 20) {
    return this.sessionRepository.find({
      where: { caisse: { id: idCaisse } },
      relations: ['caisse'],
      order: { created_at: 'DESC' },
      take: Math.max(1, Math.min(limit, 200)),
    });
  }

  async reconcileDaily(date: string, idCaisse?: number) {
    const target = date ? new Date(date) : new Date();
    const start = new Date(target);
    start.setHours(0, 0, 0, 0);
    const end = new Date(target);
    end.setHours(23, 59, 59, 999);

    const mouvementWhere: any = { date_mouvement: Between(start, end) };
    if (idCaisse) {
      mouvementWhere.caisse = { id: idCaisse };
    }

    const mouvements = await this.mouvementRepository.find({
      where: mouvementWhere,
      relations: ['caisse', 'caisse.agence'],
    });
    const workflows = mouvements.length
      ? await this.workflowRepository.find({
          where: { mouvement_id: In(mouvements.map((m) => m.id)) },
        })
      : [];
    const validatedIds = new Set(
      workflows
        .filter((w) => w.status === WorkflowStatus.VALIDATED)
        .map((w) => w.mouvement_id),
    );

    const mouvementsValides = mouvements.filter((m) => validatedIds.has(m.id));
    const entreesCaisse = mouvementsValides
      .filter((m) => m.type !== MouvementType.DECAISSEMENT)
      .reduce((sum, m) => sum + Number(m.montant), 0);
    const sortiesCaisse = mouvementsValides
      .filter((m) => m.type === MouvementType.DECAISSEMENT)
      .reduce((sum, m) => sum + Number(m.montant), 0);

    const paiementsQb = this.paiementRepository
      .createQueryBuilder('p')
      .leftJoinAndSelect('p.facture', 'f')
      .leftJoinAndSelect('f.colis', 'c')
      .where('p.date_paiement BETWEEN :start AND :end', { start, end })
      .andWhere('p.etat_validation = 1');
    if (idCaisse) {
      paiementsQb
        .leftJoin('c.agence', 'a')
        .andWhere(
          'a.id = (SELECT id_agence FROM lbp_caisses WHERE id = :idCaisse)',
          { idCaisse },
        );
    }
    const paiements = await paiementsQb.getMany();
    const totalPaiements = paiements.reduce(
      (sum, p) => sum + Number(p.montant),
      0,
    );

    const facturesQb = this.factureRepository
      .createQueryBuilder('f')
      .leftJoinAndSelect('f.colis', 'c')
      .where('f.date_facture BETWEEN :start AND :end', { start, end })
      .andWhere('f.etat != 2');
    if (idCaisse) {
      facturesQb
        .leftJoin('c.agence', 'a')
        .andWhere(
          'a.id = (SELECT id_agence FROM lbp_caisses WHERE id = :idCaisse)',
          { idCaisse },
        );
    }
    const factures = await facturesQb.getMany();
    const totalFactureTTC = factures.reduce(
      (sum, f) => sum + Number(f.montant_ttc),
      0,
    );

    const ecartPaiementsVsEntrees = Number(
      (totalPaiements - entreesCaisse).toFixed(2),
    );
    const ecartFacturesVsPaiements = Number(
      (totalFactureTTC - totalPaiements).toFixed(2),
    );

    return {
      date: start.toISOString().slice(0, 10),
      id_caisse: idCaisse || null,
      totals: {
        entrees_caisse_validees: entreesCaisse,
        sorties_caisse_validees: sortiesCaisse,
        paiements_valides: totalPaiements,
        factures_ttc: totalFactureTTC,
      },
      ecarts: {
        paiements_vs_entrees_caisse: ecartPaiementsVsEntrees,
        factures_vs_paiements: ecartFacturesVsPaiements,
      },
      counts: {
        mouvements_total: mouvements.length,
        mouvements_valides: mouvementsValides.length,
        paiements: paiements.length,
        factures: factures.length,
      },
    };
  }

  async detectAnomalies(dateDebut?: string, dateFin?: string) {
    const start = dateDebut ? new Date(dateDebut) : new Date('2000-01-01');
    const end = dateFin ? new Date(dateFin) : new Date();
    end.setHours(23, 59, 59, 999);

    const doublePaiements = await this.paiementRepository
      .createQueryBuilder('p')
      .select('p.id_facture', 'id_facture')
      .addSelect('p.montant', 'montant')
      .addSelect('p.mode_paiement', 'mode_paiement')
      .addSelect('p.date_paiement', 'date_paiement')
      .addSelect('COUNT(*)', 'occurrences')
      .where('p.date_paiement BETWEEN :start AND :end', { start, end })
      .groupBy('p.id_facture, p.montant, p.mode_paiement, p.date_paiement')
      .having('COUNT(*) > 1')
      .getRawMany();

    const incoherencesFactures = await this.factureRepository
      .createQueryBuilder('f')
      .where('(f.montant_paye > f.montant_ttc OR f.montant_paye < 0)')
      .getMany();

    const allFactures = await this.factureRepository.find({
      order: { num_facture: 'ASC' },
    });
    const sequenceGaps: Array<{ prefix: string; missing: number[] }> = [];
    const grouped = new Map<string, number[]>();
    allFactures.forEach((f) => {
      const parts = f.num_facture.split('-');
      if (parts.length >= 3) {
        const prefix = `${parts[0]}-${parts[1]}`;
        const seq = Number(parts[2]);
        if (!Number.isNaN(seq)) {
          grouped.set(prefix, [...(grouped.get(prefix) || []), seq]);
        }
      }
    });
    grouped.forEach((seqs, prefix) => {
      const sorted = [...seqs].sort((a, b) => a - b);
      const missing: number[] = [];
      for (let i = sorted[0]; i <= sorted[sorted.length - 1]; i += 1) {
        if (!sorted.includes(i)) missing.push(i);
      }
      if (missing.length > 0) {
        sequenceGaps.push({ prefix, missing: missing.slice(0, 50) });
      }
    });

    return {
      range: {
        date_debut: start.toISOString().slice(0, 10),
        date_fin: end.toISOString().slice(0, 10),
      },
      anomalies: {
        doublons_paiements: doublePaiements,
        incoherences_montants_factures: incoherencesFactures.map((f) => ({
          id: f.id,
          num_facture: f.num_facture,
          montant_ttc: Number(f.montant_ttc),
          montant_paye: Number(f.montant_paye),
        })),
        trous_sequence_factures: sequenceGaps,
      },
      summary: {
        doublons: doublePaiements.length,
        incoherences: incoherencesFactures.length,
        sequences_avec_trous: sequenceGaps.length,
      },
    };
  }

  async getSolde(id_caisse: number = 1): Promise<number> {
    const caisse = await this.caisseRepository.findOne({
      where: { id: id_caisse },
    });
    if (!caisse) return 0;

    const mouvements = await this.mouvementRepository.find({
      where: { caisse: { id: id_caisse } },
    });

    const total = mouvements.reduce(
      (acc, mv) => {
        // Les décaissements diminuent le solde
        if (mv.type === MouvementType.DECAISSEMENT) {
          return acc - Number(mv.montant);
        }
        // Tous les autres types (APPRO, ENTREE_*) augmentent le solde
        return acc + Number(mv.montant);
      },
      Number(caisse.solde_initial || 0),
    );

    return total;
  }

  /**
   * Solde "réel" : basé sur les sessions (ouverture/fermeture) plutôt que sur `solde_initial`.
   * - S'il existe une session OPEN : base = solde_ouverture_reel, on applique les mouvements depuis l'ouverture.
   * - Sinon, s'il existe une session CLOSED : base = solde_fermeture_reel (si renseigné), puis mouvements après clôture.
   * - Sinon : fallback sur le solde théorique (`getSolde`).
   */
  async getSoldeReelCourant(id_caisse: number): Promise<number> {
    const caisseId = Number(id_caisse);
    if (!caisseId) return 0;

    const sessionOpen = await this.sessionRepository.findOne({
      where: {
        caisse: { id: caisseId },
        status: CaisseSessionStatus.OPEN,
      },
      relations: ['caisse'],
      order: { created_at: 'DESC' },
    });
    if (sessionOpen) {
      const mouvements = await this.mouvementRepository.find({
        where: {
          caisse: { id: caisseId },
          created_at: Between(sessionOpen.created_at, new Date()),
        },
      });
      const delta = mouvements.reduce((acc, mv) => {
        if (mv.type === MouvementType.DECAISSEMENT) return acc - Number(mv.montant);
        return acc + Number(mv.montant);
      }, 0);
      return Number(sessionOpen.solde_ouverture_reel || 0) + delta;
    }

    const lastClosed = await this.sessionRepository.findOne({
      where: {
        caisse: { id: caisseId },
        status: CaisseSessionStatus.CLOSED,
      },
      relations: ['caisse'],
      order: { updated_at: 'DESC' },
    });
    if (lastClosed && lastClosed.solde_fermeture_reel != null) {
      const from = lastClosed.updated_at ?? lastClosed.created_at;
      const mouvements = await this.mouvementRepository.find({
        where: {
          caisse: { id: caisseId },
          created_at: Between(from, new Date()),
        },
      });
      const delta = mouvements.reduce((acc, mv) => {
        if (mv.type === MouvementType.DECAISSEMENT) return acc - Number(mv.montant);
        return acc + Number(mv.montant);
      }, 0);
      return Number(lastClosed.solde_fermeture_reel || 0) + delta;
    }

    return this.getSolde(caisseId);
  }

  async getPointCaisse(date?: string, id_caisse: number = 1): Promise<any> {
    const targetDate = date ? new Date(date) : new Date();
    const startOfDay = new Date(targetDate.setHours(0, 0, 0, 0));
    const endOfDay = new Date(targetDate.setHours(23, 59, 59, 999));

    const mouvements = await this.mouvementRepository.find({
      where: {
        caisse: { id: id_caisse },
        date_mouvement: Between(startOfDay, endOfDay),
      },
    });

    const entrees = mouvements
      .filter((m) => m.type !== MouvementType.DECAISSEMENT)
      .reduce((sum, m) => sum + Number(m.montant), 0);

    const sorties = mouvements
      .filter((m) => m.type === MouvementType.DECAISSEMENT)
      .reduce((sum, m) => sum + Number(m.montant), 0);

    const solde = await this.getSolde(id_caisse);

    return {
      date: startOfDay,
      entrees,
      sorties,
      solde,
      mouvementsCount: mouvements.length,
    };
  }

  /** Agrège entrées, sorties et nombre de mouvements sur toutes les caisses pour un jour donné. */
  async summarizeAllCaissesForDate(date?: string): Promise<{
    entrees: number;
    sorties: number;
    mouvementsCount: number;
  }> {
    const caisses = await this.caisseRepository.find();
    let entrees = 0;
    let sorties = 0;
    let mouvementsCount = 0;
    for (const c of caisses) {
      const p = await this.getPointCaisse(date, c.id);
      entrees += Number(p.entrees) || 0;
      sorties += Number(p.sorties) || 0;
      mouvementsCount += Number(p.mouvementsCount) || 0;
    }
    return { entrees, sorties, mouvementsCount };
  }

  /**
   * Vue « direction / caissier principal » : pour une date, totaux globaux + détail par caisse (donc par agence).
   * Les versements inter-agences devraient apparaître comme mouvements sur la caisse concernée.
   */
  async getJourneeConsolideeParCaisses(date?: string): Promise<{
    date_ref: string;
    consolide: {
      entrees: number;
      sorties: number;
      mouvementsCount: number;
    };
    par_caisse: Array<{
      id_caisse: number;
      nom_caisse: string | null;
      id_agence: number | null;
      agence: { id: number; nom: string; code: string } | null;
      solde_actuel: number;
      point_du_jour: Awaited<ReturnType<CaisseService['getPointCaisse']>>;
    }>;
  }> {
    const consolide = await this.summarizeAllCaissesForDate(date);
    const caisses = await this.caisseRepository.find({
      relations: ['agence'],
      order: { id: 'ASC' },
    });
    const par_caisse: Array<{
      id_caisse: number;
      nom_caisse: string | null;
      id_agence: number | null;
      agence: { id: number; nom: string; code: string } | null;
      solde_actuel: number;
      point_du_jour: Awaited<ReturnType<CaisseService['getPointCaisse']>>;
    }> = [];
    for (const c of caisses) {
      const point = await this.getPointCaisse(date, c.id);
      const solde_actuel = await this.getSolde(c.id);
      par_caisse.push({
        id_caisse: c.id,
        nom_caisse: c.nom ?? null,
        id_agence: c.agence?.id ?? null,
        agence: c.agence
          ? { id: c.agence.id, nom: c.agence.nom, code: c.agence.code }
          : null,
        solde_actuel,
        point_du_jour: point,
      });
    }
    const date_ref =
      date && String(date).trim() !== ''
        ? String(date).slice(0, 10)
        : new Date().toISOString().slice(0, 10);
    return { date_ref, consolide, par_caisse };
  }

  /** Même logique que la consolidation, limitée à l’agence du caissier d’agence. */
  async getJourneeConsolideePourAgence(
    date: string | undefined,
    agenceId: number,
  ): Promise<{
    date_ref: string;
    consolide: {
      entrees: number;
      sorties: number;
      mouvementsCount: number;
    };
    par_caisse: Array<{
      id_caisse: number;
      nom_caisse: string | null;
      id_agence: number | null;
      agence: { id: number; nom: string; code: string } | null;
      solde_actuel: number;
      point_du_jour: Awaited<ReturnType<CaisseService['getPointCaisse']>>;
    }>;
  }> {
    const caisses = await this.caisseRepository.find({
      where: { agence: { id: agenceId } },
      relations: ['agence'],
      order: { id: 'ASC' },
    });
    if (!caisses.length) {
      const date_ref =
        date && String(date).trim() !== ''
          ? String(date).slice(0, 10)
          : new Date().toISOString().slice(0, 10);
      return {
        date_ref,
        consolide: { entrees: 0, sorties: 0, mouvementsCount: 0 },
        par_caisse: [],
      };
    }
    let entrees = 0;
    let sorties = 0;
    let mouvementsCount = 0;
    const par_caisse: Array<{
      id_caisse: number;
      nom_caisse: string | null;
      id_agence: number | null;
      agence: { id: number; nom: string; code: string } | null;
      solde_actuel: number;
      point_du_jour: Awaited<ReturnType<CaisseService['getPointCaisse']>>;
    }> = [];
    for (const c of caisses) {
      const point = await this.getPointCaisse(date, c.id);
      const solde_actuel = await this.getSolde(c.id);
      entrees += Number(point.entrees) || 0;
      sorties += Number(point.sorties) || 0;
      mouvementsCount += Number(point.mouvementsCount) || 0;
      par_caisse.push({
        id_caisse: c.id,
        nom_caisse: c.nom ?? null,
        id_agence: c.agence?.id ?? null,
        agence: c.agence
          ? { id: c.agence.id, nom: c.agence.nom, code: c.agence.code }
          : null,
        solde_actuel,
        point_du_jour: point,
      });
    }
    const date_ref =
      date && String(date).trim() !== ''
        ? String(date).slice(0, 10)
        : new Date().toISOString().slice(0, 10);
    return {
      date_ref,
      consolide: { entrees, sorties, mouvementsCount },
      par_caisse,
    };
  }

  async findAllCaisses(agenceId?: number): Promise<any[]> {
    const caisses = agenceId
      ? await this.caisseRepository.find({
          where: { agence: { id: agenceId } },
          relations: ['agence'],
        })
      : await this.caisseRepository.find({ relations: ['agence'] });

    const results: any[] = [];
    for (const caisse of caisses) {
      const solde_actuel = await this.getSolde(caisse.id);
      results.push({
        ...caisse,
        libelle: caisse.nom,
        montant_initial: caisse.solde_initial,
        solde_actuel: solde_actuel,
        id_agence: caisse.agence?.id ?? null,
      });
    }
    return results;
  }

  async getRapportGrandesLignes(params: {
    date_debut: string;
    date_fin: string;
    id_caisse?: number;
  }): Promise<any> {
    const { date_debut, date_fin, id_caisse = 1 } = params;
    const startDate = new Date(date_debut);
    const endDate = new Date(date_fin);
    endDate.setHours(23, 59, 59, 999);

    // Récupérer la caisse
    const caisse = await this.caisseRepository.findOne({
      where: { id: id_caisse },
    });
    if (!caisse) {
      throw new NotFoundException(`Caisse #${id_caisse} not found`);
    }

    // Récupérer tous les mouvements dans la période
    const mouvements = await this.mouvementRepository.find({
      where: {
        caisse: { id: id_caisse },
        date_mouvement: Between(startDate, endDate),
      },
      order: { date_mouvement: 'ASC' },
    });

    // Calculer les totaux
    const totalAppro = mouvements
      .filter((m) => m.type === MouvementType.APPRO)
      .reduce((sum, m) => sum + Number(m.montant), 0);

    const totalDecaissement = mouvements
      .filter((m) => m.type === MouvementType.DECAISSEMENT)
      .reduce((sum, m) => sum + Number(m.montant), 0);

    const totalEntreesCheque = mouvements
      .filter((m) => m.type === MouvementType.ENTREE_CHEQUE)
      .reduce((sum, m) => sum + Number(m.montant), 0);

    const totalEntreesEspece = mouvements
      .filter((m) => m.type === MouvementType.ENTREE_ESPECE)
      .reduce((sum, m) => sum + Number(m.montant), 0);

    const totalEntreesVirement = mouvements
      .filter((m) => m.type === MouvementType.ENTREE_VIREMENT)
      .reduce((sum, m) => sum + Number(m.montant), 0);

    const totalEntrees =
      totalEntreesCheque + totalEntreesEspece + totalEntreesVirement;

    // Solde initial (avant la période)
    const mouvementsAvant = await this.mouvementRepository.find({
      where: {
        caisse: { id: id_caisse },
        date_mouvement: Between(
          new Date('1900-01-01'),
          new Date(startDate.getTime() - 1),
        ),
      },
    });

    const soldeInitial = mouvementsAvant.reduce((acc, mv) => {
      if (mv.type === MouvementType.DECAISSEMENT) {
        return acc - Number(mv.montant);
      }
      return acc + Number(mv.montant);
    }, Number(caisse.solde_initial));

    const soldeFinal =
      soldeInitial + totalAppro - totalDecaissement + totalEntrees;

    return {
      date_debut: startDate.toISOString(),
      date_fin: endDate.toISOString(),
      total_appro: totalAppro,
      total_decaissement: totalDecaissement,
      total_entrees_cheque: totalEntreesCheque,
      total_entrees_espece: totalEntreesEspece,
      total_entrees_virement: totalEntreesVirement,
      total_entrees: totalEntrees,
      solde_initial: soldeInitial,
      solde_final: soldeFinal,
    };
  }

  private getValidationLevelRequired(
    type: MouvementType,
    montant: number,
  ): number {
    const threshold = Number(
      process.env.CAISSE_DOUBLE_VALIDATION_THRESHOLD || 100000,
    );
    if (type === MouvementType.DECAISSEMENT && montant >= threshold) {
      return 2;
    }
    return 1;
  }

  private isJustificatifRequired(
    type: MouvementType,
    montant: number,
  ): boolean {
    const threshold = this.getJustificatifThreshold();
    return type === MouvementType.DECAISSEMENT && montant >= threshold;
  }

  private getJustificatifThreshold(): number {
    return Number(process.env.CAISSE_JUSTIFICATIF_THRESHOLD || 50000);
  }

  private async auditCaisse(
    action: string,
    mouvementId: number | null,
    sessionId: number | null,
    actorUsername: string,
    beforeData: any,
    afterData: any,
  ) {
    await this.caisseAuditRepository.save(
      this.caisseAuditRepository.create({
        action,
        mouvement_id: mouvementId,
        session_id: sessionId,
        actor_username: actorUsername,
        before_data: beforeData,
        after_data: afterData,
      }),
    );
  }

  // ==========================================
  // WORKFLOWS DE TRANSFERT SÉCURISÉ D'AGENCE
  // ==========================================

  async initiateTransfer(
    fromCaisseId: number,
    montant: number,
    modeTransfert: string,
    username: string,
  ): Promise<MouvementCaisse> {
    const fromCaisse = await this.caisseRepository.findOne({
      where: { id: fromCaisseId },
      relations: ['agence'],
    });
    if (!fromCaisse) throw new NotFoundException('Caisse émettrice introuvable');

    const label = `En transit - Transfert vers Caisse Principale (${modeTransfert})`;

    return this.createMovement(
      {
        id_caisse: fromCaisseId,
        montant,
        libelle: label,
        mode_retrait: modeTransfert,
        details: {
          en_transit: true,
          from_caisse_id: fromCaisseId,
          from_agence_nom: fromCaisse.agence?.nom ?? 'Agence',
          confirmed: false,
          initiated_at: new Date(),
        },
      },
      MouvementType.DECAISSEMENT,
      username,
      fromCaisse.agence?.id,
      'CAISSIER_AGENCE',
    );
  }

  async getPendingTransfers(): Promise<MouvementCaisse[]> {
    const movements = await this.mouvementRepository.find({
      where: { type: MouvementType.DECAISSEMENT },
      order: { created_at: 'DESC' },
      relations: ['caisse', 'caisse.agence'],
    });

    return movements.filter(
      (m) => m.details && m.details.en_transit === true && m.details.confirmed === false,
    );
  }

  async confirmTransfer(
    mouvementId: number,
    username: string,
    roleCode?: string,
  ): Promise<any> {
    const mouvementDecaissement = await this.mouvementRepository.findOne({
      where: { id: mouvementId },
      relations: ['caisse', 'caisse.agence'],
    });

    if (!mouvementDecaissement) {
      throw new NotFoundException('Mouvement de transfert introuvable');
    }

    if (!mouvementDecaissement.details || mouvementDecaissement.details.en_transit !== true) {
      throw new BadRequestException('Ce mouvement n’est pas un transfert en transit');
    }

    if (mouvementDecaissement.details.confirmed === true) {
      throw new BadRequestException('Ce transfert a déjà été réceptionné et confirmé');
    }

    const hubCaisseId = await this.resolveHubPrincipalCaisseId();
    const hubCaisse = await this.caisseRepository.findOne({
      where: { id: hubCaisseId },
      relations: ['agence'],
    });

    if (!hubCaisse) throw new NotFoundException('Caisse Principale Hub introuvable');

    // Mettre à jour le mouvement émetteur (marqué confirmé)
    mouvementDecaissement.details.confirmed = true;
    mouvementDecaissement.details.confirmed_at = new Date();
    mouvementDecaissement.details.confirmed_by = username;
    await this.mouvementRepository.save(mouvementDecaissement);

    // Automatiquement valider le workflow si existant
    const wf = await this.workflowRepository.findOne({
      where: { mouvement_id: mouvementId },
    });
    if (wf) {
      wf.status = WorkflowStatus.VALIDATED;
      wf.approved_by = username;
      wf.approved_at = new Date();
      await this.workflowRepository.save(wf);
    }

    // Créer le mouvement entrant (APPRO) sur la caisse principale du Siège
    const labelEntree = `APPRO centralisation - Réception de ${mouvementDecaissement.caisse.nom}`;
    const mouvementAppro = await this.createMovement(
      {
        id_caisse: hubCaisseId,
        montant: mouvementDecaissement.montant,
        libelle: labelEntree,
        mode_retrait: mouvementDecaissement.mode_retrait,
        details: {
          transfer_source_mouvement_id: mouvementId,
          from_caisse_id: mouvementDecaissement.caisse.id,
        },
      },
      MouvementType.APPRO,
      username,
      hubCaisse.agence?.id,
      roleCode ?? 'CAISSIER',
    );

    return {
      success: true,
      senderMouvementId: mouvementId,
      receiverMouvementId: mouvementAppro.id,
      montant: mouvementDecaissement.montant,
    };
  }
}

