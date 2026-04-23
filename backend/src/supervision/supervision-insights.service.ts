import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, DataSource } from 'typeorm';
import { Colis } from '../colis/entities/colis.entity';
import { Agence } from '../agences/entities/agence.entity';
import { Paiement } from '../paiements/entities/paiement.entity';
import { Facture } from '../factures/entities/facture.entity';
import { Caisse } from '../caisse/entities/caisse.entity';
import {
  MouvementCaisse,
  MouvementType,
} from '../caisse/entities/mouvement-caisse.entity';
import { Client } from '../clients/entities/client.entity';
import { CaisseService } from '../caisse/caisse.service';

const ENTREES: MouvementType[] = [
  MouvementType.APPRO,
  MouvementType.ENTREE_CHEQUE,
  MouvementType.ENTREE_ESPECE,
  MouvementType.ENTREE_VIREMENT,
];

/**
 * Métriques d’analyse pour la Superviseure générale (périmètre réseau, période paramétrable).
 */
@Injectable()
export class SupervisionInsightsService {
  constructor(
    @InjectRepository(Colis) private colisRepo: Repository<Colis>,
    @InjectRepository(Paiement) private paiementRepo: Repository<Paiement>,
    @InjectRepository(Facture) private factureRepo: Repository<Facture>,
    @InjectRepository(Caisse) private caisseRepo: Repository<Caisse>,
    @InjectRepository(MouvementCaisse)
    private mouvementRepo: Repository<MouvementCaisse>,
    @InjectRepository(Agence) private agenceRepo: Repository<Agence>,
    @InjectRepository(Client) private clientRepo: Repository<Client>,
    private readonly dataSource: DataSource,
    private readonly caisseService: CaisseService,
  ) {}

  private parseDateEnd(d: Date): void {
    d.setHours(23, 59, 59, 999);
  }

  /**
   * bornes [start, end] en fin de période inclusive pour end.
   */
  resolveDateRange(
    debut?: string,
    fin?: string,
  ): { start: Date; end: Date; label: string } {
    const end = fin ? new Date(fin) : new Date();
    this.parseDateEnd(end);
    let start: Date;
    if (debut) {
      start = new Date(debut);
      start.setHours(0, 0, 0, 0);
    } else {
      start = new Date(end);
      start.setDate(1);
      start.setHours(0, 0, 0, 0);
    }
    if (start.getTime() > end.getTime()) {
      const t = start;
      start = end;
      end.setTime(t.getTime());
      this.parseDateEnd(end);
    }
    return {
      start,
      end,
      label: `${start.toISOString().slice(0, 10)} – ${end.toISOString().slice(0, 10)}`,
    };
  }

  async getKpisRange(
    debut?: string,
    fin?: string,
  ): Promise<{
    periode: { debut: string; fin: string; label: string };
    colisCrees: number;
    facturesEmises: number;
    encaissementsValides: number;
    nouveauxClients: number;
    nbAgences: number;
  }> {
    const { start, end, label } = this.resolveDateRange(debut, fin);
    const colisCrees = await this.colisRepo
      .createQueryBuilder('c')
      .where('c.created_at BETWEEN :a AND :b', { a: start, b: end })
      .getCount();
    const facturesEmises = await this.factureRepo
      .createQueryBuilder('f')
      .where('f.date_facture BETWEEN :a AND :b', { a: start, b: end })
      .andWhere('f.etat != 2')
      .getCount();
    const row = await this.paiementRepo
      .createQueryBuilder('p')
      .select('COALESCE(SUM(p.montant::numeric),0)', 's')
      .where('p.etat_validation = 1')
      .andWhere('p.date_paiement BETWEEN :a AND :b', { a: start, b: end })
      .getRawOne();
    const nouveauxClients = await this.clientRepo
      .createQueryBuilder('cl')
      .where('cl.created_at BETWEEN :a AND :b', { a: start, b: end })
      .getCount();
    return {
      periode: { debut: start.toISOString().slice(0, 10), fin: end.toISOString().slice(0, 10), label },
      colisCrees,
      facturesEmises,
      encaissementsValides: Number(row?.s ?? 0),
      nouveauxClients,
      nbAgences: await this.agenceRepo.count(),
    };
  }

