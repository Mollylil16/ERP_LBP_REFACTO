import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { DeepPartial, Repository } from 'typeorm';
import { GroupeurDocument } from '../entities/groupeur-document.entity';
import { NotificationService } from '../../notifications/notification.service';
import {
  NotificationCategory,
  NotificationType,
} from '../../notifications/entities/notification.entity';
import { InjectRepository as InjectRepo } from '@nestjs/typeorm';
import { User } from '../../users/entities/user.entity';

@Injectable()
export class DocumentsService {
  constructor(
    @InjectRepository(GroupeurDocument)
    private readonly docRepo: Repository<GroupeurDocument>,
    @InjectRepo(User)
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
    return this.docRepo.find({
      where: { groupeur_id: groupeurId },
      order: { created_at: 'DESC' },
      take: 500,
    });
  }

  async upload(
    dto: {
      expedition_id?: string;
      type_document: string;
      nom_fichier: string;
      url_fichier: string;
      taille_octets?: number;
    },
    groupeurId: string,
    uploadedByUserId?: number,
  ) {
    const row = this.docRepo.create({
      ...dto,
      groupeur_id: groupeurId,
      expedition_id: dto.expedition_id ?? null,
      uploaded_par: uploadedByUserId ?? null,
      statut: 'valide',
    } as DeepPartial<GroupeurDocument>);
    const saved = await this.docRepo.save(row);
    await this.notifyRoles(
      [
        'SUPERVISEUR_REGIONAL',
        'SUPERVISEURE_GENERALE',
        'ASSISTANT_DG',
        'DIRECTEUR',
      ],
      {
        title: 'Nouveau document déposé (groupeur)',
        message: `Document ${dto.type_document} — ${dto.nom_fichier}`,
        action_url: '/#/groupeurs/admin',
        audit_data: {
          groupeurId,
          documentId: saved.id,
          type: dto.type_document,
        },
      },
    );
    return saved;
  }

  async supprimer(id: string, groupeurId: string) {
    const row = await this.docRepo.findOne({
      where: { id, groupeur_id: groupeurId },
    });
    if (!row) throw new NotFoundException('Document introuvable');
    await this.docRepo.delete({ id });
    return { ok: true };
  }
}
