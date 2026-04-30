import {
  Injectable,
  NotFoundException,
  BadRequestException,
  Logger,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, DataSource } from 'typeorm';
import { Colis, Marchandise, ColisStatutSuivi } from './entities/colis.entity';
import { CreateColisDto } from './dto/create-colis.dto';
import { Client } from '../clients/entities/client.entity';
import { FacturesService } from '../factures/factures.service';
import { TarifsService } from '../tarifs/tarifs.service';
import { Tarif } from '../tarifs/entities/tarif.entity';
import { WhatsappService } from '../notifications/whatsapp.service';
import { Agence } from '../agences/entities/agence.entity';
import { effectiveRoleCode } from '../common/effective-role-code';

@Injectable()
export class ColisService {
  private readonly logger = new Logger(ColisService.name);
  constructor(
    @InjectRepository(Colis)
    private colisRepository: Repository<Colis>,
    @InjectRepository(Marchandise)
    private marchandiseRepository: Repository<Marchandise>,
    @InjectRepository(Client)
    private clientRepository: Repository<Client>,
    private facturesService: FacturesService,
    private tarifsService: TarifsService,
    private whatsappService: WhatsappService,
    private dataSource: DataSource,
  ) {}

  /** Colis : vue réseau (même principe que factures / caisse consolidée). */
  private userSeesAllColis(user: any): boolean {
    const rc = effectiveRoleCode(user).toUpperCase();
    return [
      'ADMIN',
      'DIRECTEUR',
      'ASSISTANT_DG',
      'SUPERVISEUR_REGIONAL',
      'SUPERVISEURE_GENERALE',
    ].includes(rc);
  }

  private hasGlobalAgencyRouting(user: any): boolean {
    const r =
      typeof user?.role === 'string'
        ? user.role
        : user?.role?.code ?? user?.roleEntity?.code;
    return Boolean(
      user?.peut_voir_toutes_agences ||
        user?.code_acces === 2 ||
        user?.code_acces === 1 ||
        r === 'DIRECTEUR' ||
        r === 'ADMIN' ||
        r === 'SUPER_ADMIN',
    );
  }

  async resolveAgenceIdForCreate(
    explicitFromDto: number | undefined,
    user: any,
  ): Promise<number | undefined> {
    if (
      explicitFromDto != null &&
      !Number.isNaN(Number(explicitFromDto)) &&
      Number(explicitFromDto) > 0
    ) {
      return Number(explicitFromDto);
    }
    const fromUser = user?.agence?.id ?? user?.id_agence;
    if (fromUser != null && Number(fromUser) > 0) {
      return Number(fromUser);
    }
    if (!this.hasGlobalAgencyRouting(user)) {
      return undefined;
    }
    const rows = await this.dataSource.query(
      `SELECT id FROM agences WHERE actif = true AND (code = 'DG' OR code LIKE 'CI-%')
       ORDER BY CASE WHEN code = 'DG' THEN 0 ELSE 1 END, id ASC LIMIT 1`,
    );
    return rows[0]?.id != null ? Number(rows[0].id) : undefined;
  }

