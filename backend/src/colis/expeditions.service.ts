import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { Expedition, ExpeditionStatut } from './entities/expedition.entity';
import { Colis } from './entities/colis.entity';
import { CreateExpeditionDto } from './dto/create-expedition.dto';
import { Agence } from '../agences/entities/agence.entity';
import { WhatsappService } from '../notifications/whatsapp.service';

@Injectable()
export class ExpeditionsService {
    constructor(
        @InjectRepository(Expedition)
        private expeditionRepository: Repository<Expedition>,
        @InjectRepository(Colis)
        private colisRepository: Repository<Colis>,
        @InjectRepository(Agence)
        private agenceRepository: Repository<Agence>,
        private whatsappService: WhatsappService,
    ) { }

    async create(createExpeditionDto: any, user: any) {
        const agenceDepart = await this.agenceRepository.findOne({ where: { id: user.id_agence } });
        if (!agenceDepart) {
            throw new NotFoundException('Agence de départ non trouvée');
        }

        const agenceDestination = await this.agenceRepository.findOne({ where: { id: createExpeditionDto.id_agence_destination } });
        if (!agenceDestination) {
            throw new NotFoundException('Agence de destination non trouvée');
        }

        // Generate ref: EXP-MMYY-XXXX
        const count = await this.expeditionRepository.count();
        const date = new Date();
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const year = date.getFullYear().toString().substr(-2);
        const ref = `EXP-${month}${year}-${(count + 1).toString().padStart(4, '0')}`;

        const expedition = this.expeditionRepository.create({
            ...createExpeditionDto,
            ref_expedition: ref,
            agence_depart: agenceDepart,
            agence_destination: agenceDestination,
            statut: ExpeditionStatut.EN_PREPARATION,
        });

        return await this.expeditionRepository.save(expedition);
    }

    async findAll(user: any) {
        // Users can see expeditions from their agency (depart) OR coming to their agency (destination)
        if (!user.id_agence) {
            return await this.expeditionRepository.find({
                relations: ['agence_depart', 'agence_destination', 'colis'],
                order: { created_at: 'DESC' }
            });
        }

        return await this.expeditionRepository.find({
            where: [
                { agence_depart: { id: user.id_agence } },
                { agence_destination: { id: user.id_agence } }
            ],
            relations: ['agence_depart', 'agence_destination', 'colis'],
            order: { created_at: 'DESC' }
        });
    }

    async findOne(id: number) {
        const expedition = await this.expeditionRepository.findOne({
            where: { id },
            relations: ['agence_depart', 'agence_destination', 'colis', 'colis.client', 'colis.agence'],
        });

        if (!expedition) {
            throw new NotFoundException(`Expédition #${id} non trouvée`);
        }

        return expedition;
    }

    async addColis(id: number, colisIds: number[]) {
        const expedition = await this.findOne(id);

        // Fetch colis to verify they exist and update them
        const colisToAdd = await this.colisRepository.findByIds(colisIds); // Deprecated in some versions, but let's check or use findBy

        // Update each colis with the expedition
        for (const colis of colisToAdd) {
            colis.expedition = expedition;
            await this.colisRepository.save(colis);
        }

        return this.findOne(id);
    }

    async removeColis(id: number, colisId: number) {
        const colis = await this.colisRepository.findOne({ where: { id: colisId }, relations: ['expedition'] });
        if (colis && colis.expedition && Number(colis.expedition.id) === Number(id)) {
            colis.expedition = null as any;
            await this.colisRepository.save(colis);
        }
        return this.findOne(id);
    }

    async updateStatus(id: number, statut: ExpeditionStatut) {
        const expedition = await this.findOne(id);
        const oldStatut = expedition.statut;
        expedition.statut = statut;

        const savedExpedition = await this.expeditionRepository.save(expedition);

        // ✅ AJOUT: Notification de départ (CI -> FR/Ailleurs)
        if (statut === ExpeditionStatut.EN_TRANSIT && oldStatut !== ExpeditionStatut.EN_TRANSIT) {
            if (expedition.colis && expedition.colis.length > 0) {
                for (const colis of expedition.colis) {
                    if (colis.client && colis.client.tel_exp) {
                        const origin = expedition.agence_depart?.nom || 'Côte d\'Ivoire';
                        const destination = expedition.agence_destination?.nom || 'France';
                        await this.whatsappService.notifyDeparture(
                            colis.client.nom_exp,
                            colis.client.tel_exp,
                            colis.ref_colis,
                            origin,
                            destination
                        );
                    }
                }
            }
        }

        return savedExpedition;
    }
}
