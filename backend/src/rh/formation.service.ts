import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { RhFormation, StatutInscription } from './entities/rh-formation.entity';
import { RhInscriptionFormation } from './entities/rh-formation.entity';

@Injectable()
export class FormationService {
  constructor(
    @InjectRepository(RhFormation) private formationRepo: Repository<RhFormation>,
    @InjectRepository(RhInscriptionFormation) private inscriptionRepo: Repository<RhInscriptionFormation>,
  ) {}

  async getFormations(annee?: number): Promise<RhFormation[]> {
    const where = annee ? { annee_plan: annee, est_actif: true } : { est_actif: true };
    return this.formationRepo.find({ where, order: { date_debut: 'ASC' } });
  }

  async createFormation(data: Partial<RhFormation>): Promise<RhFormation> {
    return this.formationRepo.save(this.formationRepo.create(data));
  }

  async updateFormation(id: number, data: Partial<RhFormation>): Promise<RhFormation> {
    const f = await this.formationRepo.findOne({ where: { id } });
    if (!f) throw new NotFoundException('Formation introuvable');
    Object.assign(f, data);
    return this.formationRepo.save(f);
  }

  async getInscriptions(formationId?: number, employeId?: number): Promise<RhInscriptionFormation[]> {
    const where: Record<string, unknown> = {};
    if (formationId) where['id_formation'] = formationId;
    if (employeId) where['id_employe'] = employeId;
    return this.inscriptionRepo.find({
      where,
      relations: ['formation', 'employe', 'validateur_manager'],
      order: { created_at: 'DESC' },
    });
  }

  async inscrire(data: Partial<RhInscriptionFormation>): Promise<RhInscriptionFormation> {
    const formation = await this.formationRepo.findOne({ where: { id: data.id_formation } });
    if (!formation) throw new NotFoundException('Formation introuvable');
    return this.inscriptionRepo.save(this.inscriptionRepo.create(data));
  }

  async updateInscription(id: number, data: Partial<RhInscriptionFormation>): Promise<RhInscriptionFormation> {
    const ins = await this.inscriptionRepo.findOne({ where: { id } });
    if (!ins) throw new NotFoundException('Inscription introuvable');
    Object.assign(ins, data);
    return this.inscriptionRepo.save(ins);
  }

  async getDashboardFormation(): Promise<{
    formations_planifiees: number;
    inscriptions_total: number;
    taux_realisation: number;
    cout_total: number;
  }> {
    const formations = await this.formationRepo.find({ where: { est_actif: true } });
    const inscriptions = await this.inscriptionRepo.find();
    const terminees = inscriptions.filter((i) => i.statut === StatutInscription.TERMINE).length;
    const taux = inscriptions.length > 0 ? (terminees / inscriptions.length) * 100 : 0;
    const cout = formations.reduce((s, f) => s + Number(f.cout ?? 0), 0);
    return {
      formations_planifiees: formations.length,
      inscriptions_total: inscriptions.length,
      taux_realisation: Math.round(taux),
      cout_total: cout,
    };
  }
}