  async create(
    createColisDto: CreateColisDto,
    userId: string,
    agenceId?: number,
  ): Promise<Colis> {
    const queryRunner = this.dataSource.createQueryRunner();
    await queryRunner.connect();
    await queryRunner.startTransaction();

    try {
      const { id_client, marchandises, ...colisData } = createColisDto;

      // 1. Check client
      const client = await this.clientRepository.findOne({
        where: { id: id_client },
      });
      if (!client) {
        throw new NotFoundException(`Client with ID ${id_client} not found`);
      }

      // 2. Generate reference (préfixe par type de colis)
      const agencePays = agenceId
        ? (
            await queryRunner.manager
              .getRepository(Agence)
              .findOne({ where: { id: agenceId } })
          )?.pays
        : undefined;
      const refColis = await this.generateReference(
        agencePays,
        createColisDto.mode_envoi,
        createColisDto.trafic_envoi,
        createColisDto.forme_envoi,
      );

      const isGroupage = createColisDto.forme_envoi === 'groupage';

      // 3. Create Colis — groupage : enregistré comme validé + proforma générée juste après (plus d'étape manuelle)
      const colis = this.colisRepository.create({
        ...colisData,
        ref_colis: refColis,
        client,
        code_user: userId,
        agence: agenceId ? ({ id: agenceId } as any) : undefined,
        date_envoi: new Date(createColisDto.date_envoi),
        etat_validation: isGroupage ? 1 : 0,
      });

      const savedColis = await queryRunner.manager.save(colis);

      // 4. Create Marchandises with financial snapshots
      if (marchandises && marchandises.length > 0) {
        const marchandiseEntities: Marchandise[] = [];
        for (const m of marchandises) {
          let cout_reel = 0;
          let charges_reelles = 0;
          let tarifEntity: Tarif | undefined = undefined;

          if (m.id_tarif) {
            tarifEntity = await this.tarifsService.findOne(m.id_tarif);
            if (tarifEntity) {
              cout_reel =
                Number(tarifEntity.cout_transport_kg || 0) *
                Number(m.poids_total || 0);
              charges_reelles =
                Number(tarifEntity.charges_fixes_unit || 0) *
                Number(m.nbre_colis || 0);
            }
          }

          const marchandise: any = this.marchandiseRepository.create({
            ...m,
            colis: savedColis,
            tarif: tarifEntity,
            cout_reel: cout_reel,
            charges_reelles: charges_reelles,
          } as any);
          marchandiseEntities.push(marchandise);
        }
        await queryRunner.manager.save(Marchandise, marchandiseEntities);
        savedColis.marchandises = marchandiseEntities;
      }

      await queryRunner.commitTransaction();

      if (isGroupage) {
        try {
          await this.facturesService.generateFromColis(savedColis.id, userId);
        } catch (e: any) {
          const msg = e?.message ?? String(e);
          if (msg.includes('existe déjà')) {
            this.logger.warn(
              `Facture déjà présente pour colis #${savedColis.id}: ${msg}`,
            );
          } else {
            this.logger.error(
              `Échec génération facture proforma groupage (colis #${savedColis.id}): ${msg}`,
            );
          }
        }
      }

      // 📱 Notification au client (non-bloquante)
      if (client.tel_exp) {
        const destination =
          savedColis.lieu_dest || savedColis.nom_dest || 'destination';
        this.whatsappService
          .notifyColisCreated(
            client.nom_exp,
            client.tel_exp,
            savedColis.ref_colis,
            destination,
          )
          .catch((err) => {
            this.logger?.warn?.(
              `Erreur notification création colis: ${err.message}`,
            );
          });
      }

      return savedColis;
    } catch (err) {
      await queryRunner.rollbackTransaction();
      throw err;
    } finally {
      await queryRunner.release();
    }
  }

