import {
  Injectable,
  NotFoundException,
  BadRequestException,
  Logger,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, DataSource } from 'typeorm';
import { Paiement } from './entities/paiement.entity';
import { CreatePaiementDto } from './dto/create-paiement.dto';
import { Facture } from '../factures/entities/facture.entity';
import { Colis } from '../colis/entities/colis.entity';
import { CaisseService } from '../caisse/caisse.service';
import { MouvementType } from '../caisse/entities/mouvement-caisse.entity';

@Injectable()
export class PaiementsService {
  private readonly logger = new Logger(PaiementsService.name);

  constructor(
    @InjectRepository(Paiement)
    private paiementRepository: Repository<Paiement>,
    @InjectRepository(Facture)
    private factureRepository: Repository<Facture>,
    @InjectRepository(Colis)
    private colisRepository: Repository<Colis>,
    private dataSource: DataSource,
    private caisseService: CaisseService,
  ) {}

  async create(
    createPaiementDto: CreatePaiementDto,
    userId: string,
  ): Promise<Paiement> {
    const queryRunner = this.dataSource.createQueryRunner();
    await queryRunner.connect();
    await queryRunner.startTransaction();

    try {
      const { id_facture, ref_colis, ...paiementData } =
        createPaiementDto as any;

      // 1. Trouver la facture par id ou par ref_colis
      let facture: Facture | null = null;
      if (id_facture) {
        facture = await this.factureRepository.findOne({
          where: { id: id_facture },
          relations: ['colis', 'colis.client'],
        });
      } else if (ref_colis) {
        // Résoudre ref_colis → facture
        const colis = await this.colisRepository.findOne({
          where: { ref_colis },
        });
        if (!colis) {
          throw new NotFoundException(`Colis "${ref_colis}" introuvable`);
        }
        facture = await this.factureRepository.findOne({
          where: { colis: { id: colis.id }, etat: 1 }, // uniquement factures validées
          relations: ['colis', 'colis.client'],
          order: { created_at: 'DESC' },
        });
        if (!facture) {
          // Essayer aussi les proformas
          facture = await this.factureRepository.findOne({
            where: { colis: { id: colis.id } },
            relations: ['colis', 'colis.client'],
            order: { created_at: 'DESC' },
          });
        }
      }

      if (!facture) {
        throw new NotFoundException(`Aucune facture trouvée`);
      }

      // ✅ AJOUT: Gestion paiements partiels - Vérifier le montant restant
      const montantRestant =
        Number(facture.montant_ttc) - Number(facture.montant_paye);

      if (montantRestant <= 0) {
        throw new BadRequestException(
          `Cette facture est déjà entièrement payée (${facture.montant_paye}/${facture.montant_ttc} FCFA)`,
        );
      }

      if (Number(paiementData.montant) > montantRestant) {
        throw new BadRequestException(
          `Le montant du paiement (${paiementData.montant} FCFA) dépasse le montant restant (${montantRestant} FCFA)`,
        );
      }

      // 2. Business Logic: Check if payment doesn't exceed total
      // The new partial payment logic above replaces this check.
      // const remaining = Number(facture.montant_ttc) - Number(facture.montant_paye);
      // if (paiementData.montant > remaining && remaining > 0) {
      //     // Encaisser seulement le reste ? Ou bloquer ?
      //     // Ici on bloque pour éviter les erreurs de saisie
      //     // throw new BadRequestException(`Amount exceeds remaining balance: ${remaining}`);
      // }

      // 3. Create Paiement
      const paiement = this.paiementRepository.create({
        ...paiementData,
        facture,
        date_paiement: new Date(paiementData.date_paiement),
        code_user: userId,
      });

      const savedPaiement = (await queryRunner.manager.save(
        paiement,
      )) as unknown as Paiement;

      // 4. Update Facture totals
      facture.montant_paye =
        Number(facture.montant_paye) + Number(paiementData.montant);

      // ✅ AJOUT: Mise à jour du statut de paiement
      if (Number(facture.montant_paye) >= Number(facture.montant_ttc)) {
        facture.payment_status = 'paid';
        facture.etat = 1; // Définitive
        this.logger.log(
          `Facture ${facture.num_facture} entièrement payée et validée`,
        );
      } else if (Number(facture.montant_paye) > 0) {
        facture.payment_status = 'partial';
      } else {
        facture.payment_status = 'unpaid';
      }

      // Si c'était une proforma, elle devient tacitement définitive ou on gère ça via validation manuelle ?
      // Dans LBP, la validation est souvent manuelle avant paiement, mais on peut forcer ici.

      await queryRunner.manager.save(facture);

      // ✅ AJOUT: Créer automatiquement un mouvement de caisse
      const mouvementType = this.getMouvementTypeFromModePaiement(
        paiementData.mode_paiement,
      );
      await this.caisseService.createMovement(
        {
          montant: paiementData.montant,
          libelle: `Paiement facture ${facture.num_facture} - ${facture.colis.ref_colis}`,
          mode_reglement: paiementData.mode_paiement,
          num_dossier: facture.colis.ref_colis,
          nom_client: facture.colis.client.nom_exp,
          date_mouvement: paiementData.date_paiement,
        },
        mouvementType,
        userId,
      );

      await queryRunner.commitTransaction();
      return savedPaiement;
    } catch (err) {
      await queryRunner.rollbackTransaction();
      throw err;
    } finally {
      await queryRunner.release();
    }
  }

