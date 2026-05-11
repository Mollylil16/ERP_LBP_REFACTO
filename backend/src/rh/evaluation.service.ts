import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { RhEvaluation, StatutEvaluation, TypeEvaluation } from './entities/rh-evaluation.entity';
import { RhEmploye } from './entities/rh-employe.entity';
import { RhProductionBridgeService } from './rh-production-bridge.service';

@Injectable()
export class EvaluationService {
  constructor(
    @InjectRepository(RhEvaluation) private evalRepo: Repository<RhEvaluation>,
    @InjectRepository(RhEmploye) private employeRepo: Repository<RhEmploye>,
    private readonly productionBridge: RhProductionBridgeService,
  ) {}

  async getEvaluations(employeId?: number, statut?: StatutEvaluation): Promise<RhEvaluation[]> {
    const where: Record<string, unknown> = {};
    if (employeId) where['id_employe'] = employeId;
    if (statut) where['statut'] = statut;
    return this.evalRepo.find({
      where,
      order: { created_at: 'DESC' },
      relations: ['employe', 'evaluateur'],
      take: 200,
    });
  }

  async createEvaluation(data: Partial<RhEvaluation>): Promise<RhEvaluation> {
    const enriched = { ...data };

    // Auto-calcul score_resultats depuis la production si l'employé a un compte user
    if (data.id_employe && data.periode && !data.score_resultats) {
      try {
        const employe = await this.employeRepo.findOne({ where: { id: data.id_employe } });
        if (employe?.id_user) {
          // Normaliser la période vers 'YYYY-MM' (prend le premier mois de la période eval)
          const periodeMonth = this.normalisePeriodeMois(data.periode);
          if (periodeMonth) {
            const metrics = await this.productionBridge.getProductionMetrics(employe.id_user, periodeMonth);
            enriched.score_resultats = metrics.score_resultats || null;
            enriched.metriques_auto = {
              colis_count: metrics.colis_count,
              ca_total: metrics.ca_total,
              periode: periodeMonth,
              calcule_le: new Date().toISOString(),
            };
          }
        }
      } catch {
        // si la requête de production échoue, on crée l'évaluation sans auto-score
      }
    }

    return this.evalRepo.save(this.evalRepo.create(enriched));
  }

  private normalisePeriodeMois(periode: string): string | null {
    // '2025-Q1' → '2025-01', '2025-S1' → '2025-01', '2025' → '2025-01', '2025-06' → '2025-06'
    if (/^\d{4}-\d{2}$/.test(periode)) return periode;
    if (/^\d{4}-Q1$/i.test(periode)) return `${periode.slice(0, 4)}-01`;
    if (/^\d{4}-Q2$/i.test(periode)) return `${periode.slice(0, 4)}-04`;
    if (/^\d{4}-Q3$/i.test(periode)) return `${periode.slice(0, 4)}-07`;
    if (/^\d{4}-Q4$/i.test(periode)) return `${periode.slice(0, 4)}-10`;
    if (/^\d{4}-S1$/i.test(periode)) return `${periode.slice(0, 4)}-01`;
    if (/^\d{4}-S2$/i.test(periode)) return `${periode.slice(0, 4)}-07`;
    if (/^\d{4}$/.test(periode)) return `${periode}-01`;
    return null;
  }

  async updateEvaluation(id: number, data: Partial<RhEvaluation>): Promise<RhEvaluation> {
    const eval_ = await this.evalRepo.findOne({ where: { id } });
    if (!eval_) throw new NotFoundException('Évaluation introuvable');
    Object.assign(eval_, data);
    if (data.score_resultats !== undefined) {
      eval_.note_globale = this.calculerNoteGlobale(eval_);
    }
    // Rafraîchir les métriques auto si on change la période
    if (data.periode && data.periode !== eval_.periode) {
      try {
        const employe = await this.employeRepo.findOne({ where: { id: eval_.id_employe } });
        if (employe?.id_user) {
          const periodeMonth = this.normalisePeriodeMois(data.periode);
          if (periodeMonth) {
            const metrics = await this.productionBridge.getProductionMetrics(employe.id_user, periodeMonth);
            eval_.metriques_auto = {
              colis_count: metrics.colis_count,
              ca_total: metrics.ca_total,
              periode: periodeMonth,
              calcule_le: new Date().toISOString(),
            };
          }
        }
      } catch { /* pas bloquant */ }
    }
    return this.evalRepo.save(eval_);
  }

  async validerEvaluation(id: number, etape: 'evalue' | 'evaluateur' | 'rh'): Promise<RhEvaluation> {
    const eval_ = await this.evalRepo.findOne({ where: { id } });
    if (!eval_) throw new NotFoundException('Évaluation introuvable');
    const transitions: Record<string, StatutEvaluation> = {
      evalue: StatutEvaluation.SIGNE_EVALUE,
      evaluateur: StatutEvaluation.SIGNE_EVALUATEUR,
      rh: StatutEvaluation.VALIDE_RH,
    };
    eval_.statut = transitions[etape] ?? eval_.statut;
    return this.evalRepo.save(eval_);
  }

  private calculerNoteGlobale(e: RhEvaluation): number {
    const s = (n: number | null, w: number) => (n ?? 0) * w;
    const total =
      s(e.score_resultats, 0.4) +
      s(e.score_competences_metier, 0.25) +
      s(e.score_comportement, 0.2) +
      s(e.score_conformite, 0.1) +
      s(e.score_developpement, 0.05);
    return Math.round(total * 100) / 100;
  }

  async getDashboardEval(): Promise<{
    en_cours: number;
    clotures: number;
    moyenne_globale: number;
    par_type: Array<{ type: TypeEvaluation; nb: number; moyenne: number }>;
  }> {
    const all = await this.evalRepo.find();
    const en_cours = all.filter((e) =>
      [StatutEvaluation.EN_COURS, StatutEvaluation.SIGNE_EVALUE, StatutEvaluation.SIGNE_EVALUATEUR].includes(e.statut),
    ).length;
    const clotures = all.filter((e) => e.statut === StatutEvaluation.CLOTURE).length;
    const avecNote = all.filter((e) => e.note_globale !== null);
    const moyenne_globale =
      avecNote.length > 0
        ? avecNote.reduce((s, e) => s + Number(e.note_globale), 0) / avecNote.length
        : 0;

    const types = Object.values(TypeEvaluation);
    const par_type = types.map((type) => {
      const items = all.filter((e) => e.type === type && e.note_globale !== null);
      return {
        type,
        nb: all.filter((e) => e.type === type).length,
        moyenne: items.length ? items.reduce((s, e) => s + Number(e.note_globale), 0) / items.length : 0,
      };
    });

    return { en_cours, clotures, moyenne_globale: Math.round(moyenne_globale * 100) / 100, par_type };
  }
}
