import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { ILike, Repository } from 'typeorm';
import { RhPoste, StatutPoste } from './entities/rh-recrutement.entity';
import { RhCandidature, StatutCandidature } from './entities/rh-recrutement.entity';

@Injectable()
export class RecrutementService {
  constructor(
    @InjectRepository(RhPoste) private posteRepo: Repository<RhPoste>,
    @InjectRepository(RhCandidature) private candidatureRepo: Repository<RhCandidature>,
  ) {}

  // ── Postes ────────────────────────────────────────────────────────────────

  async getPostes(statut?: StatutPoste): Promise<RhPoste[]> {
    const where = statut ? { statut } : {};
    return this.posteRepo.find({ where, order: { created_at: 'DESC' }, relations: ['agence'] });
  }

  async createPoste(data: Partial<RhPoste>): Promise<RhPoste> {
    return this.posteRepo.save(this.posteRepo.create(data));
  }

  async updatePoste(id: number, data: Partial<RhPoste>): Promise<RhPoste> {
    const p = await this.posteRepo.findOne({ where: { id } });
    if (!p) throw new NotFoundException('Poste introuvable');
    Object.assign(p, data);
    return this.posteRepo.save(p);
  }

  // ── Candidatures ──────────────────────────────────────────────────────────

  async getCandidatures(posteId?: number, statut?: StatutCandidature): Promise<RhCandidature[]> {
    const where: Record<string, unknown> = {};
    if (posteId) where['id_poste'] = posteId;
    if (statut) where['statut'] = statut;
    return this.candidatureRepo.find({
      where,
      order: { created_at: 'DESC' },
      relations: ['poste', 'recruteur'],
    });
  }

  async createCandidature(data: Partial<RhCandidature>): Promise<RhCandidature> {
    return this.candidatureRepo.save(this.candidatureRepo.create(data));
  }

  async updateStatutCandidature(
    id: number,
    statut: StatutCandidature,
    notes?: string,
    note_entretien?: number,
    date_entretien?: string,
  ): Promise<RhCandidature> {
    const c = await this.candidatureRepo.findOne({ where: { id } });
    if (!c) throw new NotFoundException('Candidature introuvable');
    c.statut = statut;
    if (notes !== undefined) c.notes_recruteur = notes;
    if (note_entretien !== undefined) c.note_entretien = note_entretien;
    if (date_entretien !== undefined) c.date_entretien = date_entretien;
    return this.candidatureRepo.save(c);
  }

  async getDashboardRecrutement(): Promise<{
    postes_ouverts: number;
    candidatures_total: number;
    par_statut: Array<{ statut: StatutCandidature; nb: number }>;
    par_poste: Array<{ poste: string; nb: number }>;
  }> {
    const postes = await this.posteRepo.find({ where: { statut: StatutPoste.OUVERT } });
    const candidatures = await this.candidatureRepo.find({ relations: ['poste'] });
    const par_statut = Object.values(StatutCandidature).map((s) => ({
      statut: s,
      nb: candidatures.filter((c) => c.statut === s).length,
    }));
    const parPoste = new Map<string, number>();
    candidatures.forEach((c) => {
      const k = c.poste?.intitule ?? String(c.id_poste);
      parPoste.set(k, (parPoste.get(k) ?? 0) + 1);
    });
    return {
      postes_ouverts: postes.length,
      candidatures_total: candidatures.length,
      par_statut,
      par_poste: Array.from(parPoste.entries()).map(([poste, nb]) => ({ poste, nb })),
    };
  }
}
