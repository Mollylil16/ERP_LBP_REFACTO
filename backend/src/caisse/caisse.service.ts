import { Injectable, NotFoundException, OnApplicationBootstrap } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, Between, DataSource } from 'typeorm';
import { Caisse } from './entities/caisse.entity';
import { MouvementCaisse, MouvementType } from './entities/mouvement-caisse.entity';
import { Agence } from '../agences/entities/agence.entity';

@Injectable()
export class CaisseService implements OnApplicationBootstrap {
    constructor(
        @InjectRepository(Caisse)
        private caisseRepository: Repository<Caisse>,
        @InjectRepository(MouvementCaisse)
        private mouvementRepository: Repository<MouvementCaisse>,
        @InjectRepository(Agence)
        private agenceRepository: Repository<Agence>,
    ) { }

    async onApplicationBootstrap() {
        // S'assurer qu'il existe une caisse pour chaque agence existante
        const agences = await this.agenceRepository.find();
        for (const agence of agences) {
            const existing = await this.caisseRepository.findOne({ where: { agence: { id: agence.id } } });
            if (!existing) {
                await this.caisseRepository.save({
                    nom: `Caisse - ${agence.nom}`,
                    solde_initial: 0,
                    agence: agence,
                });
                console.log(`Cash register created for agency: ${agence.nom}`);
            }
        }
    }

    async createMovement(data: any, type: MouvementType, userId: string, agenceId?: number): Promise<MouvementCaisse> {
        let caisseId = data.id_caisse;

        if (!caisseId && agenceId) {
            const caisse = await this.caisseRepository.findOne({ where: { agence: { id: agenceId } } });
            caisseId = caisse?.id;
        }

        const caisse = await this.caisseRepository.findOne({ where: { id: caisseId || 1 } });

        if (!caisse) {
            throw new NotFoundException(`Caisse #${caisseId} not found`);
        }

        const mouvement = this.mouvementRepository.create({
            ...data,
            type,
            caisse,
            code_user: userId,
            date_mouvement: data.date_mouvement ? new Date(data.date_mouvement) : new Date(),
        } as MouvementCaisse);

        return await this.mouvementRepository.save(mouvement);
    }

    async getMouvements(params: any, agenceId?: number): Promise<MouvementCaisse[]> {
        const { id_caisse, date_debut, date_fin, type } = params;
        const where: any = {};

        if (id_caisse) {
            where.caisse = { id: id_caisse };
        } else if (agenceId) {
            where.caisse = { agence: { id: agenceId } };
        }

        if (type) where.type = type;
        if (date_debut && date_fin) {
            where.date_mouvement = Between(new Date(date_debut), new Date(date_fin));
        }

        return this.mouvementRepository.find({
            where,
            order: { created_at: 'DESC' },
            relations: ['caisse'],
        });
    }

    async getSolde(id_caisse: number = 1): Promise<number> {
        const caisse = await this.caisseRepository.findOne({ where: { id: id_caisse } });
        if (!caisse) return 0;

        const mouvements = await this.mouvementRepository.find({
            where: { caisse: { id: id_caisse } }
        });

        const total = mouvements.reduce((acc, mv) => {
            // Les décaissements diminuent le solde
            if (mv.type === MouvementType.DECAISSEMENT) {
                return acc - Number(mv.montant);
            }
            // Tous les autres types (APPRO, ENTREE_*) augmentent le solde
            return acc + Number(mv.montant);
        }, Number(caisse.solde_initial || 0));

        return total;
    }

