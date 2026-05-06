import { Injectable, Logger } from '@nestjs/common';
import { Cron } from '@nestjs/schedule';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, Between } from 'typeorm';
import { Paiement } from '../paiements/entities/paiement.entity';
import { Colis } from '../colis/entities/colis.entity';
import { Litige, LitigeStatut } from '../litiges/entities/litige.entity';
import { Facture } from '../factures/entities/facture.entity';
import { User, UserRole } from '../users/entities/user.entity';
import { Agence } from '../agences/entities/agence.entity';
import { NotificationService } from '../notifications/notification.service';
import { WhatsappService } from '../notifications/whatsapp.service';
import { CaisseService } from '../caisse/caisse.service';

@Injectable()
export class WeeklyReportService {
  private readonly logger = new Logger(WeeklyReportService.name);

  constructor(
    @InjectRepository(Paiement) private paiementRepo: Repository<Paiement>,
    @InjectRepository(Colis) private colisRepo: Repository<Colis>,
    @InjectRepository(Litige) private litigeRepo: Repository<Litige>,
    @InjectRepository(Facture) private factureRepo: Repository<Facture>,
    @InjectRepository(User) private userRepo: Repository<User>,
    @InjectRepository(Agence) private agenceRepo: Repository<Agence>,
    private notificationService: NotificationService,
    private whatsappService: WhatsappService,
    private caisseService: CaisseService,
  ) {}

  /** Chaque lundi à 8h (heure Abidjan) */
  @Cron('0 8 * * 1', { name: 'weekly-executive-report', timeZone: 'Africa/Abidjan' })
  async sendWeeklyReport() {
    this.logger.log('📊 Génération du rapport hebdomadaire...');
    try {
      const report = await this.generateWeeklyData();
      await this.sendToRecipients(report);
      this.logger.log('✅ Rapport hebdomadaire envoyé');
    } catch (err) {
      this.logger.error('❌ Erreur rapport hebdo:', err);
    }
  }

  /** Expose pour appel manuel (GET /api/dashboard/weekly-report) */
  async generateWeeklyData() {
    const now = new Date();
    const startOfWeek = new Date(now);
    startOfWeek.setDate(now.getDate() - 7);
    startOfWeek.setHours(0, 0, 0, 0);
    const endOfWeek = new Date(now);
    endOfWeek.setHours(23, 59, 59, 999);

    const prevStart = new Date(startOfWeek);
    prevStart.setDate(prevStart.getDate() - 7);
    const prevEnd = new Date(startOfWeek);
    prevEnd.setMilliseconds(-1);

    const [
      caWeekRow, caPrevRow, colisWeek, colisPrev,
      litigesOuverts, litigesResolus,
      facturesImpayeesRow, agencesPerf,
    ] = await Promise.all([
      this.sumPaiements(startOfWeek, endOfWeek),
      this.sumPaiements(prevStart, prevEnd),
      this.colisRepo.count({ where: { created_at: Between(startOfWeek, endOfWeek) } }),
      this.colisRepo.count({ where: { created_at: Between(prevStart, prevEnd) } }),
      this.litigeRepo.count({
        where: { statut: LitigeStatut.OUVERT as any },
      }),
      this.litigeRepo
        .createQueryBuilder('l')
        .where('l.statut = :s', { s: LitigeStatut.RESOLU })
        .andWhere('l.updated_at BETWEEN :a AND :b', { a: startOfWeek, b: endOfWeek })
        .getCount(),
      this.factureRepo
        .createQueryBuilder('f')
        .select('COUNT(*)', 'cnt')
        .addSelect('COALESCE(SUM(f.montant_ttc::numeric - f.montant_paye::numeric), 0)', 'total')
        .where('f.etat = 1')
        .andWhere('f.montant_paye < f.montant_ttc')
        .getRawOne(),
      this.getAgencesPerformance(startOfWeek, endOfWeek),
    ]);

    const caWeek = Number(caWeekRow?.s ?? 0);
    const caPrev = Number(caPrevRow?.s ?? 0);
    const evolution = caPrev > 0 ? ((caWeek - caPrev) / caPrev * 100) : 0;

    return {
      periode: {
        debut: startOfWeek.toISOString().slice(0, 10),
        fin: endOfWeek.toISOString().slice(0, 10),
      },
      ca_semaine: caWeek,
      ca_semaine_prec: caPrev,
      evolution_ca_pct: Math.round(evolution * 10) / 10,
      colis_semaine: colisWeek,
      colis_semaine_prec: colisPrev,
      litiges_ouverts: litigesOuverts,
      litiges_resolus_semaine: litigesResolus,
      factures_impayees: {
        count: Number(facturesImpayeesRow?.cnt ?? 0),
        total: Number(facturesImpayeesRow?.total ?? 0),
      },
      top_agences: agencesPerf.slice(0, 5),
      generated_at: new Date().toISOString(),
    };
  }