  async update(
    id: number,
    updateColisDto: CreateColisDto,
    userId: string,
    agenceId?: number,
  ): Promise<Colis> {
    const queryRunner = this.dataSource.createQueryRunner();
    await queryRunner.connect();
    await queryRunner.startTransaction();

    try {
      const { id_client, marchandises, ...colisData } = updateColisDto;

      // 1. Charger le colis existant
      const colis = await this.colisRepository.findOne({
        where: { id },
        relations: ['client', 'marchandises'],
      });
      if (!colis) {
        throw new NotFoundException(`Colis #${id} not found`);
      }

      // 2. Vérifier le client
      const client = await this.clientRepository.findOne({
        where: { id: id_client },
      });
      if (!client) {
        throw new NotFoundException(`Client with ID ${id_client} not found`);
      }

      // 3. Mettre à jour les infos du colis (on garde la même ref_colis)
      colis.trafic_envoi = colisData.trafic_envoi;
      colis.forme_envoi = colisData.forme_envoi;

      if (typeof colisData.mode_envoi !== 'undefined') {
        colis.mode_envoi = colisData.mode_envoi;
      }
      colis.livraison = colisData.livraison ?? false;

      colis.date_envoi = new Date(updateColisDto.date_envoi);
      colis.client = client;
      colis.nom_dest = colisData.nom_dest;

      if (typeof colisData.lieu_dest !== 'undefined') {
        colis.lieu_dest = colisData.lieu_dest;
      }
      if (typeof colisData.tel_dest !== 'undefined') {
        colis.tel_dest = colisData.tel_dest;
      }
      if (typeof colisData.email_dest !== 'undefined') {
        colis.email_dest = colisData.email_dest;
      }

      if (typeof colisData.nom_recup !== 'undefined') {
        colis.nom_recup = colisData.nom_recup;
      }
      if (typeof colisData.adresse_recup !== 'undefined') {
        colis.adresse_recup = colisData.adresse_recup;
      }
      if (typeof colisData.tel_recup !== 'undefined') {
        colis.tel_recup = colisData.tel_recup;
      }
      if (typeof colisData.email_recup !== 'undefined') {
        colis.email_recup = colisData.email_recup;
      }

      colis.code_user = userId;
      if (typeof agenceId !== 'undefined') {
        colis.agence = agenceId ? ({ id: agenceId } as any) : null;
      }

      const savedColis = await queryRunner.manager.save(colis);

      // 4. Supprimer les anciennes marchandises et recréer à partir du DTO avec snapshots
      await queryRunner.manager.delete(Marchandise, {
        colis: { id: savedColis.id } as any,
      });

      if (marchandises && marchandises.length > 0) {
        const marchandiseEntities: Marchandise[] = [];
        for (const m of marchandises) {
          let cout_reel = 0;
          let charges_reelles = 0;
          let tarifEntity: Tarif | undefined = undefined;

          if (m.id_tarif) {
            tarifEntity = await this.tarifsService.findOne(m.id_tarif);
            if (tarifEntity) {
              cout_reel =
                Number(tarifEntity.cout_transport_kg || 0) *
                Number(m.poids_total || 0);
              charges_reelles =
                Number(tarifEntity.charges_fixes_unit || 0) *
                Number(m.nbre_colis || 0);
            }
          }

          const marchandise: any = this.marchandiseRepository.create({
            ...m,
            colis: savedColis,
            tarif: tarifEntity,
            cout_reel: cout_reel,
            charges_reelles: charges_reelles,
          } as any);
          marchandiseEntities.push(marchandise);
        }
        await queryRunner.manager.save(Marchandise, marchandiseEntities);
        savedColis.marchandises = marchandiseEntities;
      } else {
        savedColis.marchandises = [];
      }

      await queryRunner.commitTransaction();
      return savedColis;
    } catch (err) {
      await queryRunner.rollbackTransaction();
      throw err;
    } finally {
      await queryRunner.release();
    }
  }

  private getPrefixFromAgencePays(pays?: string): string {
    const p = (pays || '').trim().toLowerCase();
    const norm = p.normalize('NFD').replace(/\p{M}/gu, '');
    if (
      norm.includes('ivoire') ||
      norm.includes('civ') ||
      /^ci$/i.test(pays?.trim() || '') ||
      /cote\s*d\s*ivoire/i.test(norm)
    ) {
      return 'LB-CI';
    }
    if (norm.includes('senegal') || /^sn$/i.test(pays?.trim() || '')) {
      return 'LB-SEN';
    }
    if (norm.includes('france') || /^fr$/i.test(pays?.trim() || '')) {
      return 'LB-FR';
    }
    return 'LBP';
  }

  private getPrefixFromColisType(
    modeEnvoi?: string,
    traficEnvoi?: string,
    formeEnvoi?: string,
    agencePays?: string,
  ): string {
    const mode = (modeEnvoi || '').toLowerCase().trim();
    const trafic = (traficEnvoi || '').toLowerCase().trim();

    // Colis Rapides Export (CI → FR)
    if (mode.includes('export')) return 'CA-CI';

    // Colis Rapides Import (FR → CI)
    if (mode.includes('import')) return 'CA-FR';

    // DHL
    if (mode === 'dhl') return 'DL-CI';

    // Groupage Maritime : export (CI) vs import (FR) selon pays agence
    if (trafic.includes('maritime')) {
      const pays = (agencePays || '').trim().toLowerCase();
      const norm = pays.normalize('NFD').replace(/\p{M}/gu, '');
      if (norm.includes('france') || /^fr$/i.test(pays)) return 'MP-FR';
      return 'MP-CI';
    }

    // Fallback : Groupage Aérien et autres → préfixe pays agence
    return this.getPrefixFromAgencePays(agencePays);
  }

