import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { Tarif } from './entities/tarif.entity';

@Injectable()
export class TarifsService {
  constructor(
    @InjectRepository(Tarif)
    private tarifRepository: Repository<Tarif>,
  ) {}

  async create(createTarifDto: Partial<Tarif>): Promise<Tarif> {
    const tarif = this.tarifRepository.create(createTarifDto);
    return await this.tarifRepository.save(tarif);
  }

  async findAll(): Promise<Tarif[]> {
    return await this.tarifRepository.find({ order: { nom: 'ASC' } });
  }

  async findOne(id: number): Promise<Tarif> {
    const tarif = await this.tarifRepository.findOne({ where: { id } });
    if (!tarif) {
      throw new NotFoundException(`Tarif #${id} non trouvé`);
    }
    return tarif;
  }

  async update(id: number, updateTarifDto: any): Promise<Tarif> {
    const tarif = await this.findOne(id);
    Object.assign(tarif, updateTarifDto);
    return await this.tarifRepository.save(tarif);
  }

  async remove(id: number): Promise<void> {
    const tarif = await this.findOne(id);
    await this.tarifRepository.remove(tarif);
  }
}
