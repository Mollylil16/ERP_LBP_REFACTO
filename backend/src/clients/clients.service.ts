import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { Client } from './entities/client.entity';

@Injectable()
export class ClientsService {
  constructor(
    @InjectRepository(Client)
    private clientsRepository: Repository<Client>,
  ) {}

  async create(clientData: Partial<Client>): Promise<Client> {
    const client = this.clientsRepository.create(clientData);
    return await this.clientsRepository.save(client);
  }

  async findAll(): Promise<Client[]> {
    return this.clientsRepository.find({
      order: { nom_exp: 'ASC' },
    });
  }

  async findOne(id: number): Promise<Client> {
    const client = await this.clientsRepository.findOne({ where: { id } });
    if (!client) {
      throw new NotFoundException(`Client #${id} not found`);
    }
    return client;
  }

  async searchClients(searchTerm: string): Promise<Client[]> {
    return this.clientsRepository.find({
      where: [
        { nom_exp: searchTerm }, // TypeORM doesn't support ILIKE directly in simple find easily for all DBs, but PostgreSQL does
        // Better use QueryBuilder for partial match
      ],
    });
  }

  // Version augmentée avec QueryBuilder pour plus de flexibilité (ILIKE)
  async search(searchTerm: string): Promise<Client[]> {
    return this.clientsRepository
      .createQueryBuilder('client')
      .where('client.nom_exp ILIKE :search OR client.tel_exp ILIKE :search', {
        search: `%${searchTerm}%`,
      })
      .getMany();
  }

  async getClientHistory(id: number): Promise<any[]> {
    const client = await this.findOne(id);
    // On récupère les colis via le repository colis (ou via relation si définie)
    // Pour l'instant, on fait un mock ou on utilise QueryBuilder
    return []; // À implémenter avec ColisRepository
  }
}
