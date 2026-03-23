import { Injectable, Logger } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, Between, MoreThanOrEqual } from 'typeorm';
import { Colis } from '../colis/entities/colis.entity';
import { Client } from '../clients/entities/client.entity';
import { Facture } from '../factures/entities/facture.entity';
import { Paiement } from '../paiements/entities/paiement.entity';
import { MouvementCaisse } from '../caisse/entities/mouvement-caisse.entity';

@Injectable()
export class AnalyticsService {
    private readonly logger = new Logger(AnalyticsService.name);

    constructor(
        @InjectRepository(Colis)
        private colisRepository: Repository<Colis>,
        @InjectRepository(Client)
        private clientRepository: Repository<Client>,
        @InjectRepository(Facture)
        private factureRepository: Repository<Facture>,
        @InjectRepository(Paiement)
        private paiementRepository: Repository<Paiement>,
        @InjectRepository(MouvementCaisse)
        private mouvementRepository: Repository<MouvementCaisse>,
    ) { }

    async getChartData(period: string = '6month'): Promise<any[]> {
        const months = 6; // Par défaut 6 mois
        const data: any[] = [];
        const now = new Date();

        for (let i = months - 1; i >= 0; i--) {
            const date = new Date(now.getFullYear(), now.getMonth() - i, 1);
            const firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
            const lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0, 23, 59, 59);

            const countGroupage = await this.colisRepository.count({
                where: { date_envoi: Between(firstDay, lastDay), forme_envoi: 'GROUPAGE' as any }
            });

            const countAutres = await this.colisRepository.count({
                where: { date_envoi: Between(firstDay, lastDay), forme_envoi: 'AUTRES_ENVOI' as any }
            });

            // Revenus (factures payées)
            const payments = await this.paiementRepository.find({
                where: { created_at: Between(firstDay, lastDay), etat_validation: 1 },
                select: ['montant']
            });
            const revenus = payments.reduce((sum, p) => sum + Number(p.montant), 0);

            data.push({
                mois: date.toLocaleString('default', { month: 'short' }),
                groupage: countGroupage,
                autresEnvois: countAutres,
                total: countGroupage + countAutres,
                revenus: revenus
            });
        }