  async findAll(): Promise<Paiement[]> {
    return this.paiementRepository.find({
      relations: ['facture', 'facture.colis'],
      order: { created_at: 'DESC' },
    });
  }

  async findByFacture(factureId: number): Promise<Paiement[]> {
    return this.paiementRepository.find({
      where: { facture: { id: factureId } },
      order: { date_paiement: 'DESC' },
    });
  }

  async findByColis(refColis: string): Promise<Paiement[]> {
    return this.paiementRepository.find({
      where: { facture: { colis: { ref_colis: refColis } } },
      relations: ['facture'],
      order: { date_paiement: 'DESC' },
    });
  }

  /**
   * Calculer le montant restant à payer pour un colis (via sa référence)
   */
  async calculateRestantAPayer(refColis: string): Promise<{
    ref_colis: string;
    facture_num: string | null;
    montant_total: number;
    montant_paye: number;
    restant_a_payer: number;
  }> {
    const colis = await this.colisRepository.findOne({
      where: { ref_colis: refColis },
    });
    if (!colis) throw new NotFoundException(`Colis "${refColis}" introuvable`);

    const facture = await this.factureRepository.findOne({
      where: { colis: { id: colis.id } },
      order: { created_at: 'DESC' },
    });

    if (!facture) {
      return {
        ref_colis: refColis,
        facture_num: null,
        montant_total: 0,
        montant_paye: 0,
        restant_a_payer: 0,
      };
    }

    const montant_total = Number(facture.montant_ttc);
    const montant_paye = Number(facture.montant_paye);
    return {
      ref_colis: refColis,
      facture_num: facture.num_facture,
      montant_total,
      montant_paye,
      restant_a_payer: Math.max(0, montant_total - montant_paye),
    };
  }

  async findOne(id: number): Promise<Paiement> {
    const paiement = await this.paiementRepository.findOne({
      where: { id },
      relations: ['facture'],
    });
    if (!paiement) {
      throw new NotFoundException(`Paiement #${id} not found`);
    }
    return paiement;
  }

  async cancel(id: number): Promise<void> {
    const paiement = await this.findOne(id);
    if (paiement.etat_validation === 0) return;

    paiement.etat_validation = 0; // Annulé
    await this.paiementRepository.save(paiement);

    // Déduire le montant de la facture
    const facture = paiement.facture;
    facture.montant_paye =
      Number(facture.montant_paye) - Number(paiement.montant);
    await this.factureRepository.save(facture);
  }

  /**
   * ✅ AJOUT: Convertir mode de paiement en type de mouvement caisse
   */
  private getMouvementTypeFromModePaiement(
    modePaiement: string,
  ): MouvementType {
    const modeUpper = modePaiement.toUpperCase();

    if (modeUpper.includes('ESPECE') || modeUpper === 'CASH') {
      return MouvementType.ENTREE_ESPECE;
    } else if (modeUpper.includes('CHEQUE') || modeUpper === 'CHECK') {
      return MouvementType.ENTREE_CHEQUE;
    } else if (modeUpper.includes('VIREMENT') || modeUpper === 'TRANSFER') {
      return MouvementType.ENTREE_VIREMENT;
    }

    // Par défaut, considérer comme entrée espèces
    return MouvementType.ENTREE_ESPECE;
  }

