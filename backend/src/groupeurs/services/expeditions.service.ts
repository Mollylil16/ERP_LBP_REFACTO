import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { GroupeurExpedition } from '../entities/groupeur-expedition.entity';
import { CreateExpeditionDto } from '../dto/create-expedition.dto';
import { NotificationService } from '../../notifications/notification.service';
import {
  NotificationCategory,
  NotificationType,
} from '../../notifications/entities/notification.entity';
import { User } from '../../users/entities/user.entity';

@Injectable()
export class ExpeditionsService {
  constructor(
    @InjectRepository(GroupeurExpedition)
    private readonly expRepo: Repository<GroupeurExpedition>,
    @InjectRepository(User)
    private readonly userRepo: Repository<User>,
    private readonly notificationService: NotificationService,
  ) {}

  private async notifyRoles(
    roleCodes: string[],
    payload: {
      title: string;
      message: string;
      action_url?: string;
      audit_data?: Record<string, unknown>;
    },
  ) {
    const users = await this.userRepo
      .createQueryBuilder('u')
      .innerJoin('u.roleEntity', 'r')
      .where('u.actif = :a', { a: true })
      .andWhere('r.code IN (:...codes)', { codes: roleCodes })
      .getMany();
    for (const u of users) {
      await this.notificationService.notifyUser(u.id, {
        title: payload.title,
        message: payload.message,
        type: NotificationType.INFO,
        category: NotificationCategory.SYSTEM,
        action_url: payload.action_url,
        audit_data: payload.audit_data,
      });
    }
  }

  getParGroupeur(groupeurId: string) {
    return this.expRepo.find({
      where: { groupeur_id: groupeurId },
      order: { created_at: 'DESC' },
      take: 500,
    });
  }

  async creer(dto: CreateExpeditionDto, groupeurId: string) {
    const row = this.expRepo.create({
      ...dto,
      groupeur_id: groupeurId,
      devis_id: (dto as any).devis_id ?? null,
      statut: (dto as any).statut ?? 'en_preparation',
    } as any);
    return await this.expRepo.save(row);
  }

  async mettreAJourStatut(
    id: string,
    body: { statut: string; notes?: string },
    groupeurId: string,
  ) {
    const row = await this.expRepo.findOne({
      where: { id, groupeur_id: groupeurId },
    });
    if (!row) throw new NotFoundException('Expédition introuvable');
    row.statut = body.statut as any;
    if (body.notes != null) row.notes = body.notes;
    const saved = await this.expRepo.save(row);
    await this.notifyRoles(
      [
        'SUPERVISEUR_REGIONAL',
        'SUPERVISEURE_GENERALE',
        'ASSISTANT_DG',
        'DIRECTEUR',
      ],
      {
        title: `Expédition mise à jour`,
        message: `Expédition ${saved.numero_expedition} — nouveau statut : ${saved.statut}`,
        action_url: '/#/groupeurs/admin',
        audit_data: {
          groupeurId,
          expeditionId: saved.id,
          statut: saved.statut,
        },
      },
    );
    return saved;
  }
}