  /**
   * Série temporelle d’activité (colis + factures émises).
   */
  async getActivitySeries(
    debut?: string,
    fin?: string,
    bucket: 'day' | 'month' = 'day',
  ): Promise<{ point: string; colis: number; factures: number }[]> {
    const { start, end } = this.resolveDateRange(debut, fin);
    const format = bucket === 'month' ? 'YYYY-MM' : 'YYYY-MM-DD';
    const colisSql = `
      SELECT to_char(c.created_at, '${format}') AS d, COUNT(*)::int AS n
      FROM lbp_colis c
      WHERE c.created_at >= $1 AND c.created_at <= $2
      GROUP BY 1 ORDER BY 1`;
    const colisRows: { d: string; n: string }[] = await this.dataSource.query(
      colisSql,
      [start, end],
    );
    const factSql = `
      SELECT to_char(f.date_facture::timestamptz, '${format}') AS d, COUNT(*)::int AS n
      FROM lbp_factures f
      WHERE f.date_facture::date >= $1::date AND f.date_facture::date <= $2::date
        AND f.etat != 2
      GROUP BY 1 ORDER BY 1`;
    const factureRows: { d: string; n: string }[] = await this.dataSource.query(
      factSql,
      [start, end],
    );
    const cMap = new Map(colisRows.map((r) => [r.d, parseInt(r.n, 10)]));
    const fMap = new Map(factureRows.map((r) => [r.d, parseInt(r.n, 10)]));
    const keys = new Set([...cMap.keys(), ...fMap.keys()]);
    return [...keys]
      .sort()
      .map((p) => ({
        point: p,
        colis: cMap.get(p) ?? 0,
        factures: fMap.get(p) ?? 0,
      }));
  }

  /** Chiffre d’encaissements validés par année calendaire. */
  async getRevenueByYear(
    from = new Date().getFullYear() - 4,
    to = new Date().getFullYear() + 1,
  ): Promise<
    { annee: number; encaissements_valides: number; nb_paiements: number }[]
  > {
    const rows = await this.dataSource.query(
      `
      SELECT
        EXTRACT(YEAR FROM p.date_paiement)::int AS annee,
        COALESCE(SUM(p.montant::numeric), 0) AS encaissements_valides,
        COUNT(*)::int AS nb_paiements
      FROM lbp_paiements p
      WHERE p.etat_validation = 1
        AND EXTRACT(YEAR FROM p.date_paiement) BETWEEN $1 AND $2
      GROUP BY 1
      ORDER BY 1
    `,
      [from, to],
    );
    return (rows as any[]).map((r) => ({
      annee: Number(r.annee),
      encaissements_valides: Number(r.encaissements_valides),
      nb_paiements: Number(r.nb_paiements),
    }));
  }

  /**
   * Comparaison simple entre deux années (encaissements).
   */
  async getCompareYears(a1: number, a2: number) {
    const s = await this.getRevenueByYear(
      Math.min(a1, a2),
      Math.max(a1, a2),
    );
    const m1 = s.find((x) => x.annee === a1);
    const m2 = s.find((x) => x.annee === a2);
    const e1 = m1?.encaissements_valides ?? 0;
    const e2 = m2?.encaissements_valides ?? 0;
    const ecart_pct =
      e1 === 0 ? null : ((e2 - e1) / e1) * 100;
    return {
      annees: [a1, a2],
      encaissements: { [a1]: e1, [a2]: e2 },
      ecart_pourcent: ecart_pct,
    };
  }

  /**
   * Indication indicative à partir de la moyenne mobile sur les encaissements des N derniers mois complets.
   */
  async getProjectionIndicative(): Promise<{
    methodologie: string;
    base_moyenne_mensuelle: number;
    encaissement_annee_reference_estime: number;
    avertissement: string;
  }> {
    const months = 6;
    const rows: { s: string }[] = await this.dataSource.query(
      `
      SELECT COALESCE(SUM(p.montant::numeric), 0)::text AS s
      FROM lbp_paiements p
      WHERE p.etat_validation = 1
        AND p.date_paiement >= date_trunc('month', CURRENT_TIMESTAMP) - ($1::int * interval '1 month')
        AND p.date_paiement < date_trunc('month', CURRENT_TIMESTAMP)
    `,
      [months],
    );
    const total6 = Number(rows[0]?.s ?? 0);
    const moyMens = total6 / months;
    return {
      methodologie: `Moyenne des encaissements validés sur ${months} mois civils précédant le mois en cours (hors mois courant).`,
      base_moyenne_mensuelle: Math.round(moyMens),
      encaissement_annee_reference_estime: Math.round(moyMens * 12),
      avertissement:
        "Projection indicative non contractuelle, à croiser avec la saisonnalité et la stratégie commerciale. Les « années à venir » ne sont pas chiffrées de façon prédictive avancée dans cet écran.",
    };
  }