  /**
   * ✅ AJOUT: Générer un reçu PDF pour un paiement
   */
  async generateReceipt(id: number): Promise<Buffer> {
    const paiement = await this.findOne(id);
    const PDFDocument = require('pdfkit');

    return new Promise((resolve, reject) => {
      try {
        const doc = new PDFDocument({ margin: 50, size: 'A5' });
        const chunks: Buffer[] = [];

        doc.on('data', (chunk: Buffer) => chunks.push(chunk));
        doc.on('end', () => resolve(Buffer.concat(chunks)));
        doc.on('error', reject);

        // En-tête
        doc.fontSize(18).text('LBP LOGISTICS', { align: 'center' });
        doc.fontSize(10).text("Abidjan, Côte d'Ivoire", { align: 'center' });
        doc.moveDown();

        // Titre
        doc
          .fontSize(14)
          .text('REÇU DE PAIEMENT', { align: 'center', underline: true });
        doc.moveDown();

        // Informations paiement
        doc.fontSize(10);
        doc.text(
          `Date: ${new Date(paiement.date_paiement).toLocaleDateString('fr-FR')}`,
        );
        doc.text(`Reçu N°: PAY-${paiement.id.toString().padStart(6, '0')}`);
        doc.moveDown();

        // Client
        doc.fontSize(11).text('CLIENT:', { underline: true });
        doc.fontSize(10);
        doc.text(`Nom: ${paiement.facture.colis.client.nom_exp}`);
        doc.text(`Téléphone: ${paiement.facture.colis.client.tel_exp}`);
        doc.moveDown();

        // Détails paiement
        doc.fontSize(11).text('DÉTAILS DU PAIEMENT:', { underline: true });
        doc.fontSize(10);
        doc.text(`Facture: ${paiement.facture.num_facture}`);
        doc.text(`Colis: ${paiement.facture.colis.ref_colis}`);
        doc.text(`Mode de paiement: ${paiement.mode_paiement}`);

        if (paiement.reference_paiement) {
          doc.text(`Référence: ${paiement.reference_paiement}`);
        }
        doc.moveDown();

        // Montants
        doc.fontSize(12).font('Helvetica-Bold');
        doc.text(`MONTANT PAYÉ: ${paiement.montant} FCFA`, { align: 'center' });
        doc.moveDown();

        doc.fontSize(10).font('Helvetica');
        const montantRestant =
          Number(paiement.facture.montant_ttc) -
          Number(paiement.facture.montant_paye);
        doc.text(`Montant total facture: ${paiement.facture.montant_ttc} FCFA`);
        doc.text(`Montant déjà payé: ${paiement.facture.montant_paye} FCFA`);
        doc.text(`Reste à payer: ${montantRestant} FCFA`, {
          fillColor: montantRestant > 0 ? 'red' : 'green',
        });

        // Pied de page
        doc.fillColor('black').fontSize(8);
        doc.text(
          'Merci de votre confiance - LBP Logistics',
          50,
          doc.page.height - 50,
          { align: 'center' },
        );

        doc.end();
      } catch (error) {
        reject(error);
      }
    });
  }

  async validate(id: number): Promise<Paiement> {
    const paiement = await this.findOne(id);
    paiement.etat_validation = 1; // Validé
    return await this.paiementRepository.save(paiement);
  }

  /**
   * Get daily payment history with paid and unpaid invoices
   */
  async getDailyPaymentHistory(date: Date, agenceId?: number) {
    const startOfDay = new Date(date);
    startOfDay.setHours(0, 0, 0, 0);
    const endOfDay = new Date(date);
    endOfDay.setHours(23, 59, 59, 999);

    // Get payments for the day
    const queryBuilder = this.paiementRepository
      .createQueryBuilder('paiement')
      .leftJoinAndSelect('paiement.facture', 'facture')
      .leftJoinAndSelect('facture.colis', 'colis')
      .leftJoinAndSelect('colis.client', 'client')
      .leftJoinAndSelect('colis.agence', 'agence')
      .where('paiement.date_paiement >= :startOfDay', { startOfDay })
      .andWhere('paiement.date_paiement <= :endOfDay', { endOfDay })
      .andWhere('paiement.etat_validation = 1');

    if (agenceId) {
      queryBuilder.andWhere('colis.agence.id = :agenceId', { agenceId });
    }

    const paiements = await queryBuilder
      .orderBy('paiement.date_paiement', 'DESC')
      .getMany();

    // Get unpaid invoices
    const unpaidInvoices = await this.getUnpaidInvoices(agenceId, 'all');

    // Calculate totals
    const totalPaye = paiements.reduce((sum, p) => sum + Number(p.montant), 0);
    const totalImpaye = unpaidInvoices.reduce(
      (sum, f) => sum + (Number(f.montant_ttc) - Number(f.montant_paye)),
      0,
    );

    return {
      date: date.toISOString().split('T')[0],
      agence: agenceId ? paiements[0]?.facture?.colis?.agence : undefined,
      totalPaye,
      totalImpaye,
      nombrePaiements: paiements.length,
      nombreFacturesImpayees: unpaidInvoices.length,
      paiements,
      facturesImpayees: unpaidInvoices,
    };
  }

