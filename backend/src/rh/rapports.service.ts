import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, DataSource } from 'typeorm';
import { RhEmploye, StatutEmploye } from './entities/rh-employe.entity';
import { RhPaieRun, StatutPaieRun } from './entities/rh-paie-run.entity';
import { RhPaieLigne } from './entities/rh-paie-ligne.entity';
import { RhPresence, StatutPresence } from './entities/rh-presence.entity';
import { RhCongeRequest } from './entities/rh-conge-request.entity';

@Injectable()
export class RapportsService {
  constructor(
    @InjectRepository(RhEmploye) private employeRepo: Repository<RhEmploye>,
    @InjectRepository(RhPaieRun) private runRepo: Repository<RhPaieRun>,
    @InjectRepository(RhPaieLigne) private ligneRepo: Repository<RhPaieLigne>,
    @InjectRepository(RhPresence) private presenceRepo: Repository<RhPresence>,
    @InjectRepository(RhCongeRequest) private congeRepo: Repository<RhCongeRequest>,
    private readonly dataSource: DataSource,
  ) {}

  // ── Bilan social annuel ────────────────────────────────────────────────────

  async getBilanSocial(annee: number) {
    const [effectifTotal, effectifActif, effectifSorti] = await Promise.all([
      this.employeRepo.count(),
      this.employeRepo.count({ where: { statut: StatutEmploye.ACTIF } }),
      this.employeRepo.count({ where: { statut: StatutEmploye.SORTI } }),
    ]);

    const parSexe: Array<{ sexe: string; nb: number }> = await this.dataSource.query(
      `SELECT COALESCE(sexe::text, 'non_renseigne') as sexe, COUNT(*)::int as nb
       FROM rh_employes WHERE statut = 'actif' GROUP BY sexe`,
    );

    const parTypeContrat: Array<{ type: string; nb: number }> = await this.dataSource.query(
      `SELECT type_contrat_actuel as type, COUNT(*)::int as nb
       FROM rh_employes WHERE statut = 'actif' GROUP BY type_contrat_actuel`,
    );

    const parDepartement: Array<{ departement: string; nb: number }> = await this.dataSource.query(
      `SELECT COALESCE(departement, 'Non défini') as departement, COUNT(*)::int as nb
       FROM rh_employes WHERE statut = 'actif' GROUP BY departement ORDER BY nb DESC LIMIT 15`,
    );

    const parAgence: Array<{ agence: string; nb: number }> = await this.dataSource.query(
      `SELECT COALESCE(a.nom, 'Sans agence') as agence, COUNT(e.id)::int as nb
       FROM rh_employes e LEFT JOIN agences a ON a.id = e.id_agence
       WHERE e.statut = 'actif' GROUP BY a.nom ORDER BY nb DESC`,
    );

    // Masse salariale annuelle
    const masseSalariale: { total_brut: string; total_net: string }[] = await this.dataSource.query(
      `SELECT COALESCE(SUM(total_brut),0)::numeric as total_brut,
              COALESCE(SUM(total_net),0)::numeric as total_net
       FROM rh_paie_runs WHERE periode LIKE $1 AND statut IN ('valide_daf','cloture')`,
      [`${annee}-%`],
    );

    // Taux d'absentéisme
    const absencesAnnee = await this.presenceRepo.count({
      where: { statut: StatutPresence.ABSENT },
    });
    const presencesAnnee = await this.presenceRepo.count();
    const tauxAbsenteisme = presencesAnnee > 0
      ? Math.round((absencesAnnee / presencesAnnee) * 100 * 10) / 10
      : 0;

    // Turnover : sorties dans l'année
    const sorties: { nb: string }[] = await this.dataSource.query(
      `SELECT COUNT(*)::int as nb FROM rh_employes
       WHERE statut = 'sorti' AND EXTRACT(YEAR FROM date_sortie::date) = $1`,
      [annee],
    );

    const embauches: { nb: string }[] = await this.dataSource.query(
      `SELECT COUNT(*)::int as nb FROM rh_employes
       WHERE EXTRACT(YEAR FROM date_embauche::date) = $1`,
      [annee],
    );

    const nbSorties = Number(sorties[0]?.nb ?? 0);
    const tauxTurnover = effectifTotal > 0
      ? Math.round((nbSorties / effectifTotal) * 100 * 10) / 10
      : 0;

    return {
      annee,
      effectif_total: effectifTotal,
      effectif_actif: effectifActif,
      effectif_sorti: effectifSorti,
      embauches_annee: Number(embauches[0]?.nb ?? 0),
      sorties_annee: nbSorties,
      par_sexe: parSexe,
      par_type_contrat: parTypeContrat,
      par_departement: parDepartement,
      par_agence: parAgence,
      masse_salariale_brute: Number(masseSalariale[0]?.total_brut ?? 0),
      masse_salariale_nette: Number(masseSalariale[0]?.total_net ?? 0),
      taux_absenteisme: tauxAbsenteisme,
      taux_turnover: tauxTurnover,
    };
  }

  // ── État CNPS mensuel ──────────────────────────────────────────────────────

