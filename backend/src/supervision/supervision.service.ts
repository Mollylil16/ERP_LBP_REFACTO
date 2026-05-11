import { Injectable, Logger } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, Between, DataSource, In } from 'typeorm';
import { Colis } from '../colis/entities/colis.entity';
import { Agence } from '../agences/entities/agence.entity';
import { User } from '../users/entities/user.entity';
import { Paiement } from '../paiements/entities/paiement.entity';
import { Facture } from '../factures/entities/facture.entity';
import { Caisse } from '../caisse/entities/caisse.entity';
import { MouvementCaisse } from '../caisse/entities/mouvement-caisse.entity';
import { CaisseService } from '../caisse/caisse.service';
import { SupervisionSignalement } from './entities/supervision-signalement.entity';
import { SupervisionDemandeJustification } from './entities/supervision-demande-justification.entity';
import { SupervisionAnnotation } from './entities/supervision-annotation.entity';
import { SupervisionRapport } from './entities/supervision-rapport.entity';
import { SignalementDto } from './dto/signalement.dto';
import { DemanderJustificationDto } from './dto/justification.dto';
import { AnnotationDto } from './dto/annotation.dto';
import { SoumettreRapportDto } from './dto/rapport.dto';
import { effectiveRoleCode } from '../common/effective-role-code';
import { UserRole } from '../users/entities/user.entity';
import { NotificationService } from '../notifications/notification.service';
import {
  NotificationCategory,
  NotificationType,
} from '../notifications/entities/notification.entity';
import { RhCongeRequest, StatutConge } from '../rh/entities/rh-conge-request.entity';
import { RhEvaluation } from '../rh/entities/rh-evaluation.entity';
import { RhEmploye } from '../rh/entities/rh-employe.entity';

@Injectable()
export class SupervisionService {
  private readonly logger = new Logger(SupervisionService.name);

  constructor(
    @InjectRepository(Agence)
    private agenceRepo: Repository<Agence>,
    @InjectRepository(Colis)
    private colisRepo: Repository<Colis>,
    @InjectRepository(Paiement)
    private paiementRepo: Repository<Paiement>,
    @InjectRepository(Facture)
    private factureRepo: Repository<Facture>,
    @InjectRepository(Caisse)
    private caisseRepo: Repository<Caisse>,
    @InjectRepository(MouvementCaisse)
    private mouvementRepo: Repository<MouvementCaisse>,
    @InjectRepository(User)
    private userRepo: Repository<User>,
    @InjectRepository(SupervisionSignalement)
    private signalementRepo: Repository<SupervisionSignalement>,
    @InjectRepository(SupervisionDemandeJustification)
    private djRepo: Repository<SupervisionDemandeJustification>,
    @InjectRepository(SupervisionAnnotation)
    private annRepo: Repository<SupervisionAnnotation>,
    @InjectRepository(SupervisionRapport)
    private rapportRepo: Repository<SupervisionRapport>,
    @InjectRepository(RhCongeRequest)
    private congeRepo: Repository<RhCongeRequest>,
    @InjectRepository(RhEvaluation)
    private evalRepo: Repository<RhEvaluation>,
    @InjectRepository(RhEmploye)
    private rhEmployeRepo: Repository<RhEmploye>,
    private readonly dataSource: DataSource,
    private readonly caisseService: CaisseService,
    private readonly notificationService: NotificationService,
  ) {}

  private startEndToday(): { start: Date; end: Date } {
    const t = new Date();
    const start = new Date(t);
    start.setHours(0, 0, 0, 0);
    const end = new Date(t);
    end.setHours(23, 59, 59, 999);
    return { start, end };
  }

  async getKpisConsolides() {
    const { start, end } = this.startEndToday();
    const colisAuj = await this.colisRepo
      .createQueryBuilder('c')
      .where('c.created_at BETWEEN :a AND :b', { a: start, b: end })
      .getCount();
    const nbAgences = await this.agenceRepo.count();
    const paiementRow = await this.paiementRepo
      .createQueryBuilder('p')
      .select('COALESCE(SUM(p.montant::numeric),0)', 's')
      .where('p.etat_validation = 1')
      .andWhere('p.date_paiement BETWEEN :a AND :b', { a: start, b: end })
      .getRawOne();
    const totalEncaissements = Number(paiementRow?.s ?? 0);
    const facturesJour = await this.factureRepo
      .createQueryBuilder('f')
      .where('f.date_facture::date = CURRENT_DATE')
      .andWhere('f.etat != 2')
      .getCount();
    return {
      date: start.toISOString().slice(0, 10),
      colisCrees: colisAuj,
      factures: facturesJour,
      totalEncaissementsValides: totalEncaissements,
      agences: nbAgences,
    };
  }

