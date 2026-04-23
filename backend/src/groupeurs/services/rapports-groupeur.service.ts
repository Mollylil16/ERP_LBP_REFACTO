import { Injectable, Logger } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { DeepPartial, Repository } from 'typeorm';
import { GroupeurRapport } from '../entities/groupeur-rapport.entity';
import { SoumettreRapportGroupeurDto } from '../dto/soumettre-rapport.dto';
import { NotificationService } from '../../notifications/notification.service';
import {
  NotificationCategory,
  NotificationType,
} from '../../notifications/entities/notification.entity';
import { User } from '../../users/entities/user.entity';

@Injectable()
export class RapportsGroupeurService {
  private readonly logger = new Logger(RapportsGroupeurService.name);

  constructor(
    @InjectRepository(GroupeurRapport)
    private readonly rapportRepo: Repository<GroupeurRapport>,
    @InjectRepository(User)
    private readonly userRepo: Repository<User>,
    private readonly notificationService: NotificationService,
  ) {}

  async getHistorique() {
    return this.rapportRepo.find({
      order: { created_at: 'DESC' },
      take: 200,
      relations: ['groupeur', 'auteur', 'destinataire'],
    });
  }

  private async findDirectionUserIds(): Promise<number[]> {
    const rows = await this.userRepo
      .createQueryBuilder('u')
      .innerJoin('u.roleEntity', 'r')
      .where('u.actif = :a', { a: true })
      .andWhere('r.code IN (:...codes)', {
        codes: ['DIRECTEUR', 'ASSISTANT_DG'],
      })
      .getMany();
    return rows.map((u) => u.id);
  }

  async soumettre(
    dto: SoumettreRapportGroupeurDto,
    auteur: { id: number; username: string },
  ) {
    const row = this.rapportRepo.create({
      auteur_id: auteur.id,
      type: dto.type as any,
      periode: (dto.periode ?? null) as any,
      date_debut: dto.date_debut ?? null,
      date_fin: dto.date_fin ?? null,
      groupeur_id: dto.groupeur_id ?? null,
      commentaire: dto.commentaire ?? null,
      statut_lecture: 'non_lu',
      soumis_a: null,
    } as DeepPartial<GroupeurRapport>);
    const saved = await this.rapportRepo.save(row);

    const directionIds = await this.findDirectionUserIds();
    if (directionIds.length === 0) {
      this.logger.warn(
        'Aucun DIRECTEUR/ASSISTANT_DG actif : notification rapport groupeurs non envoyée.',
      );
      return saved;
    }
    const message = [
      `${auteur.username} a soumis un rapport “groupeurs”.`,
      `Type : ${dto.type} — période : ${dto.periode ?? '—'} (${dto.date_debut ?? '—'} → ${dto.date_fin ?? '—'}).`,
    ].join(' ');

    for (const userId of directionIds) {
      await this.notificationService.notifyUser(userId, {
        title: 'Nouveau rapport groupeurs',
        message,
        type: NotificationType.INFO,
        category: NotificationCategory.SYSTEM,
        action_url: '/#/groupeurs/admin/rapports',
        audit_data: { groupeurRapportId: saved.id, type: dto.type },
      });
    }
    return saved;
  }
}