  private async sumPaiements(start: Date, end: Date) {
    return this.paiementRepo
      .createQueryBuilder('p')
      .select('COALESCE(SUM(p.montant::numeric), 0)', 's')
      .where('p.etat_validation = 1')
      .andWhere('p.date_paiement BETWEEN :a AND :b', { a: start, b: end })
      .getRawOne();
  }

  private async getAgencesPerformance(start: Date, end: Date) {
    return this.paiementRepo
      .createQueryBuilder('p')
      .leftJoin('p.facture', 'f')
      .leftJoin('f.colis', 'c')
      .leftJoin('c.agence', 'a')
      .select('a.id', 'agenceId')
      .addSelect('a.nom', 'agenceNom')
      .addSelect('COALESCE(SUM(p.montant::numeric), 0)', 'ca')
      .addSelect('COUNT(DISTINCT c.id)', 'nb_colis')
      .where('p.etat_validation = 1')
      .andWhere('p.date_paiement BETWEEN :a AND :b', { a: start, b: end })
      .andWhere('a.id IS NOT NULL')
      .groupBy('a.id, a.nom')
      .orderBy('"ca"', 'DESC')
      .getRawMany();
  }

  private async sendToRecipients(report: any) {
    const recipients = await this.userRepo.find({
      where: [
        { role: UserRole.DIRECTEUR, actif: true },
        { role: UserRole.ASSISTANT_DG, actif: true },
        { role: UserRole.ADMIN, actif: true },
      ],
      relations: ['agence'],
    });

    const arrow = report.evolution_ca_pct >= 0 ? '📈' : '📉';
    const text = [
      `📊 RAPPORT HEBDOMADAIRE LBP`,
      `Période : ${report.periode.debut} → ${report.periode.fin}`,
      ``,
      `💰 CA semaine : ${report.ca_semaine.toLocaleString('fr-FR')} FCFA`,
      `${arrow} Évolution : ${report.evolution_ca_pct > 0 ? '+' : ''}${report.evolution_ca_pct}%`,
      `📦 Colis traités : ${report.colis_semaine} (vs ${report.colis_semaine_prec} sem. préc.)`,
      ``,
      `⚠️ Litiges ouverts : ${report.litiges_ouverts}`,
      `✅ Litiges résolus cette semaine : ${report.litiges_resolus_semaine}`,
      `🧾 Factures impayées : ${report.factures_impayees.count} (${report.factures_impayees.total.toLocaleString('fr-FR')} FCFA)`,
      ``,
      `🏆 Top agences :`,
      ...report.top_agences.map(
        (a: any, i: number) =>
          `  ${i + 1}. ${a.agenceNom} — ${Number(a.ca).toLocaleString('fr-FR')} FCFA (${a.nb_colis} colis)`,
      ),
    ].join('\n');

    for (const user of recipients) {
      // Notification in-app
      await this.notificationService.createInAppNotification(user.id, 'weekly_report', report);
      // WhatsApp
      if (user.phone) {
        await this.whatsappService.sendMessage(user.phone, text).catch(() => null);
      }
      // Email
      await this.notificationService
        .sendEmailNotification(user, `[LBP] Rapport Hebdomadaire — ${report.periode.debut}`, text)
        .catch(() => null);
    }
  }
}
