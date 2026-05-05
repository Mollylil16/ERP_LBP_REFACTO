import { Injectable, Logger } from '@nestjs/common';
import { Cron, CronExpression } from '@nestjs/schedule';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, MoreThan } from 'typeorm';
import { Caisse } from '../caisse/entities/caisse.entity';
import { Facture } from '../factures/entities/facture.entity';
import { Litige, LitigeStatut } from '../litiges/entities/litige.entity';
import { PointJournalier } from '../exploitation/entities/point-journalier.entity';
import { MouvementCaisse, MouvementType } from '../caisse/entities/mouvement-caisse.entity';
import { Agence } from '../agences/entities/agence.entity';
import { Notification, NotificationCategory, NotificationType } from '../notifications/entities/notification.entity';
import { NotificationService } from '../notifications/notification.service';
import { CaisseService } from '../caisse/caisse.service';

/** Seuil de retrait considéré anormal (env ou 500 000 FCFA par défaut) */
const RETRAIT_ALERTE_SEUIL = () =>
  Number(process.env.CAISSE_RETRAIT_ALERTE_SEUIL || 500_000);

/** Nombre max de retraits par heure avant alerte fréquence */
const RETRAIT_MAX_PAR_HEURE = () =>
  Number(process.env.CAISSE_RETRAIT_MAX_PAR_HEURE || 3);

@Injectable()
export class AlertService {
  private readonly logger = new Logger(AlertService.name);

  constructor(
    @InjectRepository(Caisse)
    private caisseRepository: Repository<Caisse>,
    @InjectRepository(Facture)
    private factureRepository: Repository<Facture>,
    @InjectRepository(Litige)
    private litigeRepository: Repository<Litige>,
    @InjectRepository(PointJournalier)
    private pointJournalierRepository: Repository<PointJournalier>,
    @InjectRepository(MouvementCaisse)
    private mouvementRepository: Repository<MouvementCaisse>,
    @InjectRepository(Agence)
    private agenceRepository: Repository<Agence>,
    @InjectRepository(Notification)
    private notificationRepository: Repository<Notification>,
    private notificationService: NotificationService,
    private caisseService: CaisseService,
  ) {}

  // ─────────────────────────────────────────────────────────────────────────────
  // 1. SOLDE CAISSE FAIBLE — toutes les heures
  // ─────────────────────────────────────────────────────────────────────────────