    async getPointCaisse(date?: string, id_caisse: number = 1): Promise<any> {
        const targetDate = date ? new Date(date) : new Date();
        const startOfDay = new Date(targetDate.setHours(0, 0, 0, 0));
        const endOfDay = new Date(targetDate.setHours(23, 59, 59, 999));

        const mouvements = await this.mouvementRepository.find({
            where: {
                caisse: { id: id_caisse },
                date_mouvement: Between(startOfDay, endOfDay),
            }
        });

        const entrees = mouvements
            .filter(m => m.type !== MouvementType.DECAISSEMENT)
            .reduce((sum, m) => sum + Number(m.montant), 0);

        const sorties = mouvements
            .filter(m => m.type === MouvementType.DECAISSEMENT)
            .reduce((sum, m) => sum + Number(m.montant), 0);

        const solde = await this.getSolde(id_caisse);

        return {
            date: startOfDay,
            entrees,
            sorties,
            solde,
            mouvementsCount: mouvements.length,
        };
    }

    async findAllCaisses(agenceId?: number): Promise<any[]> {
        const caisses = agenceId
            ? await this.caisseRepository.find({ where: { agence: { id: agenceId } } })
            : await this.caisseRepository.find();

        const results: any[] = [];
        for (const caisse of caisses) {
            const solde_actuel = await this.getSolde(caisse.id);
            results.push({
                ...caisse,
                libelle: caisse.nom,
                montant_initial: caisse.solde_initial,
                solde_actuel: solde_actuel,
            });
        }
        return results;
    }

    async getRapportGrandesLignes(params: {
        date_debut: string;
        date_fin: string;
        id_caisse?: number;
    }): Promise<any> {
        const { date_debut, date_fin, id_caisse = 1 } = params;
        const startDate = new Date(date_debut);
        const endDate = new Date(date_fin);
        endDate.setHours(23, 59, 59, 999);

        // Récupérer la caisse
        const caisse = await this.caisseRepository.findOne({ where: { id: id_caisse } });
        if (!caisse) {
            throw new NotFoundException(`Caisse #${id_caisse} not found`);
        }

        // Récupérer tous les mouvements dans la période
        const mouvements = await this.mouvementRepository.find({
            where: {
                caisse: { id: id_caisse },
                date_mouvement: Between(startDate, endDate),
            },
            order: { date_mouvement: 'ASC' },
        });

        // Calculer les totaux
        const totalAppro = mouvements
            .filter(m => m.type === MouvementType.APPRO)
            .reduce((sum, m) => sum + Number(m.montant), 0);

        const totalDecaissement = mouvements
            .filter(m => m.type === MouvementType.DECAISSEMENT)
            .reduce((sum, m) => sum + Number(m.montant), 0);

        const totalEntreesCheque = mouvements
            .filter(m => m.type === MouvementType.ENTREE_CHEQUE)
            .reduce((sum, m) => sum + Number(m.montant), 0);

        const totalEntreesEspece = mouvements
            .filter(m => m.type === MouvementType.ENTREE_ESPECE)
            .reduce((sum, m) => sum + Number(m.montant), 0);

        const totalEntreesVirement = mouvements
            .filter(m => m.type === MouvementType.ENTREE_VIREMENT)
            .reduce((sum, m) => sum + Number(m.montant), 0);

        const totalEntrees = totalEntreesCheque + totalEntreesEspece + totalEntreesVirement;

        // Solde initial (avant la période)
        const mouvementsAvant = await this.mouvementRepository.find({
            where: {
                caisse: { id: id_caisse },
                date_mouvement: Between(new Date('1900-01-01'), new Date(startDate.getTime() - 1)),
            },
        });

        const soldeInitial = mouvementsAvant.reduce((acc, mv) => {
            if (mv.type === MouvementType.DECAISSEMENT) {
                return acc - Number(mv.montant);
            }
            return acc + Number(mv.montant);
        }, Number(caisse.solde_initial));

        const soldeFinal = soldeInitial + totalAppro - totalDecaissement + totalEntrees;

        return {
            date_debut: startDate.toISOString(),
            date_fin: endDate.toISOString(),
            total_appro: totalAppro,
            total_decaissement: totalDecaissement,
            total_entrees_cheque: totalEntreesCheque,
            total_entrees_espece: totalEntreesEspece,
            total_entrees_virement: totalEntreesVirement,
            total_entrees: totalEntrees,
            solde_initial: soldeInitial,
            solde_final: soldeFinal,
        };
    }

}