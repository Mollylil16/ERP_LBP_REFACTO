import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, Between } from 'typeorm';
import { Colis } from '../colis/entities/colis.entity';
import { Client } from '../clients/entities/client.entity';
import { Facture } from '../factures/entities/facture.entity';
import { Paiement } from '../paiements/entities/paiement.entity';
import { CaisseService } from '../caisse/caisse.service';
import { AgencesService } from '../agences/agences.service';
import { Agence } from '../agences/entities/agence.entity';
import { Caisse } from '../caisse/entities/caisse.entity';
import { Litige, LitigeStatut } from '../litiges/entities/litige.entity';
import { PointJournalier } from '../exploitation/entities/point-journalier.entity';
import { FournitureDemande } from '../fournitures-bureau/entities/fourniture-demande.entity';
import { ColisStatutSuivi } from '../colis/entities/colis.entity';

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
    @InjectRepository(Caisse)
    private caisseRepository: Repository<Caisse>,
    @InjectRepository(Litige)
    private litigeRepository: Repository<Litige>,
    @InjectRepository(PointJournalier)
    private pointJournalierRepository: Repository<PointJournalier>,
    @InjectRepository(FournitureDemande)
    private fournitureDemandeRepository: Repository<FournitureDemande>,
    private caisseService: CaisseService,
    private agencesService: AgencesService,
  ) {}

  async getStats(): Promise<any> {
    const today = new Date();
    const startOfToday = new Date(today);
    startOfToday.setHours(0, 0, 0, 0);
    const endOfToday = new Date(today);
    endOfToday.setHours(23, 59, 59, 999);

    const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    endOfMonth.setHours(23, 59, 59, 999);

    const [
      colisAujourdhui,
      colisEnTransit,
      colisLivres,
      clientsActifs,
      facturesAValider,
      paiementsAttente,
      revenusJourRow,
      revenusMoisRow,
    ] = await Promise.all([
      this.colisRepository.count({
        where: { created_at: Between(startOfToday, endOfToday) },
      }),
      this.colisRepository.count({
        where: { statut_suivi: Between(ColisStatutSuivi.EMBALLE, ColisStatutSuivi.EN_LIVRAISON) as any },
      }).catch(async () => {
        // Fallback simple si l'enum/Between n'est pas supporté selon driver
        return this.colisRepository
          .createQueryBuilder('c')
          .where('c.statut_suivi != :livre', { livre: ColisStatutSuivi.LIVRE })
          .getCount();
      }),
      this.colisRepository.count({
        where: { statut_suivi: ColisStatutSuivi.LIVRE },
      }),
      this.clientRepository.count({ where: { isActive: true } }),
      this.factureRepository.count({ where: { etat: 0 } }),
      this.paiementRepository.count({ where: { etat_validation: 0 } }),
      this.paiementRepository
        .createQueryBuilder('p')
        .select('COALESCE(SUM(p.montant::numeric),0)', 's')
        .where('p.etat_validation = 1')
        .andWhere('p.date_paiement BETWEEN :a AND :b', { a: startOfToday, b: endOfToday })
        .getRawOne(),
      this.paiementRepository
        .createQueryBuilder('p')
        .select('COALESCE(SUM(p.montant::numeric),0)', 's')
        .where('p.etat_validation = 1')
        .andWhere('p.date_paiement BETWEEN :a AND :b', { a: startOfMonth, b: endOfMonth })
        .getRawOne(),
    ]);

    const revenus_jour = Number(revenusJourRow?.s ?? 0);
    const revenus_mois = Number(revenusMoisRow?.s ?? 0);

    return {
      colis_aujourdhui: colisAujourdhui,
      colis_en_transit: colisEnTransit,
      colis_livres: colisLivres,
      revenus_jour,
      revenus_mois,
      clients_actifs: clientsActifs,
      factures_a_valider: facturesAValider,
      paiements_attente: paiementsAttente,
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
      ...lastColis.map((c) => ({
        type: 'COLIS_CREATE',
        title: `Nouveau colis ${c.ref_colis}`,
        description: `Expédié par ${c.client.nom_exp} pour ${c.nom_dest}`,
        date: c.created_at,
        id: c.id,
      })),
      ...lastPayments.map((p) => ({
        type: 'PAYMENT_RECEIVE',
        title: `Paiement reçu - ${p.facture.num_facture}`,
        description: `Montant: ${p.montant} FCFA`,
        date: p.created_at,
        id: p.id,
      })),
    ];

    // Sort combined by date and limit
    return activities
      .sort((a, b) => b.date.getTime() - a.date.getTime())
      .slice(0, limit);
  }

  async getPointCaisse(date?: string): Promise<any> {
    return this.caisseService.getPointCaisse(date);
  }

  async getExecutiveSummary(): Promise<any> {
    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    const startOfToday = new Date(today);
    startOfToday.setHours(0, 0, 0, 0);
    const endOfToday = new Date(today);
    endOfToday.setHours(23, 59, 59, 999);
    const sevenDaysAgo = new Date(today);
    sevenDaysAgo.setDate(today.getDate() - 7);
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(today.getDate() - 30);

    const [
      caJourRow,
      encaissementsAttenteRow,
      litigesUrgentsRow,
      litigesStatsRaw,
      topAgencesRaw,
      soldeCaissesRaw,
      pointsJourRaw,
      totalAgences,
    ] = await Promise.all([
      // CA réseau du jour
      this.paiementRepository
        .createQueryBuilder('p')
        .select('COALESCE(SUM(p.montant::numeric), 0)', 's')
        .where('p.etat_validation = 1')
        .andWhere('p.date_paiement BETWEEN :a AND :b', {
          a: startOfToday,
          b: endOfToday,
        })
        .getRawOne(),

      // Encaissements en attente de validation
      this.paiementRepository
        .createQueryBuilder('p')
        .select('COUNT(*)', 'cnt')
        .addSelect('COALESCE(SUM(p.montant::numeric), 0)', 'total')
        .where('p.etat_validation = 0')
        .getRawOne(),

      // Litiges ouverts depuis plus de 7 jours
      this.litigeRepository
        .createQueryBuilder('l')
        .select('COUNT(*)', 'cnt')
        .where('l.statut IN (:...statuts)', {
          statuts: [LitigeStatut.OUVERT, LitigeStatut.EN_COURS],
        })
        .andWhere('l.created_at < :seuil', { seuil: sevenDaysAgo })
        .getRawOne(),

      // Répartition litiges ouverts par priorité
      this.litigeRepository
        .createQueryBuilder('l')
        .select('l.priorite', 'priorite')
        .addSelect('COUNT(*)', 'cnt')
        .where('l.statut IN (:...statuts)', {
          statuts: [LitigeStatut.OUVERT, LitigeStatut.EN_COURS],
        })
        .groupBy('l.priorite')
        .getRawMany(),

      // Top 5 agences par CA du jour
      this.paiementRepository
        .createQueryBuilder('p')
        .leftJoin('p.facture', 'f')
        .leftJoin('f.colis', 'c')
        .leftJoin('c.agence', 'a')
        .select('a.id', 'agenceId')
        .addSelect('a.nom', 'agenceNom')
        .addSelect('COALESCE(SUM(p.montant::numeric), 0)', 'ca')
        .addSelect('COUNT(DISTINCT c.id)', 'nb_colis')
        .where('p.etat_validation = 1')
        .andWhere('p.date_paiement BETWEEN :a AND :b', {
          a: startOfToday,
          b: endOfToday,
        })
        .andWhere('a.id IS NOT NULL')
        .groupBy('a.id, a.nom')
        .orderBy('"ca"', 'DESC')
        .limit(5)
        .getRawMany(),

      // Solde + seuil par caisse active
      this.caisseRepository
        .createQueryBuilder('c')
        .leftJoin('c.mouvements', 'm')
        .leftJoin('c.agence', 'a')
        .select('c.id', 'caisseId')
        .addSelect('c.nom', 'caisseNom')
        .addSelect('CAST(c.solde_initial AS numeric)', 'solde_initial')
        .addSelect('CAST(c.seuil_alerte AS numeric)', 'seuil_alerte')
        .addSelect('a.nom', 'agenceNom')
        .addSelect(
          `COALESCE(SUM(
            CASE WHEN m.type IN ('APPRO','ENTREE_CHEQUE','ENTREE_ESPECE','ENTREE_VIREMENT')
                 THEN CAST(m.montant AS numeric)
                 WHEN m.type = 'DECAISSEMENT' THEN -CAST(m.montant AS numeric)
                 ELSE 0 END
          ), 0)`,
          'mouvements_net',
        )
        .where('c."isActive" = true')
        .groupBy('c.id, c.nom, c.solde_initial, c.seuil_alerte, a.nom')
        .getRawMany(),

      // Points journaliers soumis/validés aujourd'hui (pour trouver les agences en retard)
      this.pointJournalierRepository
        .createQueryBuilder('pj')
        .leftJoin('pj.agence', 'a')
        .select('a.id', 'agenceId')
        .addSelect('a.nom', 'agenceNom')
        .where('pj.date_point = :date', { date: todayStr })
        .andWhere('pj.statut IN (:...statuts)', {
          statuts: ['SOUMIS', 'VALIDE'],
        })
        .andWhere('a.id IS NOT NULL')
        .groupBy('a.id')
        .addGroupBy('a.nom')
        .getRawMany(),

      // Nombre total d'agences actives
      this.agencesService.findAll().then((a) => a.length),
    ]);

    // --- Calculs ---
    const caJour = Number(caJourRow?.s ?? 0);
    const encaissementsAttenteCount = Number(encaissementsAttenteRow?.cnt ?? 0);
    const encaissementsAttenteTotal = Number(
      encaissementsAttenteRow?.total ?? 0,
    );
    const litigesUrgents = Number(litigesUrgentsRow?.cnt ?? 0);

    const litigesParPriorite: Record<string, number> = {};
    let litigesOuvertsTotal = 0;
    for (const r of litigesStatsRaw) {
      litigesParPriorite[r.priorite] = Number(r.cnt);
      litigesOuvertsTotal += Number(r.cnt);
    }

    const topAgences = topAgencesRaw.map((r, i) => ({
      rang: i + 1,
      agenceId: r.agenceId,
      agenceNom: r.agenceNom ?? 'Inconnue',
      ca: Number(r.ca),
      nb_colis: Number(r.nb_colis),
    }));

    let soldeConsolide = 0;
    const caissesEnAlerte: any[] = [];
    for (const c of soldeCaissesRaw) {
      const solde = Number(c.solde_initial) + Number(c.mouvements_net);
      soldeConsolide += solde;
      if (solde < Number(c.seuil_alerte)) {
        caissesEnAlerte.push({
          caisseNom: c.caisseNom,
          agenceNom: c.agenceNom ?? '—',
          solde,
          seuil: Number(c.seuil_alerte),
          deficit: Number(c.seuil_alerte) - solde,
        });
      }
    }

    const agencesAvecPointSoumis = new Set(
      pointsJourRaw.map((r: any) => r.agenceId),
    );
    const nbAgencesEnRetard = totalAgences - agencesAvecPointSoumis.size;

    return {
      ca_jour: caJour,
      encaissements_attente: {
        count: encaissementsAttenteCount,
        total: encaissementsAttenteTotal,
      },
      litiges: {
        urgents_plus_7j: litigesUrgents,
        ouverts_total: litigesOuvertsTotal,
        par_priorite: litigesParPriorite,
      },
      solde_consolide: soldeConsolide,
      caisses_en_alerte: caissesEnAlerte,
      top_agences: topAgences,
      points_journaliers: {
        agences_soumis_auj: agencesAvecPointSoumis.size,
        agences_en_retard: nbAgencesEnRetard < 0 ? 0 : nbAgencesEnRetard,
        total_agences: totalAgences,
      },
      generated_at: new Date().toISOString(),
    };
  }

  async getAgenceSummary(agenceId: number): Promise<any> {
    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    const startOfToday = new Date(today);
    startOfToday.setHours(0, 0, 0, 0);
    const endOfToday = new Date(today);
    endOfToday.setHours(23, 59, 59, 999);
    const twoHoursAgo = new Date(today);
    twoHoursAgo.setHours(today.getHours() - 2);
    const sevenDaysAgo = new Date(today);
    sevenDaysAgo.setDate(today.getDate() - 7);
    const fifteenDaysAgo = new Date(today);
    fifteenDaysAgo.setDate(today.getDate() - 15);

    const [
      colisJour,
      colisEnAttente,
      colisBloqués,
      encaisséJourRow,
      facturesImpayéesRow,
      facturesImpayées15jRow,
      soldeCaisseRow,
      fournituresEnCours,
      pointJourStatus,
    ] = await Promise.all([
      // Colis créés aujourd'hui pour cette agence
      this.colisRepository.count({
        where: {
          agence: { id: agenceId },
          created_at: Between(startOfToday, endOfToday),
        },
      }),

      // Colis en EMBALLE depuis +2h (en attente de traitement)
      this.colisRepository
        .createQueryBuilder('c')
        .where('c.id_agence = :agenceId', { agenceId })
        .andWhere('c.statut_suivi = :statut', { statut: ColisStatutSuivi.EMBALLE })
        .andWhere('c.created_at < :seuil', { seuil: twoHoursAgo })
        .select(['c.id', 'c.ref_colis', 'c.created_at', 'c.nom_dest'])
        .orderBy('c.created_at', 'ASC')
        .limit(10)
        .getMany(),

      // Colis bloqués en transit depuis +7 jours (EXPEDIE ou REC_BOBIGNY)
      this.colisRepository
        .createQueryBuilder('c')
        .where('c.id_agence = :agenceId', { agenceId })
        .andWhere('c.statut_suivi IN (:...statuts)', {
          statuts: [ColisStatutSuivi.EXPEDIE, ColisStatutSuivi.REC_BOBIGNY],
        })
        .andWhere('c.updated_at < :seuil', { seuil: sevenDaysAgo })
        .select(['c.id', 'c.ref_colis', 'c.updated_at', 'c.statut_suivi', 'c.nom_dest'])
        .orderBy('c.updated_at', 'ASC')
        .limit(10)
        .getMany(),

      // Montant encaissé aujourd'hui (paiements validés liés à l'agence)
      this.paiementRepository
        .createQueryBuilder('p')
        .leftJoin('p.facture', 'f')
        .leftJoin('f.colis', 'c')
        .select('COALESCE(SUM(p.montant::numeric), 0)', 's')
        .where('p.etat_validation = 1')
        .andWhere('p.date_paiement BETWEEN :a AND :b', { a: startOfToday, b: endOfToday })
        .andWhere('c.id_agence = :agenceId', { agenceId })
        .getRawOne(),

      // Factures impayées (définitives, non soldées)
      this.factureRepository
        .createQueryBuilder('f')
        .leftJoin('f.colis', 'c')
        .select('COUNT(*)', 'cnt')
        .addSelect('COALESCE(SUM((f.montant_ttc::numeric - f.montant_paye::numeric)), 0)', 'total')
        .where('f.etat = 1')
        .andWhere('f.montant_paye < f.montant_ttc')
        .andWhere('c.id_agence = :agenceId', { agenceId })
        .getRawOne(),

      // Factures impayées depuis +15 jours
      this.factureRepository
        .createQueryBuilder('f')
        .leftJoin('f.colis', 'c')
        .select('COUNT(*)', 'cnt')
        .addSelect('COALESCE(SUM((f.montant_ttc::numeric - f.montant_paye::numeric)), 0)', 'total')
        .where('f.etat = 1')
        .andWhere('f.montant_paye < f.montant_ttc')
        .andWhere('f.date_facture < :seuil', { seuil: fifteenDaysAgo })
        .andWhere('c.id_agence = :agenceId', { agenceId })
        .getRawOne(),

      // Solde de la caisse de l'agence
      this.caisseRepository
        .createQueryBuilder('c')
        .leftJoin('c.mouvements', 'm')
        .select('CAST(c.solde_initial AS numeric)', 'solde_initial')
        .addSelect('CAST(c.seuil_alerte AS numeric)', 'seuil_alerte')
        .addSelect(
          `COALESCE(SUM(
            CASE WHEN m.type IN ('APPRO','ENTREE_CHEQUE','ENTREE_ESPECE','ENTREE_VIREMENT')
                 THEN CAST(m.montant AS numeric)
                 WHEN m.type = 'DECAISSEMENT' THEN -CAST(m.montant AS numeric)
                 ELSE 0 END
          ), 0)`,
          'mvt_net',
        )
        .where('c.id_agence = :agenceId', { agenceId })
        .andWhere('c."isActive" = true')
        .groupBy('c.solde_initial, c.seuil_alerte')
        .getRawOne(),

      // Demandes de fournitures en cours pour cette agence
      this.fournitureDemandeRepository.find({
        where: { agence: { id: agenceId } },
        order: { created_at: 'DESC' },
        take: 5,
        select: ['id', 'statut', 'created_at', 'date_validation', 'date_livraison'],
      }),

      // Point journalier du jour
      this.pointJournalierRepository.findOne({
        where: { agence: { id: agenceId }, date_point: todayStr },
        order: { created_at: 'DESC' },
        select: ['id', 'statut', 'total_recettes', 'date_point'],
      }),
    ]);

    const solde =
      soldeCaisseRow
        ? Number(soldeCaisseRow.solde_initial) + Number(soldeCaisseRow.mvt_net)
        : null;

    return {
      date: todayStr,
      colis: {
        aujourd_hui: colisJour,
        en_attente_traitement: colisEnAttente.map((c) => ({
          id: c.id,
          ref: c.ref_colis,
          destination: c.nom_dest,
          created_at: c.created_at,
          attente_minutes: Math.floor(
            (Date.now() - new Date(c.created_at).getTime()) / 60_000,
          ),
        })),
        bloqués_transit: colisBloqués.map((c) => ({
          id: c.id,
          ref: c.ref_colis,
          destination: c.nom_dest,
          statut: c.statut_suivi,
          jours_transit: Math.floor(
            (Date.now() - new Date(c.updated_at).getTime()) / 86_400_000,
          ),
        })),
      },
      encaisse_jour: Number(encaisséJourRow?.s ?? 0),
      factures_impayees: {
        count: Number(facturesImpayéesRow?.cnt ?? 0),
        total: Number(facturesImpayéesRow?.total ?? 0),
      },
      factures_impayees_15j: {
        count: Number(facturesImpayées15jRow?.cnt ?? 0),
        total: Number(facturesImpayées15jRow?.total ?? 0),
      },
      caisse: soldeCaisseRow
        ? {
            solde,
            seuil_alerte: Number(soldeCaisseRow.seuil_alerte),
            en_alerte: solde !== null && solde < Number(soldeCaisseRow.seuil_alerte),
          }
        : null,
      fournitures_recentes: fournituresEnCours.map((d) => ({
        id: d.id,
        statut: d.statut,
        created_at: d.created_at,
        date_validation: d.date_validation,
        date_livraison: d.date_livraison,
      })),
      point_journalier: pointJourStatus
        ? {
            id: pointJourStatus.id,
            statut: pointJourStatus.statut,
            total_recettes: Number(pointJourStatus.total_recettes ?? 0),
          }
        : null,
      generated_at: new Date().toISOString(),
    };
  }

  async getAgenciesPerformances(date?: string): Promise<any[]> {
    const agences = await this.agencesService.findAll();
    const results: any[] = [];

    const target = date ? new Date(date) : new Date();
    const start = new Date(target);
    start.setHours(0, 0, 0, 0);
    const end = new Date(target);
    end.setHours(23, 59, 59, 999);

    for (const agence of agences) {
      // Entrées: paiements validés du jour rattachés à l'agence (plus fidèle à "l'activité" que les seuls mouvements de caisse).
      const payRow = await this.paiementRepository
        .createQueryBuilder('p')
        .leftJoin('p.facture', 'f')
        .leftJoin('f.colis', 'c')
        .leftJoin('c.agence', 'a')
        .select('COALESCE(SUM(p.montant::numeric),0)', 's')
        .where('p.etat_validation = 1')
        .andWhere('p.date_paiement BETWEEN :start AND :end', { start, end })
        .andWhere('a.id = :aid', { aid: agence.id })
        .getRawOne();
      const totalEntrees = Number(payRow?.s ?? 0);

      // Sorties: décaissements du jour (mouvements caisse)
      const caisses = await this.caisseService.findAllCaisses(agence.id);
      let totalSorties = 0;
      for (const caisse of caisses) {
        const point = await this.caisseService.getPointCaisse(
          start.toISOString().slice(0, 10),
          caisse.id,
        );
        totalSorties += Number(point.sorties ?? 0);
      }

      results.push({
        agenceId: agence.id,
        agenceNom: agence.nom,
        agenceCode: agence.code,
        entrees: totalEntrees,
        sorties: totalSorties,
        // Solde Net (jour) attendu par la grille front.
        solde: Number(totalEntrees) - Number(totalSorties),
        date: (date || new Date().toISOString().split('T')[0]) as string,
      });
    }

    return results;
  }
}