        return data;
    }

    async getTrafficRepartition(): Promise<any[]> {
        const result = await this.colisRepository
            .createQueryBuilder('colis')
            .select('colis.trafic_envoi', 'name')
            .addSelect('COUNT(*)', 'value')
            .groupBy('colis.trafic_envoi')
            .getRawMany();

        const total = result.reduce((sum, r) => sum + parseInt(r.value), 0);

        return result.map(r => ({
            name: r.name,
            value: total > 0 ? Math.round((parseInt(r.value) / total) * 100) : 0
        }));
    }

    async getStrategicRecommendations(): Promise<any[]> {
        this.logger.log('Génération des recommandations stratégiques (IA)...');

        const recommendations: any[] = [];

        // 1. Analyse Revenue vs Retraits
        const analysisRevenue = await this.analyzeRevenueAndWithdrawals();
        if (analysisRevenue) recommendations.push(analysisRevenue);

        // 2. Analyse Volume de Colis
        const analysisVolume = await this.analyzeVolumeTrends();
        if (analysisVolume) recommendations.push(analysisVolume);

        // 3. Analyse Rétention Client
        const analysisClients = await this.analyzeClientBase();
        if (analysisClients) recommendations.push(analysisClients);

        // 4. Analyse Long Terme (Détection des chutes)
        const analysisLongTerm = await this.analyzeLongTermTrends();
        if (analysisLongTerm) recommendations.push(analysisLongTerm);

        // 5. Recommandation par défaut si vide
        if (recommendations.length === 0) {
            recommendations.push({
                type: 'info',
                title: 'Analyse en cours',
                description: 'Les données actuelles sont stables. Aucune anomalie majeure détectée.',
                cause: 'Stabilité opérationnelle.',
                action: 'Continuez à surveiller les indicateurs hebdomadaires.'
            });
        }

        return recommendations;
    }

    private async analyzeRevenueAndWithdrawals() {
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);

        const payments = await this.paiementRepository.find({
            where: { created_at: MoreThanOrEqual(thirtyDaysAgo), etat_validation: 1 },
            select: ['montant']
        });
        const totalPayments = payments.reduce((sum, p) => sum + Number(p.montant), 0);

        const withdrawals = await this.mouvementRepository.find({
            where: { created_at: MoreThanOrEqual(thirtyDaysAgo), type: 'DECAISSEMENT' as any },
            select: ['montant']
        });
        const totalWithdrawals = withdrawals.reduce((sum, w) => sum + Number(w.montant), 0);

        const ratio = totalPayments > 0 ? (totalWithdrawals / totalPayments) * 100 : 0;

        // IA Simulation : Génération de texte plus dynamique
        if (ratio > 50) {
            return {
                type: 'error',
                title: 'CRITIQUE : Hémorragie de Trésorerie Détectée',
                description: `Alerte Rouge : ${ratio.toFixed(1)}% des revenus encaissés sont immédiatement décaissés.`,
                cause: 'La structure des coûts opérationnels est insoutenable à court terme. Probable fuite de fonds ou charges fixes trop lourdes.',
                action: 'AUDIT IMMÉDIAT REQUIS : Gelez les décaissements non-régaliens et convoquez une réunion financière d\'urgence.'
            };
        }

        if (ratio > 30) {
            return {
                type: 'warning',
                title: 'Tension sur la Trésorerie',
                description: `Attention : Le ratio de décaissement monte à ${ratio.toFixed(1)}%.`,
                cause: 'Accumulation de petites dépenses non contrôlées ou paiement de fournisseurs anticipé.',
                action: 'Instaurez une double validation pour tout retrait supérieur à 50.000 FCFA.'
            };
        }

        if (totalPayments > 0 && ratio < 15) {
            return {
                type: 'success',
                title: 'Excellent BFR (Besoin en Fonds de Roulement)',
                description: `Santé financière optimale : seulement ${ratio.toFixed(1)}% de burn rate.`,
                cause: 'Optimisation réussie des délais de paiement fournisseurs et contrôle strict des coûts.',
                action: 'Opportunité d\'investissement : Le cash excédentaire permet d\'envisager l\'ouverture d\'un nouveau point relais.'
            };
        }

        return null;
    }

    private async analyzeVolumeTrends() {
        const now = new Date();
        const sevenDaysAgo = new Date();
        sevenDaysAgo.setDate(now.getDate() - 7);
        const fourteenDaysAgo = new Date();
        fourteenDaysAgo.setDate(now.getDate() - 14);

        const thisWeek = await this.colisRepository.count({
            where: { created_at: MoreThanOrEqual(sevenDaysAgo) }
        });
        const lastWeek = await this.colisRepository.count({
            where: { created_at: MoreThanOrEqual(fourteenDaysAgo) } // Approximation
        }) - thisWeek;

        if (lastWeek === 0) return null;

        const trend = ((thisWeek - lastWeek) / lastWeek) * 100;

        if (trend < -15) {
            // IA : Analyse de corrélation pour la cause
            const repartition = await this.getTrafficRepartition();
            const maritimeTraffic = repartition.find(r =>
                r.name?.toUpperCase().includes('MARITIME')
            );

            let cause = 'Baisse de demande globale sur tous les axes.';
            let action = 'ACTIVER PLAN RELANCE : SMS marketing ciblé + Promo -10% pour réactivation.';

            if (maritimeTraffic && maritimeTraffic.value > 60) {
                cause = 'Dépendance excessive au trafic maritime qui subit une chute séculaire.';
                action = 'DIVERSIFICATION : Boostez les offres Aériennes (transit plus rapide) pour compenser.';
            }

            return {
                type: 'error',
                title: 'Décrochage Commercial Détecté',
                description: `Chute brutale de ${Math.abs(trend).toFixed(1)}% du volume cette semaine.`,
                cause: cause,
                action: action
            };
        }

        if (trend > 15) {
            return {
                type: 'success',
                title: 'Pic d\'Activité Saisonnier',
                description: `Croissance exponentielle de ${trend.toFixed(1)}% identifiée.`,
                cause: 'Traction virale ou saisonnalité (Fêtes/Rentrée).',
                action: 'RENFORCER LES ÉQUIPES : Risque de saturation logistique. Préparez des intérimaires pour le tri.'
            };
        }

        return null;
    }

    private async analyzeClientBase() {
        const totalClients = await this.clientRepository.count();
        const activeClients = await this.clientRepository.count({ where: { isActive: true } });

        if (totalClients > 0) {
            const ratioInactif = ((totalClients - activeClients) / totalClients) * 100;

            if (ratioInactif > 30) {
                return {
                    type: 'info',
                    title: 'Potentiel de Réactivation Client',
                    description: `${ratioInactif.toFixed(1)}% de votre base client est inactive.`,
                    cause: 'Manque de suivi post-expédition ou perte de contact.',
                    action: 'Mettez en place un programme de fidélité ou envoyez un message de relance aux clients inactifs.'
                };
            }
        }
        return null;
    }

    private async analyzeLongTermTrends() {
        // Calcul de la moyenne mobile sur 3 mois
        const now = new Date();
        const months: number[] = [];
        for (let i = 1; i <= 3; i++) {
            const start = new Date(now.getFullYear(), now.getMonth() - i, 1);
            const end = new Date(now.getFullYear(), now.getMonth() - i + 1, 0, 23, 59, 59);
            const count = await this.colisRepository.count({ where: { date_envoi: Between(start, end) } });
            months.push(count);
        }

        const averageLast3Months = months.reduce((a, b) => a + b, 0) / months.length;

        // Volume mois actuel (pro-rata ou total si on est en fin de mois)
        const currentMonthStart = new Date(now.getFullYear(), now.getMonth(), 1);
        const currentMonthCount = await this.colisRepository.count({ where: { date_envoi: MoreThanOrEqual(currentMonthStart) } });

        // On compense le fait que le mois actuel n'est pas fini pour la comparaison
        const dayOfMonth = now.getDate();
        const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
        const projectedCurrentMonth = (currentMonthCount / dayOfMonth) * daysInMonth;

        if (averageLast3Months > 0) {
            const dropPercent = ((averageLast3Months - projectedCurrentMonth) / averageLast3Months) * 100;

            if (dropPercent > 20) {
                // Analyse de la cause
                let cause = 'Baisse de la demande globale sur le marché.';
                let action = 'CAMPAGNE DE RÉACTIVATION : Offrez une remise exceptionnelle aux clients n\'ayant pas expédié ce mois-ci.';

                // Vérifier si c'est lié aux tarifs
                const averagePriceThisMonth = await this.colisRepository
                    .createQueryBuilder('colis')
                    .leftJoin('colis.marchandises', 'm')
                    .select('AVG(m.prix_unit)', 'avg')
                    .where('colis.date_envoi >= :start', { start: currentMonthStart })
                    .getRawOne();

                const averagePricePast = await this.colisRepository
                    .createQueryBuilder('colis')
                    .leftJoin('colis.marchandises', 'm')
                    .select('AVG(m.prix_unit)', 'avg')
                    .where('colis.date_envoi < :start', { start: currentMonthStart })
                    .andWhere('colis.date_envoi >= :threeMonthsAgo', { threeMonthsAgo: new Date(now.getFullYear(), now.getMonth() - 3, 1) })
                    .getRawOne();

                if (averagePriceThisMonth?.avg > averagePricePast?.avg * 1.1) {
                    cause = 'Sensibilité au prix détectée : Vos tarifs moyens ont augmenté de plus de 10%.';
                    action = 'AJUSTEMENT TARIFAIRE : Revoyez vos marges sur les produits phares pour rester compétitif.';
                }

                return {
                    type: 'error',
                    title: 'ALERTE : Chute d\'Activité Structurelle',
                    description: `Votre volume projeté est en baisse de ${dropPercent.toFixed(1)}% par rapport à votre moyenne trimestrielle.`,
                    cause: cause,
                    action: action
                };
            }
        }

        return null;
    }
}