  async getEtatCnps(periode: string) {
    const run = await this.runRepo.findOne({
      where: { periode, statut: StatutPaieRun.CLOTURE },
    });

    const lignes = await this.ligneRepo.find({
      where: run ? { id_run: run.id } : undefined,
      relations: ['employe'],
    });

    const lignesCnps = lignes.map((l) => ({
      matricule: l.employe?.matricule ?? '',
      nom: l.employe ? `${l.employe.nom} ${l.employe.prenoms}` : '',
      numero_cnps: l.employe?.numero_cnps ?? '',
      salaire_brut: Number(l.salaire_brut),
      cnps_retraite_salarial: Number(l.cnps_retraite_salarial),
      cnps_retraite_patronal: Number(l.cnps_retraite_patronal),
      cnps_at_patronal: Number(l.cnps_at_patronal),
      cnps_famille_patronal: Number(l.cnps_famille_patronal),
      cmu_salarial: Number(l.cmu_salarial),
      cmu_patronal: Number(l.cmu_patronal),
      total_cnps: Number(l.cnps_retraite_salarial) + Number(l.cnps_retraite_patronal) +
        Number(l.cnps_at_patronal) + Number(l.cnps_famille_patronal),
    }));

    const totaux = lignesCnps.reduce(
      (acc, l) => ({
        salaire_brut: acc.salaire_brut + l.salaire_brut,
        cnps_retraite_salarial: acc.cnps_retraite_salarial + l.cnps_retraite_salarial,
        cnps_retraite_patronal: acc.cnps_retraite_patronal + l.cnps_retraite_patronal,
        cnps_at_patronal: acc.cnps_at_patronal + l.cnps_at_patronal,
        cnps_famille_patronal: acc.cnps_famille_patronal + l.cnps_famille_patronal,
        cmu_salarial: acc.cmu_salarial + l.cmu_salarial,
        cmu_patronal: acc.cmu_patronal + l.cmu_patronal,
        total_cnps: acc.total_cnps + l.total_cnps,
      }),
      {
        salaire_brut: 0, cnps_retraite_salarial: 0, cnps_retraite_patronal: 0,
        cnps_at_patronal: 0, cnps_famille_patronal: 0, cmu_salarial: 0,
        cmu_patronal: 0, total_cnps: 0,
      },
    );

    return { periode, run_statut: run?.statut ?? null, lignes: lignesCnps, totaux };
  }

  // ── État ITS mensuel (DGI) ─────────────────────────────────────────────────

  async getEtatIts(periode: string) {
    const run = await this.runRepo.findOne({ where: { periode } });
    const lignes = await this.ligneRepo.find({
      where: run ? { id_run: run.id } : undefined,
      relations: ['employe'],
    });

    const lignesIts = lignes.map((l) => ({
      matricule: l.employe?.matricule ?? '',
      nom: l.employe ? `${l.employe.nom} ${l.employe.prenoms}` : '',
      salaire_brut: Number(l.salaire_brut),
      its: Number(l.its),
      cn: Number(l.cn),
      total_its_cn: Number(l.its) + Number(l.cn),
    }));

    const total_its = lignesIts.reduce((s, l) => s + l.its, 0);
    const total_cn = lignesIts.reduce((s, l) => s + l.cn, 0);

    return { periode, lignes: lignesIts, total_its, total_cn, total_its_cn: total_its + total_cn };
  }

  // ── Déclaration main-d'œuvre ────────────────────────────────────────────────

  async getDeclarationMainOeuvre(annee: number) {
    const employes: Array<{
      matricule: string; nom: string; prenoms: string;
      sexe: string | null; nationalite: string | null;
      date_embauche: string; date_sortie: string | null;
      type_contrat_actuel: string; intitule_poste: string | null;
      categorie: string | null; departement: string | null;
      numero_cnps: string | null; agence_nom: string | null;
    }> = await this.dataSource.query(
      `SELECT e.matricule, e.nom, e.prenoms, e.sexe::text, e.nationalite,
              e.date_embauche, e.date_sortie, e.type_contrat_actuel::text,
              e.intitule_poste, e.categorie, e.departement, e.numero_cnps,
              a.nom as agence_nom
       FROM rh_employes e
       LEFT JOIN agences a ON a.id = e.id_agence
       WHERE EXTRACT(YEAR FROM e.date_embauche::date) <= $1
         AND (e.date_sortie IS NULL OR EXTRACT(YEAR FROM e.date_sortie::date) >= $1)
       ORDER BY e.nom, e.prenoms`,
      [annee],
    );

    // Alerte si après le 31 janvier (Décret 2024-902 Art.6)
    const today = new Date();
    const limiteDepot = new Date(`${annee}-01-31`);
    const enRetard = today > limiteDepot;

    return {
      annee,
      nb_employes: employes.length,
      employes,
      alerte_delai: enRetard
        ? `ATTENTION : La déclaration de main-d'œuvre ${annee} devait être déposée avant le 31/01/${annee} (Décret 2024-902 Art.6)`
        : null,
    };
  }

  // ── Évolution masse salariale (12 derniers mois) ───────────────────────────

