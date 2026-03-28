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
  ) {}

  async getChartData(period: string = '6month'): Promise<any[]> {
    const months = 6; // Par défaut 6 mois
    const data: any[] = [];
    const now = new Date();

    for (let i = months - 1; i >= 0; i--) {
      const date = new Date(now.getFullYear(), now.getMonth() - i, 1);
      const firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
      const lastDay = new Date(
        date.getFullYear(),
        date.getMonth() + 1,
        0,
        23,
        59,
        59,
      );

      const countGroupage = await this.colisRepository.count({
        where: {
          date_envoi: Between(firstDay, lastDay),
          forme_envoi: 'GROUPAGE' as any,
        },
      });

      const countAutres = await this.colisRepository.count({
        where: {
          date_envoi: Between(firstDay, lastDay),
          forme_envoi: 'AUTRES_ENVOI' as any,
        },
      });

      // Revenus (factures payées)
      const payments = await this.paiementRepository.find({
        where: { created_at: Between(firstDay, lastDay), etat_validation: 1 },
        select: ['montant'],
      });
      const revenus = payments.reduce((sum, p) => sum + Number(p.montant), 0);

      data.push({
        mois: date.toLocaleString('default', { month: 'short' }),
        groupage: countGroupage,
        autresEnvois: countAutres,
        total: countGroupage + countAutres,
        revenus: revenus,
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

    return result.map((r) => ({
      name: r.name,
      value: total > 0 ? Math.round((parseInt(r.value) / total) * 100) : 0,
    }));
  }

  async getStrategicRecommendations(): Promise<any[]> {
    this.logger.log('Lot 4 IA V1: recommandations orientées actions...');

    const [volumeModel, unpaidModel, cashAnomalyModel, pricingModel] =
      await Promise.all([
        this.runVolumeModel(),
        this.runUnpaidRiskModel(),
        this.runCashAnomalyModel(),
        this.runPricingRecommendationModel(),
      ]);

    const recommendations = [
      volumeModel,
      unpaidModel,
      cashAnomalyModel,
      pricingModel,
    ]
      .filter(Boolean)
      .sort(
        (a: any, b: any) => Number(b.priority || 0) - Number(a.priority || 0),
      );

    if (recommendations.length === 0) {
      recommendations.push({
        model: 'assistant-default',
        type: 'info',
        title: 'Assistant opérationnel prêt',
        description:
          'Les indicateurs sont stables, aucune action critique immédiate.',
        cause: 'Absence de signal fort sur les 30 derniers jours.',
        action: 'Maintenir la cadence et surveiller les KPI chaque matin.',
        explanation: {
          factors: ['Volume stable', 'Impayés maîtrisés', 'Trésorerie stable'],
          confidence: 0.72,
        },
        metrics: { value: 0, threshold: 1, status: 'ok' },
        drift: { status: 'stable', score: 0.04 },
        priority: 10,
        actions: [
          {
            code: 'OPEN_DAILY_BRIEF',
            label: 'Ouvrir briefing opérationnel',
            route: '/dashboard',
          },
        ],
      });
    }

    return recommendations;
  }

  private async analyzeRevenueAndWithdrawals() {
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);

    const payments = await this.paiementRepository.find({
      where: { created_at: MoreThanOrEqual(thirtyDaysAgo), etat_validation: 1 },
      select: ['montant'],
    });
    const totalPayments = payments.reduce(
      (sum, p) => sum + Number(p.montant),
      0,
    );

    const withdrawals = await this.mouvementRepository.find({
      where: {
        created_at: MoreThanOrEqual(thirtyDaysAgo),
        type: 'DECAISSEMENT' as any,
      },
      select: ['montant'],
    });
    const totalWithdrawals = withdrawals.reduce(
      (sum, w) => sum + Number(w.montant),
      0,
    );

    const ratio =
      totalPayments > 0 ? (totalWithdrawals / totalPayments) * 100 : 0;

    // IA Simulation : Génération de texte plus dynamique
    if (ratio > 50) {
      return {
        type: 'error',
        title: 'CRITIQUE : Hémorragie de Trésorerie Détectée',
        description: `Alerte Rouge : ${ratio.toFixed(1)}% des revenus encaissés sont immédiatement décaissés.`,
        cause:
          'La structure des coûts opérationnels est insoutenable à court terme. Probable fuite de fonds ou charges fixes trop lourdes.',
        action:
          "AUDIT IMMÉDIAT REQUIS : Gelez les décaissements non-régaliens et convoquez une réunion financière d'urgence.",
      };
    }

    if (ratio > 30) {
      return {
        type: 'warning',
        title: 'Tension sur la Trésorerie',
        description: `Attention : Le ratio de décaissement monte à ${ratio.toFixed(1)}%.`,
        cause:
          'Accumulation de petites dépenses non contrôlées ou paiement de fournisseurs anticipé.',
        action:
          'Instaurez une double validation pour tout retrait supérieur à 50.000 FCFA.',
      };
    }

    if (totalPayments > 0 && ratio < 15) {
      return {
        type: 'success',
        title: 'Excellent BFR (Besoin en Fonds de Roulement)',
        description: `Santé financière optimale : seulement ${ratio.toFixed(1)}% de burn rate.`,
        cause:
          'Optimisation réussie des délais de paiement fournisseurs et contrôle strict des coûts.',
        action:
          "Opportunité d'investissement : Le cash excédentaire permet d'envisager l'ouverture d'un nouveau point relais.",
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
      where: { created_at: MoreThanOrEqual(sevenDaysAgo) },
    });
    const lastWeek =
      (await this.colisRepository.count({
        where: { created_at: MoreThanOrEqual(fourteenDaysAgo) }, // Approximation
      })) - thisWeek;

    if (lastWeek === 0) return null;

    const trend = ((thisWeek - lastWeek) / lastWeek) * 100;

    if (trend < -15) {
      // IA : Analyse de corrélation pour la cause
      const repartition = await this.getTrafficRepartition();
      const maritimeTraffic = repartition.find((r) =>
        r.name?.toUpperCase().includes('MARITIME'),
      );

      let cause = 'Baisse de demande globale sur tous les axes.';
      let action =
        'ACTIVER PLAN RELANCE : SMS marketing ciblé + Promo -10% pour réactivation.';

      if (maritimeTraffic && maritimeTraffic.value > 60) {
        cause =
          'Dépendance excessive au trafic maritime qui subit une chute séculaire.';
        action =
          'DIVERSIFICATION : Boostez les offres Aériennes (transit plus rapide) pour compenser.';
      }

      return {
        type: 'error',
        title: 'Décrochage Commercial Détecté',
        description: `Chute brutale de ${Math.abs(trend).toFixed(1)}% du volume cette semaine.`,
        cause: cause,
        action: action,
      };
    }

    if (trend > 15) {
      return {
        type: 'success',
        title: "Pic d'Activité Saisonnier",
        description: `Croissance exponentielle de ${trend.toFixed(1)}% identifiée.`,
        cause: 'Traction virale ou saisonnalité (Fêtes/Rentrée).',
        action:
          'RENFORCER LES ÉQUIPES : Risque de saturation logistique. Préparez des intérimaires pour le tri.',
      };
    }

    return null;
  }

  private async analyzeClientBase() {
    const totalClients = await this.clientRepository.count();
    const activeClients = await this.clientRepository.count({
      where: { isActive: true },
    });

    if (totalClients > 0) {
      const ratioInactif =
        ((totalClients - activeClients) / totalClients) * 100;

      if (ratioInactif > 30) {
        return {
          type: 'info',
          title: 'Potentiel de Réactivation Client',
          description: `${ratioInactif.toFixed(1)}% de votre base client est inactive.`,
          cause: 'Manque de suivi post-expédition ou perte de contact.',
          action:
            'Mettez en place un programme de fidélité ou envoyez un message de relance aux clients inactifs.',
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
      const end = new Date(
        now.getFullYear(),
        now.getMonth() - i + 1,
        0,
        23,
        59,
        59,
      );
      const count = await this.colisRepository.count({
        where: { date_envoi: Between(start, end) },
      });
      months.push(count);
    }

    const averageLast3Months =
      months.reduce((a, b) => a + b, 0) / months.length;

    // Volume mois actuel (pro-rata ou total si on est en fin de mois)
    const currentMonthStart = new Date(now.getFullYear(), now.getMonth(), 1);
    const currentMonthCount = await this.colisRepository.count({
      where: { date_envoi: MoreThanOrEqual(currentMonthStart) },
    });

    // On compense le fait que le mois actuel n'est pas fini pour la comparaison
    const dayOfMonth = now.getDate();
    const daysInMonth = new Date(
      now.getFullYear(),
      now.getMonth() + 1,
      0,
    ).getDate();
    const projectedCurrentMonth =
      (currentMonthCount / dayOfMonth) * daysInMonth;

    if (averageLast3Months > 0) {
      const dropPercent =
        ((averageLast3Months - projectedCurrentMonth) / averageLast3Months) *
        100;

      if (dropPercent > 20) {
        // Analyse de la cause
        let cause = 'Baisse de la demande globale sur le marché.';
        let action =
          "CAMPAGNE DE RÉACTIVATION : Offrez une remise exceptionnelle aux clients n'ayant pas expédié ce mois-ci.";

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
          .andWhere('colis.date_envoi >= :threeMonthsAgo', {
            threeMonthsAgo: new Date(now.getFullYear(), now.getMonth() - 3, 1),
          })
          .getRawOne();

        if (averagePriceThisMonth?.avg > averagePricePast?.avg * 1.1) {
          cause =
            'Sensibilité au prix détectée : Vos tarifs moyens ont augmenté de plus de 10%.';
          action =
            'AJUSTEMENT TARIFAIRE : Revoyez vos marges sur les produits phares pour rester compétitif.';
        }

        return {
          type: 'error',
          title: "ALERTE : Chute d'Activité Structurelle",
          description: `Votre volume projeté est en baisse de ${dropPercent.toFixed(1)}% par rapport à votre moyenne trimestrielle.`,
          cause: cause,
          action: action,
        };
      }
    }

    return null;
  }

  async getRealProfitability(params: {
    date_debut?: string;
    date_fin?: string;
    agence_id?: number;
  }) {
    const rows = await this.loadProfitabilityRows(params);
    const totalRevenue = rows.reduce((s, r) => s + r.revenue, 0);
    const totalCosts = rows.reduce((s, r) => s + r.totalCost, 0);
    const totalMargin = totalRevenue - totalCosts;
    const totalUnpaid = rows.reduce((s, r) => s + r.unpaidAmount, 0);

    const paidRows = rows.filter((r) => r.unpaidAmount <= 0);
    const avgRecoveryDelayDays = paidRows.length
      ? Number(
          (
            paidRows.reduce((s, r) => s + r.recoveryDelayDays, 0) /
            paidRows.length
          ).toFixed(2),
        )
      : 0;

    const cohortes = {
      d30: {
        count: rows.filter((r) => r.recoveryDelayDays <= 30).length,
        amount: rows
          .filter((r) => r.recoveryDelayDays <= 30)
          .reduce((s, r) => s + r.revenue, 0),
      },
      d60: {
        count: rows.filter(
          (r) => r.recoveryDelayDays > 30 && r.recoveryDelayDays <= 60,
        ).length,
        amount: rows
          .filter((r) => r.recoveryDelayDays > 30 && r.recoveryDelayDays <= 60)
          .reduce((s, r) => s + r.revenue, 0),
      },
      d90plus: {
        count: rows.filter((r) => r.recoveryDelayDays > 60).length,
        amount: rows
          .filter((r) => r.recoveryDelayDays > 60)
          .reduce((s, r) => s + r.revenue, 0),
      },
    };

    const pnlByAgence = this.groupPnl(rows, (r) => r.agence);
    const pnlByDestination = this.groupPnl(rows, (r) => r.destination);
    const pnlByProduit = this.groupPnl(rows, (r) => r.produit);

    return {
      summary: {
        total_revenue: totalRevenue,
        total_costs: totalCosts,
        total_margin: totalMargin,
        margin_pct:
          totalRevenue > 0
            ? Number(((totalMargin / totalRevenue) * 100).toFixed(2))
            : 0,
        total_unpaid: totalUnpaid,
        avg_recovery_delay_days: avgRecoveryDelayDays,
      },
      cohortes_30_60_90: cohortes,
      pnl: {
        par_agence: pnlByAgence,
        par_destination: pnlByDestination,
        par_produit: pnlByProduit,
      },
      marge_unitaire: rows.slice(0, 500).map((r) => ({
        facture_id: r.factureId,
        num_facture: r.numFacture,
        agence: r.agence,
        destination: r.destination,
        produit: r.produit,
        revenue_unitaire: r.unitRevenue,
        cost_unitaire: r.unitCost,
        marge_unitaire: r.unitMargin,
        marge_pct:
          r.unitRevenue > 0
            ? Number(((r.unitMargin / r.unitRevenue) * 100).toFixed(2))
            : 0,
        recovery_delay_days: r.recoveryDelayDays,
        unpaid_amount: r.unpaidAmount,
      })),
    };
  }

  async simulatePricingScenario(params: {
    price_change_pct?: number;
    cost_change_pct?: number;
    volume_change_pct?: number;
    date_debut?: string;
    date_fin?: string;
    agence_id?: number;
  }) {
    const rows = await this.loadProfitabilityRows(params);
    const priceFactor = 1 + Number(params.price_change_pct || 0) / 100;
    const costFactor = 1 + Number(params.cost_change_pct || 0) / 100;
    const volumeFactor = 1 + Number(params.volume_change_pct || 0) / 100;

    const baseRevenue = rows.reduce((s, r) => s + r.revenue, 0);
    const baseCost = rows.reduce((s, r) => s + r.totalCost, 0);
    const baseMargin = baseRevenue - baseCost;

    const projectedRevenue = baseRevenue * priceFactor * volumeFactor;
    const projectedCost = baseCost * costFactor * volumeFactor;
    const projectedMargin = projectedRevenue - projectedCost;

    return {
      scenario: {
        price_change_pct: Number(params.price_change_pct || 0),
        cost_change_pct: Number(params.cost_change_pct || 0),
        volume_change_pct: Number(params.volume_change_pct || 0),
      },
      baseline: {
        revenue: baseRevenue,
        costs: baseCost,
        margin: baseMargin,
        margin_pct:
          baseRevenue > 0
            ? Number(((baseMargin / baseRevenue) * 100).toFixed(2))
            : 0,
      },
      projected: {
        revenue: Number(projectedRevenue.toFixed(2)),
        costs: Number(projectedCost.toFixed(2)),
        margin: Number(projectedMargin.toFixed(2)),
        margin_pct:
          projectedRevenue > 0
            ? Number(((projectedMargin / projectedRevenue) * 100).toFixed(2))
            : 0,
      },
      impact: {
        delta_revenue: Number((projectedRevenue - baseRevenue).toFixed(2)),
        delta_costs: Number((projectedCost - baseCost).toFixed(2)),
        delta_margin: Number((projectedMargin - baseMargin).toFixed(2)),
      },
    };
  }

  async getModelMonitoring() {
    const recommendations = await this.getStrategicRecommendations();
    const models = recommendations.map((r: any) => ({
      model: r.model || 'unknown',
      confidence: Number(r?.explanation?.confidence || 0),
      metric_status: r?.metrics?.status || 'n/a',
      metric_value: Number(r?.metrics?.value || 0),
      drift_score: Number(r?.drift?.score || 0),
      drift_status: r?.drift?.status || 'stable',
      priority: Number(r?.priority || 0),
      generated_at: new Date().toISOString(),
    }));

    const avgDrift = models.length
      ? Number(
          (
            models.reduce((s, m) => s + m.drift_score, 0) / models.length
          ).toFixed(3),
        )
      : 0;

    return {
      generated_at: new Date().toISOString(),
      models,
      summary: {
        total_models: models.length,
        avg_drift_score: avgDrift,
        alerts_count: models.filter((m) => m.metric_status === 'alert').length,
        high_priority_count: models.filter((m) => m.priority >= 80).length,
      },
    };
  }

  private async runVolumeModel(): Promise<any | null> {
    const now = new Date();
    const start30 = new Date(now);
    start30.setDate(now.getDate() - 30);
    const prev30 = new Date(start30);
    prev30.setDate(start30.getDate() - 30);

    const current = await this.colisRepository.count({
      where: { created_at: MoreThanOrEqual(start30) },
    });
    const previous = await this.colisRepository.count({
      where: { created_at: Between(prev30, start30) },
    });
    if (previous <= 0) return null;

    const deltaPct = ((current - previous) / previous) * 100;
    const isRisk = deltaPct < -12;
    const isOpportunity = deltaPct > 15;
    const type = isRisk ? 'warning' : isOpportunity ? 'success' : 'info';

    return {
      model: 'volume_baseline_v1',
      type,
      title: isRisk
        ? 'Risque baisse volume (V1)'
        : isOpportunity
          ? 'Hausse volume détectée (V1)'
          : 'Volume global stable (V1)',
      description: `Evolution 30j: ${deltaPct.toFixed(1)}% (période actuelle vs précédente).`,
      cause: isRisk
        ? 'Décélération des expéditions observée sur les 30 derniers jours.'
        : 'Variation contenue du flux colis.',
      action: isRisk
        ? 'Lancer une relance clients inactifs et vérifier les tarifs sensibles.'
        : isOpportunity
          ? 'Renforcer la capacité opérationnelle (tri/caisse).'
          : 'Maintenir le rythme et suivre le KPI hebdomadaire.',
      explanation: {
        factors: [
          `current_30d=${current}`,
          `previous_30d=${previous}`,
          `delta_pct=${deltaPct.toFixed(2)}`,
        ],
        confidence: 0.68,
      },
      metrics: {
        value: Number(deltaPct.toFixed(2)),
        threshold: -12,
        status: isRisk ? 'alert' : 'ok',
      },
      drift: {
        status: Math.abs(deltaPct) > 35 ? 'warning' : 'stable',
        score: Number((Math.abs(deltaPct) / 100).toFixed(3)),
      },
      priority: isRisk ? 90 : isOpportunity ? 70 : 30,
      actions: isRisk
        ? [
            {
              code: 'OPEN_RAPPORTS',
              label: 'Voir rapport historique',
              route: '/statistiques/historiques',
            },
            {
              code: 'OPEN_TARIFS',
              label: 'Ajuster tarifs',
              route: '/settings/tarifs',
            },
          ]
        : [
            {
              code: 'OPEN_DASHBOARD',
              label: 'Suivre dashboard',
              route: '/dashboard',
            },
          ],
    };
  }

  private async runUnpaidRiskModel(): Promise<any> {
    const now = new Date();
    const start60 = new Date(now);
    start60.setDate(now.getDate() - 60);

    const factures = await this.factureRepository.find({
      where: { date_facture: MoreThanOrEqual(start60), etat: 1 },
      select: ['id', 'montant_ttc', 'montant_paye', 'date_facture'],
    });

    const total = factures.length || 1;
    const impayes = factures.filter(
      (f) => Number(f.montant_paye || 0) < Number(f.montant_ttc || 0),
    );
    const unpaidRate = (impayes.length / total) * 100;
    const unpaidAmount = impayes.reduce(
      (s, f) =>
        s +
        Math.max(0, Number(f.montant_ttc || 0) - Number(f.montant_paye || 0)),
      0,
    );
    const severity =
      unpaidRate > 25 ? 'error' : unpaidRate > 12 ? 'warning' : 'info';

    return {
      model: 'unpaid_risk_score_v1',
      type: severity,
      title: 'Risque impayé (V1)',
      description: `Taux impayé 60j: ${unpaidRate.toFixed(1)}% (${impayes.length}/${total}).`,
      cause: 'Factures partielles/non soldées dans la fenêtre de recouvrement.',
      action:
        unpaidRate > 12
          ? 'Prioriser relances J+7/J+15 et demander acompte sur profils à risque.'
          : 'Maintenir le process recouvrement actuel.',
      explanation: {
        factors: [
          `unpaid_count=${impayes.length}`,
          `invoice_count=${total}`,
          `unpaid_amount=${unpaidAmount.toFixed(0)}`,
        ],
        confidence: 0.74,
      },
      metrics: {
        value: Number(unpaidRate.toFixed(2)),
        threshold: 12,
        status: unpaidRate > 12 ? 'alert' : 'ok',
        unpaid_amount: Number(unpaidAmount.toFixed(2)),
      },
      drift: {
        status: unpaidRate > 30 ? 'warning' : 'stable',
        score: Number((unpaidRate / 100).toFixed(3)),
      },
      priority: unpaidRate > 25 ? 100 : unpaidRate > 12 ? 80 : 35,
      actions: [
        {
          code: 'OPEN_PAIEMENTS',
          label: 'Ouvrir suivi paiements',
          route: '/paiements',
        },
        { code: 'OPEN_FACTURES', label: 'Ouvrir factures', route: '/factures' },
      ],
    };
  }

  private async runCashAnomalyModel(): Promise<any> {
    const anomalies = await this.detectCaisseAnomalySignals();
    const anomalyScore = anomalies.score;
    const type =
      anomalyScore >= 70 ? 'error' : anomalyScore >= 40 ? 'warning' : 'info';

    return {
      model: 'cash_anomaly_rules_v1',
      type,
      title: 'Anomalie caisse (V1)',
      description: `Score anomalie: ${anomalyScore}/100`,
      cause: anomalies.reasons.join(' | ') || 'Aucun signal notable.',
      action:
        anomalyScore >= 40
          ? 'Déclencher une revue caisse et renforcer la double validation.'
          : 'Poursuivre le contrôle journalier standard.',
      explanation: {
        factors: anomalies.reasons,
        confidence: 0.7,
      },
      metrics: {
        value: anomalyScore,
        threshold: 40,
        status: anomalyScore >= 40 ? 'alert' : 'ok',
      },
      drift: {
        status: anomalyScore >= 70 ? 'warning' : 'stable',
        score: Number((anomalyScore / 100).toFixed(3)),
      },
      priority: anomalyScore >= 70 ? 95 : anomalyScore >= 40 ? 75 : 25,
      actions: [
        {
          code: 'OPEN_CAISSE',
          label: 'Ouvrir suivi caisse',
          route: '/caisse/suivi',
        },
      ],
    };
  }

  private async detectCaisseAnomalySignals(): Promise<{
    score: number;
    reasons: string[];
  }> {
    const now = new Date();
    const start7 = new Date(now);
    start7.setDate(now.getDate() - 7);
    const start30 = new Date(now);
    start30.setDate(now.getDate() - 30);

    const weekMovements = await this.mouvementRepository.find({
      where: { created_at: MoreThanOrEqual(start7) },
      select: ['type', 'montant'],
    });
    const monthMovements = await this.mouvementRepository.find({
      where: { created_at: MoreThanOrEqual(start30) },
      select: ['type', 'montant'],
    });

    const weekDec = weekMovements
      .filter((m: any) => String(m.type).toUpperCase().includes('DECAISSEMENT'))
      .reduce((s, m) => s + Number(m.montant || 0), 0);
    const monthAvgDec = monthMovements.length
      ? monthMovements
          .filter((m: any) =>
            String(m.type).toUpperCase().includes('DECAISSEMENT'),
          )
          .reduce((s, m) => s + Number(m.montant || 0), 0) / 4
      : 0;

    const reasons: string[] = [];
    let score = 0;

    if (monthAvgDec > 0 && weekDec > monthAvgDec * 1.6) {
      score += 45;
      reasons.push('Décaissements hebdomadaires atypiques (>160% moyenne).');
    }

    const highOps = weekMovements.filter(
      (m: any) => Number(m.montant || 0) >= 100000,
    ).length;
    if (highOps >= 3) {
      score += 30;
      reasons.push('Concentration de mouvements élevés sur 7 jours.');
    }

    if (weekMovements.length > 80) {
      score += 20;
      reasons.push('Pic volumétrique de mouvements caisse.');
    }

    return { score: Math.min(score, 100), reasons };
  }

  private async runPricingRecommendationModel(): Promise<any> {
    const profitability = await this.getRealProfitability({});
    const marginPct = Number(profitability?.summary?.margin_pct || 0);
    const recommendation =
      marginPct < 18
        ? { deltaPrice: 5, deltaCost: 0, deltaVolume: -2 }
        : marginPct > 35
          ? { deltaPrice: -2, deltaCost: 0, deltaVolume: 4 }
          : { deltaPrice: 2, deltaCost: 0, deltaVolume: 0 };

    const scenario = await this.simulatePricingScenario({
      price_change_pct: recommendation.deltaPrice,
      cost_change_pct: recommendation.deltaCost,
      volume_change_pct: recommendation.deltaVolume,
    });

    return {
      model: 'pricing_reco_baseline_v1',
      type: 'info',
      title: 'Recommandation tarifaire (V1)',
      description: `Scénario conseillé: prix ${recommendation.deltaPrice >= 0 ? '+' : ''}${recommendation.deltaPrice}% / volume ${recommendation.deltaVolume >= 0 ? '+' : ''}${recommendation.deltaVolume}%.`,
      cause: `Marge globale actuelle: ${marginPct.toFixed(1)}%.`,
      action: `Impact marge estimé: ${Number(scenario?.impact?.delta_margin || 0).toLocaleString()} FCFA.`,
      explanation: {
        factors: [
          `margin_pct=${marginPct.toFixed(2)}`,
          `delta_margin=${Number(scenario?.impact?.delta_margin || 0).toFixed(2)}`,
        ],
        confidence: 0.63,
      },
      metrics: {
        value: Number(scenario?.impact?.delta_margin || 0),
        threshold: 0,
        status:
          Number(scenario?.impact?.delta_margin || 0) >= 0 ? 'ok' : 'alert',
      },
      drift: {
        status: 'stable',
        score: 0.05,
      },
      priority: 60,
      actions: [
        {
          code: 'OPEN_RENTABILITE',
          label: 'Ouvrir rentabilité',
          route: '/statistiques/rentabilite',
        },
        {
          code: 'OPEN_TARIFS',
          label: 'Ajuster les tarifs',
          route: '/settings/tarifs',
        },
      ],
      scenario,
    };
  }

  private async loadProfitabilityRows(params: {
    date_debut?: string;
    date_fin?: string;
    agence_id?: number;
  }) {
    const query = this.factureRepository
      .createQueryBuilder('facture')
      .leftJoinAndSelect('facture.colis', 'colis')
      .leftJoinAndSelect('colis.agence', 'agence')
      .leftJoinAndSelect('colis.marchandises', 'm')
      .where('facture.etat != 2');

    if (params.date_debut) {
      query.andWhere('facture.date_facture >= :dateDebut', {
        dateDebut: params.date_debut,
      });
    }
    if (params.date_fin) {
      query.andWhere('facture.date_facture <= :dateFin', {
        dateFin: params.date_fin,
      });
    }
    if (params.agence_id) {
      query.andWhere('agence.id = :agenceId', { agenceId: params.agence_id });
    }

    const factures = await query.getMany();
    const rows: Array<{
      factureId: number;
      numFacture: string;
      agence: string;
      destination: string;
      produit: string;
      revenue: number;
      totalCost: number;
      margin: number;
      unitRevenue: number;
      unitCost: number;
      unitMargin: number;
      unpaidAmount: number;
      recoveryDelayDays: number;
    }> = [];

    for (const f of factures) {
      const revenue = Number(f.montant_ttc || 0);
      const unpaidAmount = Math.max(
        0,
        Number(f.montant_ttc || 0) - Number(f.montant_paye || 0),
      );

      const marchandises = (f.colis as any)?.marchandises || [];
      const poidsTotal = marchandises.reduce(
        (s: number, m: any) => s + Number(m.poids_total || 0),
        0,
      );
      const coutDirect = marchandises.reduce(
        (s: number, m: any) => s + Number(m.cout_reel || 0),
        0,
      );
      const charges = marchandises.reduce(
        (s: number, m: any) =>
          s +
          Number(m.charges_reelles || 0) +
          Number(m.prix_emballage || 0) +
          Number(m.prix_assurance || 0) +
          Number(m.prix_agence || 0),
        0,
      );
      const totalCost = coutDirect + charges;
      const margin = revenue - totalCost;

      const lastPaiement = await this.paiementRepository
        .createQueryBuilder('p')
        .where('p.id_facture = :idFacture', { idFacture: f.id })
        .andWhere('p.etat_validation = 1')
        .orderBy('p.date_paiement', 'DESC')
        .getOne();

      const invoiceDate = new Date(f.date_facture as any);
      const endDate =
        unpaidAmount <= 0 && lastPaiement?.date_paiement
          ? new Date(lastPaiement.date_paiement as any)
          : new Date();
      const recoveryDelayDays = Math.max(
        0,
        Math.floor(
          (endDate.getTime() - invoiceDate.getTime()) / (1000 * 60 * 60 * 24),
        ),
      );

      const produit = marchandises.length
        ? Array.from(
            new Set(
              marchandises.map((m: any) => m.nom_marchandise || 'Produit'),
            ),
          ).join(', ')
        : 'Produit non renseigné';

      const divisor = poidsTotal > 0 ? poidsTotal : 1;
      const unitRevenue = revenue / divisor;
      const unitCost = totalCost / divisor;
      const unitMargin = unitRevenue - unitCost;

      rows.push({
        factureId: f.id,
        numFacture: f.num_facture,
        agence: (f.colis as any)?.agence?.nom || 'Sans agence',
        destination: (f.colis as any)?.lieu_dest || 'Sans destination',
        produit,
        revenue,
        totalCost,
        margin,
        unitRevenue: Number(unitRevenue.toFixed(2)),
        unitCost: Number(unitCost.toFixed(2)),
        unitMargin: Number(unitMargin.toFixed(2)),
        unpaidAmount: Number(unpaidAmount.toFixed(2)),
        recoveryDelayDays,
      });
    }

    return rows;
  }

  private groupPnl(
    rows: Array<{ revenue: number; totalCost: number; margin: number }>,
    keyFn: (row: any) => string,
  ) {
    const map = new Map<
      string,
      { key: string; revenue: number; costs: number; margin: number }
    >();
    rows.forEach((row: any) => {
      const key = keyFn(row) || 'N/A';
      const current = map.get(key) || { key, revenue: 0, costs: 0, margin: 0 };
      current.revenue += row.revenue;
      current.costs += row.totalCost;
      current.margin += row.margin;
      map.set(key, current);
    });
    return Array.from(map.values())
      .map((x) => ({
        ...x,
        margin_pct:
          x.revenue > 0 ? Number(((x.margin / x.revenue) * 100).toFixed(2)) : 0,
      }))
      .sort((a, b) => b.margin - a.margin);
  }
}
