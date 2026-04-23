import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { GroupeurDevis } from '../entities/groupeur-devis.entity';
import { CreateDevisDto } from '../dto/create-devis.dto';

@Injectable()
export class DevisService {
  constructor(
    @InjectRepository(GroupeurDevis)
    private readonly devisRepo: Repository<GroupeurDevis>,
  ) {}

  getParGroupeur(groupeurId: string) {
    return this.devisRepo.find({
      where: { groupeur_id: groupeurId },
      order: { created_at: 'DESC' },
      take: 500,
    });
  }

  async creer(dto: CreateDevisDto, groupeurId: string) {
    const row = this.devisRepo.create({
      ...dto,
      groupeur_id: groupeurId,
      statut: (dto as any).statut ?? 'brouillon',
      devise: dto.devise ?? 'XOF',
      validite_jours: dto.validite_jours ?? 15,
    } as any);
    return await this.devisRepo.save(row);
  }

  async modifier(id: string, dto: Partial<CreateDevisDto>, groupeurId: string) {
    const row = await this.devisRepo.findOne({
      where: { id, groupeur_id: groupeurId },
    });
    if (!row) throw new NotFoundException('Devis introuvable');
    Object.assign(row, dto);
    return await this.devisRepo.save(row);
  }

  async supprimer(id: string, groupeurId: string) {
    const row = await this.devisRepo.findOne({
      where: { id, groupeur_id: groupeurId },
    });
    if (!row) throw new NotFoundException('Devis introuvable');
    if (row.statut !== 'brouillon') {
      throw new NotFoundException(
        'Suppression autorisée uniquement pour les brouillons',
      );
    }
    await this.devisRepo.delete({ id });
    return { ok: true };
  }
}
