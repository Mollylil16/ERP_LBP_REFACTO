import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, Between, MoreThanOrEqual } from 'typeorm';
import { Colis } from '../colis/entities/colis.entity';
import { Client } from '../clients/entities/client.entity';
import { Facture } from '../factures/entities/facture.entity';
import { Paiement } from '../paiements/entities/paiement.entity';
import { CaisseService } from '../caisse/caisse.service';
import { AgencesService } from '../agences/agences.service';
import { Agence } from '../agences/entities/agence.entity';

@Injectable()
export class DashboardService {
    constructor(
        @InjectRepository(Colis)
        private colisRepository: Repository<Colis>,
        @InjectRepository(Client)
        private clientRepository: Repository<Client>,
        @InjectRepository(Facture)
        private factureRepository: Repository<Facture>,
        @InjectRepository(Paiement)
        private paiementRepository: Repository<Paiement>,
        private caisseService: CaisseService,
        private agencesService: AgencesService,
    ) { }

    async getStats(): Promise<any> {
        const totalColis = await this.colisRepository.count();
        const activeClients = await this.clientRepository.count({ where: { isActive: true } });

        // Total Revenue (all definitive invoices)
        const definitiveFacturesData = await this.factureRepository.find({
            where: { etat: 1 },
            select: ['montant_ttc']
        });
        const totalRevenue = definitiveFacturesData.reduce((sum, f) => sum + Number(f.montant_ttc), 0);

        // Today's Revenue
        const startOfToday = new Date();
        startOfToday.setHours(0, 0, 0, 0);
        const todayPayments = await this.paiementRepository.find({
            where: { created_at: MoreThanOrEqual(startOfToday), etat_validation: 1 },
            select: ['montant']
        });
        const todayRevenue = todayPayments.reduce((sum, p) => sum + Number(p.montant), 0);

        return {
            totalColis,
            totalRevenue,
            activeClients,
            todayRevenue,
        };
    }

    async getRecentActivities(limit: number = 10): Promise<any[]> {
        // Combine last Colis and last Paiements
        const lastColis = await this.colisRepository.find({
            order: { created_at: 'DESC' },
            take: limit,
            relations: ['client'],
        });

        const lastPayments = await this.paiementRepository.find({
            order: { created_at: 'DESC' },
            take: limit,
            relations: ['facture', 'facture.colis'],
        });

        const activities = [
            ...lastColis.map(c => ({
                type: 'COLIS_CREATE',
                title: `Nouveau colis ${c.ref_colis}`,
                description: `Expédié par ${c.client.nom_exp} pour ${c.nom_dest}`,
                date: c.created_at,
                id: c.id,
            })),
            ...lastPayments.map(p => ({
                type: 'PAYMENT_RECEIVE',
                title: `Paiement reçu - ${p.facture.num_facture}`,
                description: `Montant: ${p.montant} FCFA`,
                date: p.created_at,
                id: p.id,
            }))
        ];

        // Sort combined by date and limit
        return activities.sort((a, b) => b.date.getTime() - a.date.getTime()).slice(0, limit);
    }

    async getPointCaisse(date?: string): Promise<any> {
        return this.caisseService.getPointCaisse(date);
    }

    async getAgenciesPerformances(date?: string): Promise<any[]> {
        const agences = await this.agencesService.findAll();
        const results: any[] = [];

        for (const agence of agences) {
            // Pour chaque agence, on récupère ses caisses
            const caisses = await this.caisseService.findAllCaisses(agence.id);

            let totalEntrees = 0;
            let totalSorties = 0;
            let soldeGlobal = 0;

            for (const caisse of caisses) {
                const point = await this.caisseService.getPointCaisse(date, caisse.id);
                totalEntrees += point.entrees;
                totalSorties += point.sorties;
                soldeGlobal += point.solde;
            }

            results.push({
                agenceId: agence.id,
                agenceNom: agence.nom,
                agenceCode: agence.code,
                entrees: totalEntrees,
                sorties: totalSorties,
                solde: soldeGlobal,
                date: date || new Date().toISOString().split('T')[0]
            });
        }

        return results;
    }
}