  async getEtatAgences() {
    const agences = await this.agenceRepo.find({ order: { id: 'ASC' } });
    const { start, end } = this.startEndToday();
    const out: any[] = [];
    for (const a of agences) {
      const colisJ = await this.colisRepo
        .createQueryBuilder('c')
        .where('c.id_agence = :id', { id: a.id })
        .andWhere('c.created_at BETWEEN :a AND :b', { a: start, b: end })
        .getCount();
      const c = await this.caisseRepo.findOne({
        where: { agence: { id: a.id } },
        relations: ['agence'],
      });
      const solde = c ? await this.caisseService.getSoldeReelCourant(c.id) : 0;
      const statut =
        colisJ > 0 ? 'actif' : a.actif ? 'veille' : 'inactif';
      out.push({
        agence: {
          id: a.id,
          code: a.code,
          nom: a.nom,
          ville: a.ville,
          actif: a.actif,
        },
        colis_aujourdhui: colisJ,
        id_caisse: c?.id ?? null,
        solde_caisse: solde,
        statut: statut,
      });
    }
    return out;
  }

  async getDetailAgence(id: number) {
    const a = await this.agenceRepo.findOne({ where: { id } });
    if (!a) return null;
    const c = await this.caisseRepo.findOne({ where: { agence: { id } } });
    const colis7j = await this.colisRepo
      .createQueryBuilder('c')
      .where('c.id_agence = :id', { id })
      .andWhere("c.created_at >= NOW() - INTERVAL '7 days'")
      .getCount();
    const usersC = await this.userRepo
      .createQueryBuilder('u')
      .where('u.id_agence = :id', { id })
      .andWhere('u.actif = :act', { act: true })
      .getCount();
    return {
      agence: a,
      id_caisse: c?.id ?? null,
      solde_caisse: c ? await this.caisseService.getSoldeReelCourant(c.id) : 0,
      colis7j,
      agents_actifs: usersC,
    };
  }

  async getTransactionsAgence(agenceId: number, dateDebut?: string, dateFin?: string) {
    const c = await this.caisseRepo.findOne({ where: { agence: { id: agenceId } } });
    if (!c) return { caisse: null, mouvements: [] };
    const start = dateDebut ? new Date(dateDebut) : this.startEndToday().start;
    const end = dateFin ? new Date(dateFin) : this.startEndToday().end;
    if (dateFin) end.setHours(23, 59, 59, 999);
    if (dateDebut) start.setHours(0, 0, 0, 0);
    const mouvements = await this.mouvementRepo.find({
      where: {
        caisse: { id: c.id },
        date_mouvement: Between(start, end),
      },
      order: { date_mouvement: 'DESC' },
    });
    return { id_caisse: c.id, agenceId, mouvements };
  }

  async getPerformanceAgents() {
    const rows = await this.dataSource.query(
      `
      SELECT a.id as id_agence, a.nom as nom_agence, u.role::text as role_code, COUNT(u.id) as n
      FROM lbp_users u
      INNER JOIN agences a ON a.id = u.id_agence
      WHERE u."isActive" = true
      GROUP BY a.id, a.nom, u.role
      ORDER BY a.nom, u.role
    `,
    );
    return { par_agence_role: rows };
  }