  private async generateReference(
    agencePays?: string,
    modeEnvoi?: string,
    traficEnvoi?: string,
    formeEnvoi?: string,
  ): Promise<string> {
    const now = new Date();
    const mm = String(now.getMonth() + 1).padStart(2, '0');
    const yy = String(now.getFullYear()).slice(-2);
    const datePart = `${mm}${yy}`;
    const prefix = this.getPrefixFromColisType(modeEnvoi, traficEnvoi, formeEnvoi, agencePays);

    // Find the last reference for this month/year
    const lastColis = await this.colisRepository
      .createQueryBuilder('colis')
      .where('colis.ref_colis LIKE :pattern', {
        pattern: `${prefix}-${datePart}-%`,
      })
      .orderBy('colis.id', 'DESC')
      .getOne();

    let nextNumber = 1;
    if (lastColis) {
      const lastRef = lastColis.ref_colis;
      const parts = lastRef.split('-');
      // LBP-0326-001 → dernier segment = compteur ; LB-CI-0326-001 → idem (4 segments)
      const seqStr = parts[parts.length - 1];
      const lastNum = parseInt(seqStr, 10);
      if (!Number.isNaN(lastNum)) {
        nextNumber = lastNum + 1;
      }
    }

    const numPart = String(nextNumber).padStart(3, '0');
    return `${prefix}-${datePart}-${numPart}`;
  }

  async findAll(query: any, user: any): Promise<any> {
    const where: any = {};

    const canSeeAll = this.userSeesAllColis(user);

    if (!canSeeAll && user.id_agence) {
      where.agence = { id: user.id_agence };
    }

    if (query.forme_envoi) {
      where.forme_envoi = query.forme_envoi;
    }

    const [data, total] = await this.colisRepository.findAndCount({
      where,
      relations: ['client', 'marchandises'],
      order: { created_at: 'DESC' },
      ...(query.limit ? { take: query.limit } : {}),
      ...(query.page && query.limit
        ? { skip: (query.page - 1) * query.limit }
        : {}),
    });

    return { data, total };
  }

  async findOne(id: number): Promise<Colis> {
    const colis = await this.colisRepository.findOne({
      where: { id },
      relations: ['client', 'marchandises'],
    });
    if (!colis) {
      throw new NotFoundException(`Colis #${id} not found`);
    }
    return colis;
  }

  async validateColis(id: number): Promise<Colis> {
    const colis = await this.findOne(id);
    colis.etat_validation = 1;
    return await this.colisRepository.save(colis);
  }

  async searchColis(
    searchTerm: string,
    formeEnvoi: string,
    user: any,
  ): Promise<Colis[]> {
    const query = this.colisRepository
      .createQueryBuilder('colis')
      .leftJoinAndSelect('colis.client', 'client')
      .leftJoinAndSelect('colis.marchandises', 'marchandises')
      .where(
        '(colis.ref_colis ILIKE :search OR client.nom_exp ILIKE :search OR colis.nom_dest ILIKE :search)',
        { search: `%${searchTerm}%` },
      );

    if (formeEnvoi) {
      query.andWhere('colis.forme_envoi = :formeEnvoi', { formeEnvoi });
    }

    const canSeeAll = this.userSeesAllColis(user);
    if (!canSeeAll && user.id_agence) {
      query.andWhere('colis.id_agence = :agenceId', {
        agenceId: user.id_agence,
      });
    }

    return await query.getMany();
  }

