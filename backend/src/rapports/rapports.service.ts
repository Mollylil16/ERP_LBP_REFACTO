import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, Between } from 'typeorm';
import { Colis } from '../colis/entities/colis.entity';

@Injectable()
export class RapportsService {
  constructor(
    @InjectRepository(Colis)
    private colisRepository: Repository<Colis>,
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

  async generateRapportColis(params: any): Promise<Colis[]> {
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

    return this.colisRepository.find({
      where,
      relations: ['client', 'marchandises'],
      order: { date_envoi: 'DESC' },
    });
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