  /**
   * Anomalies / contrôles caisse-factures sur une période (défaut : 7 derniers jours → aujourd'hui).
   */
  async getAnomalies(debut?: string, fin?: string) {
    const endD = fin ? new Date(fin) : new Date();
    endD.setHours(23, 59, 59, 999);
    let startD: Date;
    if (debut) {
      startD = new Date(debut);
      startD.setHours(0, 0, 0, 0);
    } else {
      startD = new Date();
      startD.setDate(startD.getDate() - 7);
      startD.setHours(0, 0, 0, 0);
    }
    let t0 = startD.getTime();
    let t1 = endD.getTime();
    if (t0 > t1) {
      const s = t0;
      t0 = t1;
      t1 = s;
    }
    const dStart = new Date(t0);
    dStart.setHours(0, 0, 0, 0);
    const dEnd = new Date(t1);
    dEnd.setHours(23, 59, 59, 999);
    try {
      return await this.caisseService.detectAnomalies(
        dStart.toISOString().slice(0, 10),
        dEnd.toISOString().slice(0, 10),
      );
    } catch {
      return { message: 'Anomalies non disponibles', donnees: null };
    }
  }

  async getHistoriqueRapports() {
    return this.rapportRepo.find({
      order: { created_at: 'DESC' },
      take: 200,
      relations: ['agence', 'auteur', 'destinataire'],
    });
  }

  async getRapport(id: number) {
    return this.rapportRepo.findOne({
      where: { id },
      relations: ['agence', 'auteur', 'destinataire'],
    });
  }

  async soumettreRapport(dto: SoumettreRapportDto, auteur: { id: number; username: string }) {
    const r = this.rapportRepo.create({
      type: dto.type,
      periode: dto.periode,
      id_agence: dto.agenceId ?? null,
      date_debut: dto.dateDebut,
      date_fin: dto.dateFin,
      commentaire: dto.commentaire ?? null,
      auteur: { id: auteur.id } as User,
      id_auteur: auteur.id,
      statut_lecture: 'non_lu',
    });
    const saved = await this.rapportRepo.save(r);
    void this.notifyDirectionNouveauRapport(saved, auteur).catch((err: unknown) => {
      this.logger.warn(
        `Notification direction (rapport supervision #${saved.id}) : ${err instanceof Error ? err.message : String(err)}`,
      );
    });
    return saved;
  }

  /**
   * Comptes actifs « direction » pour les rapports de supervision : DIRECTEUR et ASSISTANT_DG
   * (colonne enum `role` et/ou `roleEntity.code`).
   */
  private async findDirectionAudienceUserIds(): Promise<number[]> {
    const directionRoles = [UserRole.DIRECTEUR, UserRole.ASSISTANT_DG] as const;
    const byColumn = await this.userRepo.find({
      where: { actif: true, role: In([...directionRoles]) },
      select: ['id'],
    });
    const byEntity = await this.userRepo
      .createQueryBuilder('u')
      .innerJoin('u.roleEntity', 'r')
      .where('u.actif = :a', { a: true })
      .andWhere('r.code IN (:...codes)', {
        codes: ['DIRECTEUR', 'ASSISTANT_DG'],
      })
      .getMany();
    const set = new Set<number>();
    for (const u of byColumn) set.add(u.id);
    for (const u of byEntity) set.add(u.id);
    return [...set];
  }

  private async notifyDirectionNouveauRapport(
    rapport: SupervisionRapport,
    auteur: { id: number; username: string },
  ): Promise<void> {
    const userIds = await this.findDirectionAudienceUserIds();
    if (userIds.length === 0) {
      this.logger.warn(
        'Aucun utilisateur DIRECTEUR ou ASSISTANT_DG actif : notification de rapport de supervision non envoyée.',
      );
      return;
    }
    const debut = rapport.date_debut ?? '—';
    const fin = rapport.date_fin ?? '—';
    const message = [
      `${auteur.username} a soumis un rapport de supervision.`,
      `Type : ${rapport.type} — période libellée : ${rapport.periode}.`,
      `Dates couvertes : ${debut} au ${fin}.`,
    ].join(' ');
    for (const userId of userIds) {
      await this.notificationService.notifyUser(userId, {
        title: 'Nouveau rapport de supervision',
        message,
        type: NotificationType.INFO,
        category: NotificationCategory.SYSTEM,
        action_url: '/#/supervision',
        audit_data: {
          supervisionRapportId: rapport.id,
          auteurId: auteur.id,
          type: rapport.type,
          periode: rapport.periode,
        },
      });
    }
  }

