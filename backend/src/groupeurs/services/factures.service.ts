import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { GroupeurFacture } from '../entities/groupeur-facture.entity';
import { CreateFactureDto } from '../dto/create-facture.dto';

@Injectable()
export class FacturesService {
  constructor(
    @InjectRepository(GroupeurFacture)
    private readonly factureRepo: Repository<GroupeurFacture>,
  ) {}

  getParGroupeur(groupeurId: string) {
    return this.factureRepo.find({
      where: { groupeur_id: groupeurId },
      order: { created_at: 'DESC' },
      take: 500,
    });
  }

  async creer(dto: CreateFactureDto, groupeurId: string) {
    const row = this.factureRepo.create({
      ...dto,
      groupeur_id: groupeurId,
      expedition_id: (dto as any).expedition_id ?? null,
      devise: dto.devise ?? 'XOF',
      tva_pct: dto.tva_pct ?? 18,
      statut_paiement: (dto as any).statut_paiement ?? 'en_attente',
      montant_recu: dto.montant_recu ?? 0,
    } as any);
    return await this.factureRepo.save(row);
  }

  async modifier(
    id: string,
    dto: Partial<CreateFactureDto>,
    groupeurId: string,
  ) {
    const row = await this.factureRepo.findOne({
      where: { id, groupeur_id: groupeurId },
    });
    if (!row) throw new NotFoundException('Facture introuvable');
    if (row.statut_paiement !== 'en_attente') {
      throw new NotFoundException(
        'Modification autorisée uniquement si paiement = en_attente',
      );
    }
    Object.assign(row, dto);
    return await this.factureRepo.save(row);
  }
}
