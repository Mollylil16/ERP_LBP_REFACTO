import { Injectable, Logger } from '@nestjs/common';
import { Cron } from '@nestjs/schedule';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, Between } from 'typeorm';
import { Agence } from '../agences/entities/agence.entity';
import { Paiement } from '../paiements/entities/paiement.entity';
import { Colis } from '../colis/entities/colis.entity';
import { Litige, LitigeStatut } from '../litiges/entities/litige.entity';
import { PointJournalier } from '../exploitation/entities/point-journalier.entity';
import { NotificationService } from '../notifications/notification.service';
import { Notification, NotificationCategory, NotificationType } from '../notifications/entities/notification.entity';

export interface AgenceScore {
  agenceId: number;
  agenceNom: string;
  score_total: number;          // /100
  score_ponctualite: number;    // /25 - points journaliers soumis à temps
  score_encaissement: number;   // /25 - taux factures encaissées
  score_volume: number;         // /25 - volume colis traités vs moyenne réseau
  score_litiges: number;        // /25 - litiges résolus vs ouverts
  details: {
    pj_soumis: number;
    pj_total_jours: number;
    factures_payees: number;
    factures_total: number;
    colis_traites: number;
    colis_moyenne_reseau: number;
    litiges_ouverts: number;
    litiges_resolus: number;
  };
  semaine: string;
}

@Injectable()
export class AgenceScoringService {
  private readonly logger = new Logger(AgenceScoringService.name);

  constructor(
    @InjectRepository(Agence) private agenceRepo: Repository<Agence>,
    @InjectRepository(Paiement) private paiementRepo: Repository<Paiement>,
    @InjectRepository(Colis) private colisRepo: Repository<Colis>,
    @InjectRepository(Litige) private litigeRepo: Repository<Litige>,
    @InjectRepository(PointJournalier) private pjRepo: Repository<PointJournalier>,
    @InjectRepository(Notification) private notifRepo: Repository<Notification>,
    private notificationService: NotificationService,
  ) {}

  /** Chaque dimanche à 23h — calcul du scoring de la semaine écoulée */
  @Cron('0 23 * * 0', { name: 'agency-scoring', timeZone: 'Africa/Abidjan' })
  async computeWeeklyScoring() {
    this.logger.log('🏆 Calcul du scoring hebdomadaire des agences...');
    try {
      const scores = await this.computeAllScores();
      // Persist as notifications for audit
      for (const score of scores) {
        await this.notificationService.createNotification({
          title: `Score agence — ${score.agenceNom} : ${score.score_total}/100`,
          message: `Ponctualité: ${score.score_ponctualite}/25, Encaissement: ${score.score_encaissement}/25, Volume: ${score.score_volume}/25, Litiges: ${score.score_litiges}/25`,
          type: score.score_total >= 70 ? NotificationType.SUCCESS : score.score_total >= 50 ? NotificationType.WARNING : NotificationType.ERROR,
          category: NotificationCategory.SYSTEM,
          action_url: `/supervision`,
          id_agence: score.agenceId,
          audit_data: { type: 'agency_score', ...score },
        });
      }
      this.logger.log(`✅ Scores calculés pour ${scores.length} agences`);
    } catch (err) {
      this.logger.error('❌ Erreur scoring agences:', err);
    }
  }

