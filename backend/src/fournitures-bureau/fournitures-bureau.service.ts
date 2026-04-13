import {
  BadRequestException,
  ForbiddenException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { DataSource, Repository } from 'typeorm';
import { FournitureArticle } from './entities/fourniture-article.entity';
import { FournitureDemande } from './entities/fourniture-demande.entity';
import { FournitureDemandeLigne } from './entities/fourniture-demande-ligne.entity';
import { Agence } from '../agences/entities/agence.entity';
import { User } from '../users/entities/user.entity';
import { CreateFournitureArticleDto } from './dto/create-fourniture-article.dto';
import { AjustStockArticleDto } from './dto/ajust-stock-article.dto';
import { CreateDemandeFournitureDto } from './dto/create-demande-fourniture.dto';
import { ValiderDemandeFournitureDto } from './dto/valider-demande-fourniture.dto';
import { NotificationService } from '../notifications/notification.service';
import {
  NotificationCategory,
  NotificationType,
} from '../notifications/entities/notification.entity';
import { RolesService } from '../roles/roles.service';
import { effectiveRoleCode } from '../common/effective-role-code';

@Injectable()
export class FournituresBureauService {
  constructor(
    @InjectRepository(FournitureArticle)
    private readonly articleRepo: Repository<FournitureArticle>,
    @InjectRepository(FournitureDemande)
    private readonly demandeRepo: Repository<FournitureDemande>,
    @InjectRepository(FournitureDemandeLigne)
    private readonly ligneRepo: Repository<FournitureDemandeLigne>,
    @InjectRepository(Agence)
    private readonly agenceRepo: Repository<Agence>,
    private readonly dataSource: DataSource,
    private readonly notificationService: NotificationService,
    private readonly rolesService: RolesService,
  ) {}

  private async appCodesFor(user: any): Promise<string[]> {
    const roleCode = effectiveRoleCode(user);
    try {
      const fromDb = await this.rolesService.getAppPermissionCodesForRole(
        roleCode,
      );
      return fromDb?.length ? fromDb : [];
    } catch {
      return [];
    }
  }

  private async assertRead(user: any): Promise<void> {
    const c = await this.appCodesFor(user);
    if (
      c.includes('*') ||
      c.includes('exploitation.fournitures.read') ||
      c.includes('exploitation.fournitures.manage') ||
      c.includes('exploitation.fournitures.request')
    ) {
      return;
    }
    throw new ForbiddenException();
  }

  private async assertManage(user: any): Promise<void> {
    const c = await this.appCodesFor(user);
    if (c.includes('*') || c.includes('exploitation.fournitures.manage')) return;
    throw new ForbiddenException();
  }

  private async assertRequest(user: any): Promise<void> {
    const c = await this.appCodesFor(user);
    if (
      c.includes('*') ||
      c.includes('exploitation.fournitures.manage') ||
      c.includes('exploitation.fournitures.request')
    ) {
      return;
    }
    throw new ForbiddenException();
  }

  private canSeeAllDemandes(codes: string[]): boolean {
    return (
      codes.includes('*') || codes.includes('exploitation.fournitures.manage')
    );
  }

  private assertAgenceDemande(
    user: any,
    idAgence: number,
    codes: string[],
  ): void {
    if (this.canSeeAllDemandes(codes)) return;
    const aid = user?.agence?.id ?? user?.id_agence;
    if (!aid || Number(aid) !== Number(idAgence)) {
      throw new ForbiddenException('Agence non autorisée pour cette demande');
    }
  }

  async listArticles(user: any): Promise<FournitureArticle[]> {
    await this.assertRead(user);
    return this.articleRepo.find({ order: { nom: 'ASC' } });
  }

  async createArticle(
    user: any,
    dto: CreateFournitureArticleDto,
  ): Promise<FournitureArticle> {
    await this.assertManage(user);
    const exists = await this.articleRepo.findOne({
      where: { code: dto.code.trim() },
    });
    if (exists) throw new BadRequestException('Code article déjà utilisé');
    const a = this.articleRepo.create({
      code: dto.code.trim(),
      nom: dto.nom.trim(),
      unite: dto.unite?.trim() || 'unité',
      quantite_stock: dto.quantite_stock ?? 0,
      seuil_alerte: dto.seuil_alerte ?? 0,
      actif: dto.actif !== false,
    });
    return this.articleRepo.save(a);
  }

  async ajustStock(
    user: any,
    id: number,
    dto: AjustStockArticleDto,
  ): Promise<FournitureArticle> {
    await this.assertManage(user);
    const a = await this.articleRepo.findOne({ where: { id } });
    if (!a) throw new NotFoundException('Article introuvable');
    a.quantite_stock = dto.quantite_stock;
    return this.articleRepo.save(a);
  }

  async createDemande(
    user: any,
    dto: CreateDemandeFournitureDto,
  ): Promise<FournitureDemande> {
    await this.assertRequest(user);
    const codes = await this.appCodesFor(user);
    this.assertAgenceDemande(user, dto.id_agence, codes);

    const agence = await this.agenceRepo.findOne({
      where: { id: dto.id_agence },
    });
    if (!agence) throw new NotFoundException('Agence introuvable');

    const demande = this.demandeRepo.create({
      agence,
      demandeur: { id: user.id } as User,
      statut: 'BROUILLON',
      observations: dto.observations ?? null,
      lignes: [],
    });

    for (const l of dto.lignes) {
      const art = await this.articleRepo.findOne({
        where: { id: l.id_article },
      });
      if (!art || !art.actif) {
        throw new BadRequestException(`Article #${l.id_article} invalide`);
      }
      const ligne = this.ligneRepo.create({
        article: art,
        quantite: l.quantite,
        demande,
      });
      demande.lignes.push(ligne);
    }

    return this.demandeRepo.save(demande);
  }

  async soumettreDemande(user: any, id: number): Promise<FournitureDemande> {
    await this.assertRequest(user);
    const codes = await this.appCodesFor(user);
    const d = await this.demandeRepo.findOne({
      where: { id },
      relations: ['agence', 'demandeur', 'lignes', 'lignes.article'],
    });
    if (!d) throw new NotFoundException();
    this.assertAgenceDemande(user, d.agence.id, codes);
    if (d.statut !== 'BROUILLON') {
      throw new BadRequestException('Seul un brouillon peut être soumis');
    }
    if (!d.lignes?.length) {
      throw new BadRequestException('Demande sans ligne');
    }
    d.statut = 'SOUMIS';
    const saved = await this.demandeRepo.save(d);
    await this.notifierAgentsExploitation(saved);
    return this.loadDemande(saved.id);
  }

  private async notifierAgentsExploitation(
    demande: FournitureDemande,
  ): Promise<void> {
    const users = await this.demandeRepo.manager
      .getRepository(User)
      .createQueryBuilder('u')
      .leftJoinAndSelect('u.agence', 'a')
      .where('u.actif = :actif', { actif: true })
      .andWhere(
        '(a.pays ILIKE :ci1 OR a.pays ILIKE :ci2 OR a.pays ILIKE :ci3)',
        {
          ci1: '%Ivoire%',
          ci2: '%ivoire%',
          ci3: "%Côte d'Ivoire%",
        },
      )
      .getMany();

    for (const u of users) {
      const codes = await this.appCodesFor(u);
      if (
        codes.includes('*') ||
        codes.includes('exploitation.fournitures.manage')
      ) {
        await this.notificationService.notifyUser(u.id, {
          title: 'Demande fournitures bureau',
          message: `${demande.agence?.nom ?? 'Agence'} a soumis une demande de fournitures (#${demande.id})`,
          type: NotificationType.INFO,
          category: NotificationCategory.SYSTEM,
          action_url: '#/exploitation/fournitures',
          audit_data: { demandeId: demande.id },
        });
      }
    }
  }

  async listDemandes(
    user: any,
    q: { statut?: string; agence_id?: number },
  ): Promise<FournitureDemande[]> {
    await this.assertRead(user);
    const codes = await this.appCodesFor(user);
    const qb = this.demandeRepo
      .createQueryBuilder('d')
      .leftJoinAndSelect('d.agence', 'agence')
      .leftJoinAndSelect('d.demandeur', 'demandeur')
      .leftJoinAndSelect('d.valideur', 'valideur')
      .leftJoinAndSelect('d.livreur', 'livreur')
      .leftJoinAndSelect('d.lignes', 'lignes')
      .leftJoinAndSelect('lignes.article', 'article')
      .orderBy('d.created_at', 'DESC');

    if (!this.canSeeAllDemandes(codes)) {
      const aid = user?.agence?.id ?? user?.id_agence;
      if (!aid) throw new ForbiddenException();
      qb.andWhere('d.id_agence = :aid', { aid });
    }
    if (q.statut) qb.andWhere('d.statut = :st', { st: q.statut });
    if (q.agence_id && this.canSeeAllDemandes(codes)) {
      qb.andWhere('d.id_agence = :ag', { ag: q.agence_id });
    }
    return qb.getMany();
  }

  /** Synthèse dashboard agent : nombre de demandes SOUMIS (réservé aux profils `manage`). */
  async countDemandesSoumisesPourDashboard(user: any): Promise<number> {
    const c = await this.appCodesFor(user);
    if (!c.includes('*') && !c.includes('exploitation.fournitures.manage')) {
      return 0;
    }
    return this.demandeRepo.count({ where: { statut: 'SOUMIS' } });
  }

  private async loadDemande(id: number): Promise<FournitureDemande> {
    const d = await this.demandeRepo.findOne({
      where: { id },
      relations: [
        'agence',
        'demandeur',
        'valideur',
        'livreur',
        'lignes',
        'lignes.article',
      ],
    });
    if (!d) throw new NotFoundException();
    return d;
  }

  async validerDemande(
    user: any,
    id: number,
    dto: ValiderDemandeFournitureDto,
  ): Promise<FournitureDemande> {
    await this.assertManage(user);
    const d = await this.loadDemande(id);
    if (d.statut !== 'SOUMIS') {
      throw new BadRequestException(
        'Seule une demande SOUMIS peut être validée',
      );
    }

    const map = new Map(
      dto.lignes.map((x) => [x.id_ligne, x.quantite_validee]),
    );
    for (const ligne of d.lignes) {
      const qv = map.get(ligne.id);
      if (qv === undefined) {
        throw new BadRequestException(
          `Ligne #${ligne.id} manquante dans la validation`,
        );
      }
      if (qv > ligne.quantite) {
        throw new BadRequestException(
          `Quantité validée supérieure à la demande (ligne #${ligne.id})`,
        );
      }
      const art = await this.articleRepo.findOne({
        where: { id: ligne.article.id },
      });
      if (!art) throw new BadRequestException('Article introuvable');
      if (qv > Number(art.quantite_stock)) {
        throw new BadRequestException(
          `Stock insuffisant pour ${art.nom} (validé: ${qv}, stock: ${art.quantite_stock})`,
        );
      }
      ligne.quantite_validee = qv;
      await this.ligneRepo.save(ligne);
    }

    d.statut = 'VALIDE';
    d.valideur = { id: user.id } as User;
    d.date_validation = new Date();
    d.motif_refus = null;
    await this.demandeRepo.save(d);
    return this.loadDemande(id);
  }

  async refuserDemande(
    user: any,
    id: number,
    motif: string,
  ): Promise<FournitureDemande> {
    await this.assertManage(user);
    const d = await this.loadDemande(id);
    if (d.statut !== 'SOUMIS') {
      throw new BadRequestException(
        'Seule une demande SOUMIS peut être refusée',
      );
    }
    d.statut = 'REFUSE';
    d.motif_refus = motif;
    d.valideur = { id: user.id } as User;
    d.date_validation = new Date();
    await this.demandeRepo.save(d);
    return this.loadDemande(id);
  }

  async livrerDemande(user: any, id: number): Promise<FournitureDemande> {
    await this.assertManage(user);
    const d = await this.loadDemande(id);
    if (d.statut !== 'VALIDE') {
      throw new BadRequestException(
        'Seule une demande VALIDEE peut être livrée',
      );
    }

    const queryRunner = this.dataSource.createQueryRunner();
    await queryRunner.connect();
    await queryRunner.startTransaction();
    try {
      for (const ligne of d.lignes) {
        const q =
          ligne.quantite_validee != null
            ? ligne.quantite_validee
            : ligne.quantite;
        const art = await queryRunner.manager.findOne(FournitureArticle, {
          where: { id: ligne.article.id },
          lock: { mode: 'pessimistic_write' },
        });
        if (!art) throw new BadRequestException('Article introuvable');
        if (q > art.quantite_stock) {
          throw new BadRequestException(`Stock insuffisant pour ${art.nom}`);
        }
        art.quantite_stock -= q;
        ligne.quantite_livree = q;
        await queryRunner.manager.save(FournitureArticle, art);
        await queryRunner.manager.save(FournitureDemandeLigne, ligne);
      }
      d.statut = 'LIVRE';
      d.livreur = { id: user.id } as User;
      d.date_livraison = new Date();
      await queryRunner.manager.save(FournitureDemande, d);
      await queryRunner.commitTransaction();
    } catch (e) {
      await queryRunner.rollbackTransaction();
      throw e;
    } finally {
      await queryRunner.release();
    }
    return this.loadDemande(id);
  }
}
