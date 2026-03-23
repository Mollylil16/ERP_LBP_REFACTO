import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { Agence } from './entities/agence.entity';

export interface CreateAgenceDto {
    code: string;
    nom: string;
    pays: string;
    ville: string;
    adresse?: string;
    telephone?: string;
    email?: string;
    nom_responsable?: string;
    tel_responsable?: string;
    devise?: string;
    latitude?: number;
    longitude?: number;
    place_id?: string;
}

@Injectable()
export class AgencesService {
    constructor(
        @InjectRepository(Agence)
        private agencesRepository: Repository<Agence>,
    ) { }

    async findAll(): Promise<Agence[]> {
        return this.agencesRepository.find({
            where: { actif: true },
            order: { nom: 'ASC' },
        });
    }

    async findOne(id: number): Promise<Agence> {
        const agence = await this.agencesRepository.findOne({ where: { id } });
        if (!agence) throw new NotFoundException(`Agence #${id} introuvable`);
        return agence;
    }

    async create(dto: CreateAgenceDto): Promise<Agence> {
        const agence = this.agencesRepository.create({
            ...dto,
            actif: true,
        });
        return this.agencesRepository.save(agence);
    }

    async update(id: number, dto: Partial<CreateAgenceDto>): Promise<Agence> {
        await this.agencesRepository.update(id, dto);
        return this.findOne(id);
    }

    async remove(id: number): Promise<void> {
        await this.agencesRepository.update(id, { actif: false });
    }

    /**
     * Retourne les stats par agence : nb colis, CA, paiements,
     * triés du plus actif au moins actif.
     * Utilisé sur le dashboard superadmin et directeur.
     */
    async getStats(): Promise<Array<{
        agence: Agence;
        nb_colis: number;
        nb_utilisateurs: number;
        total_paiements: number;
        score: number;  // score composite pour le classement
    }>> {
        const agences = await this.agencesRepository.find({
            where: { actif: true },
            relations: ['users', 'colis'],
        });

        const stats = agences.map((agence) => {
            const nb_colis = agence.colis?.length ?? 0;
            const nb_utilisateurs = agence.users?.length ?? 0;
            // Score composite : colis compte double
            const score = nb_colis * 2 + nb_utilisateurs;

            return {
                agence,
                nb_colis,
                nb_utilisateurs,
                total_paiements: 0, // TODO: join avec paiements si besoin
                score,
            };
        });

        // Trier du plus actif au moins actif
        return stats.sort((a, b) => b.score - a.score);
    }
}
