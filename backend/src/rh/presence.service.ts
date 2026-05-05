import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, Between } from 'typeorm';
import { RhPresence, StatutPresence } from './entities/rh-presence.entity';
import { RhJourFerie } from './entities/rh-jour-ferie.entity';

@Injectable()
export class PresenceService {
  constructor(
    @InjectRepository(RhPresence) private presenceRepo: Repository<RhPresence>,
    @InjectRepository(RhJourFerie) private ferieRepo: Repository<RhJourFerie>,
  ) {}

  async getPresences(employeId?: number, dateDebut?: string, dateFin?: string): Promise<RhPresence[]> {
    const where: Record<string, unknown> = {};
    if (employeId) where['id_employe'] = employeId;
    if (dateDebut && dateFin) where['date_presence'] = Between(dateDebut, dateFin);
    return this.presenceRepo.find({
      where,
      order: { date_presence: 'DESC' },
      relations: ['employe', 'validateur'],
      take: 500,
    });
  }

  async saisirPresence(data: Partial<RhPresence>): Promise<RhPresence> {
    const presence = this.presenceRepo.create(data);
    return this.presenceRepo.save(presence);
  }

  async validerPresence(id: number, userId: number): Promise<RhPresence> {
    const p = await this.presenceRepo.findOne({ where: { id } });
    if (!p) throw new NotFoundException('Présence introuvable');
    p.est_valide = true;
    p.id_validateur = userId;
    return this.presenceRepo.save(p);
  }

  async getJoursFeries(annee: number): Promise<RhJourFerie[]> {
    return this.ferieRepo.find({ where: { annee }, order: { date: 'ASC' } });
  }

  async createJourFerie(data: Partial<RhJourFerie>): Promise<RhJourFerie> {
    return this.ferieRepo.save(this.ferieRepo.create(data));
  }

  async deleteJourFerie(id: number): Promise<void> {
    await this.ferieRepo.delete(id);
  }

  async getStatsMensuellesEmploye(employeId: number, periode: string): Promise<{
    jours_travailles: number;
    heures_totales: number;
    heures_sup_totales: number;
    retards: number;
    absences: number;
  }> {
    const [year, month] = periode.split('-').map(Number);
    const debut = `${year}-${String(month).padStart(2, '0')}-01`;
    const fin = `${year}-${String(month).padStart(2, '0')}-31`;

    const presences = await this.presenceRepo.find({
      where: { id_employe: employeId, date_presence: Between(debut, fin) },
    });

    return {
      jours_travailles: presences.filter((p) => p.statut === StatutPresence.PRESENT).length,
      heures_totales: presences.reduce((s, p) => s + Number(p.heures_travaillees), 0),
      heures_sup_totales: presences.reduce((s, p) => s + Number(p.heures_sup), 0),
      retards: presences.filter((p) => p.statut === StatutPresence.RETARD).length,
      absences: presences.filter((p) => p.statut === StatutPresence.ABSENT).length,
    };
  }

  // Seed des jours fériés CI légaux (liste fixe non islamiques)
  async seedJoursFeriesCI(annee: number): Promise<number> {
    const feries = [
      { date: `${annee}-01-01`, libelle: "Jour de l'An" },
      { date: `${annee}-01-03`, libelle: 'Anniversaire de la Côte d\'Ivoire' },
      { date: `${annee}-04-18`, libelle: 'Vendredi Saint' },
      { date: `${annee}-04-21`, libelle: 'Lundi de Pâques' },
      { date: `${annee}-05-01`, libelle: 'Fête du Travail' },
      { date: `${annee}-05-29`, libelle: 'Ascension' },
      { date: `${annee}-06-09`, libelle: 'Lundi de Pentecôte' },
      { date: `${annee}-08-07`, libelle: "Fête Nationale (Indépendance)" },
      { date: `${annee}-08-15`, libelle: 'Assomption' },
      { date: `${annee}-11-01`, libelle: 'Toussaint' },
      { date: `${annee}-11-15`, libelle: 'Paix Nationale' },
      { date: `${annee}-12-25`, libelle: 'Noël' },
    ];

    let seeded = 0;
    for (const f of feries) {
      const exists = await this.ferieRepo.findOne({ where: { date: f.date } });
      if (!exists) {
        await this.ferieRepo.save(this.ferieRepo.create({ ...f, annee, est_islamique: false }));
        seeded++;
      }
    }
    return seeded;
  }
}
