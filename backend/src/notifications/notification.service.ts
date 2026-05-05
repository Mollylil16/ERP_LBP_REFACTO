import { Injectable, Logger } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import {
  Notification,
  NotificationType,
  NotificationCategory,
} from './entities/notification.entity';
import { MessagingGatewayService } from './messaging-gateway.service';

export interface EmailOptions {
  to: string;
  subject: string;
  template?: string;
  data?: any;
  html?: string;
  text?: string;
}

export interface SMSOptions {
  to: string;
  message: string;
}

@Injectable()
export class NotificationService {
  private readonly logger = new Logger(NotificationService.name);

  constructor(
    @InjectRepository(Notification)
    private notificationRepository: Repository<Notification>,
    private readonly messagingGateway: MessagingGatewayService,
  ) {}

  /**
   * Créer une notification persistante en base de données
   */
  async createNotification(data: Partial<Notification>): Promise<Notification> {
    const notification = this.notificationRepository.create(data);
    return await this.notificationRepository.save(notification);
  }

  /**
   * Notifications non lues filtrées par utilisateur ET agence.
   * - Notifs destinées spécifiquement à cet utilisateur (user_id = userId)
   * - Notifs globales sans destinataire ni agence (user_id IS NULL AND id_agence IS NULL)
   * - Notifs globales pour l'agence de l'utilisateur (user_id IS NULL AND id_agence = user.id_agence)
   */
  async getUnreadForUser(userId: number, idAgence?: number | null): Promise<Notification[]> {
    const qb = this.notificationRepository
      .createQueryBuilder('n')
      .where('n.read = :read', { read: false });

    if (idAgence) {
      qb.andWhere(
        '(n.user_id = :userId OR (n.user_id IS NULL AND (n.id_agence IS NULL OR n.id_agence = :agenceId)))',
        { userId, agenceId: idAgence },
      );
    } else {
      qb.andWhere('(n.user_id = :userId OR n.user_id IS NULL)', { userId });
    }

    return qb.orderBy('n.created_at', 'DESC').getMany();
  }

  /**
   * @deprecated Préférer getUnreadForUser — conservé pour scripts/tests.
   */
  async getUnread(): Promise<Notification[]> {
    return await this.notificationRepository.find({
      where: { read: false },
      order: { created_at: 'DESC' },
    });
  }

  /**
   * Marquer comme lu (uniquement si la notification est destinée à cet utilisateur ou globale).
   */
  async markAsReadForUser(id: number, userId: number): Promise<void> {
    await this.notificationRepository
      .createQueryBuilder()
      .update(Notification)
      .set({ read: true })
      .where('id = :id', { id })
      .andWhere('read = false')
      .andWhere('(user_id = :userId OR user_id IS NULL)', { userId })
      .execute();
  }

  /**
   * Marquer comme lu (sans contrôle destinataire — usage interne / admin).
   */
  async markAsRead(id: number): Promise<void> {
    await this.notificationRepository.update(id, { read: true });
  }

  /**
   * Marque comme lues uniquement les notifications personnelles (user_id = userId).
   */
  async markAllAsReadForUser(userId: number): Promise<void> {
    await this.notificationRepository
      .createQueryBuilder()
      .update(Notification)
      .set({ read: true })
      .where('read = false')
      .andWhere('user_id = :userId', { userId })
      .execute();
  }

  /**
   * @deprecated Préférer markAllAsReadForUser.
   */
  async markAllAsRead(): Promise<void> {
    await this.notificationRepository.update({ read: false }, { read: true });
  }

  /**
   * Envoyer un email
   */
  async sendEmail(options: EmailOptions): Promise<void> {
    this.logger.log(`📧 Email envoyé à ${options.to}: ${options.subject}`);
    // Logique réelle d'envoi à implémenter si besoin
  }

  /**
   * Envoyer un SMS
   */
  async sendSMS(options: SMSOptions): Promise<void> {
    const ok = await this.messagingGateway.sendSms(options.to, options.message);
    if (!ok) {
      this.logger.warn(`📱 Échec envoi SMS vers ${options.to}`);
    }
  }

  /**
   * Notification lors de la création d'un colis
   */
  async onColisCreated(colis: any): Promise<void> {
    const client = colis.client;
    if (client.email_exp) {
      await this.sendEmail({
        to: client.email_exp,
        subject: `Colis ${colis.ref_colis} enregistré`,
        template: 'colis_created',
        data: {
          clientName: client.nom_exp,
          refColis: colis.ref_colis,
          dateEnvoi: colis.date_envoi,
          destination: colis.nom_dest,
        },
      });
    }
  }

