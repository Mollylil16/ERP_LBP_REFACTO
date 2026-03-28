import {
  Injectable,
  NotFoundException,
  BadRequestException,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import {
  DataSource,
  EntityManager,
  FindOptionsWhere,
  Repository,
} from 'typeorm';
import { Litige, LitigeStatut } from './entities/litige.entity';
import { LitigeMessage, MessageType } from './entities/litige-message.entity';
import { CreateLitigeDto } from './dto/create-litige.dto';
import { UpdateLitigeDto } from './dto/update-litige.dto';
import { CreateMessageDto } from './dto/create-message.dto';
import { BusinessAuditService } from '../audit/business-audit.service';

@Injectable()
export class LitigesService {
  private readonly allowedStatutTransitions: Record<
    LitigeStatut,
    LitigeStatut[]
  > = {
    [LitigeStatut.OUVERT]: [
      LitigeStatut.EN_COURS,
      LitigeStatut.REJETE,
      LitigeStatut.FERME,
    ],
    [LitigeStatut.EN_COURS]: [
      LitigeStatut.RESOLU,
      LitigeStatut.REJETE,
      LitigeStatut.FERME,
    ],
    [LitigeStatut.RESOLU]: [LitigeStatut.FERME],
    [LitigeStatut.FERME]: [],
    [LitigeStatut.REJETE]: [LitigeStatut.EN_COURS],
  };

  constructor(
    private readonly dataSource: DataSource,
    @InjectRepository(Litige)
    private readonly litigeRepository: Repository<Litige>,

    @InjectRepository(LitigeMessage)
    private readonly messageRepository: Repository<LitigeMessage>,
    private readonly businessAudit: BusinessAuditService,
  ) {}

  /**
   * Créer un nouveau litige
   */
  async create(
    createLitigeDto: CreateLitigeDto,
    userId: number,
  ): Promise<Litige> {
    return await this.dataSource.transaction(async (manager) => {
      const litigeRepo = manager.getRepository(Litige);
      const msgRepo = manager.getRepository(LitigeMessage);

      const MAX_RETRIES = 5;
      let lastError: unknown;

      for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
        try {
          const numLitige = await this.generateNumeroLitigeAtomic(manager);

          const litige = litigeRepo.create({
            num_litige: numLitige,
            type: createLitigeDto.type,
            priorite: createLitigeDto.priorite,
            objet: createLitigeDto.objet,
            description: createLitigeDto.description,
            contact_nom: createLitigeDto.contact_nom,
            contact_email: createLitigeDto.contact_email,
            contact_telephone: createLitigeDto.contact_telephone,
            metadata: createLitigeDto.metadata,
            createur: { id: userId } as any,
            statut: LitigeStatut.OUVERT,
            agence: { id: createLitigeDto.id_agence } as any,
            client: { id: createLitigeDto.id_client } as any,
            colis: createLitigeDto.id_colis
              ? ({ id: createLitigeDto.id_colis } as any)
              : null,
            facture: createLitigeDto.id_facture
              ? ({ id: createLitigeDto.id_facture } as any)
              : null,
            assigne: createLitigeDto.id_assigne
              ? ({ id: createLitigeDto.id_assigne } as any)
              : null,
          });

          const savedLitige = await litigeRepo.save(litige);

          const message = msgRepo.create({
            litige: { id: savedLitige.id } as any,
            auteur: { id: userId } as any,
            contenu: `Litige créé: ${createLitigeDto.objet}`,
            type: MessageType.MESSAGE,
          });

          await msgRepo.save(message);
          return await this.findOne(savedLitige.id);
        } catch (e: any) {
          lastError = e;
          const isUniqueViolation = e?.code === '23505';
          if (!isUniqueViolation) throw e;
        }
      }

      throw new BadRequestException(
        `Impossible de générer un numéro de litige unique après plusieurs tentatives.`,
        { cause: lastError as any },
      );
    });
  }

  /**
   * Récupérer tous les litiges avec filtres
   */
  async findAll(
    filters: {
      statut?: LitigeStatut;
      type?: string;
      agence_id?: number;
      assigne_id?: number;
      with_deleted?: boolean;
      page?: number;
      limit?: number;
    } = {},
  ) {
    const { page = 1, limit = 20, ...searchFilters } = filters;

    const where: FindOptionsWhere<Litige> = {};

    if (searchFilters.statut) where.statut = searchFilters.statut;
    if (searchFilters.type) where.type = searchFilters.type as any;
    if (searchFilters.agence_id) where.agence = { id: searchFilters.agence_id };
    if (searchFilters.assigne_id)
      where.assigne = { id: searchFilters.assigne_id };

    const [litiges, total] = await this.litigeRepository.findAndCount({
      where,
      withDeleted: !!searchFilters.with_deleted,
      relations: [
        'colis',
        'facture',
        'client',
        'agence',
        'createur',
        'assigne',
      ],
      order: { created_at: 'DESC' },
      skip: (page - 1) * limit,
      take: limit,
    });

    return {
      data: litiges,
      total,
      page,
      limit,
      totalPages: Math.ceil(total / limit),
    };
  }

  /**
   * Récupérer un litige par ID
   */
  async findOne(id: number): Promise<Litige> {
    const litige = await this.litigeRepository.findOne({
      where: { id },
      relations: [
        'colis',
        'facture',
        'client',
        'agence',
        'createur',
        'assigne',
        'messages',
        'messages.auteur',
      ],
      order: {
        messages: { created_at: 'ASC' },
      },
    });

    if (!litige) {
      throw new NotFoundException(`Litige avec ID ${id} non trouvé`);
    }

    return litige;
  }

  /**
   * Mettre à jour un litige
   */
  async update(
    id: number,
    updateLitigeDto: UpdateLitigeDto,
    userId: number,
  ): Promise<Litige> {
    const auditCtx: {
      num_litige: string;
      statut_avant: LitigeStatut;
      statut_apres: LitigeStatut;
    } = {
      num_litige: '',
      statut_avant: LitigeStatut.OUVERT,
      statut_apres: LitigeStatut.OUVERT,
    };

    await this.dataSource.transaction(async (manager) => {
      const litigeRepo = manager.getRepository(Litige);
      const msgRepo = manager.getRepository(LitigeMessage);

      const litige = await litigeRepo.findOne({
        where: { id },
        relations: ['assigne'],
      });

      if (!litige) {
        throw new NotFoundException(`Litige avec ID ${id} non trouvé`);
      }

      const ancienStatut = litige.statut;
      auditCtx.num_litige = litige.num_litige;
      auditCtx.statut_avant = ancienStatut;
      const ancienAssigne = litige.assigne?.id ?? null;

      // Champs simples
      if (updateLitigeDto.type) litige.type = updateLitigeDto.type;
      if (updateLitigeDto.priorite) litige.priorite = updateLitigeDto.priorite;
      if (updateLitigeDto.objet) litige.objet = updateLitigeDto.objet;
      if (updateLitigeDto.description)
        litige.description = updateLitigeDto.description;
      if (typeof updateLitigeDto.escalade === 'boolean')
        litige.escalade = updateLitigeDto.escalade;
      if (typeof updateLitigeDto.resolution === 'string')
        litige.resolution = updateLitigeDto.resolution;
      if (
        updateLitigeDto.montant_compensation !== undefined &&
        updateLitigeDto.montant_compensation !== null
      ) {
        litige.montant_compensation =
          updateLitigeDto.montant_compensation as any;
      }

      // Assignation
      if (updateLitigeDto.id_assigne !== undefined) {
        litige.assigne = updateLitigeDto.id_assigne
          ? ({ id: updateLitigeDto.id_assigne } as any)
          : null;
      }

      // Statut + dates
      if (updateLitigeDto.statut) {
        this.assertValidStatutTransition(litige.statut, updateLitigeDto.statut);
        litige.statut = updateLitigeDto.statut;
        switch (updateLitigeDto.statut) {
          case LitigeStatut.EN_COURS:
            if (!litige.date_premiere_reponse)
              litige.date_premiere_reponse = new Date();
            break;
          case LitigeStatut.RESOLU:
            litige.date_resolution = new Date();
            break;
          case LitigeStatut.FERME:
            litige.date_fermeture = new Date();
            break;
        }
      }

      await litigeRepo.save(litige);
      auditCtx.statut_apres = litige.statut;

      if (updateLitigeDto.statut && ancienStatut !== updateLitigeDto.statut) {
        await msgRepo.save(
          msgRepo.create({
            litige: { id } as any,
            auteur: { id: userId } as any,
            contenu: `Statut changé de "${ancienStatut}" vers "${updateLitigeDto.statut}"`,
            type: MessageType.CHANGEMENT_STATUT,
            metadata: {
              ancien_statut: ancienStatut,
              nouveau_statut: updateLitigeDto.statut,
            },
          }),
        );
      }

      if (
        updateLitigeDto.id_assigne !== undefined &&
        ancienAssigne !== (updateLitigeDto.id_assigne ?? null)
      ) {
        await msgRepo.save(
          msgRepo.create({
            litige: { id } as any,
            auteur: { id: userId } as any,
            contenu: `Litige assigné à un nouveau responsable`,
            type: MessageType.ASSIGNATION,
            metadata: {
              ancien_assigne: ancienAssigne,
              nouveau_assigne: updateLitigeDto.id_assigne ?? null,
            },
          }),
        );
      }
    });

    this.businessAudit.logEvent({
      action: 'litige.updated',
      entity: 'litige',
      entityId: String(id),
      userId,
      details: {
        num_litige: auditCtx.num_litige,
        statut_avant: auditCtx.statut_avant,
        statut_apres: auditCtx.statut_apres,
      },
    });

    return this.findOne(id);
  }

  /**
   * Supprimer un litige
   */
  async remove(id: number): Promise<void> {
    const litige = await this.findOne(id);
    await this.litigeRepository.softRemove(litige);
  }

  /**
   * Restaurer un litige supprimé (soft delete)
   */
  async restore(id: number): Promise<Litige> {
    const litige = await this.litigeRepository.findOne({
      where: { id },
      withDeleted: true,
    });

    if (!litige) {
      throw new NotFoundException(`Litige avec ID ${id} non trouvé`);
    }

    if (litige.deleted_at) {
      await this.litigeRepository.restore(id);
    }

    return this.findOne(id);
  }

  /**
   * Ajouter un message à un litige
   */
  async addMessage(
    litigeId: number,
    createMessageDto: CreateMessageDto,
    userId: number,
  ): Promise<LitigeMessage> {
    const exists = await this.litigeRepository.exist({
      where: { id: litigeId },
    });
    if (!exists) {
      throw new NotFoundException(`Litige avec ID ${litigeId} non trouvé`);
    }

    const message = this.messageRepository.create({
      ...createMessageDto,
      litige: { id: litigeId },
      auteur: { id: userId },
    });

    return await this.messageRepository.save(message);
  }

  /**
   * Récupérer les messages d'un litige (paginé)
   */
  async getMessages(
    litigeId: number,
    page = 1,
    limit = 20,
    order: 'ASC' | 'DESC' = 'ASC',
  ) {
    const exists = await this.litigeRepository.exist({
      where: { id: litigeId },
    });
    if (!exists) {
      throw new NotFoundException(`Litige avec ID ${litigeId} non trouvé`);
    }

    const safePage = Number.isFinite(page) && page > 0 ? page : 1;
    const safeLimit =
      Number.isFinite(limit) && limit > 0 ? Math.min(limit, 100) : 20;

    const [messages, total] = await this.messageRepository.findAndCount({
      where: { litige: { id: litigeId } },
      relations: ['auteur'],
      order: { created_at: order },
      skip: (safePage - 1) * safeLimit,
      take: safeLimit,
    });

    return {
      data: messages,
      total,
      page: safePage,
      limit: safeLimit,
      totalPages: Math.ceil(total / safeLimit),
    };
  }

  /**
   * Statistiques des litiges
   */
  async getStatistics(agenceId?: number) {
    const baseWhere: FindOptionsWhere<Litige> = {};
    if (agenceId) baseWhere.agence = { id: agenceId };

    const [totalLitiges, ouvert, enCours, resolu, ferme, enRetard] =
      await Promise.all([
        this.litigeRepository.count({ where: baseWhere }),
        this.litigeRepository.count({
          where: { ...baseWhere, statut: LitigeStatut.OUVERT },
        }),
        this.litigeRepository.count({
          where: { ...baseWhere, statut: LitigeStatut.EN_COURS },
        }),
        this.litigeRepository.count({
          where: { ...baseWhere, statut: LitigeStatut.RESOLU },
        }),
        this.litigeRepository.count({
          where: { ...baseWhere, statut: LitigeStatut.FERME },
        }),
        this.countLitigesEnRetard(agenceId),
      ]);

    return {
      total: totalLitiges,
      par_statut: {
        ouvert,
        en_cours: enCours,
        resolu,
        ferme,
      },
      en_retard: enRetard,
      taux_resolution:
        totalLitiges > 0 ? ((resolu + ferme) / totalLitiges) * 100 : 0,
    };
  }

  /**
   * Générer un numéro de litige de manière atomique
   */
  private async generateNumeroLitigeAtomic(
    manager: EntityManager,
  ): Promise<string> {
    const now = new Date();
    const mois = String(now.getMonth() + 1).padStart(2, '0');
    const annee = String(now.getFullYear()).slice(-2);

    const counterKey = `${annee}${mois}`;
    const rows = await manager.query(
      `
      INSERT INTO lbp_litige_counters (counter_key, sequence_value)
      VALUES ($1, 1)
      ON CONFLICT (counter_key)
      DO UPDATE SET sequence_value = lbp_litige_counters.sequence_value + 1
      RETURNING sequence_value
      `,
      [counterKey],
    );

    const numero = String(rows[0].sequence_value).padStart(3, '0');
    return `LIT-${mois}${annee}-${numero}`;
  }

  /**
   * Compter les litiges en retard
   */
  private async countLitigesEnRetard(agenceId?: number): Promise<number> {
    let query = this.litigeRepository
      .createQueryBuilder('litige')
      .where('litige.statut IN (:...statuts)', {
        statuts: [LitigeStatut.OUVERT, LitigeStatut.EN_COURS],
      }).andWhere(`
        CASE 
          WHEN litige.priorite = 'CRITIQUE' THEN DATE_PART('day', NOW() - litige.created_at) > 1
          WHEN litige.priorite = 'HAUTE' THEN DATE_PART('day', NOW() - litige.created_at) > 2
          ELSE DATE_PART('day', NOW() - litige.created_at) > 5
        END
      `);

    if (agenceId) {
      query = query.andWhere('litige.id_agence = :agenceId', { agenceId });
    }

    return await query.getCount();
  }

  private assertValidStatutTransition(
    currentStatut: LitigeStatut,
    nextStatut: LitigeStatut,
  ): void {
    if (currentStatut === nextStatut) return;
    const allowed = this.allowedStatutTransitions[currentStatut] ?? [];
    if (!allowed.includes(nextStatut)) {
      throw new BadRequestException(
        `Transition de statut invalide: ${currentStatut} -> ${nextStatut}`,
      );
    }
  }
}