  async getMasseSalariale12Mois() {
    const rows: Array<{ periode: string; total_brut: string; total_net: string; nb_employes: string }> =
      await this.dataSource.query(
        `SELECT periode, total_brut, total_net, nb_employes
         FROM rh_paie_runs
         WHERE statut IN ('valide_rh','valide_daf','cloture')
         ORDER BY periode DESC LIMIT 12`,
      );
    return rows
      .map((r) => ({
        periode: r.periode,
        total_brut: Number(r.total_brut),
        total_net: Number(r.total_net),
        nb_employes: Number(r.nb_employes),
      }))
      .reverse();
  }

  // ── Rapport heures supplémentaires ────────────────────────────────────────

  async getRapportHeursSup(periode: string) {
    const run = await this.runRepo.findOne({ where: { periode } });
    if (!run) return { periode, lignes: [], total_montant: 0 };

    const lignes = await this.ligneRepo.find({
      where: { id_run: run.id },
      relations: ['employe'],
    });

    const avecHs = lignes
      .filter((l) => l.heures_sup_montant > 0)
      .map((l) => ({
        matricule: l.employe?.matricule ?? '',
        nom: l.employe ? `${l.employe.nom} ${l.employe.prenoms}` : '',
        montant_hs: Number(l.heures_sup_montant),
      }));

    return {
      periode,
      lignes: avecHs,
      total_montant: avecHs.reduce((s, l) => s + l.montant_hs, 0),
    };
  }

  // ── Registre employeur 3 fascicules (Décret 2024-902 Art.9) ──────────────

  /** Fascicule A : Registre du personnel (liste nominative) */
  async getFasciculeA(annee: number) {
    const employes = await this.dataSource.query(
      `SELECT e.matricule, e.nom, e.prenoms, e.sexe::text, e.date_naissance,
              e.nationalite, e.numero_cnps, e.numero_cni,
              e.date_embauche, e.date_sortie,
              e.intitule_poste, e.categorie, e.grade, e.departement, e.service,
              e.type_contrat_actuel::text, e.statut::text,
              a.nom as agence_nom
       FROM rh_employes e
       LEFT JOIN agences a ON a.id = e.id_agence
       WHERE EXTRACT(YEAR FROM e.date_embauche::date) <= $1
         AND (e.date_sortie IS NULL OR EXTRACT(YEAR FROM e.date_sortie::date) >= $1)
       ORDER BY e.nom, e.prenoms`,
      [annee],
    );
    return { annee, fascicule: 'A', titre: 'Registre du personnel', employes };
  }

  /** Fascicule B : Registre des congés payés */
  async getFasciculeB(annee: number) {
    const conges = await this.dataSource.query(
      `SELECT e.matricule, e.nom, e.prenoms,
              c.type_code, c.date_debut, c.date_fin,
              c.nb_jours_ouvrables, c.statut::text, c.motif
       FROM rh_conge_requests c
       JOIN rh_employes e ON e.id = c.id_employe
       WHERE EXTRACT(YEAR FROM c.date_debut::date) = $1
         OR  EXTRACT(YEAR FROM c.date_fin::date)   = $1
       ORDER BY e.nom, c.date_debut`,
      [annee],
    );
    return { annee, fascicule: 'B', titre: 'Registre des congés payés', conges };
  }

  /** Fascicule C : Registre des accidents du travail (absences maladie/AT) */
  async getFasciculeC(annee: number) {
    const absences = await this.dataSource.query(
      `SELECT e.matricule, e.nom, e.prenoms,
              p.date, p.statut::text, p.commentaire
       FROM rh_presences p
       JOIN rh_employes e ON e.id = p.id_employe
       WHERE p.statut IN ('absence_injustifiee','absence_maladie')
         AND EXTRACT(YEAR FROM p.date::date) = $1
       ORDER BY e.nom, p.date`,
      [annee],
    );
    return { annee, fascicule: 'C', titre: 'Registre des accidents du travail et maladies', absences };
  }

  // ── Import CSV/Excel employés ──────────────────────────────────────────────

  parseImportRow(row: Record<string, string>): Partial<RhEmploye> {
    return {
      nom: (row['NOM'] ?? row['nom'] ?? '').trim().toUpperCase(),
      prenoms: (row['PRENOMS'] ?? row['prenoms'] ?? '').trim(),
      date_embauche: row['DATE_EMBAUCHE'] ?? row['date_embauche'] ?? new Date().toISOString().slice(0, 10),
      intitule_poste: row['POSTE'] ?? row['intitule_poste'] ?? null,
      categorie: row['CATEGORIE'] ?? row['categorie'] ?? null,
      departement: row['DEPARTEMENT'] ?? row['departement'] ?? null,
      telephone: row['TELEPHONE'] ?? row['telephone'] ?? null,
      email_pro: row['EMAIL'] ?? row['email_pro'] ?? null,
      numero_cnps: row['CNPS'] ?? row['numero_cnps'] ?? null,
      numero_cni: row['CNI'] ?? row['numero_cni'] ?? null,
    };
  }
}
