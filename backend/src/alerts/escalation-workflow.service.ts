import { Injectable, Logger } from '@nestjs/common';
import { Cron } from '@nestjs/schedule';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, LessThan } from 'typeorm';
import { Litige, LitigeStatut } from '../litiges/entities/litige.entity';
import { Notification, NotificationCategory, NotificationType } from '../notifications/entities/notification.entity';
import { NotificationService } from '../notifications/notification.service';
import { User, UserRole } from '../users/entities/user.entity';

/**
 * Workflow d'escalade automatique :
 * - Si un litige ou une anomalie n'est pas traité en 24h → notification superviseur régional
 * - Si toujours pas traité en 48h → notification DG
 * - Si toujours pas traité en 72h → notification critique ERROR
 */
@Injectable()
export class EscalationWorkflowService {
  private readonly logger = new Logger(EscalationWorkflowService.name);

  constructor(
    @InjectRepository(Litige) private litigeRepo: Repository<Litige>,
    @InjectRepository(User) private userRepo: Repository<User>,
    @InjectRepository(Notification) private notifRepo: Repository<Notification>,
    private notificationService: NotificationService,
  ) {}

  /** Toutes les 2 heures pendant les heures ouvrables (8h-18h, lun-sam) */
  @Cron('0 8-18/2 * * 1-6', { name: 'escalation-workflow', timeZone: 'Africa/Abidjan' })
  async checkEscalations() {
    this.logger.log('🔄 Vérification des escalades...');
    try {
      await this.escalateLitiges();
      this.logger.log('✅ Vérification des escalades terminée');
    } catch (err) {
      this.logger.error('❌ Erreur workflow escalade:', err);
    }
  }

  private async escalateLitiges() {
    const now = new Date();
    const h24 = new Date(now); h24.setHours(h24.getHours() - 24);
    const h48 = new Date(now); h48.setHours(h48.getHours() - 48);
    const h72 = new Date(now); h72.setHours(h72.getHours() - 72);
    const todayStr = now.toISOString().slice(0, 10);

    // Litiges ouverts classés par ancienneté
    const litiges = await this.litigeRepo.find({
      where: [
        { statut: LitigeStatut.OUVERT },
        { statut: LitigeStatut.EN_COURS },
      ],
      relations: ['agence'],
      order: { created_at: 'ASC' },
    });

    for (const litige of litiges) {
      const age = now.getTime() - new Date(litige.created_at).getTime();
      const ageHours = age / (1000 * 60 * 60);

      let level: 'L1' | 'L2' | 'L3' | null = null;
      let targetRoles: UserRole[] = [];
      let notifType = NotificationType.WARNING;

      if (ageHours >= 72) {
        level = 'L3';
        targetRoles = [UserRole.DIRECTEUR, UserRole.ADMIN];
        notifType = NotificationType.ERROR;
      } else if (ageHours >= 48) {
        level = 'L2';
        targetRoles = [UserRole.DIRECTEUR, UserRole.ASSISTANT_DG];
        notifType = NotificationType.WARNING;
      } else if (ageHours >= 24) {
        level = 'L1';
        targetRoles = [UserRole.SUPERVISEUR_REGIONAL, UserRole.SUPERVISEURE_GENERALE];
        notifType = NotificationType.WARNING;
      }

      if (!level) continue;

      const dedupKey = `ESCALADE_${level}_${litige.id}_${todayStr}`;
      const existe = await this.notifRepo
        .createQueryBuilder('n')
        .where(`n.audit_data->>'dedup_key' = :key`, { key: dedupKey })
        .getOne();
      if (existe) continue;

      const joursOuvert = Math.floor(ageHours / 24);
      const levelLabel = level === 'L1' ? 'Superviseur' : level === 'L2' ? 'Direction' : 'CRITIQUE';

      await this.notificationService.createNotification({
        title: `⬆️ Escalade ${levelLabel} — Litige ${litige.num_litige}`,
        message: `Le litige "${litige.objet}" (${litige.agence?.nom ?? 'N/A'}) est ouvert depuis ${joursOuvert} jours sans résolution. Escalade niveau ${level}.`,
        type: notifType,
        category: NotificationCategory.SYSTEM,
        action_url: `/litiges/${litige.id}`,
        id_agence: litige.agence?.id ?? null,
        audit_data: {
          dedup_key: dedupKey,
          litigeId: litige.id,
          num_litige: litige.num_litige,
          level,
          age_hours: Math.round(ageHours),
        },
      });

      // Notifier les utilisateurs cibles directement
      const users = await this.userRepo
        .createQueryBuilder('u')
        .where('u.role IN (:...roles)', { roles: targetRoles })
        .andWhere('u.actif = true')
        .getMany();

      for (const user of users) {
        await this.notificationService.createInAppNotification(
          user.id,
          `escalation_${level.toLowerCase()}`,
          {
            litigeId: litige.id,
            num_litige: litige.num_litige,
            objet: litige.objet,
            agence: litige.agence?.nom,
            age_hours: Math.round(ageHours),
            level,
          },
        );
      }

      this.logger.warn(
        `⬆️ Escalade ${level} — Litige ${litige.num_litige} (${joursOuvert}j)`,
      );
    }
  }
}
