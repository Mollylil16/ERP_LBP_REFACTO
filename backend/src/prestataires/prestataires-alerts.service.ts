import { Injectable, Logger } from '@nestjs/common';
import { Cron } from '@nestjs/schedule';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { FacturePrestataire } from './entities/facture-prestataire.entity';
import { NotificationService } from '../notifications/notification.service';
import {
  NotificationCategory,
  NotificationType,
} from '../notifications/entities/notification.entity';
import { User } from '../users/entities/user.entity';

const DEFAULT_ALERT_DAYS = [7, 3, 1] as const;

function todayISO(): string {
  return new Date().toISOString().slice(0, 10);
}

function addDaysISO(dateISO: string, days: number): string {
  const d = new Date(dateISO);
  if (Number.isNaN(d.getTime())) return dateISO;
  d.setDate(d.getDate() + days);
  return d.toISOString().slice(0, 10);
}

@Injectable()
export class PrestatairesAlertsService {
  private readonly logger = new Logger(PrestatairesAlertsService.name);

  constructor(
    @InjectRepository(FacturePrestataire)
    private readonly factureRepo: Repository<FacturePrestataire>,
    @InjectRepository(User)
    private readonly userRepo: Repository<User>,
    private readonly notificationService: NotificationService,
  ) {}

  /**
   * Tous les jours à 8h (Abidjan) : alerte échéances prestataires.
   * - J-7 / J-3 / J-1
   * - En retard
   */
  @Cron('0 8 * * *', {
    name: 'prestataires-echeances-reminder',
    timeZone: 'Africa/Abidjan',
  })
  async runDaily() {
    try {
      const t = todayISO();
      const targetDates = DEFAULT_ALERT_DAYS.map((d) => addDaysISO(t, d));

      const dueSoon = await this.factureRepo
        .createQueryBuilder('f')
        .leftJoinAndSelect('f.agence', 'agence')
        .leftJoinAndSelect('f.prestataire', 'prestataire')
        .where('f.reliquat > 0')
        .andWhere('f.statut != :ann', { ann: 'ANNULEE' })
        .andWhere('f.date_echeance IN (:...ds)', { ds: targetDates })
        .orderBy('f.date_echeance', 'ASC')
        .getMany();

      const overdue = await this.factureRepo
        .createQueryBuilder('f')
        .leftJoinAndSelect('f.agence', 'agence')
        .leftJoinAndSelect('f.prestataire', 'prestataire')
        .where('f.reliquat > 0')
        .andWhere('f.statut != :ann', { ann: 'ANNULEE' })
        .andWhere('f.date_echeance < :t', { t })
        .orderBy('f.date_echeance', 'ASC')
        .getMany();

      if (dueSoon.length === 0 && overdue.length === 0) {
        this.logger.log('✅ Aucune échéance prestataire à notifier');
        return;
      }

      // Destinataires: superviseur régional + directeur + caissier (vision + préparation)
      const recipients = await this.userRepo.find({
        where: { actif: true } as any,
        relations: ['roleEntity', 'agence'],
      });

      const wanted = new Set(['SUPERVISEUR_REGIONAL', 'DIRECTEUR', 'CAISSIER']);
      const targets = recipients.filter((u: any) =>
        wanted.has(String(u.roleEntity?.code || u.role || '').toUpperCase()),
      );

      const formatLine = (f: any) => {
        const ag = f.agence?.nom ?? 'Agence';
        const pr = f.prestataire?.nom ?? 'Prestataire';
        const nf = f.numero_facture ?? '';
        const ech = f.date_echeance ?? '';
        const rel = Number(f.reliquat ?? 0).toLocaleString('fr-FR');
        const dev = f.devise ?? 'XOF';
        return `- ${ag} | ${pr} | Facture ${nf} | Échéance ${ech} | Reliquat ${rel} ${dev}`;
      };

      const linesSoon = dueSoon.slice(0, 15).map(formatLine).join('\n');
      const linesOverdue = overdue.slice(0, 15).map(formatLine).join('\n');

      const title =
        overdue.length > 0
          ? `Prestataires: ${overdue.length} facture(s) en retard`
          : `Prestataires: échéances à venir (${dueSoon.length})`;

      const messageParts: string[] = [];
      if (dueSoon.length > 0) {
        messageParts.push(
          `Échéances proches (J-7/J-3/J-1): ${dueSoon.length}\n${linesSoon}`,
        );
      }
      if (overdue.length > 0) {
        messageParts.push(`En retard: ${overdue.length}\n${linesOverdue}`);
      }

      const message = messageParts.join('\n\n');

      for (const u of targets) {
        await this.notificationService.notifyUser(u.id, {
          title,
          message,
          type: overdue.length > 0 ? NotificationType.WARNING : NotificationType.INFO,
          category: NotificationCategory.SYSTEM,
          action_url: '/prestataires/factures',
          audit_data: {
            dueSoon: dueSoon.length,
            overdue: overdue.length,
            date: t,
          },
        });
      }

      this.logger.log(
        `✅ Notifications prestataires envoyées: dueSoon=${dueSoon.length}, overdue=${overdue.length}, recipients=${targets.length}`,
      );
    } catch (e: any) {
      this.logger.error(
        `❌ Erreur alertes prestataires: ${e?.message || e}`,
        e?.stack,
      );
    }
  }
}

