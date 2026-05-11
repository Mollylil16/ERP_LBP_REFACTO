import { BadRequestException, Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, DataSource } from 'typeorm';
import { RhConfigPaie } from './entities/rh-config-paie.entity';
import { RhPaieRun, StatutPaieRun } from './entities/rh-paie-run.entity';
import { RhPaieLigne } from './entities/rh-paie-ligne.entity';
import { RhAvanceSalaire, StatutAvance } from './entities/rh-avance-salaire.entity';
import { RhEmploye, StatutEmploye } from './entities/rh-employe.entity';
import { RhContrat, StatutContrat } from './entities/rh-contrat.entity';
import { RhProductionBridgeService } from './rh-production-bridge.service';

@Injectable()
export class PaieService {
  constructor(
    @InjectRepository(RhConfigPaie) private configRepo: Repository<RhConfigPaie>,
    @InjectRepository(RhPaieRun) private runRepo: Repository<RhPaieRun>,
    @InjectRepository(RhPaieLigne) private ligneRepo: Repository<RhPaieLigne>,
    @InjectRepository(RhAvanceSalaire) private avanceRepo: Repository<RhAvanceSalaire>,
    @InjectRepository(RhEmploye) private employeRepo: Repository<RhEmploye>,
    @InjectRepository(RhContrat) private contratRepo: Repository<RhContrat>,
    private readonly dataSource: DataSource,
    private readonly productionBridge: RhProductionBridgeService,
  ) {}

  // ─── Configuration paie ───────────────────────────────────────────────────

  async getConfig(periode?: string): Promise<RhConfigPaie> {
    const key = periode ?? 'DEFAULT';
    let config = await this.configRepo.findOne({ where: { annee_mois: key } });
    if (!config) {
      config = await this.configRepo.findOne({ where: { annee_mois: 'DEFAULT' } });
    }
    if (!config) {
      // Créer config par défaut
      config = this.configRepo.create({
        annee_mois: 'DEFAULT',
        its_tranches: [
          { min: 0, max: 75000, taux: 0 },
          { min: 75001, max: 240000, taux: 0.16 },
          { min: 240001, max: 800000, taux: 0.21 },
          { min: 800001, max: 2400000, taux: 0.24 },
          { min: 2400001, max: null, taux: 0.28 },
        ],
      });
      await this.configRepo.save(config);
    }
    return config;
  }

  async upsertConfig(data: Partial<RhConfigPaie>): Promise<RhConfigPaie> {
    const key = data.annee_mois ?? 'DEFAULT';
    const existing = await this.configRepo.findOne({ where: { annee_mois: key } });
    if (existing) {
      Object.assign(existing, data);
      return this.configRepo.save(existing);
    }
    return this.configRepo.save(this.configRepo.create({ ...data, annee_mois: key }));
  }

  // ─── Calcul ITS (barème progressif) ─────────────────────────────────────

  private calculITS(
    brut: number,
    tranches: Array<{ min: number; max: number | null; taux: number }>,
  ): number {
    let its = 0;
    for (const t of tranches) {
      const max = t.max ?? Infinity;
      if (brut <= t.min) break;
      const tranche = Math.min(brut, max) - t.min;
      its += tranche * t.taux;
    }
    return Math.round(its);
  }

  // ─── Calcul ancienneté ────────────────────────────────────────────────────

  private anciennete(dateEmbauche: string, ref = new Date()): number {
    const d = new Date(dateEmbauche);
    let years = ref.getFullYear() - d.getFullYear();
    const m = ref.getMonth() - d.getMonth();
    if (m < 0 || (m === 0 && ref.getDate() < d.getDate())) years--;
    return Math.max(0, years);
  }

  // ─── Prime ancienneté (barème légal CI) ──────────────────────────────────

  private primeAnciennete(salaireBase: number, dateEmbauche: string): number {
    const ans = this.anciennete(dateEmbauche);
    if (ans < 2) return 0;
    // 2% par année au-delà de 2 ans, plafonné à 25% (15+ ans)
    const taux = Math.min(0.25, (ans - 2) * 0.02);
    return Math.round(salaireBase * taux);
  }

  // ─── Calcul d'une ligne de paie ───────────────────────────────────────────