  /**
   * Get all unpaid or partially paid invoices
   */
  async getUnpaidInvoices(
    agenceId?: number,
    status: 'all' | 'overdue' | 'pending' = 'all',
  ) {
    const queryBuilder = this.factureRepository
      .createQueryBuilder('facture')
      .leftJoinAndSelect('facture.colis', 'colis')
      .leftJoinAndSelect('colis.client', 'client')
      .leftJoinAndSelect('colis.agence', 'agence')
      .where('facture.payment_status IN (:...statuses)', {
        statuses: ['unpaid', 'partial'],
      })
      .andWhere('facture.etat != 2'); // Not cancelled

    if (agenceId) {
      queryBuilder.andWhere('colis.agence.id = :agenceId', { agenceId });
    }

    const factures = await queryBuilder
      .orderBy('facture.date_facture', 'DESC')
      .getMany();

    // Add computed fields
    const facturesWithDetails = factures.map((f) => ({
      ...f,
      montantRestant: Number(f.montant_ttc) - Number(f.montant_paye),
      estPayee: Number(f.montant_paye) >= Number(f.montant_ttc),
      joursDepuisCreation: Math.floor(
        (new Date().getTime() - new Date(f.date_facture).getTime()) /
          (1000 * 60 * 60 * 24),
      ),
    }));

    // Filter by status if needed
    if (status === 'overdue') {
      // Consider overdue if more than 30 days old and not paid
      return facturesWithDetails.filter((f) => f.joursDepuisCreation > 30);
    } else if (status === 'pending') {
      // Pending = not overdue yet
      return facturesWithDetails.filter((f) => f.joursDepuisCreation <= 30);
    }

    return facturesWithDetails;
  }

  /**
   * Get agency reconciliation for daily versement
   */
  async getAgencyReconciliation(date: Date, agenceId?: number) {
    const startOfDay = new Date(date);
    startOfDay.setHours(0, 0, 0, 0);
    const endOfDay = new Date(date);
    endOfDay.setHours(23, 59, 59, 999);

    const queryBuilder = this.paiementRepository
      .createQueryBuilder('paiement')
      .leftJoinAndSelect('paiement.facture', 'facture')
      .leftJoinAndSelect('facture.colis', 'colis')
      .leftJoinAndSelect('colis.agence', 'agence')
      .where('paiement.date_paiement >= :startOfDay', { startOfDay })
      .andWhere('paiement.date_paiement <= :endOfDay', { endOfDay })
      .andWhere('paiement.etat_validation = 1');

    if (agenceId) {
      queryBuilder.andWhere('colis.agence.id = :agenceId', { agenceId });
    }

    const paiements = await queryBuilder
      .orderBy('agence.nom', 'ASC')
      .addOrderBy('paiement.date_paiement', 'DESC')
      .getMany();

    // Group by agency
    const byAgency = paiements.reduce((acc, p) => {
      const agenceNom = p.facture?.colis?.agence?.nom || 'Sans agence';
      if (!acc[agenceNom]) {
        acc[agenceNom] = {
          agence: p.facture?.colis?.agence,
          montantEncaisse: 0,
          nombrePaiements: 0,
          paiements: [],
        };
      }
      acc[agenceNom].montantEncaisse += Number(p.montant);
      acc[agenceNom].nombrePaiements++;
      acc[agenceNom].paiements.push(p);
      return acc;
    }, {});

    return {
      date: date.toISOString().split('T')[0],
      reconciliation: Object.values(byAgency),
      totalGeneral: paiements.reduce((sum, p) => sum + Number(p.montant), 0),
    };
  }