  async trackColis(refColis: string): Promise<any> {
    const colis = await this.colisRepository.findOne({
      where: { ref_colis: refColis },
      relations: ['client', 'marchandises'],
    });

    if (!colis) {
      throw new NotFoundException(`Colis ${refColis} not found`);
    }

    // Simuler des étapes de suivi basées sur l'état
    const steps = [
      {
        title: 'Colis enregistré',
        date: colis.created_at,
        location: 'Agence LBP',
      },
    ];

    if (colis.etat_validation === 1) {
      steps.push({
        title: 'Colis validé et prêt pour expédition',
        date: colis.updated_at,
        location: 'Entrepôt LBP',
      });
    }

    // ✅ AJOUT: Vérifier le statut du paiement
    const facture = await this.dataSource
      .getRepository('Facture')
      .findOne({ where: { colis: { id: colis.id } } });

    const paymentStatus = facture
      ? Number(facture.montant_paye) >= Number(facture.montant_ttc)
        ? 'Payé'
        : 'En attente'
      : 'Non facturé';

    return {
      ref_colis: colis.ref_colis,
      status: colis.etat_validation === 1 ? 'En cours' : 'Brouillon',
      statut_suivi: colis.statut_suivi,
      payment_status: paymentStatus,
      current_location: this.getLocationByStatut(colis.statut_suivi),
      steps,
      client_colis: colis.client,
      colis,
    };
  }

  private getLocationByStatut(statut: ColisStatutSuivi): string {
    switch (statut) {
      case ColisStatutSuivi.EMBALLE:
        return 'Agence de départ (CI)';
      case ColisStatutSuivi.EXPEDIE:
        return 'En transit international';
      case ColisStatutSuivi.REC_BOBIGNY:
        return 'Hub Bobigny (France)';
      case ColisStatutSuivi.EN_LIVRAISON:
        return 'En cours de livraison locale';
      case ColisStatutSuivi.LIVRE:
        return 'Livré au destinataire';
      default:
        return 'LBP Logistics';
    }
  }

  /**
   * ✅ AJOUT: Supprimer un colis avec vérifications
   */
  async remove(id: number, user: any): Promise<void> {
    const colis = await this.findOne(id);

    // Vérifier si le colis a déjà une facture
    const facture = await this.dataSource
      .getRepository('Facture')
      .findOne({ where: { colis: { id } } });

    if (facture) {
      throw new BadRequestException(
        "Impossible de supprimer ce colis car il possède déjà une facture. Veuillez d'abord supprimer la facture.",
      );
    }

    // Vérifier si le colis est validé
    if (colis.etat_validation === 1) {
      throw new BadRequestException(
        "Impossible de supprimer un colis validé. Veuillez le dé-valider d'abord.",
      );
    }

    // Supprimer le colis (les marchandises seront supprimées en cascade)
    await this.colisRepository.delete(id);
  }

  /**
   * ✅ AJOUT: Marquer un colis comme reçu au hub (Destination)
   */
  async receiveAtHub(id: number): Promise<Colis> {
    const colis = await this.colisRepository.findOne({
      where: { id },
      relations: [
        'client',
        'expedition',
        'expedition.agence_destination',
        'agence',
      ],
    });

    if (!colis) {
      throw new NotFoundException(`Colis #${id} non trouvé`);
    }

    colis.statut_suivi = ColisStatutSuivi.REC_BOBIGNY; // On garde ce nom d'enum pour le moment
    const savedColis = await this.colisRepository.save(colis);

    // Envoyer la notification
    if (colis.client && colis.client.tel_exp) {
      let location = 'Bobigny';
      let address = 'PARIS 17 CHEMIN DES VIGNES 93000 BOBIGNY';

      // Si c'est une expédition vers une autre agence (ex: CI, SEN)
      if (colis.expedition?.agence_destination) {
        location = colis.expedition.agence_destination.nom;
        address = colis.expedition.agence_destination.adresse || '';
      } else if (
        colis.lieu_dest &&
        !colis.lieu_dest.toLowerCase().includes('france')
      ) {
        // Heuristique simple si pas d'agence de destination liée
        location = colis.lieu_dest;
        address = '';
      }

      await this.whatsappService.notifyArrivalAtHub(
        colis.client.nom_exp,
        colis.client.tel_exp,
        colis.ref_colis,
        location,
        address,
      );
    }

    return savedColis;
  }
}