  @Cron(CronExpression.EVERY_HOUR)
  async checkCaisseBalance() {
    this.logger.log('Vérification des soldes de caisse...');
    try {
      const caisses = await this.caisseRepository.find({ relations: ['agence'] });
      for (const caisse of caisses) {
        const solde = await this.caisseService.getSolde(caisse.id);
        if (solde < Number(caisse.seuil_alerte || 50_000)) {
          await this.notificationService.alertSoldeFaible(caisse, solde);
        }
      }
    } catch (err) {
      this.logger.error('Erreur vérification solde caisse:', err);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────────
  // 2. POINTS JOURNALIERS NON SOUMIS — tous les jours à 18h
  // ─────────────────────────────────────────────────────────────────────────────

  @Cron('0 18 * * 1-6') // Lun→Sam à 18h (pas le dimanche)
  async checkPointsJournaliersNonSoumis() {
    this.logger.log('Vérification des points journaliers non soumis...');
    try {
      const todayStr = new Date().toISOString().split('T')[0];

      // Agences qui ont soumis ou validé leur point aujourd'hui
      const soumisRaw = await this.pointJournalierRepository
        .createQueryBuilder('pj')
        .leftJoin('pj.agence', 'a')
        .select('DISTINCT a.id', 'agenceId')
        .where('pj.date_point = :date', { date: todayStr })
        .andWhere('pj.statut IN (:...statuts)', { statuts: ['SOUMIS', 'VALIDE'] })
        .getRawMany();

      const agencesOk = new Set(soumisRaw.map((r: any) => Number(r.agenceId)));
      const toutesAgences = await this.agenceRepository.find({ where: { actif: true } });
      const agencesEnRetard = toutesAgences.filter((a) => !agencesOk.has(a.id));

      for (const agence of agencesEnRetard) {
        const dedupKey = `POINT_JOURNALIER_RETARD_${todayStr}_${agence.id}`;

        // Éviter de notifier deux fois le même jour pour la même agence
        const existe = await this.notificationRepository
          .createQueryBuilder('n')
          .where(`n.audit_data->>'dedup_key' = :key`, { key: dedupKey })
          .getOne();

        if (existe) continue;

        await this.notificationService.createNotification({
          title: `Point journalier non soumis — ${agence.nom}`,
          message: `L'agence ${agence.nom} n'a pas soumis son point journalier pour le ${todayStr}.`,
          type: NotificationType.WARNING,
          category: NotificationCategory.SYSTEM,
          action_url: '/exploitation/points-journaliers',
          id_agence: agence.id,
          audit_data: { dedup_key: dedupKey, agenceId: agence.id, date: todayStr },
        });

        this.logger.warn(`⏰ Point journalier manquant — ${agence.nom} (${todayStr})`);
      }

      this.logger.log(
        `Points journaliers : ${agencesOk.size}/${toutesAgences.length} soumis, ${agencesEnRetard.length} en retard`,
      );
    } catch (err) {
      this.logger.error('Erreur vérification points journaliers:', err);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────────
  // 3. LITIGES OUVERTS DEPUIS +30 JOURS — tous les jours à 9h
  // ─────────────────────────────────────────────────────────────────────────────

  @Cron('0 9 * * *')
  async checkLitigesAnciens() {
    this.logger.log('Vérification des litiges ouverts depuis +30 jours...');
    try {
      const seuilDate = new Date();
      seuilDate.setDate(seuilDate.getDate() - 30);
      const todayStr = new Date().toISOString().split('T')[0];

      const litiges = await this.litigeRepository
        .createQueryBuilder('l')
        .leftJoinAndSelect('l.agence', 'agence')
        .where('l.statut IN (:...statuts)', {
          statuts: [LitigeStatut.OUVERT, LitigeStatut.EN_COURS],
        })
        .andWhere('l.created_at < :seuil', { seuil: seuilDate })
        .getMany();

      for (const litige of litiges) {
        const joursOuvert = Math.floor(
          (Date.now() - new Date(litige.created_at).getTime()) / (1000 * 60 * 60 * 24),
        );
        const dedupKey = `LITIGE_30J_${litige.id}_${todayStr}`;

        const existe = await this.notificationRepository
          .createQueryBuilder('n')
          .where(`n.audit_data->>'dedup_key' = :key`, { key: dedupKey })
          .getOne();

        if (existe) continue;

        await this.notificationService.createNotification({
          title: `Litige non résolu depuis ${joursOuvert} jours`,
          message: `Le litige ${litige.num_litige} (${litige.objet}) est ouvert depuis ${joursOuvert} jours sans résolution.`,
          type: joursOuvert > 45 ? NotificationType.ERROR : NotificationType.WARNING,
          category: NotificationCategory.SYSTEM,
          action_url: `/litiges/${litige.id}`,
          id_agence: litige.agence?.id ?? null,
          audit_data: {
            dedup_key: dedupKey,
            litigeId: litige.id,
            num_litige: litige.num_litige,
            jours: joursOuvert,
          },
        });

        this.logger.warn(
          `⚠️ Litige ${litige.num_litige} ouvert depuis ${joursOuvert} jours`,
        );
      }

      this.logger.log(`Litiges +30j vérifiés : ${litiges.length} litige(s) concerné(s)`);
    } catch (err) {
      this.logger.error('Erreur vérification litiges anciens:', err);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────────
  // 4. RETRAITS ANORMAUX — toutes les 15 minutes
  // ─────────────────────────────────────────────────────────────────────────────

  @Cron('*/15 * * * *')
  async checkRetraitsAnormaux() {
    try {
      const depuisQuand = new Date();
      depuisQuand.setMinutes(depuisQuand.getMinutes() - 15);
      const seuil = RETRAIT_ALERTE_SEUIL();
      const maxParHeure = RETRAIT_MAX_PAR_HEURE();

      // Retraits > seuil dans les 15 dernières minutes
      const grosRetraits = await this.mouvementRepository
        .createQueryBuilder('m')
        .leftJoinAndSelect('m.caisse', 'c')
        .leftJoinAndSelect('c.agence', 'a')
        .where('m.type = :type', { type: MouvementType.DECAISSEMENT })
        .andWhere('CAST(m.montant AS numeric) >= :seuil', { seuil })
        .andWhere('m.created_at >= :depuis', { depuis: depuisQuand })
        .getMany();

      for (const mv of grosRetraits) {
        const dedupKey = `RETRAIT_ANORMAL_${mv.id}`;
        const existe = await this.notificationRepository
          .createQueryBuilder('n')
          .where(`n.audit_data->>'dedup_key' = :key`, { key: dedupKey })
          .getOne();
        if (existe) continue;

        await this.notificationService.createNotification({
          title: `Retrait important détecté — ${mv.caisse?.nom ?? 'Caisse'}`,
          message: `Un décaissement de ${Number(mv.montant).toLocaleString('fr-FR')} FCFA a été enregistré. Montant supérieur au seuil de ${seuil.toLocaleString('fr-FR')} FCFA.`,
          type: NotificationType.WARNING,
          category: NotificationCategory.CAISSE,
          action_url: '/caisse/suivi',
          id_agence: mv.caisse?.agence?.id ?? null,
          audit_data: {
            dedup_key: dedupKey,
            mouvementId: mv.id,
            montant: Number(mv.montant),
            caisseNom: mv.caisse?.nom,
            seuil,
          },
        });

        this.logger.warn(
          `💸 Retrait anormal : ${Number(mv.montant).toLocaleString()} FCFA sur ${mv.caisse?.nom}`,
        );
      }

      // Vérification fréquence : plus de N retraits dans la dernière heure
      const uneHeureAvant = new Date();
      uneHeureAvant.setHours(uneHeureAvant.getHours() - 1);

      const retraitsParCaisse = await this.mouvementRepository
        .createQueryBuilder('m')
        .leftJoinAndSelect('m.caisse', 'c')
        .leftJoinAndSelect('c.agence', 'a')
        .where('m.type = :type', { type: MouvementType.DECAISSEMENT })
        .andWhere('m.created_at >= :depuis', { depuis: uneHeureAvant })
        .select('c.id', 'caisseId')
        .addSelect('c.nom', 'caisseNom')
        .addSelect('a.id', 'agenceId')
        .addSelect('COUNT(*)', 'nb')
        .groupBy('c.id, c.nom, a.id')
        .having('COUNT(*) > :max', { max: maxParHeure })
        .getRawMany();

      for (const row of retraitsParCaisse) {
        const dedupKey = `RETRAIT_FREQUENCE_${row.caisseId}_${new Date().toISOString().slice(0, 13)}`;
        const existe = await this.notificationRepository
          .createQueryBuilder('n')
          .where(`n.audit_data->>'dedup_key' = :key`, { key: dedupKey })
          .getOne();
        if (existe) continue;

        await this.notificationService.createNotification({
          title: `Fréquence de retraits anormale — ${row.caisseNom}`,
          message: `${row.nb} retraits enregistrés en moins d'une heure sur la caisse ${row.caisseNom}.`,
          type: NotificationType.ERROR,
          category: NotificationCategory.CAISSE,
          action_url: '/caisse/suivi',
          id_agence: row.agenceId ?? null,
          audit_data: {
            dedup_key: dedupKey,
            caisseId: row.caisseId,
            caisseNom: row.caisseNom,
            nb_retraits: Number(row.nb),
          },
        });

        this.logger.error(
          `🚨 Fréquence retraits anormale : ${row.nb} retraits/h sur ${row.caisseNom}`,
        );
      }
    } catch (err) {
      this.logger.error('Erreur vérification retraits anormaux:', err);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────────
  // 5. FACTURES IMPAYÉES > 7 JOURS — tous les jours à 9h
  // ─────────────────────────────────────────────────────────────────────────────

  @Cron('0 9 * * *')
  async checkUnpaidInvoices() {
    this.logger.log('Vérification des factures impayées...');
    try {
      const sevenDaysAgo = new Date();
      sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);

      const unpaidInvoices = await this.factureRepository
        .createQueryBuilder('facture')
        .leftJoinAndSelect('facture.colis', 'colis')
        .leftJoinAndSelect('colis.client', 'client')
        .where('facture.etat = :etat', { etat: 1 })
        .andWhere('facture.montant_paye < facture.montant_ttc')
        .andWhere('facture.date_facture < :date', { date: sevenDaysAgo })
        .getMany();

      for (const facture of unpaidInvoices) {
        const montantRestant = Number(facture.montant_ttc) - Number(facture.montant_paye);
        const joursRetard = Math.floor(
          (Date.now() - new Date(facture.date_facture).getTime()) / (1000 * 60 * 60 * 24),
        );
        this.logger.warn(
          `⚠️ Facture ${facture.num_facture} — Retard: ${joursRetard}j — Reste: ${montantRestant} FCFA`,
        );
      }
    } catch (err) {
      this.logger.error('Erreur vérification factures impayées:', err);
    }
  }
}
