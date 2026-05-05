import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { Client } from './entities/client.entity';

const SEES_ALL_CLIENTS_ROLES = [
  'ADMIN', 'DIRECTEUR', 'ASSISTANT_DG',
  'SUPERVISEUR_REGIONAL', 'SUPERVISEURE_GENERALE',
  'AGENT_EXPLOITATION', 'CAISSIER',
];

@Injectable()
export class ClientsService {
  constructor(
    @InjectRepository(Client)
    private clientsRepository: Repository<Client>,
  ) {}

  private userSeesAllClients(user?: any): boolean {
    if (!user) return true;
    const rc = (typeof user.role === 'string' ? user.role : user?.role?.code ?? '').toUpperCase();
    if (SEES_ALL_CLIENTS_ROLES.includes(rc)) return true;
    if (user?.peut_voir_toutes_agences === true) return true;
    if (Number(user?.code_acces) === 2) return true;
    return false;
  }

  async create(clientData: Partial<Client>): Promise<Client> {
    const client = this.clientsRepository.create(clientData);
    return await this.clientsRepository.save(client);
  }

  async findAll(user?: any): Promise<Client[]> {
    const qb = this.clientsRepository
      .createQueryBuilder('client')
      .leftJoinAndSelect('client.colis', 'colis')
      .leftJoinAndSelect('colis.agence', 'agence')
      .orderBy('client.nom_exp', 'ASC');

    if (!this.userSeesAllClients(user) && user?.id_agence) {
      qb.where('agence.id = :agenceId', { agenceId: Number(user.id_agence) });
    }

    return qb.getMany();
  }

  async findOne(id: number): Promise<Client> {
    const client = await this.clientsRepository.findOne({ where: { id } });
    if (!client) {
      throw new NotFoundException(`Client #${id} not found`);
    }
    return client;
  }

  async searchClients(searchTerm: string): Promise<Client[]> {
    return this.clientsRepository
      .createQueryBuilder('client')
      .where('client.nom_exp ILIKE :search', { search: `%${searchTerm}%` })
      .getMany();
  }

  async search(searchTerm: string, user?: any): Promise<Client[]> {
    const qb = this.clientsRepository
      .createQueryBuilder('client')
      .leftJoinAndSelect('client.colis', 'colis')
      .leftJoinAndSelect('colis.agence', 'agence')
      .where('client.nom_exp ILIKE :search OR client.tel_exp ILIKE :search', {
        search: `%${searchTerm}%`,
      });

    if (!this.userSeesAllClients(user) && user?.id_agence) {
      qb.andWhere('agence.id = :agenceId', { agenceId: Number(user.id_agence) });
    }

    return qb.getMany();
  }

  async getClientHistory(id: number): Promise<any[]> {
    await this.findOne(id);
    return [];
  }
}
