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