  async signalerAnomalie(dto: SignalementDto, auteur: { id: number }) {
    const s = this.signalementRepo.create({
      type: dto.type,
      description: dto.description,
      gravite: dto.gravite,
      id_auteur: auteur.id,
      auteur: { id: auteur.id } as User,
      agence: dto.agenceId ? ({ id: dto.agenceId } as Agence) : null,
      id_agence: dto.agenceId ?? null,
    });
    return this.signalementRepo.save(s);
  }

  async demanderJustification(
    dto: DemanderJustificationDto,
    demandeur: { id: number },
  ) {
    const d = this.djRepo.create({
      id_demandeur: demandeur.id,
      demandeur: { id: demandeur.id } as User,
      id_destinataire: dto.agentId ?? dto.chefAgenceId ?? null,
      destinataire: dto.agentId
        ? ({ id: dto.agentId } as User)
        : dto.chefAgenceId
          ? ({ id: dto.chefAgenceId } as User)
          : null,
      agence: { id: dto.agenceId } as Agence,
      id_agence: dto.agenceId,
      motif: dto.motif,
      id_operation: dto.operationId ?? null,
    });
    return this.djRepo.save(d);
  }

  async annoter(dto: AnnotationDto, auteur: { id: number }) {
    const a = this.annRepo.create({
      cible: dto.cible,
      cible_id: dto.cibleId,
      contenu: dto.contenu,
      id_auteur: auteur.id,
      auteur: { id: auteur.id } as User,
    });
    return this.annRepo.save(a);
  }

  async getSignalements() {
    return this.signalementRepo.find({
      order: { created_at: "DESC" },
      take: 200,
      relations: ["agence", "auteur"],
    });
  }

  async getJustifications() {
    return this.djRepo.find({
      order: { created_at: "DESC" },
      take: 200,
      relations: ["agence", "demandeur", "destinataire"],
    });
  }

  async getAnnotations() {
    return this.annRepo.find({
      order: { created_at: "DESC" },
      take: 200,
      relations: ["auteur"],
    });
  }

  async getAgentsList(): Promise<
    Array<{ id: number; username: string; nom_complet: string | null; role_code: string; agence_nom: string | null }>
  > {
    return this.dataSource.query(
      `SELECT u.id, u.username, u."fullname" AS nom_complet, u.role::text AS role_code, a.nom AS agence_nom
       FROM lbp_users u
       LEFT JOIN agences a ON a.id = u.id_agence
       WHERE u."isActive" = true
       ORDER BY a.nom NULLS LAST, u."fullname"
       LIMIT 500`,
    );
  }

  /** Périmètre lecture colis côté liste (même filtre qu'un directeur) */
  isSupervisionReadNetwork(user: any): boolean {
    return effectiveRoleCode(user) === UserRole.SUPERVISEURE_GENERALE;
  }

  /**
   * Agents actuellement absents (congés approuvés couvrant la date de référence).
   */
  async getAgentsAbsents(dateRef?: string): Promise<
    Array<{
      id_employe: number;
      matricule: string;
      nom_complet: string;
      agence_nom: string | null;
      date_debut: string;
      date_fin: string;
      nb_jours: number;
      type_conge: string;
    }>
  > {
    const ref = dateRef ? dateRef : new Date().toISOString().slice(0, 10);
    return this.dataSource.query(
      `SELECT
         e.id AS id_employe,
         e.matricule,
         CONCAT(e.nom, ' ', e.prenoms) AS nom_complet,
         a.nom AS agence_nom,
         cr.date_debut,
         cr.date_fin,
         cr.nb_jours,
         ct.libelle AS type_conge
       FROM rh_conge_requests cr
       INNER JOIN rh_employes e ON e.id = cr.id_employe
       LEFT JOIN agences a ON a.id = e.id_agence
       LEFT JOIN rh_conge_types ct ON ct.id = cr.id_conge_type
       WHERE cr.statut = $1
         AND cr.date_debut <= $2
         AND cr.date_fin >= $2
       ORDER BY a.nom NULLS LAST, e.nom`,
      [StatutConge.APPROUVE_RH, ref],
    );
  }

