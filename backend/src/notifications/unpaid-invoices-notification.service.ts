import { Injectable, Logger } from '@nestjs/common';
import { Cron, CronExpression } from '@nestjs/schedule';
import { PaiementsService } from '../paiements/paiements.service';
import { NotificationService } from './notification.service';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { User, UserRole } from '../users/entities/user.entity';

const UNPAID_INVOICE_NOTIFY_ROLES: UserRole[] = [
  UserRole.CAISSIER,
  UserRole.MANAGER,
  UserRole.ADMIN,
];

@Injectable()
export class UnpaidInvoicesNotificationService {
  private readonly logger = new Logger(UnpaidInvoicesNotificationService.name);

  constructor(
    private readonly paiementsService: PaiementsService,
    private readonly notificationService: NotificationService,
    @InjectRepository(User)
    private readonly userRepository: Repository<User>,
  ) {}

  /**
   * Daily reminder at 9:00 AM - Check unpaid invoices and send notifications
   */
  @Cron('0 9 * * *', {
    name: 'unpaid-invoices-morning-reminder',
    timeZone: 'Africa/Abidjan',
  })
  async sendMorningReminder() {
    this.logger.log('🔔 Running morning unpaid invoices reminder...');
    await this.checkAndNotifyUnpaidInvoices('morning');
  }

  /**
   * Daily reminder at 5:00 PM - End of day reminder
   */
  @Cron('0 17 * * *', {
    name: 'unpaid-invoices-evening-reminder',
    timeZone: 'Africa/Abidjan',
  })
  async sendEveningReminder() {
    this.logger.log('🔔 Running evening unpaid invoices reminder...');
    await this.checkAndNotifyUnpaidInvoices('evening');
  }

  /**
   * Check unpaid invoices and send notifications to cashiers and managers
   */
  private async checkAndNotifyUnpaidInvoices(period: 'morning' | 'evening') {
    try {
      // Get all unpaid invoices
      const unpaidInvoices = await this.paiementsService.getUnpaidInvoices();
      const overdueInvoices = await this.paiementsService.getOverdueInvoices();

      if (unpaidInvoices.length === 0) {
        this.logger.log('✅ No unpaid invoices found');
        return;
      }

      // Get cashiers and managers
      const recipients = await this.userRepository
        .createQueryBuilder('user')
        .where('user.role IN (:...roles)', {
          roles: UNPAID_INVOICE_NOTIFY_ROLES,
        })
        .andWhere('user.actif = true')
        .getMany();

      if (recipients.length === 0) {
        this.logger.warn('⚠️ No cashiers or managers found to notify');
        return;
      }

      this.logger.log(`📧 Sending notifications to ${recipients.length} users`);

      // Calculate totals
      const totalUnpaid = unpaidInvoices.reduce(
        (sum, f) => sum + (Number(f.montant_ttc) - Number(f.montant_paye)),
        0,
      );

      // Send notifications
      for (const user of recipients) {
        // In-app notification
        await this.notificationService.createInAppNotification(
          user.id,
          'unpaid_invoices',
          {
            period,
            count: unpaidInvoices.length,
            overdueCount: overdueInvoices.length,
            totalAmount: totalUnpaid,
            invoices: unpaidInvoices.slice(0, 10), // Limit to 10 for notification
          },
        );

        // Email notification
        const subject =
          period === 'morning'
            ? `[LBP] Rappel matinal - ${unpaidInvoices.length} facture(s) impayée(s)`
            : `[LBP] Rappel fin de journée - ${unpaidInvoices.length} facture(s) impayée(s)`;

        const emailBody = this.buildEmailBody(
          unpaidInvoices,
          overdueInvoices,
          totalUnpaid,
          period,
        );

        await this.notificationService.sendEmailNotification(
          user,
          subject,
          emailBody,
        );
      }

      this.logger.log(
        `✅ Notifications sent successfully for ${unpaidInvoices.length} unpaid invoices`,
      );
    } catch (error) {
      this.logger.error(
        `❌ Error sending unpaid invoices notifications: ${error.message}`,
        error.stack,
      );
    }
  }

  /**
   * Send immediate notification for new unpaid invoice
   */
  async notifyNewUnpaidInvoice(factureId: number) {
    try {
      const facture = await this.paiementsService['factureRepository'].findOne({
        where: { id: factureId },
        relations: ['colis', 'colis.client', 'colis.agence'],
      });

      if (!facture) {
        this.logger.warn(`⚠️ Facture #${factureId} not found`);
        return;
      }

      // Get cashiers and managers
      const recipients = await this.userRepository
        .createQueryBuilder('user')
        .where('user.role IN (:...roles)', {
          roles: UNPAID_INVOICE_NOTIFY_ROLES,
        })
        .andWhere('user.actif = true')
        .getMany();

      const montantRestant =
        Number(facture.montant_ttc) - Number(facture.montant_paye);

      for (const user of recipients) {
        await this.notificationService.createInAppNotification(
          user.id,
          'new_unpaid_invoice',
          {
            factureNum: facture.num_facture,
            clientNom: facture.colis.client.nom_exp,
            montantRestant,
            agence: facture.colis.agence?.nom,
          },
        );
      }

      this.logger.log(
        `✅ New unpaid invoice notification sent for ${facture.num_facture}`,
      );
    } catch (error) {
      this.logger.error(
        `❌ Error notifying new unpaid invoice: ${error.message}`,
        error.stack,
      );
    }
  }

  /**
   * Build email body for unpaid invoices notification
   */
  private buildEmailBody(
    unpaidInvoices: any[],
    overdueInvoices: any[],
    totalAmount: number,
    period: string,
  ): string {
    const greeting = period === 'morning' ? 'Bonjour' : 'Bonsoir';
    const periodText = period === 'morning' ? 'ce matin' : 'en fin de journée';

    let body = `${greeting},\n\n`;
    body += `Voici le récapitulatif ${periodText} des factures impayées :\n\n`;
    body += `📊 RÉSUMÉ :\n`;
    body += `- Total factures impayées : ${unpaidInvoices.length}\n`;
    body += `- Factures en retard (>30 jours) : ${overdueInvoices.length}\n`;
    body += `- Montant total impayé : ${totalAmount.toLocaleString('fr-FR')} FCFA\n\n`;

    if (overdueInvoices.length > 0) {
      body += `⚠️ FACTURES EN RETARD :\n`;
      overdueInvoices.slice(0, 5).forEach((f) => {
        body += `- ${f.num_facture} | Client: ${f.colis?.client?.nom_exp || 'N/A'} | `;
        body += `Montant restant: ${(Number(f.montant_ttc) - Number(f.montant_paye)).toLocaleString('fr-FR')} FCFA | `;
        body += `${f.joursDepuisCreation} jours\n`;
      });
      body += `\n`;
    }

    body += `📋 DERNIÈRES FACTURES IMPAYÉES :\n`;
    unpaidInvoices.slice(0, 10).forEach((f) => {
      body += `- ${f.num_facture} | Client: ${f.colis?.client?.nom_exp || 'N/A'} | `;
      body += `Montant restant: ${(Number(f.montant_ttc) - Number(f.montant_paye)).toLocaleString('fr-FR')} FCFA\n`;
    });

    body += `\n\nConnectez-vous au système LBP pour plus de détails.\n\n`;
    body += `Cordialement,\nLBP Logistics`;

    return body;
  }
}
