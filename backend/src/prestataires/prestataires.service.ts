import {
  BadRequestException,
  ForbiddenException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { Prestataire } from './entities/prestataire.entity';
import { FacturePrestataire } from './entities/facture-prestataire.entity';
import { ReglementPrestataire } from './entities/reglement-prestataire.entity';
import { CreatePrestataireDto } from './dto/create-prestataire.dto';
import { CreateFacturePrestataireDto } from './dto/create-facture-prestataire.dto';
import { CreateReglementPrestataireDto } from './dto/create-reglement-prestataire.dto';
import { Agence } from '../agences/entities/agence.entity';
import { NotificationService } from '../notifications/notification.service';
import {
  NotificationCategory,
  NotificationType,
} from '../notifications/entities/notification.entity';
import { effectiveRoleCode } from '../common/effective-role-code';
import { User } from '../users/entities/user.entity';

function addDaysISO(dateISO: string, days: number): string {
  const d = new Date(dateISO);
  if (Number.isNaN(d.getTime())) return dateISO;
  d.setDate(d.getDate() + Number(days || 0));
  return d.toISOString().slice(0, 10);
}

function todayISO(): string {
  return new Date().toISOString().slice(0, 10);
}

@Injectable()
export class PrestatairesService {
  constructor(
    @InjectRepository(Prestataire)
    private readonly prestRepo: Repository<Prestataire>,
    @InjectRepository(FacturePrestataire)
    private readonly factureRepo: Repository<FacturePrestataire>,
    @InjectRepository(ReglementPrestataire)
    private readonly regRepo: Repository<ReglementPrestataire>,
    @InjectRepository(Agence)
    private readonly agenceRepo: Repository<Agence>,
    @InjectRepository(User)
    private readonly userRepo: Repository<User>,
    private readonly notificationService: NotificationService,
  ) {}

  async createPrestataire(dto: CreatePrestataireDto): Promise<Prestataire> {
    const row = this.prestRepo.create({
      nom: dto.nom.trim(),
      pays: dto.pays?.trim() || null,
      actif: dto.actif ?? true,
      contact_nom: dto.contact_nom?.trim() || null,
      contact_tel: dto.contact_tel?.trim() || null,
      contact_email: dto.contact_email?.trim() || null,
    });
    return await this.prestRepo.save(row);
  }

  async listPrestataires(q?: { actif?: string; pays?: string }) {
    const qb = this.prestRepo.createQueryBuilder('p').orderBy('p.nom', 'ASC');
    if (q?.actif != null) {
      qb.andWhere('p.actif = :a', { a: q.actif === 'true' });
    }
    if (q?.pays) {
      qb.andWhere('LOWER(p.pays) = LOWER(:pays)', { pays: q.pays });
    }
    return qb.getMany();
  }

  async createFacture(dto: CreateFacturePrestataireDto, actorUsername?: string) {
    const agence = await this.agenceRepo.findOne({ where: { id: dto.id_agence } });
    if (!agence) throw new NotFoundException('Agence introuvable');

    const prest = await this.prestRepo.findOne({ where: { id: dto.prestataire_id } });
    if (!prest) throw new NotFoundException('Prestataire introuvable');

    const devise = (dto.devise || 'XOF').toUpperCase();
    const date_echeance =
      dto.date_echeance ||
      addDaysISO(dto.date_reception, Number(dto.delai_reglement_jours ?? 0));

    const montantTotal = Number(dto.montant_total || 0);
    const f = this.factureRepo.create({
      agence,
      pays: dto.pays?.trim() || (agence.pays as any) || null,
      prestataire: prest,
      date_reception: dto.date_reception,
      numero_lta: dto.numero_lta?.trim() || null,
      numero_envoi: dto.numero_envoi?.trim() || null,
      numero_facture: dto.numero_facture.trim(),
      montant_total: montantTotal,
      devise,
      delai_reglement_jours: dto.delai_reglement_jours ?? null,
      date_echeance,
      statut: 'A_PAYER',
      montant_regle: 0,
      reliquat: montantTotal,
      note: dto.note?.trim() || null,
      created_by: actorUsername || null,
    });
    return await this.factureRepo.save(f);
  }

  async listFactures(user: any, q?: any) {
    const role = effectiveRoleCode(user).toUpperCase();
    const canSeeAll = ['ADMIN', 'DIRECTEUR', 'SUPER_ADMIN', 'CAISSIER'].includes(role);
    const qb = this.factureRepo
      .createQueryBuilder('f')
      .leftJoinAndSelect('f.agence', 'agence')
      .leftJoinAndSelect('f.prestataire', 'prestataire')
      .orderBy('f.created_at', 'DESC');

    if (!canSeeAll) {
      if (!user?.id_agence) throw new ForbiddenException();
      qb.andWhere('f.id_agence = :aid', { aid: user.id_agence });
    }
    if (q?.id_agence) qb.andWhere('f.id_agence = :aid2', { aid2: Number(q.id_agence) });
    if (q?.prestataire_id)
      qb.andWhere('f.prestataire_id = :pid', { pid: Number(q.prestataire_id) });
    if (q?.statut) qb.andWhere('f.statut = :st', { st: String(q.statut) });
    if (q?.pays) qb.andWhere('LOWER(f.pays) = LOWER(:p)', { p: String(q.pays) });
    if (q?.date_debut && q?.date_fin) {
      qb.andWhere('f.date_echeance BETWEEN :d1 AND :d2', {
        d1: String(q.date_debut),
        d2: String(q.date_fin),
      });
    }

    return qb.getMany();
  }

  async getFacture(id: number, user: any) {
    const f = await this.factureRepo.findOne({
      where: { id },
      relations: ['agence', 'prestataire', 'reglements'],
      order: { reglements: { created_at: 'DESC' } } as any,
    });
    if (!f) throw new NotFoundException();

    const role = effectiveRoleCode(user).toUpperCase();
    const canSeeAll = ['ADMIN', 'DIRECTEUR', 'SUPER_ADMIN', 'CAISSIER'].includes(role);
    if (!canSeeAll && f.agence?.id !== user?.id_agence) {
      throw new ForbiddenException();
    }
    return f;
  }

  private computeStatut(f: FacturePrestataire): FacturePrestataire['statut'] {
    if (f.statut === 'ANNULEE') return 'ANNULEE';
    if (Number(f.reliquat) <= 0) return 'PAYE';
    const t = todayISO();
    if (String(f.date_echeance) < t) return 'EN_RETARD';
    // “bientôt dû” : dans 7 jours (par défaut)
    const dEch = new Date(String(f.date_echeance));
    const dNow = new Date(t);
    const diffDays = Math.ceil((dEch.getTime() - dNow.getTime()) / (24 * 3600 * 1000));
    if (diffDays <= 7) return 'BIENTOT_DU';
    return Number(f.montant_regle) > 0 ? 'PARTIEL' : 'A_PAYER';
  }

  private async notifyUsersByRoleCodes(
    roleCodes: string[],
    payload: {
      title: string;
      message: string;
      type?: NotificationType;
      category?: NotificationCategory;
      action_url?: string;
      audit_data?: Record<string, unknown>;
    },
  ) {
    const users = await this.userRepo.find({
      where: { actif: true } as any,
      relations: ['roleEntity', 'agence'],
    });
    const target = users.filter((u: any) =>
      roleCodes.includes(String(u.roleEntity?.code || u.role || '').toUpperCase()),
    );
    for (const u of target) {
      await this.notificationService.notifyUser(u.id, payload);
    }
  }

  async addReglement(factureId: number, dto: CreateReglementPrestataireDto, actor: any) {
    const facture = await this.factureRepo.findOne({
      where: { id: factureId },
      relations: ['agence', 'prestataire'],
    });
    if (!facture) throw new NotFoundException('Facture prestataire introuvable');

    const montant = Number(dto.montant || 0);
    if (montant <= 0) throw new BadRequestException('Montant invalide');

    const origine =
      (dto.origine_fonds as any) || (dto.mode_reglement === 'ESPECE' ? 'AGENCE' : 'CAISSE_PRINCIPALE');

    const isEspeceAgence = dto.mode_reglement === 'ESPECE' && origine === 'AGENCE';

    const reg = this.regRepo.create({
      facture,
      date_reglement: dto.date_reglement,
      mode_reglement: dto.mode_reglement,
      montant,
      reference: dto.reference?.trim() || null,
      note: dto.note?.trim() || null,
      origine_fonds: origine,
      hub_retrait_status: isEspeceAgence ? 'A_RETIRER' : 'NA',
      hub_retrait_approval_status: isEspeceAgence ? 'NA' : 'NA',
      created_by: actor?.username || null,
    });
    const saved = await this.regRepo.save(reg);

    // Recalcul agrégats facture
    const regs = await this.regRepo.find({
      where: { facture: { id: facture.id } } as any,
    });
    const totalRegle = regs.reduce((s, r) => s + Number(r.montant || 0), 0);
    facture.montant_regle = Number(totalRegle.toFixed(2));
    facture.reliquat = Number((Number(facture.montant_total) - facture.montant_regle).toFixed(2));
    facture.statut = this.computeStatut(facture);
    await this.factureRepo.save(facture);

    if (isEspeceAgence) {
      // Info au caissier principal + directeur: retrait à effectuer au hub
      await this.notifyUsersByRoleCodes(['CAISSIER', 'DIRECTEUR'], {
        title: 'Retrait à effectuer (caisse principale)',
        message: `Paiement espèces déclaré en agence ${facture.agence?.nom ?? ''} — Prestataire: ${facture.prestataire?.nom ?? ''} — Facture: ${facture.numero_facture} — Montant: ${montant.toLocaleString('fr-FR')} ${facture.devise}.`,
        type: NotificationType.WARNING,
        category: NotificationCategory.CAISSE,
        action_url: '/prestataires/retraits-hub',
        audit_data: { reglementPrestataireId: saved.id, facturePrestataireId: facture.id },
      });
    }

    return {
      reglement: saved,
      facture: await this.getFacture(facture.id, actor),
    };
  }

  async listRetraitsHub(user: any, q?: { status?: string; id_agence?: string; pays?: string }) {
    const role = effectiveRoleCode(user).toUpperCase();
    const canSeeAll = ['ADMIN', 'DIRECTEUR', 'SUPER_ADMIN', 'CAISSIER'].includes(role);

    const qb = this.regRepo
      .createQueryBuilder('r')
      .leftJoinAndSelect('r.facture', 'f')
      .leftJoinAndSelect('f.agence', 'agence')
      .leftJoinAndSelect('f.prestataire', 'prestataire')
      .where('r.hub_retrait_status != :na', { na: 'NA' })
      .orderBy('r.created_at', 'DESC');

    if (!canSeeAll) {
      if (!user?.id_agence) throw new ForbiddenException();
      qb.andWhere('f.id_agence = :aid', { aid: user.id_agence });
    }
    if (q?.status) qb.andWhere('r.hub_retrait_status = :st', { st: String(q.status) });
    if (q?.id_agence) qb.andWhere('f.id_agence = :aid2', { aid2: Number(q.id_agence) });
    if (q?.pays) qb.andWhere('LOWER(f.pays) = LOWER(:p)', { p: String(q.pays) });

    return qb.getMany();
  }

  async requestRetraitApproval(reglementId: number, actor: any) {
    const rc = effectiveRoleCode(actor).toUpperCase();
    if (rc !== 'ASSISTANT_DG') {
      throw new ForbiddenException('Réservé à l’assistante DG');
    }
    const r = await this.regRepo.findOne({
      where: { id: reglementId },
      relations: ['facture', 'facture.agence', 'facture.prestataire'],
    });
    if (!r) throw new NotFoundException();
    if (r.hub_retrait_status !== 'A_RETIRER') {
      throw new BadRequestException('Ce règlement ne nécessite pas de retrait hub');
    }
    if (r.hub_retrait_approval_status === 'APPROVED') {
      return r;
    }
    r.hub_retrait_approval_status = 'PENDING';
    r.hub_retrait_approval_requested_at = new Date();
    r.hub_retrait_approval_requested_by = actor.username;
    await this.regRepo.save(r);

    await this.notifyUsersByRoleCodes(['DIRECTEUR'], {
      title: 'Approbation demandée (retrait caisse principale)',
      message: `L’assistante DG demande l’approbation pour marquer le retrait hub. Agence: ${r.facture?.agence?.nom ?? ''} — Prestataire: ${r.facture?.prestataire?.nom ?? ''} — Facture: ${r.facture?.numero_facture ?? ''} — Montant: ${Number(r.montant).toLocaleString('fr-FR')} ${r.facture?.devise ?? ''}.`,
      type: NotificationType.INFO,
      category: NotificationCategory.CAISSE,
      action_url: '/prestataires/retraits-hub',
      audit_data: { reglementPrestataireId: r.id },
    });

    return r;
  }

  async decideRetraitApproval(reglementId: number, actor: any, approve: boolean, reason?: string) {
    const rc = effectiveRoleCode(actor).toUpperCase();
    if (!['DIRECTEUR', 'ADMIN', 'SUPER_ADMIN'].includes(rc)) {
      throw new ForbiddenException('Seul le directeur peut approuver/rejeter');
    }
    const r = await this.regRepo.findOne({
      where: { id: reglementId },
      relations: ['facture', 'facture.agence', 'facture.prestataire'],
    });
    if (!r) throw new NotFoundException();
    if (r.hub_retrait_approval_status !== 'PENDING') {
      throw new BadRequestException('Aucune demande en attente');
    }
    r.hub_retrait_approval_status = approve ? 'APPROVED' : 'REJECTED';
    r.hub_retrait_approval_decided_at = new Date();
    r.hub_retrait_approval_decided_by = actor.username;
    await this.regRepo.save(r);

    // Notifier l’assistante DG si possible (tous les ASSISTANT_DG actifs)
    await this.notifyUsersByRoleCodes(['ASSISTANT_DG'], {
      title: approve
        ? 'Approbation accordée (retrait hub)'
        : 'Approbation rejetée (retrait hub)',
      message: approve
        ? `Approbation accordée par le directeur pour marquer le retrait. Règlement #${r.id}.`
        : `Approbation rejetée par le directeur. ${reason ? `Motif: ${reason}` : ''}`,
      type: approve ? NotificationType.SUCCESS : NotificationType.WARNING,
      category: NotificationCategory.CAISSE,
      action_url: '/prestataires/retraits-hub',
      audit_data: { reglementPrestataireId: r.id },
    });

    return r;
  }

  async markRetraitEffectue(reglementId: number, actor: any) {
    const rc = effectiveRoleCode(actor).toUpperCase();
    const r = await this.regRepo.findOne({
      where: { id: reglementId },
      relations: ['facture', 'facture.agence', 'facture.prestataire'],
    });
    if (!r) throw new NotFoundException();
    if (r.hub_retrait_status !== 'A_RETIRER') {
      throw new BadRequestException('Ce retrait n’est pas en attente');
    }

    const canMarkDirect =
      ['CAISSIER', 'DIRECTEUR', 'ADMIN', 'SUPER_ADMIN'].includes(rc);

    const canMarkWithApproval =
      rc === 'ASSISTANT_DG' && r.hub_retrait_approval_status === 'APPROVED';

    if (!canMarkDirect && !canMarkWithApproval) {
      throw new ForbiddenException('Vous ne pouvez pas marquer ce retrait');
    }

    r.hub_retrait_status = 'RETIRE';
    r.hub_retrait_marked_at = new Date();
    r.hub_retrait_marked_by = actor.username;
    await this.regRepo.save(r);

    // Info superviseur régional + directeur/caissier
    await this.notifyUsersByRoleCodes(['SUPERVISEUR_REGIONAL', 'DIRECTEUR', 'CAISSIER'], {
      title: 'Retrait caisse principale tracé',
      message: `Retrait marqué comme effectué — Agence: ${r.facture?.agence?.nom ?? ''} — Prestataire: ${r.facture?.prestataire?.nom ?? ''} — Facture: ${r.facture?.numero_facture ?? ''} — Montant: ${Number(r.montant).toLocaleString('fr-FR')} ${r.facture?.devise ?? ''}.`,
      type: NotificationType.SUCCESS,
      category: NotificationCategory.CAISSE,
      action_url: '/prestataires/retraits-hub',
      audit_data: { reglementPrestataireId: r.id },
    });

    return r;
  }
}

