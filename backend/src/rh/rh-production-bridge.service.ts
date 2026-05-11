import { Injectable } from '@nestjs/common';
import { DataSource } from 'typeorm';

export interface ProductionMetrics {
  username: string | null;
  colis_count: number;
  ca_total: number;
  prime_performance: number;
  score_resultats: number;
}

@Injectable()
export class RhProductionBridgeService {
  constructor(private readonly dataSource: DataSource) {}

  /**
   * Récupère les métriques de production pour un employé via son id_user → username → code_user sur les colis/factures.
   * période au format 'YYYY-MM'.
   */
  async getProductionMetrics(idUser: number, periode: string): Promise<ProductionMetrics> {
    const [year, month] = periode.split('-').map(Number);
    const startDate = new Date(year, month - 1, 1);
    const endDate = new Date(year, month, 0, 23, 59, 59, 999);

    const userRows: Array<{ username: string }> = await this.dataSource.query(
      `SELECT username FROM lbp_users WHERE id = $1 LIMIT 1`,
      [idUser],
    );
    if (!userRows.length) {
      return { username: null, colis_count: 0, ca_total: 0, prime_performance: 0, score_resultats: 0 };
    }
    const username = userRows[0].username;

    const colisRows: Array<{ nb: string }> = await this.dataSource.query(
      `SELECT COUNT(*) AS nb FROM lbp_colis WHERE code_user = $1 AND created_at BETWEEN $2 AND $3`,
      [username, startDate.toISOString(), endDate.toISOString()],
    );
    const colisCount = parseInt(colisRows[0]?.nb ?? '0', 10);

    const caRows: Array<{ ca: string }> = await this.dataSource.query(
      `SELECT COALESCE(SUM(total_ttc::numeric), 0) AS ca FROM lbp_factures WHERE code_user = $1 AND created_at BETWEEN $2 AND $3`,
      [username, startDate.toISOString(), endDate.toISOString()],
    );
    const caTotal = parseFloat(caRows[0]?.ca ?? '0');

    return {
      username,
      colis_count: colisCount,
      ca_total: caTotal,
      prime_performance: this.calculerPrime(colisCount),
      score_resultats: this.calculerScore(colisCount),
    };
  }

  /**
   * Barème prime performance basé sur le nombre de colis traités dans le mois.
   * Retourne un montant forfaitaire (à ajuster selon la politique RH).
   */
  private calculerPrime(colisCount: number): number {
    if (colisCount >= 300) return 50_000;
    if (colisCount >= 200) return 35_000;
    if (colisCount >= 100) return 20_000;
    if (colisCount >= 50) return 10_000;
    return 0;
  }

  /**
   * Score résultats (0–100) basé sur le volume de colis.
   * Cible de référence : 150 colis/mois = 75 points.
   */
  private calculerScore(colisCount: number): number {
    const CIBLE = 150;
    const raw = (colisCount / CIBLE) * 75;
    return Math.min(100, Math.round(raw * 100) / 100);
  }

  /**
   * Synthèse réseau pour la supervision : métriques de production par user (mois en cours).
   */
  async getNetworkProductionSummary(debut: string, fin: string): Promise<
    Array<{
      id_user: number;
      username: string;
      nom_complet: string | null;
      agence_nom: string | null;
      colis_count: number;
      ca_total: number;
    }>
  > {
    return this.dataSource.query(
      `SELECT
         u.id AS id_user,
         u.username,
         u."fullname" AS nom_complet,
         a.nom AS agence_nom,
         COUNT(DISTINCT c.id) AS colis_count,
         COALESCE(SUM(f.total_ttc::numeric), 0) AS ca_total
       FROM lbp_users u
       LEFT JOIN agences a ON a.id = u."id_agence"
       LEFT JOIN lbp_colis c ON c.code_user = u.username
         AND c.created_at BETWEEN $1 AND $2
       LEFT JOIN lbp_factures f ON f.code_user = u.username
         AND f.created_at BETWEEN $1 AND $2
       WHERE u."isActive" = true
       GROUP BY u.id, u.username, u."fullname", a.nom
       ORDER BY colis_count DESC
       LIMIT 200`,
      [debut, fin],
    );
  }
}