  /** API publique — peut être appelée depuis le contrôleur dashboard */
  async computeAllScores(weeksAgo = 0): Promise<AgenceScore[]> {
    const now = new Date();
    const endOfWeek = new Date(now);
    endOfWeek.setDate(now.getDate() - (weeksAgo * 7));
    endOfWeek.setHours(23, 59, 59, 999);
    const startOfWeek = new Date(endOfWeek);
    startOfWeek.setDate(endOfWeek.getDate() - 6);
    startOfWeek.setHours(0, 0, 0, 0);

    const semaineLabel = `${startOfWeek.toISOString().slice(0, 10)} → ${endOfWeek.toISOString().slice(0, 10)}`;
    const agences = await this.agenceRepo.find({ where: { actif: true } });

    // Nombre de jours ouvrés dans la semaine (lun-sam = 6)
    const joursOuvres = this.countWeekdays(startOfWeek, endOfWeek);

    // Moyenne réseau colis pour la période
    const totalColisReseau = await this.colisRepo.count({
      where: { created_at: Between(startOfWeek, endOfWeek) },
    });
    const moyenneReseau = agences.length > 0 ? totalColisReseau / agences.length : 0;

    const scores: AgenceScore[] = [];

    for (const agence of agences) {
      // 1. Ponctualité PJ (/25)
      const pjSoumis = await this.pjRepo
        .createQueryBuilder('pj')
        .where('pj.id_agence = :id', { id: agence.id })
        .andWhere('pj.date_point BETWEEN :a AND :b', {
          a: startOfWeek.toISOString().slice(0, 10),
          b: endOfWeek.toISOString().slice(0, 10),
        })
        .andWhere('pj.statut IN (:...s)', { s: ['SOUMIS', 'VALIDE'] })
        .getCount();
      const scorePonctualite = joursOuvres > 0
        ? Math.round((pjSoumis / joursOuvres) * 25)
        : 25;

      // 2. Taux encaissement (/25)
      const facturesRow = await this.paiementRepo
        .createQueryBuilder('p')
        .leftJoin('p.facture', 'f')
        .leftJoin('f.colis', 'c')
        .select('COUNT(DISTINCT f.id)', 'total_factures')
        .addSelect('COUNT(DISTINCT CASE WHEN f.montant_paye >= f.montant_ttc THEN f.id END)', 'factures_payees')
        .where('c.id_agence = :id', { id: agence.id })
        .andWhere('f.date_facture BETWEEN :a AND :b', { a: startOfWeek, b: endOfWeek })
        .getRawOne();
      const totalFactures = Number(facturesRow?.total_factures ?? 0);
      const facturesPayees = Number(facturesRow?.factures_payees ?? 0);
      const scoreEncaissement = totalFactures > 0
        ? Math.round((facturesPayees / totalFactures) * 25)
        : 25;

      // 3. Volume colis (/25)
      const colisTraites = await this.colisRepo.count({
        where: { agence: { id: agence.id }, created_at: Between(startOfWeek, endOfWeek) },
      });
      const scoreVolume = moyenneReseau > 0
        ? Math.min(25, Math.round((colisTraites / moyenneReseau) * 12.5))
        : 25;

      // 4. Litiges (/25)
      const litigesOuverts = await this.litigeRepo.count({
        where: { agence: { id: agence.id }, statut: LitigeStatut.OUVERT as any },
      });
      const litigesResolus = await this.litigeRepo
        .createQueryBuilder('l')
        .where('l.id_agence = :id', { id: agence.id })
        .andWhere('l.statut = :s', { s: LitigeStatut.RESOLU })
        .andWhere('l.updated_at BETWEEN :a AND :b', { a: startOfWeek, b: endOfWeek })
        .getCount();
      const scoreLitiges = litigesOuverts === 0
        ? 25
        : Math.min(25, Math.round((litigesResolus / (litigesOuverts + litigesResolus)) * 25));

      scores.push({
        agenceId: agence.id,
        agenceNom: agence.nom,
        score_total: scorePonctualite + scoreEncaissement + scoreVolume + scoreLitiges,
        score_ponctualite: scorePonctualite,
        score_encaissement: scoreEncaissement,
        score_volume: scoreVolume,
        score_litiges: scoreLitiges,
        details: {
          pj_soumis: pjSoumis,
          pj_total_jours: joursOuvres,
          factures_payees: facturesPayees,
          factures_total: totalFactures,
          colis_traites: colisTraites,
          colis_moyenne_reseau: Math.round(moyenneReseau),
          litiges_ouverts: litigesOuverts,
          litiges_resolus: litigesResolus,
        },
        semaine: semaineLabel,
      });
    }

    return scores.sort((a, b) => b.score_total - a.score_total);
  }

  private countWeekdays(start: Date, end: Date): number {
    let count = 0;
    const d = new Date(start);
    while (d <= end) {
      const day = d.getDay();
      if (day >= 1 && day <= 6) count++; // Lun-Sam
      d.setDate(d.getDate() + 1);
    }
    return count;
  }
}