  async calculerLigne(
    employe: RhEmploye,
    contrat: RhContrat | null,
    config: RhConfigPaie,
    avanceMontant: number,
    absencesMontant: number,
    autresPrimes = 0,
    heureSup = 0,
    primeTransport = 0,
  ): Promise<Partial<RhPaieLigne>> {
    const salaireBase = Number(contrat?.salaire_base ?? 0);
    const primeAnc = contrat ? this.primeAnciennete(salaireBase, employe.date_embauche) : 0;
    const hsMontant = Math.round(heureSup);

    const salaireBrut = salaireBase + primeAnc + primeTransport + hsMontant + autresPrimes;

    // Plafonds mensuels
    const plafondCnpsRetraiteMensuel = Number(config.cnps_retraite_plafond_annuel) / 12;
    const baseRetraite = Math.min(salaireBrut, plafondCnpsRetraiteMensuel);
    const plafondFamille = Number(config.cnps_famille_plafond_mensuel);
    const baseFamille = Math.min(salaireBrut, plafondFamille);

    // Déductions salariales
    const cnpsRetraiteSal = Math.round(baseRetraite * Number(config.cnps_retraite_salarial));
    const cmuSal = Math.round(salaireBrut * Number(config.cmu_salarial));
    const cn = Math.round(salaireBrut * Number(config.cn_taux));

    const tranches = config.its_tranches ?? [];
    const its = tranches.length ? this.calculITS(salaireBrut, tranches) : 0;

    const totalDeductions =
      cnpsRetraiteSal + cmuSal + its + cn + avanceMontant + absencesMontant;
    const salaireNet = Math.max(0, salaireBrut - totalDeductions);

    // Charges patronales
    const cnpsRetraitePat = Math.round(baseRetraite * Number(config.cnps_retraite_patronal));
    const cnpsAtPat = Math.round(salaireBrut * Number(config.cnps_at_patronal));
    const cnpsFamillePat = Math.round(baseFamille * Number(config.cnps_famille_patronal));
    const cmuPat = Math.round(salaireBrut * Number(config.cmu_patronal));
    const totalChargesPat = cnpsRetraitePat + cnpsAtPat + cnpsFamillePat + cmuPat;
    const coutTotal = salaireBrut + totalChargesPat;

    const alerteSmig = salaireBrut < Number(config.smig_mensuel);

    return {
      id_employe: employe.id,
      salaire_base: salaireBase,
      prime_anciennete: primeAnc,
      prime_transport: primeTransport,
      heures_sup_montant: hsMontant,
      autres_primes: autresPrimes,
      salaire_brut: salaireBrut,
      cnps_retraite_salarial: cnpsRetraiteSal,
      cmu_salarial: cmuSal,
      its,
      cn,
      avances_deduites: avanceMontant,
      absences_deduites: absencesMontant,
      total_deductions_salariales: totalDeductions,
      salaire_net: salaireNet,
      cnps_retraite_patronal: cnpsRetraitePat,
      cnps_at_patronal: cnpsAtPat,
      cnps_famille_patronal: cnpsFamillePat,
      cmu_patronal: cmuPat,
      total_charges_patronales: totalChargesPat,
      cout_total_employeur: coutTotal,
      alerte_smig: alerteSmig,
      detail_calcul: {
        base_retraite: baseRetraite,
        base_famille: baseFamille,
        taux_cnps_ret_sal: config.cnps_retraite_salarial,
        taux_cnps_ret_pat: config.cnps_retraite_patronal,
        taux_cmu_sal: config.cmu_salarial,
        taux_cn: config.cn_taux,
        smig: config.smig_mensuel,
        anciennete_ans: this.anciennete(employe.date_embauche),
      },
    };
  }

  // ─── Créer / lancer un run de paie ───────────────────────────────────────

  async createRun(periode: string): Promise<RhPaieRun> {
    const existing = await this.runRepo.findOne({ where: { periode } });
    if (existing) throw new BadRequestException(`Un run existe déjà pour la période ${periode}`);
    const run = this.runRepo.create({ periode });
    return this.runRepo.save(run);
  }

  async calculerRun(runId: number): Promise<RhPaieRun> {
    const run = await this.runRepo.findOne({ where: { id: runId }, relations: ['lignes'] });
    if (!run) throw new NotFoundException('Run de paie introuvable');
    if (run.statut !== StatutPaieRun.BROUILLON) {
      throw new BadRequestException('Seul un run en brouillon peut être calculé');
    }

    const config = await this.getConfig(run.periode);

    // Récupérer tous les employés actifs
    const employes = await this.employeRepo.find({ where: { statut: StatutEmploye.ACTIF } });

    // Supprimer lignes existantes
    await this.ligneRepo.delete({ id_run: runId });

    let totalBrut = 0, totalNet = 0, totalChargesSal = 0, totalChargesPat = 0;

    for (const emp of employes) {
      // Contrat actif
      const contrat = await this.contratRepo.findOne({
        where: { id_employe: emp.id, statut: StatutContrat.ACTIF },
        order: { date_debut: 'DESC' },
      });

      // Avances approuvées pour ce mois
      const avances = await this.avanceRepo.find({
        where: { id_employe: emp.id, mois_deduction: run.periode, statut: StatutAvance.APPROUVE },
      });
      const totalAvances = avances.reduce((s, a) => s + Number(a.montant), 0);

      const ligneData = await this.calculerLigne(emp, contrat, config, totalAvances, 0);

      // Prime de performance via production (colis/CA) si l'employé est lié à un compte utilisateur
      let primePerf = 0;
      if (emp.id_user) {
        try {
          const metrics = await this.productionBridge.getProductionMetrics(emp.id_user, run.periode);
          primePerf = metrics.prime_performance;
          if (primePerf > 0 && ligneData.detail_calcul) {
            ligneData.detail_calcul = {
              ...ligneData.detail_calcul,
              production_colis: metrics.colis_count,
              production_ca: metrics.ca_total,
              prime_perf_source: 'production_bridge',
            };
          }
        } catch {
          // si la requête de production échoue, on continue sans prime
        }
      }

      const ligne = this.ligneRepo.create({ ...ligneData, id_run: runId, prime_performance: primePerf });
      await this.ligneRepo.save(ligne);

      totalBrut += Number(ligneData.salaire_brut ?? 0) + primePerf;
      totalNet += Number(ligneData.salaire_net ?? 0) + primePerf;
      totalChargesSal += Number(ligneData.total_deductions_salariales ?? 0);
      totalChargesPat += Number(ligneData.total_charges_patronales ?? 0);
    }

    run.statut = StatutPaieRun.CALCULE;
    run.total_brut = totalBrut;
    run.total_net = totalNet;
    run.total_charges_salariales = totalChargesSal;
    run.total_charges_patronales = totalChargesPat;
    run.nb_employes = employes.length;
    return this.runRepo.save(run);
  }

