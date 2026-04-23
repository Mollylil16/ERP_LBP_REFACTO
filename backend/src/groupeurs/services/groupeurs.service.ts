import { Injectable, Logger, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { DeepPartial, Repository } from 'typeorm';
import { Groupeur } from '../entities/groupeur.entity';
import { CreateGroupeurDto } from '../dto/create-groupeur.dto';
import { UpdateGroupeurDto } from '../dto/update-groupeur.dto';
import { GroupeurAuditLog } from '../entities/groupeur-audit-log.entity';
import { effectiveRoleCode } from '../../common/effective-role-code';
import { UsersService } from '../../users/users.service';
import { NotificationService } from '../../notifications/notification.service';
import {
  NotificationCategory,
  NotificationType,
} from '../../notifications/entities/notification.entity';
import { User, UserRole } from '../../users/entities/user.entity';

@Injectable()
export class GroupeursService {
  private readonly logger = new Logger(GroupeursService.name);

  constructor(
    @InjectRepository(Groupeur)
    private readonly groupeurRepo: Repository<Groupeur>,
    @InjectRepository(GroupeurAuditLog)
    private readonly auditRepo: Repository<GroupeurAuditLog>,
    @InjectRepository(User)
    private readonly userRepo: Repository<User>,
    private readonly usersService: UsersService,
    private readonly notificationService: NotificationService,
  ) {}

  private async findAudienceUserIds(roleCodes: string[]): Promise<number[]> {
    const rows = await this.userRepo
      .createQueryBuilder('u')
      .innerJoin('u.roleEntity', 'r')
      .where('u.actif = :a', { a: true })
      .andWhere('r.code IN (:...codes)', { codes: roleCodes })
      .getMany();
    return rows.map((u) => u.id);
  }

  private async notifyAudience(payload: {
    roleCodes: string[];
    title: string;
    message: string;
    action_url?: string;
    audit_data?: Record<string, unknown>;
  }): Promise<void> {
    const ids = await this.findAudienceUserIds(payload.roleCodes);
    for (const userId of ids) {
      await this.notificationService.notifyUser(userId, {
        title: payload.title,
        message: payload.message,
        type: NotificationType.INFO,
        category: NotificationCategory.SYSTEM,
        action_url: payload.action_url,
        audit_data: payload.audit_data,
      });
    }
  }

  private buildGroupeurUsername(
    groupeurCode: string,
    groupeurId: string,
  ): string {
    const base = String(groupeurCode || 'grp')
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9_-]/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '');
    if (base.length >= 3) return base;
    return `grp-${groupeurId.slice(0, 8)}`;
  }

  async findByUserId(userId: number): Promise<Groupeur | null> {
    return await this.groupeurRepo.findOne({ where: { user_id: userId } });
  }

  async listerTous(filters: {
    statut?: string;
    type?: string;
    ville?: string;
    q?: string;
  }) {
    const qb = this.groupeurRepo
      .createQueryBuilder('g')
      .leftJoinAndSelect('g.user', 'u');
    if (filters?.statut)
      qb.andWhere('g.statut = :statut', { statut: filters.statut });
    if (filters?.type) qb.andWhere('g.type = :type', { type: filters.type });
    if (filters?.ville)
      qb.andWhere('LOWER(g.ville) LIKE :ville', {
        ville: `%${String(filters.ville).toLowerCase()}%`,
      });
    if (filters?.q) {
      const q = `%${String(filters.q).toLowerCase()}%`;
      qb.andWhere(
        '(LOWER(g.raison_sociale) LIKE :q OR LOWER(g.nom_commercial) LIKE :q OR LOWER(g.code) LIKE :q)',
        { q },
      );
    }
    qb.orderBy('g.created_at', 'DESC');
    return await qb.getMany();
  }

  async getDetail(id: string) {
    const g = await this.groupeurRepo.findOne({
      where: { id },
      relations: ['user'],
    });
    if (!g) throw new NotFoundException('Groupeur introuvable');
    return g;
  }

  async getCompteInfo(groupeurId: string): Promise<{
    user_id: number | null;
    username: string | null;
    password_changed: boolean | null;
  }> {
    const g = await this.groupeurRepo.findOne({
      where: { id: groupeurId },
      relations: ['user'],
    });
    if (!g) throw new NotFoundException('Groupeur introuvable');
    if (!g.user_id) {
      return { user_id: null, username: null, password_changed: null };
    }
    const user = g.user;
    const pwd = await this.usersService.getPasswordPlain(g.user_id);
    return {
      user_id: g.user_id,
      username: user?.username ?? null,
      password_changed: pwd.changed,
    };
  }

  async creer(dto: CreateGroupeurDto, creeParUserId: number) {
    const g = this.groupeurRepo.create({
      ...dto,
      type: dto.type ?? 'groupeur',
      statut: 'actif',
      cree_par: creeParUserId,
    } as DeepPartial<Groupeur>);
    let saved = await this.groupeurRepo.save(g);

    // Création auto du compte de connexion (sauf si user_id déjà fourni)
    if (!saved.user_id) {
      const username = this.buildGroupeurUsername(saved.code, saved.id);
      const createdUser = await this.usersService.createUser({
        username,
        nom_complet: saved.raison_sociale,
        role: UserRole.GROUPEUR_GROSSISTE,
        code_acces: 1,
        phone: saved.telephone ?? undefined,
        email: saved.email_contact ?? undefined,
      } as any);

      // Force roleEntity GROUPEUR_GROSSISTE
      await this.userRepo
        .createQueryBuilder()
        .update(User)
        .set({ roleEntity: { code: 'GROUPEUR_GROSSISTE' } as any })
        .where('id = :id', { id: createdUser.id })
        .execute();

      saved.user_id = createdUser.id;
      saved = await this.groupeurRepo.save(saved);
    }

    await this.auditRepo.save(
      this.auditRepo.create({
        acteur_id: creeParUserId,
        acteur_role: null,
        action: 'CREER_GROUPEUR',
        entite: 'groupeur',
        entite_id: saved.id,
        detail: { after: saved },
      }),
    );

    await this.notifyAudience({
      roleCodes: [
        'DIRECTEUR',
        'ASSISTANT_DG',
        'SUPERVISEUR_REGIONAL',
        'SUPERVISEURE_GENERALE',
      ],
      title: 'Nouveau groupeur créé',
      message: `${saved.raison_sociale} (${saved.code}) a été créé.`,
      action_url: `/#/groupeurs/admin`,
      audit_data: { groupeurId: saved.id, code: saved.code },
    });
    return saved;
  }

  async modifier(
    id: string,
    dto: UpdateGroupeurDto,
    actorUserId: number,
    actor?: any,
  ) {
    const g = await this.getDetail(id);
    const before = { ...g };
    Object.assign(g, dto);
    const saved: Groupeur = await this.groupeurRepo.save(g);
    await this.auditRepo.save(
      this.auditRepo.create({
        acteur_id: actorUserId,
        acteur_role: effectiveRoleCode(actor) ?? null,
        action: 'MODIFIER_GROUPEUR',
        entite: 'groupeur',
        entite_id: saved.id,
        detail: { before, after: saved },
      }),
    );
    return saved;
  }

  async changerStatut(
    id: string,
    body: { statut: 'actif' | 'suspendu' | 'archive'; motif?: string },
    actorUserId: number,
    actor?: any,
  ) {
    const g = await this.getDetail(id);
    const before = { ...g };
    g.statut = body.statut;
    const saved: Groupeur = await this.groupeurRepo.save(g);
    await this.auditRepo.save(
      this.auditRepo.create({
        acteur_id: actorUserId,
        acteur_role: effectiveRoleCode(actor) ?? null,
        action: 'MODIFIER_STATUT',
        entite: 'groupeur',
        entite_id: saved.id,
        detail: { before, after: saved, motif: body.motif ?? null },
      }),
    );

    await this.notifyAudience({
      roleCodes: [
        'DIRECTEUR',
        'ASSISTANT_DG',
        'SUPERVISEUR_REGIONAL',
        'SUPERVISEURE_GENERALE',
      ],
      title: `Statut groupeur modifié`,
      message: `${saved.raison_sociale} (${saved.code}) — nouveau statut : ${body.statut}${
        body.motif ? ` (motif : ${body.motif})` : ''
      }`,
      action_url: `/#/groupeurs/admin`,
      audit_data: { groupeurId: saved.id, statut: body.statut },
    });
    return saved;
  }

  async archiver(id: string, actorUserId: number, actor?: any) {
    return this.changerStatut(
      id,
      { statut: 'archive', motif: 'archivage' },
      actorUserId,
      actor,
    );
  }

  async getStatsGlobales() {
    const actifs = await this.groupeurRepo.count({
      where: { statut: 'actif' as any },
    });
    const total = await this.groupeurRepo.count();
    return { groupeurs_actifs: actifs, groupeurs_total: total };
  }

  async getAuditLog(filters: { take?: number }) {
    const take = Math.max(1, Math.min(500, Number(filters?.take) || 200));
    return await this.auditRepo.find({ order: { created_at: 'DESC' }, take });
  }
}