  /**
   * Productivité : colis et factures saisis par utilisateur (code_user = username) sur la période.
   */
  async getUserProductivity(debut?: string, fin?: string) {
    const { start, end } = this.resolveDateRange(debut, fin);
    const rows: any[] = await this.dataSource.query(
      `
      SELECT
        u.id,
        u.username,
        u."fullname" AS nom_complet,
        u.role::text AS role_code,
        a.nom AS agence_nom,
        COUNT(DISTINCT c.id)::int AS colis_saisis,
        COUNT(DISTINCT f.id)::int AS factures_saisies
      FROM lbp_users u
      LEFT JOIN agences a ON a.id = u.id_agence
      LEFT JOIN lbp_colis c
        ON c.code_user = u.username
       AND c.created_at >= $1
       AND c.created_at <= $2
      LEFT JOIN lbp_factures f
        ON f.code_user = u.username
       AND f.date_facture::date >= $1::date
       AND f.date_facture::date <= $2::date
      WHERE u."isActive" = true
      GROUP BY u.id, u.username, u."fullname", u.role, a.nom
      HAVING COUNT(DISTINCT c.id) + COUNT(DISTINCT f.id) > 0
      ORDER BY colis_saisis + factures_saisies DESC
      LIMIT 150
    `,
      [start, end],
    );
    const withScore = (rows as any[]).map((r) => {
      const a = (r.colis_saisis ?? 0) + (r.factures_saisies ?? 0);
      let niveau: 'élevé' | 'modéré' | 'faible' = 'faible';
      if (a >= 20) niveau = 'élevé';
      else if (a >= 5) niveau = 'modéré';
      const indice = Math.min(100, a * 4);
      return {
        ...r,
        operations_total: a,
        indice_activite: indice,
        niveau_activite: niveau,
      };
    });
    return { periode: { debut: start.toISOString().slice(0, 10), fin: end.toISOString().slice(0, 10) }, utilisateurs: withScore };
  }

  /**
   * Synthèse caisses : solde courant + volume d’entrées (mouvements positifs) sur la période, par agence.
   */
  async getCaisseReseauSynthese(debut?: string, fin?: string) {
    const { start, end } = this.resolveDateRange(debut, fin);
    const agences = await this.agenceRepo.find({ order: { id: 'ASC' } });
    const hubId = await this.caisseService.resolveHubPrincipalCaisseId().catch(
      () => 0,
    );
    const lignes: any[] = [];
    for (const ag of agences) {
      const caisses = await this.caisseRepo.find({ where: { agence: { id: ag.id } } });
      for (const caisse of caisses) {
        const solde = await this.caisseService.getSolde(caisse.id);
        const mv = await this.mouvementRepo
          .createQueryBuilder('m')
          .select('COALESCE(SUM(m.montant::numeric),0)', 's')
          .where('m.id_caisse = :id', { id: caisse.id })
          .andWhere('m.date_mouvement::date >= :a::date', { a: start })
          .andWhere('m.date_mouvement::date <= :b::date', { b: end })
          .andWhere('m.type IN (:...types)', { types: ENTREES })
          .getRawOne();
        const entrees = Number(mv?.s ?? 0);
        lignes.push({
          agence: { id: ag.id, code: ag.code, nom: ag.nom },
          id_caisse: caisse.id,
          nom_caisse: (caisse as any).nom ?? `Caisse #${caisse.id}`,
          solde_actuel: solde,
          volume_entrees_periode: entrees,
          est_caisse_principale: Number(caisse.id) === Number(hubId),
        });
      }
    }
    return { periode: { debut: start.toISOString().slice(0, 10), fin: end.toISOString().slice(0, 10) }, caisses: lignes, id_caisse_principale: hubId || null };
  }
}