  async validerRun(runId: number, userId: number, role: 'rh' | 'daf'): Promise<RhPaieRun> {
    const run = await this.runRepo.findOne({ where: { id: runId } });
    if (!run) throw new NotFoundException('Run introuvable');

    const now = new Date();
    if (role === 'rh') {
      if (run.statut !== StatutPaieRun.CALCULE) {
        throw new BadRequestException('Le run doit être calculé avant validation RH');
      }
      run.statut = StatutPaieRun.VALIDE_RH;
      run.id_valideur_rh = userId;
      run.date_validation_rh = now;
    } else {
      if (run.statut !== StatutPaieRun.VALIDE_RH) {
        throw new BadRequestException('Le run doit être validé par RH avant la DAF');
      }
      run.statut = StatutPaieRun.VALIDE_DAF;
      run.id_valideur_daf = userId;
      run.date_validation_daf = now;
    }
    return this.runRepo.save(run);
  }

  async getRuns(): Promise<RhPaieRun[]> {
    return this.runRepo.find({ order: { periode: 'DESC' } });
  }

  async getRunDetail(runId: number): Promise<{ run: RhPaieRun; lignes: RhPaieLigne[] }> {
    const run = await this.runRepo.findOne({ where: { id: runId } });
    if (!run) throw new NotFoundException('Run introuvable');
    const lignes = await this.ligneRepo.find({
      where: { id_run: runId },
      relations: ['employe'],
      order: { id: 'ASC' },
    });
    return { run, lignes };
  }

  async getLignesEmploye(employeId: number): Promise<RhPaieLigne[]> {
    return this.ligneRepo.find({
      where: { id_employe: employeId },
      relations: ['run'],
      order: { created_at: 'DESC' },
    });
  }

  // ─── Avances sur salaire ──────────────────────────────────────────────────

  async getAvances(employeId?: number): Promise<RhAvanceSalaire[]> {
    const where = employeId ? { id_employe: employeId } : {};
    return this.avanceRepo.find({ where, order: { created_at: 'DESC' }, relations: ['employe'] });
  }

  async createAvance(data: {
    id_employe: number;
    montant: number;
    mois_deduction: string;
    motif?: string;
  }): Promise<RhAvanceSalaire> {
    const employe = await this.employeRepo.findOne({ where: { id: data.id_employe } });
    if (!employe) throw new NotFoundException('Employé introuvable');
    return this.avanceRepo.save(this.avanceRepo.create(data));
  }

  async approuverAvance(id: number, userId: number, approuve: boolean): Promise<RhAvanceSalaire> {
    const avance = await this.avanceRepo.findOne({ where: { id } });
    if (!avance) throw new NotFoundException('Avance introuvable');
    avance.statut = approuve ? StatutAvance.APPROUVE : StatutAvance.REFUSE;
    avance.id_approbateur = userId;
    return this.avanceRepo.save(avance);
  }

  // ─── Stats masse salariale ────────────────────────────────────────────────

  async getMasseSalariale(): Promise<Array<{
    periode: string;
    total_brut: number;
    total_net: number;
    nb_employes: number;
  }>> {
    const runs = await this.runRepo.find({
      where: { statut: StatutPaieRun.CLOTURE },
      order: { periode: 'DESC' },
      take: 12,
    });
    return runs
      .map((r) => ({
        periode: r.periode,
        total_brut: Number(r.total_brut),
        total_net: Number(r.total_net),
        nb_employes: Number(r.nb_employes),
      }))
      .reverse();
  }
}
