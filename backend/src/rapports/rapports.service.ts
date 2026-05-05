import { BadRequestException, Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { In, Repository, Between } from 'typeorm';
import { Colis } from '../colis/entities/colis.entity';
import { Facture } from '../factures/entities/facture.entity';
import { Paiement } from '../paiements/entities/paiement.entity';
import { effectiveRoleCode } from '../common/effective-role-code';
import { MouvementCaisse, MouvementType } from '../caisse/entities/mouvement-caisse.entity';
import { Caisse } from '../caisse/entities/caisse.entity';
import { Agence } from '../agences/entities/agence.entity';

@Injectable()
export class RapportsService {
  constructor(
    @InjectRepository(Colis)
    private colisRepository: Repository<Colis>,
    @InjectRepository(Facture)
    private factureRepository: Repository<Facture>,
    @InjectRepository(Paiement)
    private paiementRepository: Repository<Paiement>,
    @InjectRepository(MouvementCaisse)
    private mouvementRepository: Repository<MouvementCaisse>,
    @InjectRepository(Caisse)
    private caisseRepository: Repository<Caisse>,
    @InjectRepository(Agence)
    private agenceRepository: Repository<Agence>,
  ) {}

  private parseAnneesParam(annees?: string): number[] {
    if (!annees) return [];
    return String(annees)
      .split(',')
      .map((x) => Number(String(x).trim()))
      .filter((n) => Number.isFinite(n) && n > 1900 && n < 3000);
  }

  private monthLabelFromIndex(monthIndex1to12: number): string {
    const labels = [
      'Jan',
      'Fév',
      'Mar',
      'Avr',
      'Mai',
      'Juin',
      'Juil',
      'Aoû',
      'Sep',
      'Oct',
      'Nov',
      'Déc',
    ];
    return labels[Math.max(1, Math.min(12, monthIndex1to12)) - 1];
  }

  async getHistoriqueMultiAnnees(anneesParam?: string): Promise<
    Array<{
      annee: number;
      mois: Array<{
        mois: string;
        groupage: number;
        autresEnvois: number;
        total: number;
        revenus: number;
      }>;
      totalColis: number;
      totalRevenus: number;
      moyenneMensuelle: number;
    }>
  > {
    const annees = this.parseAnneesParam(anneesParam);
    if (!annees.length) return [];

    // Agrégation par année/mois, avec revenus = SUM(nbre_colis * prix_unit)
    // NB: on utilise created_at pour refléter l'activité saisie, pas seulement date_envoi.
    const rows = await this.colisRepository
      .createQueryBuilder('c')
      .leftJoin('c.marchandises', 'm')
      .select([
        `EXTRACT(YEAR FROM c.created_at)::int AS annee`,
        `EXTRACT(MONTH FROM c.created_at)::int AS mois_num`,
        `COUNT(DISTINCT c.id)::int AS colis_total`,
        `COUNT(DISTINCT c.id) FILTER (WHERE c.forme_envoi = 'groupage')::int AS colis_groupage`,
        `COUNT(DISTINCT c.id) FILTER (WHERE c.forme_envoi IS NULL OR c.forme_envoi <> 'groupage')::int AS colis_autres`,
        `COALESCE(SUM(COALESCE(m.nbre_colis,0) * COALESCE(m.prix_unit,0)), 0)::float AS revenus`,
      ])
      .where(`EXTRACT(YEAR FROM c.created_at) = ANY(:annees)`, { annees })
      .groupBy('annee')
      .addGroupBy('mois_num')
      .orderBy('annee', 'ASC')
      .addOrderBy('mois_num', 'ASC')
      .getRawMany<{
        annee: number;
        mois_num: number;
        colis_total: number;
        colis_groupage: number;
        colis_autres: number;
        revenus: number;
      }>();

    const byYearMonth = new Map<string, (typeof rows)[number]>();
    for (const r of rows) {
      const key = `${Number(r.annee)}-${Number(r.mois_num)}`;
      byYearMonth.set(key, {
        ...r,
        annee: Number(r.annee),
        mois_num: Number(r.mois_num),
        colis_total: Number(r.colis_total) || 0,
        colis_groupage: Number(r.colis_groupage) || 0,
        colis_autres: Number(r.colis_autres) || 0,
        revenus: Number(r.revenus) || 0,
      });
    }

    const result = annees
      .slice()
      .sort((a, b) => a - b)
      .map((annee) => {
        const mois = Array.from({ length: 12 }, (_, idx) => {
          const moisNum = idx + 1;
          const r = byYearMonth.get(`${annee}-${moisNum}`);
          const groupage = r?.colis_groupage ?? 0;
          const autresEnvois = r?.colis_autres ?? 0;
          const total = r?.colis_total ?? 0;
          const revenus = r?.revenus ?? 0;
          return {
            mois: this.monthLabelFromIndex(moisNum),
            groupage,
            autresEnvois,
            total,
            revenus,
          };
        });

        const totalColis = mois.reduce((acc, m) => acc + (m.total || 0), 0);
        const totalRevenus = mois.reduce((acc, m) => acc + (m.revenus || 0), 0);
        const moyenneMensuelle = totalColis / 12;

        return { annee, mois, totalColis, totalRevenus, moyenneMensuelle };
      });

    return result;
  }

  async getTendancesMensuelles(anneesParam?: string): Promise<
    Array<{
      mois: string;
      meilleureAnnee: number;
      meilleureValeur: number;
      pireAnnee: number;
      pireValeur: number;
      moyenne: number;
      evolution: number;
      tendance: 'hausse' | 'baisse' | 'stable';
    }>
  > {
    const annees = this.parseAnneesParam(anneesParam);
    if (!annees.length) return [];

    // Données de base: total colis par mois/année (sur created_at)
    const rows = await this.colisRepository
      .createQueryBuilder('c')
      .select([
        `EXTRACT(YEAR FROM c.created_at)::int AS annee`,
        `EXTRACT(MONTH FROM c.created_at)::int AS mois_num`,
        `COUNT(*)::int AS total_colis`,
      ])
      .where(`EXTRACT(YEAR FROM c.created_at) = ANY(:annees)`, { annees })
      .groupBy('annee')
      .addGroupBy('mois_num')
      .orderBy('mois_num', 'ASC')
      .addOrderBy('annee', 'ASC')
      .getRawMany<{ annee: number; mois_num: number; total_colis: number }>();

    const yearsSorted = annees.slice().sort((a, b) => a - b);
    const lastYear = yearsSorted[yearsSorted.length - 1];
    const prevYear = yearsSorted.length >= 2 ? yearsSorted[yearsSorted.length - 2] : undefined;

    const byMonth = new Map<number, Map<number, number>>(); // mois -> (annee -> total)
    for (const r of rows) {
      const moisNum = Number(r.mois_num);
      const annee = Number(r.annee);
      const total = Number(r.total_colis) || 0;
      if (!byMonth.has(moisNum)) byMonth.set(moisNum, new Map());
      byMonth.get(moisNum)!.set(annee, total);
    }

    const out = Array.from({ length: 12 }, (_, idx) => {
      const moisNum = idx + 1;
      const valuesByYear = byMonth.get(moisNum) ?? new Map<number, number>();

      let bestYear = yearsSorted[0];
      let bestValue = -Infinity;
      let worstYear = yearsSorted[0];
      let worstValue = Infinity;

      let sum = 0;
      for (const y of yearsSorted) {
        const v = valuesByYear.get(y) ?? 0;
        sum += v;
        if (v > bestValue) {
          bestValue = v;
          bestYear = y;
        }
        if (v < worstValue) {
          worstValue = v;
          worstYear = y;
        }
      }

      const moyenne = yearsSorted.length ? sum / yearsSorted.length : 0;
      const lastVal = valuesByYear.get(lastYear) ?? 0;
      const prevVal = prevYear ? valuesByYear.get(prevYear) ?? 0 : 0;
      const evolution =
        prevYear && prevVal > 0 ? ((lastVal - prevVal) / prevVal) * 100 : 0;

      let tendance: 'hausse' | 'baisse' | 'stable' = 'stable';
      if (evolution >= 5) tendance = 'hausse';
      else if (evolution <= -5) tendance = 'baisse';

      return {
        mois: this.monthLabelFromIndex(moisNum),
        meilleureAnnee: bestYear,
        meilleureValeur: Number.isFinite(bestValue) ? bestValue : 0,
        pireAnnee: worstYear,
        pireValeur: Number.isFinite(worstValue) ? worstValue : 0,
        moyenne,
        evolution,
        tendance,
      };
    });

    return out;
  }

  private canSeeAllAgences(user: any): boolean {
    const rc = String(effectiveRoleCode(user) || '').toUpperCase();
    return (
      user?.code_acces === 2 ||
      [
        'ADMIN',
        'SUPER_ADMIN',
        'DIRECTEUR',
        'CAISSIER',
        'ASSISTANT_DG',
        'SUPERVISEURE_GENERALE',
        'SUPERVISEUR_REGIONAL',
      ].includes(rc)
    );
  }

  async generateRapportColis(params: any, user?: any): Promise<any[]> {
    const {
      start_date,
      end_date,
      trafic_envoi,
      mode_envoi,
      forme_envoi,
      client_id,
    } = params;
    const where: any = {};

    if (start_date && end_date) {
      where.date_envoi = Between(new Date(start_date), new Date(end_date));
    }
    if (trafic_envoi) where.trafic_envoi = trafic_envoi;
    if (mode_envoi) where.mode_envoi = mode_envoi;
    if (forme_envoi) where.forme_envoi = forme_envoi;
    if (client_id) where.client = { id: client_id };

    // Périmètre agence: par défaut restreint à l'agence du compte, sauf profils "réseau".
    const aid =
      user?.id_agence ??
      user?.agency_id ??
      user?.agence?.id ??
      user?.agency?.id;
    if (aid && !this.canSeeAllAgences(user)) {
      where.agence = { id: Number(aid) };
    }

    const colis = await this.colisRepository.find({
      where,
      relations: ['client', 'marchandises', 'agence'],
      order: { date_envoi: 'DESC' },
    });

    if (!colis.length) return [];

    // Factures liées aux colis
    const factures = await this.factureRepository.find({
      where: { colis: { id: In(colis.map((c) => c.id)) } },
      relations: ['colis'],
    });
    const factureByColis = new Map<number, Facture>();
    for (const f of factures) {
      const cid = (f as any)?.colis?.id;
      if (cid) factureByColis.set(Number(cid), f);
    }

    // Paiements liés aux factures (agrégation rapide)
    const factureIds = factures.map((f) => f.id);
    const paiements = factureIds.length
      ? await this.paiementRepository.find({
          where: { facture: { id: In(factureIds) } },
          relations: ['facture'],
        })
      : [];
    const paiementsAgg = new Map<
      number,
      { valides: number; attente: number; nb_valides: number; nb_attente: number; last_date: Date | null }
    >();
    for (const p of paiements) {
      const fid = (p as any)?.facture?.id;
      if (!fid) continue;
      const curr =
        paiementsAgg.get(fid) ?? {
          valides: 0,
          attente: 0,
          nb_valides: 0,
          nb_attente: 0,
          last_date: null,
        };
      if (Number(p.etat_validation) === 1) {
        curr.valides += Number(p.montant ?? 0);
        curr.nb_valides += 1;
      } else {
        curr.attente += Number(p.montant ?? 0);
        curr.nb_attente += 1;
      }
      const d = (p as any).date_paiement ? new Date((p as any).date_paiement) : null;
      if (d && (!curr.last_date || d.getTime() > curr.last_date.getTime())) curr.last_date = d;
      paiementsAgg.set(fid, curr);
    }

    // Retour enrichi (sans casser l'ancienne structure)
    return colis.map((c) => {
      const f = factureByColis.get(Number(c.id)) ?? null;
      const agg = f ? paiementsAgg.get(Number(f.id)) : undefined;
      return {
        ...c,
        facture: f
          ? {
              id: f.id,
              num_facture: f.num_facture,
              etat: f.etat,
              payment_status: f.payment_status,
              montant_ttc: Number(f.montant_ttc ?? 0),
              montant_paye: Number(f.montant_paye ?? 0),
              devise: f.devise,
              date_facture: f.date_facture,
            }
          : null,
        paiements_resume: f
          ? {
              total_valides: Number(agg?.valides ?? 0),
              total_attente: Number(agg?.attente ?? 0),
              nb_valides: Number(agg?.nb_valides ?? 0),
              nb_attente: Number(agg?.nb_attente ?? 0),
              last_date_paiement: agg?.last_date ? agg.last_date.toISOString() : null,
            }
          : null,
      };
    });
  }

  private resolveAgenceScope(params: any, user: any): { agenceId?: number } {
    const requested = params?.agence_id != null ? Number(params.agence_id) : undefined;
    const aid =
      user?.id_agence ??
      user?.agency_id ??
      user?.agence?.id ??
      user?.agency?.id;
    const canAll = this.canSeeAllAgences(user);

    if (requested != null && Number.isFinite(requested) && requested > 0) {
      if (!canAll) {
        if (!aid) {
          throw new BadRequestException('Agence requise pour ce profil');
        }
        if (Number(aid) !== Number(requested)) {
          throw new BadRequestException("Vous ne pouvez exporter que l'état de votre agence.");
        }
      }
      return { agenceId: requested };
    }

    if (!canAll && aid) return { agenceId: Number(aid) };
    return {};
  }

  private resolveDateRange(params: any): { start: Date; end: Date; label: string } {
    const debut = params?.debut || params?.start_date || params?.date_debut;
    const fin = params?.fin || params?.end_date || params?.date_fin;
    if (!debut || !fin) {
      throw new BadRequestException('Paramètres debut/fin requis');
    }
    const start = new Date(String(debut));
    const end = new Date(String(fin));
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
      throw new BadRequestException('Dates invalides');
    }
    start.setHours(0, 0, 0, 0);
    end.setHours(23, 59, 59, 999);
    if (start.getTime() > end.getTime()) {
      const tmp = new Date(start);
      start.setTime(end.getTime());
      end.setTime(tmp.getTime());
    }
    const label = `${start.toISOString().slice(0, 10)} → ${end.toISOString().slice(0, 10)}`;
    return { start, end, label };
  }

  /**
   * État agence complet : colis + factures + paiements + caisse (mouvements) sur la période.
   * Retour JSON "prêt à exporter" (le PDF/Excel est généré côté front pour rester brandé LBP).
   */
  async getEtatAgenceComplet(params: any, user: any): Promise<any> {
    const { start, end, label } = this.resolveDateRange(params);
    const { agenceId } = this.resolveAgenceScope(params, user);

    const agence = agenceId
      ? await this.agenceRepository.findOne({ where: { id: agenceId } })
      : null;

    // Colis sur la période (par date_envoi)
    const colisWhere: any = {
      date_envoi: Between(start, end),
    };
    if (agenceId) colisWhere.agence = { id: agenceId };

    const colis = await this.colisRepository.find({
      where: colisWhere,
      relations: ['client', 'marchandises', 'agence'],
      order: { date_envoi: 'DESC' },
      take: 10_000,
    });

    const colisIds = colis.map((c) => c.id);
    const factures = colisIds.length
      ? await this.factureRepository.find({
          where: { colis: { id: In(colisIds) } },
          relations: ['colis'],
        })
      : [];

    const factureIds = factures.map((f) => f.id);
    const paiements = factureIds.length
      ? await this.paiementRepository.find({
          where: {
            facture: { id: In(factureIds) },
            date_paiement: Between(start, end),
          },
          relations: ['facture', 'facture.colis'],
          order: { date_paiement: 'DESC' },
          take: 50_000,
        })
      : [];

    // Caisses de l'agence + mouvements sur période
    const caisses = agenceId
      ? await this.caisseRepository.find({
          where: { agence: { id: agenceId } },
          relations: ['agence'],
          order: { id: 'ASC' },
        })
      : [];
    const caisseIds = caisses.map((c) => c.id);
    const mouvements = caisseIds.length
      ? await this.mouvementRepository.find({
          where: {
            caisse: { id: In(caisseIds) },
            date_mouvement: Between(start, end),
          },
          relations: ['caisse', 'caisse.agence'],
          order: { date_mouvement: 'DESC', id: 'DESC' } as any,
          take: 50_000,
        })
      : [];

    const montantColis = (c: any): number => {
      const ms = Array.isArray(c.marchandises) ? c.marchandises : [];
      if (ms.length) {
        return ms.reduce((acc: number, m: any) => {
          return (
            acc +
            Number(m.poids_total || 0) * Number(m.prix_unit || 0) +
            Number(m.prix_emballage || 0) +
            Number(m.prix_assurance || 0)
          );
        }, 0);
      }
      return Number((c as any).total_montant || 0);
    };

    const totalPoids = colis.reduce((acc, c: any) => {
      const ms = Array.isArray(c.marchandises) ? c.marchandises : [];
      return acc + ms.reduce((s: number, m: any) => s + Number(m.poids_total || 0), 0);
    }, 0);
    const nbColisPhysiques = colis.reduce((acc, c: any) => {
      const ms = Array.isArray(c.marchandises) ? c.marchandises : [];
      const n = ms.reduce((s: number, m: any) => s + Number(m.nbre_colis || 0), 0);
      return acc + (n || 0);
    }, 0);
    const montantTotalColis = colis.reduce((acc, c: any) => acc + montantColis(c), 0);

    const paiementsValides = paiements
      .filter((p) => Number((p as any).etat_validation) === 1)
      .reduce((s, p: any) => s + Number(p.montant || 0), 0);
    const paiementsAttente = paiements
      .filter((p) => Number((p as any).etat_validation) !== 1)
      .reduce((s, p: any) => s + Number(p.montant || 0), 0);

    const caisseEntrees = mouvements
      .filter((m: any) => m.type !== MouvementType.DECAISSEMENT)
      .reduce((s: number, m: any) => s + Number(m.montant || 0), 0);
    const caisseSorties = mouvements
      .filter((m: any) => m.type === MouvementType.DECAISSEMENT)
      .reduce((s: number, m: any) => s + Number(m.montant || 0), 0);

    return {
      meta: {
        periode: { debut: start.toISOString().slice(0, 10), fin: end.toISOString().slice(0, 10), label },
        agence: agence ? { id: agence.id, code: (agence as any).code, nom: agence.nom } : null,
        generated_at: new Date().toISOString(),
      },
      kpis: {
        colis_count: colis.length,
        colis_groupage: colis.filter((c: any) => c.forme_envoi === 'groupage').length,
        colis_autres: colis.filter((c: any) => c.forme_envoi !== 'groupage').length,
        nb_colis_physiques: nbColisPhysiques,
        poids_total_kg: totalPoids,
        montant_total_colis: montantTotalColis,
        factures_count: factures.length,
        paiements_total_valides: paiementsValides,
        paiements_total_attente: paiementsAttente,
        caisse_entrees: caisseEntrees,
        caisse_sorties: caisseSorties,
      },
      colis,
      factures,
      paiements,
      caisses,
      mouvements_caisse: mouvements,
    };
  }

  async exportExcel(params: any): Promise<Buffer> {
    // Mocking Excel export
    return Buffer.from(
      'Mock Excel Content for period ' +
        params.start_date +
        ' to ' +
        params.end_date,
    );
  }

  async exportPDF(params: any): Promise<Buffer> {
    // Mocking PDF export
    return Buffer.from(
      'Mock PDF Content for period ' +
        params.start_date +
        ' to ' +
        params.end_date,
    );
  }

  async getFinancesParTarif(): Promise<any[]> {
    const result = await this.colisRepository
      .createQueryBuilder('colis')
      .leftJoin('colis.marchandises', 'm')
      .leftJoin('m.tarif', 't')
      .select([
        'm.prix_unit as tarif',
        't.nom as nom_tarif',
        'SUM(m.nbre_colis * m.prix_unit) as revenu_total',
        'SUM(m.nbre_colis * m.cout_reel) as cout_total',
        'SUM(m.nbre_colis * m.charges_reelles) as charges_totales',
        'SUM(m.nbre_colis * (m.prix_unit - m.cout_reel - m.charges_reelles)) as benefice_total',
        'SUM(m.poids_total) as poids_total',
      ])
      .groupBy('m.prix_unit')
      .addGroupBy('t.nom')
      .getRawMany();

    return result.map((item) => ({
      tarif: parseFloat(item.tarif),
      nom_tarif: item.nom_tarif || `Tarif ${item.tarif}`,
      revenu_total: parseFloat(item.revenu_total) || 0,
      cout_total: parseFloat(item.cout_total) || 0,
      charges_totales: parseFloat(item.charges_totales) || 0,
      benefice_total: parseFloat(item.benefice_total) || 0,
      poids_total: parseFloat(item.poids_total) || 0,
    }));
  }
}