  /**
   * Get overdue invoices for notifications
   */
  /**
   * Get consolidated tracking (Suivi) for invoices/colis
   */
  async getSuiviPaiements(params: any, user: any) {
    const page = Number(params.page) || 1;
    const limit = Number(params.limit) || 20;
    const skip = (page - 1) * limit;

    const { statut, search, date_debut, date_fin } = params;

    const queryBuilder = this.factureRepository
      .createQueryBuilder('facture')
      .leftJoinAndSelect('facture.colis', 'colis')
      .leftJoinAndSelect('colis.client', 'client')
      .leftJoinAndSelect('colis.agence', 'agence')
      .where('facture.etat != 2'); // Not cancelled

    // Role-based filtering
    const canSeeAll = ['ADMIN', 'DIRECTEUR'].includes(user.role);
    if (!canSeeAll && user.id_agence) {
      const agenceId = Number(user.id_agence);
      if (!isNaN(agenceId)) {
        queryBuilder.andWhere('colis.agence.id = :agenceId', { agenceId });
      }
    }

    // Search filtering (client, ref_colis, num_facture)
    if (search) {
      queryBuilder.andWhere(
        '(colis.ref_colis ILIKE :search OR facture.num_facture ILIKE :search OR client.nom_exp ILIKE :search)',
        { search: `%${search}%` },
      );
    }

    // Date range filtering
    if (date_debut) {
      queryBuilder.andWhere('facture.date_facture >= :date_debut', {
        date_debut,
      });
    }
    if (date_fin) {
      queryBuilder.andWhere('facture.date_facture <= :date_fin', { date_fin });
    }

    // Status filtering (calculated from amounts)
    if (statut) {
      if (statut === 'paye') {
        queryBuilder.andWhere('facture.montant_paye >= facture.montant_ttc');
      } else if (statut === 'partiel') {
        queryBuilder.andWhere(
          'facture.montant_paye > 0 AND facture.montant_paye < facture.montant_ttc',
        );
      } else if (statut === 'impaye') {
        queryBuilder.andWhere('facture.montant_paye = 0');
      }
    }

    // ─── 1. GLOBAL STATS (Clone BEFORE pagination) ──────────────
    const allStatsQuery = queryBuilder.clone();
    allStatsQuery.select([
      'COUNT(*) FILTER (WHERE facture.montant_paye >= facture.montant_ttc) AS paye_count',
      'COUNT(*) FILTER (WHERE facture.montant_paye > 0 AND facture.montant_paye < facture.montant_ttc) AS partiel_count',
      'COUNT(*) FILTER (WHERE facture.montant_paye = 0) AS impaye_count',
      'SUM(facture.montant_paye) AS total_paye',
      'SUM(facture.montant_ttc - facture.montant_paye) AS total_restant',
    ]);

    const statsRaw = await allStatsQuery.getRawOne();

    // ─── 2. PAGINATED DATA ────────────────────────────────────────
    const [factures, total] = await queryBuilder
      .orderBy('facture.created_at', 'DESC')
      .skip(skip)
      .take(limit)
      .getManyAndCount();

    // 3. Fetch details for items
    const items = await Promise.all(
      factures.map(async (f) => {
        const lastPaiement = await this.paiementRepository.findOne({
          where: { facture: { id: f.id }, etat_validation: 1 },
          order: { date_paiement: 'DESC' },
        });

        const nbPaiements = await this.paiementRepository.count({
          where: { facture: { id: f.id }, etat_validation: 1 },
        });

        const paye = Number(f.montant_paye);
        const totalM = Number(f.montant_ttc);
        const status: 'paye' | 'partiel' | 'impaye' =
          paye >= totalM ? 'paye' : paye > 0 ? 'partiel' : 'impaye';

        return {
          id: f.id,
          ref_colis: f.colis?.ref_colis || '-',
          facture_num: f.num_facture,
          nom_client: f.colis?.client?.nom_exp || 'Inconnu',
          tel_client: f.colis?.client?.tel_exp,
          montant_total: totalM,
          montant_paye: paye,
          restant_a_payer: Math.max(0, totalM - paye),
          statut: status,
          dernier_paiement_date: lastPaiement?.date_paiement,
          dernier_mode_paiement: lastPaiement?.mode_paiement,
          nb_paiements: nbPaiements,
          date_creation: f.date_facture,
        };
      }),
    );

    return {
      data: items,
      total,
      page,
      limit,
      total_pages: Math.ceil(total / limit),
      stats: {
        paye: Number(statsRaw?.paye_count || 0),
        partiel: Number(statsRaw?.partiel_count || 0),
        impaye: Number(statsRaw?.impaye_count || 0),
        totalEncaissé: Number(statsRaw?.total_paye || 0),
        totalRestant: Number(statsRaw?.total_restant || 0),
      },
    };
  }

  /**
   * Get overdue invoices for notifications
   */
  async getOverdueInvoices() {
    return this.getUnpaidInvoices(undefined, 'overdue');
  }
}