  /**
   * Alerte solde caisse faible (Détaillée pour le manager)
   */
  async alertSoldeFaible(caisse: any, solde: number): Promise<void> {
    this.logger.warn(`⚠️ Solde caisse ${caisse.nom} faible: ${solde} FCFA`);

    const problem = `Le solde de la caisse "${caisse.nom}" est descendu à ${Number(solde).toLocaleString()} FCFA, ce qui est inférieur au seuil de sécurité de ${Number(caisse.seuil_alerte).toLocaleString()} FCFA.`;
    const solution = `1. Vérifiez s'il y a eu des retraits exceptionnels aujourd'hui.\n2. Effectuez un approvisionnement de fonds pour assurer la continuité du service.\n3. Si les retraits dépassent les recettes habituelles, un audit pourrait être nécessaire.`;

    await this.createNotification({
      title: `Alerte Solde Faible : ${caisse.nom}`,
      message: `Attention, le solde actuel est de ${Number(solde).toLocaleString()} FCFA.`,
      problem,
      solution,
      type: NotificationType.WARNING,
      category: NotificationCategory.CAISSE,
      action_url: `/caisse/suivi`,
      audit_data: {
        caisseId: caisse.id,
        soldeActual: solde,
        threshold: caisse.seuil_alerte,
        timestamp: new Date(),
      },
    });
  }

  /**
   * Notification lors de la génération d'une facture
   */
  async onFactureGenerated(facture: any): Promise<void> {
    this.logger.log(`📄 Facture ${facture.num_facture} générée`);
    // Logique d'envoi d'email ou SMS si nécessaire
  }

  /**
   * Send email notification to user
   */
  async sendEmailNotification(
    user: any,
    subject: string,
    body: string,
  ): Promise<void> {
    if (!user.email) {
      this.logger.warn(`User ${user.nom} has no email address`);
      return;
    }

    await this.sendEmail({
      to: user.email,
      subject,
      text: body,
    });

    this.logger.log(`📧 Email sent to ${user.email}: ${subject}`);
  }

  /**
   * Create in-app notification for user
   */
  async createInAppNotification(
    userId: number,
    type: string,
    data: any,
  ): Promise<Notification> {
    let title = '';
    let message = '';
    let notificationType = NotificationType.INFO;
    let category = NotificationCategory.SYSTEM;

    switch (type) {
      case 'unpaid_invoices':
        title = `Factures impayées - Rappel ${data.period === 'morning' ? 'matinal' : 'de fin de journée'}`;
        message = `${data.count} facture(s) impayée(s) dont ${data.overdueCount} en retard. Montant total: ${data.totalAmount.toLocaleString('fr-FR')} FCFA`;
        notificationType =
          data.overdueCount > 0
            ? NotificationType.WARNING
            : NotificationType.INFO;
        category = NotificationCategory.PAIEMENT;
        break;

      case 'new_unpaid_invoice':
        title = 'Nouvelle facture impayée';
        message = `Facture ${data.factureNum} - Client: ${data.clientNom} - Montant restant: ${data.montantRestant.toLocaleString('fr-FR')} FCFA`;
        notificationType = NotificationType.INFO;
        category = NotificationCategory.PAIEMENT;
        break;

      default:
        title = 'Notification';
        message = JSON.stringify(data);
    }

    return await this.createNotification({
      title,
      message,
      type: notificationType,
      category,
      action_url: '/paiements/history',
      audit_data: data,
      user: { id: userId } as any,
    });
  }

  /**
   * Notification in-app libre (titre/message/URL) pour un utilisateur précis.
   */
  async notifyUser(
    userId: number,
    payload: {
      title: string;
      message: string;
      type?: NotificationType;
      category?: NotificationCategory;
      action_url?: string;
      audit_data?: Record<string, unknown>;
    },
  ): Promise<Notification> {
    return await this.createNotification({
      title: payload.title,
      message: payload.message,
      type: payload.type ?? NotificationType.INFO,
      category: payload.category ?? NotificationCategory.SYSTEM,
      action_url: payload.action_url,
      audit_data: payload.audit_data,
      user: { id: userId } as any,
    });
  }
}