  /**
   * Performance agents enrichie avec données RH : contrat, absences du mois, dernière évaluation.
   */
  async getPerformanceAgentsRh(debut?: string, fin?: string): Promise<{
    par_agence_role: unknown[];
    agents_rh: unknown[];
  }> {
    const base = await this.getPerformanceAgents();

    const moisDebut = debut ?? new Date().toISOString().slice(0, 7) + '-01';
    const moisFin = fin ?? new Date().toISOString().slice(0, 10);

    const agentsRh: unknown[] = await this.dataSource.query(
      `SELECT
         e.id AS id_employe,
         e.matricule,
         CONCAT(e.nom, ' ', e.prenoms) AS nom_complet,
         e.intitule_poste,
         e.type_contrat_actuel,
         a.nom AS agence_nom,
         u.username,
         COALESCE(absences.nb_absences, 0) AS nb_absences_mois,
         ev.note_globale AS derniere_note_eval,
         ev.type AS type_eval,
         ev.created_at AS date_eval
       FROM rh_employes e
       LEFT JOIN agences a ON a.id = e.id_agence
       LEFT JOIN lbp_users u ON u.id = e.id_user
       LEFT JOIN LATERAL (
         SELECT COUNT(*) AS nb_absences
         FROM rh_conge_requests cr
         WHERE cr.id_employe = e.id
           AND cr.statut = $1
           AND cr.date_debut <= $3
           AND cr.date_fin >= $2
       ) absences ON TRUE
       LEFT JOIN LATERAL (
         SELECT note_globale, type, created_at
         FROM rh_evaluations
         WHERE id_employe = e.id
           AND note_globale IS NOT NULL
         ORDER BY created_at DESC
         LIMIT 1
       ) ev ON TRUE
       WHERE e.statut = 'actif'
       ORDER BY a.nom NULLS LAST, e.nom
       LIMIT 500`,
      [StatutConge.APPROUVE_RH, moisDebut, moisFin],
    );

    return { par_agence_role: base.par_agence_role, agents_rh: agentsRh };
  }

  /**
   * Détecte les anomalies et crée automatiquement des signalements pour celles jugées critiques.
   * Évite les doublons sur 24 h.
   */
  async autoSignalerAnomalies(debut?: string, fin?: string, auteurId?: number): Promise<{
    signalements_crees: number;
    anomalies: unknown;
  }> {
    const anomaliesResult = await this.getAnomalies(debut, fin);
    const anomalies = (anomaliesResult as any)?.anomalies;
    if (!anomalies) return { signalements_crees: 0, anomalies: anomaliesResult };

    const depuis24h = new Date(Date.now() - 24 * 3600 * 1000);
    const existants = await this.signalementRepo.find({
      where: { created_at: Between(depuis24h, new Date()) },
      select: ['type'],
    });
    const typesDejaSignales = new Set(existants.map((s) => s.type));

    const toCreate: Array<{ type: string; description: string; gravite: string }> = [];

    const doublons: unknown[] = anomalies.doublons_paiements ?? [];
    if (doublons.length > 0 && !typesDejaSignales.has('doublon_paiement')) {
      toCreate.push({
        type: 'doublon_paiement',
        description: `${doublons.length} doublon(s) de paiement détecté(s) sur la période.`,
        gravite: 'critique',
      });
    }

    const incoherences: unknown[] = anomalies.incoherences_montants_factures ?? [];
    if (incoherences.length > 0 && !typesDejaSignales.has('incoherence_facture')) {
      toCreate.push({
        type: 'incoherence_facture',
        description: `${incoherences.length} incohérence(s) de montant facture détectée(s).`,
        gravite: 'critique',
      });
    }

    const trous: unknown[] = anomalies.trous_sequence_factures ?? [];
    if (trous.length > 0 && !typesDejaSignales.has('trou_sequence_facture')) {
      toCreate.push({
        type: 'trou_sequence_facture',
        description: `${trous.length} série(s) de factures avec des trous de numérotation.`,
        gravite: 'moyen',
      });
    }

    const systemAuteurId = auteurId ?? 1;
    for (const s of toCreate) {
      const signalement = this.signalementRepo.create({
        type: s.type,
        description: s.description,
        gravite: s.gravite,
        id_auteur: systemAuteurId,
        auteur: { id: systemAuteurId } as User,
      });
      await this.signalementRepo.save(signalement);
    }

    return { signalements_crees: toCreate.length, anomalies: anomaliesResult };
  }
}
