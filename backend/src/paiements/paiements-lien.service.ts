import {
  Injectable,
  NotFoundException,
  BadRequestException,
  Logger,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, DataSource } from 'typeorm';
import {
  LienPaiement,
  LienPaiementStatut,
} from './entities/lien-paiement.entity';
import { Facture } from '../factures/entities/facture.entity';
import { PaiementsService } from './paiements.service';
import { PaymentMode } from './entities/paiement.entity';
import * as crypto from 'crypto';

@Injectable()
export class PaiementLienService {
  private readonly logger = new Logger(PaiementLienService.name);

  constructor(
    @InjectRepository(LienPaiement)
    private lienRepository: Repository<LienPaiement>,
    @InjectRepository(Facture)
    private factureRepository: Repository<Facture>,
    private paiementsService: PaiementsService,
    private dataSource: DataSource,
  ) {}

  async createLink(factureId: number, montant?: number): Promise<LienPaiement> {
    const facture = await this.factureRepository.findOne({
      where: { id: factureId },
      relations: ['colis', 'colis.client'],
    });

    if (!facture) {
      throw new NotFoundException(`Facture #${factureId} non trouvée`);
    }

    const montantRestant =
      Number(facture.montant_ttc) - Number(facture.montant_paye);
    const finalMontant = montant || montantRestant;

    if (finalMontant <= 0) {
      throw new BadRequestException(
        'Le montant du lien doit être supérieur à 0',
      );
    }

    if (finalMontant > montantRestant) {
      throw new BadRequestException(
        'Le montant dépasse le solde restant de la facture',
      );
    }

    // Générer un token unique
    const token = crypto.randomBytes(32).toString('hex');

    // Expiration par défaut : 24 heures
    const date_expiration = new Date();
    date_expiration.setHours(date_expiration.getHours() + 24);

    const lien = this.lienRepository.create({
      token,
      facture,
      montant: finalMontant,
      statut: LienPaiementStatut.EN_ATTENTE,
      date_expiration,
    });

    return await this.lienRepository.save(lien);
  }

  async findByToken(token: string): Promise<LienPaiement> {
    const lien = await this.lienRepository.findOne({
      where: { token },
      relations: ['facture', 'facture.colis', 'facture.colis.client'],
    });

    if (!lien) {
      throw new NotFoundException('Lien de paiement invalide');
    }

    if (lien.statut !== LienPaiementStatut.EN_ATTENTE) {
      throw new BadRequestException(`Ce lien est déjà ${lien.statut}`);
    }

    if (new Date() > lien.date_expiration) {
      lien.statut = LienPaiementStatut.EXPIRE;
      await this.lienRepository.save(lien);
      throw new BadRequestException('Ce lien a expiré');
    }

    return lien;
  }

  async handleCallback(
    token: string,
    data: {
      status: string;
      provider: string;
      transaction_id: string;
      customer_name?: string;
    },
  ): Promise<LienPaiement> {
    const queryRunner = this.dataSource.createQueryRunner();
    await queryRunner.connect();
    await queryRunner.startTransaction();

    try {
      const lien = await this.lienRepository.findOne({
        where: { token },
        relations: ['facture', 'facture.colis'],
      });

      if (!lien || lien.statut !== LienPaiementStatut.EN_ATTENTE) {
        throw new BadRequestException('Lien invalide ou déjà traité');
      }

      if (data.status === 'SUCCESS') {
        lien.statut = LienPaiementStatut.PAYE;
        lien.moyen_paiement = data.provider;
        lien.metadata = data;
        lien.date_paiement = new Date();

        // Créer le paiement réel via le PaiementsService
        const modePaiement = this.mapProviderToMode(data.provider);

        await this.paiementsService.create(
          {
            id_facture: lien.facture.id,
            montant: lien.montant,
            mode_paiement: modePaiement,
            date_paiement: lien.date_paiement.toISOString(),
            reference_paiement: data.transaction_id,
          },
          'SYSTEM_ONLINE',
        ); // Utilisateur spécial pour les paiements en ligne

        await queryRunner.manager.save(lien);
        await queryRunner.commitTransaction();

        this.logger.log(
          `Paiement Mobile Money reçu pour facture ${lien.facture.num_facture} via ${data.provider}`,
        );
        return lien;
      } else {
        this.logger.warn(`Échec paiement pour lien ${token}: ${data.status}`);
        throw new BadRequestException("Le paiement n'a pas abouti");
      }
    } catch (err) {
      await queryRunner.rollbackTransaction();
      throw err;
    } finally {
      await queryRunner.release();
    }
  }

  private mapProviderToMode(provider: string): PaymentMode {
    const p = provider.toLowerCase();
    if (p.includes('orange')) return PaymentMode.ORANGE_MONEY;
    if (p.includes('wave')) return PaymentMode.WAVE;
    return PaymentMode.COMPTANT;
  }
}
