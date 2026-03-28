import { Injectable, Logger } from '@nestjs/common';
import { Cron, CronExpression } from '@nestjs/schedule';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, LessThan } from 'typeorm';
import { Caisse } from '../caisse/entities/caisse.entity';
import { Facture } from '../factures/entities/facture.entity';
import { NotificationService } from '../notifications/notification.service';
import { CaisseService } from '../caisse/caisse.service';

/**
 * Service pour gérer les alertes automatiques du système
 */
@Injectable()
export class AlertService {
  private readonly logger = new Logger(AlertService.name);

  constructor(
    @InjectRepository(Caisse)
    private caisseRepository: Repository<Caisse>,
    @InjectRepository(Facture)
    private factureRepository: Repository<Facture>,
    private notificationService: NotificationService,
    private caisseService: CaisseService,
  ) {}

  /**
   * Vérifier le solde de caisse toutes les heures
   * Alerte si solde < seuil minimum (ex: 50 000 FCFA)
   */
  @Cron(CronExpression.EVERY_HOUR)
  async checkCaisseBalance() {
    this.logger.log('Vérification des soldes de caisse...');

    try {
      const caisses = await this.caisseRepository.find();

      for (const caisse of caisses) {
        const solde = await this.caisseService.getSolde(caisse.id);
        const seuil = caisse.seuil_alerte || 50000;

        if (solde < seuil) {
          this.logger.warn(
            `⚠️ ALERTE: Solde caisse faible - ${caisse.nom}: ${solde} FCFA (Seuil: ${seuil} FCFA)`,
          );

          // Envoyer notification persistante
          await this.notificationService.alertSoldeFaible(caisse, solde);
        }
      }

      if (caisses.length === 0) {
        this.logger.log(
          "✓ Aucun solde de caisse n'a été vérifié (aucune caisse trouvée)",
        );
      }
    } catch (error) {
      this.logger.error('Erreur vérification solde caisse:', error);
    }
  }

  /**
   * Vérifier les factures impayées tous les jours à 9h
   * Alerte pour factures > 7 jours
   */
  @Cron('0 9 * * *') // Tous les jours à 9h
  async checkUnpaidInvoices() {
    this.logger.log('Vérification des factures impayées...');

    try {
      const sevenDaysAgo = new Date();
      sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);

      const unpaidInvoices = await this.factureRepository
        .createQueryBuilder('facture')
        .leftJoinAndSelect('facture.colis', 'colis')
        .leftJoinAndSelect('colis.client', 'client')
        .where('facture.etat = :etat', { etat: 1 }) // Définitive
        .andWhere('facture.montant_paye < facture.montant_ttc')
        .andWhere('facture.date_facture < :date', { date: sevenDaysAgo })
        .getMany();

      this.logger.log(
        `Trouvé ${unpaidInvoices.length} facture(s) impayée(s) > 7 jours`,
      );

      for (const facture of unpaidInvoices) {
        const montantRestant =
          Number(facture.montant_ttc) - Number(facture.montant_paye);
        const joursRetard = Math.floor(
          (Date.now() - new Date(facture.date_facture).getTime()) /
            (1000 * 60 * 60 * 24),
        );

        this.logger.warn(
          `⚠️ Facture ${facture.num_facture} - Client: ${facture.colis?.client?.nom_exp} - Retard: ${joursRetard}j - Reste: ${montantRestant} FCFA`,
        );

        // Envoyer relance client
        await this.sendUnpaidInvoiceAlert(facture, joursRetard, montantRestant);
      }
    } catch (error) {
      this.logger.error('Erreur vérification factures impayées:', error);
    }
  }

  /**
   * Envoyer alerte solde caisse faible
   */
  private async sendLowBalanceAlert(caisse: Caisse, solde: number) {
    try {
      // TODO: Implémenter envoi email aux admins
      // Pour l'instant, juste logger
      this.logger.warn(
        `📧 Notification envoyée: Solde caisse ${caisse.nom} faible (${solde} FCFA)`,
      );

      // Exemple d'intégration future
      // await this.notificationService.sendEmail({
      //     to: 'admin@lbp.com',
      //     subject: 'Alerte: Solde de caisse faible',
      //     body: `Le solde de la caisse ${caisse.agence?.nom_agence} est de ${caisse.solde} FCFA`
      // });
    } catch (error) {
      this.logger.error('Erreur envoi alerte solde:', error);
    }
  }

  /**
   * Envoyer alerte facture impayée
   */
  private async sendUnpaidInvoiceAlert(
    facture: Facture,
    joursRetard: number,
    montantRestant: number,
  ) {
    try {
      const client = facture.colis?.client;
      if (!client) return;

      this.logger.log(
        `📧 Relance client ${client.nom_exp} pour facture ${facture.num_facture}`,
      );

      // TODO: Implémenter envoi SMS/Email au client
      // await this.notificationService.sendSMS({
      //     to: client.tel_exp,
      //     message: `Rappel: Facture ${facture.num_facture} impayée depuis ${joursRetard} jours. Montant restant: ${montantRestant} FCFA`
      // });
    } catch (error) {
      this.logger.error('Erreur envoi relance facture:', error);
    }
  }

  /**
   * Rapport hebdomadaire - Tous les lundis à 8h
   */
  @Cron('0 8 * * 1') // Lundi 8h
  async sendWeeklyReport() {
    this.logger.log('📊 Génération rapport hebdomadaire...');

    try {
      // Statistiques de la semaine
      const stats = await this.getWeeklyStats();

      this.logger.log(
        `Rapport: ${stats.nouveauxColis} colis, ${stats.facturesGenerees} factures, ${stats.paiementsRecus} paiements`,
      );

      // TODO: Envoyer rapport par email
    } catch (error) {
      this.logger.error('Erreur génération rapport hebdomadaire:', error);
    }
  }

  /**
   * Récupérer statistiques hebdomadaires
   */
  private async getWeeklyStats() {
    const oneWeekAgo = new Date();
    oneWeekAgo.setDate(oneWeekAgo.getDate() - 7);

    // TODO: Implémenter requêtes pour stats réelles
    return {
      nouveauxColis: 0,
      facturesGenerees: 0,
      paiementsRecus: 0,
    };
  }
}
