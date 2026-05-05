import { BadRequestException, Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { DataSource, ILike, Repository } from 'typeorm';
import { RhEmploye, StatutEmploye, TypeContrat } from './entities/rh-employe.entity';
import { RhContrat, StatutContrat } from './entities/rh-contrat.entity';
import { RhCongeType } from './entities/rh-conge-type.entity';
import { RhCongeRequest, StatutConge } from './entities/rh-conge-request.entity';
import { RhCongeBalance } from './entities/rh-conge-balance.entity';
import { RhHistoriquePoste } from './entities/rh-historique-poste.entity';
import { CreateEmployeDto } from './dto/create-employe.dto';
import { CreateContratDto } from './dto/create-contrat.dto';
import { CreateCongeRequestDto } from './dto/conge.dto';

@Injectable()
export class RhService {
  constructor(
    @InjectRepository(RhEmploye) private employeRepo: Repository<RhEmploye>,
    @InjectRepository(RhContrat) private contratRepo: Repository<RhContrat>,
    @InjectRepository(RhCongeType) private congeTypeRepo: Repository<RhCongeType>,
    @InjectRepository(RhCongeRequest) private congeRequestRepo: Repository<RhCongeRequest>,
    @InjectRepository(RhCongeBalance) private congeBalanceRepo: Repository<RhCongeBalance>,
    @InjectRepository(RhHistoriquePoste) private historiquePosteRepo: Repository<RhHistoriquePoste>,
    private readonly dataSource: DataSource,
  ) {}

  // ─── Matricule ────────────────────────────────────────────────────────────────

  private async generateMatricule(): Promise<string> {
    const last = await this.employeRepo
      .createQueryBuilder('e')
      .orderBy('e.id', 'DESC')
      .getOne();
    const seq = (last?.id ?? 0) + 1;
    return `LBP-RH-${String(seq).padStart(4, '0')}`;
  }

  // ─── Ancienneté (années complètes) ───────────────────────────────────────────

  private anciennete(dateEmbauche: string, ref = new Date()): number {
    const d = new Date(dateEmbauche);
    let years = ref.getFullYear() - d.getFullYear();
    const m = ref.getMonth() - d.getMonth();
    if (m < 0 || (m === 0 && ref.getDate() < d.getDate())) years--;
    return Math.max(0, years);
  }

  // ─── Droits à congés légaux (Art. 25 CDT) ────────────────────────────────────

  droitsCongesLegaux(dateEmbauche: string): number {
    const ans = this.anciennete(dateEmbauche);
    if (ans >= 15) return 66;
    if (ans >= 10) return 54;
    if (ans >= 5) return 42;
    return 30;
  }

  // ─── EMPLOYÉS ────────────────────────────────────────────────────────────────

  async findAllEmployes(search?: string, statut?: StatutEmploye) {
    const where: any = {};
    if (statut) where.statut = statut;
    if (search) {
      return this.employeRepo.find({
        where: [
          { nom: ILike(`%${search}%`), ...where },
          { prenoms: ILike(`%${search}%`), ...where },
          { matricule: ILike(`%${search}%`), ...where },
        ],
        relations: ['agence', 'responsable'],
        order: { nom: 'ASC' },
        take: 200,
      });
    }
    return this.employeRepo.find({
      where,
      relations: ['agence', 'responsable'],
      order: { nom: 'ASC' },
      take: 200,
    });
  }

  async findOneEmploye(id: number) {
    const e = await this.employeRepo.findOne({
      where: { id },
      relations: ['agence', 'responsable', 'contrats', 'conge_requests', 'conge_requests.type_conge'],
    });
    if (!e) throw new NotFoundException(`Employé #${id} introuvable`);
    return { ...e, anciennete: this.anciennete(e.date_embauche) };
  }

  async createEmploye(dto: CreateEmployeDto) {
    const matricule = await this.generateMatricule();
    const e = this.employeRepo.create({
      ...dto,
      matricule,
      nb_enfants: dto.nb_enfants ?? 0,
      type_contrat_actuel: dto.type_contrat_actuel ?? TypeContrat.CDI,
      statut: dto.statut ?? StatutEmploye.ACTIF,
    });
    return this.employeRepo.save(e);
  }

  async updateEmploye(id: number, dto: Partial<CreateEmployeDto>) {
    const e = await this.employeRepo.findOne({ where: { id } });
    if (!e) throw new NotFoundException(`Employé #${id} introuvable`);
    Object.assign(e, dto);
    return this.employeRepo.save(e);
  }

  async sortirEmploye(id: number, dateSortie: string, motif?: string) {
    const e = await this.employeRepo.findOne({ where: { id } });
    if (!e) throw new NotFoundException(`Employé #${id} introuvable`);
    e.statut = StatutEmploye.SORTI;
    e.date_sortie = dateSortie;
    await this.employeRepo.save(e);
    // Résilier le contrat actif
    const contratActif = await this.contratRepo.findOne({
      where: { id_employe: id, statut: StatutContrat.ACTIF },
    });
    if (contratActif) {
      contratActif.statut = StatutContrat.TERMINE;
      contratActif.date_fin = dateSortie;
      contratActif.motif_fin = motif ?? 'Sortie employé';
      await this.contratRepo.save(contratActif);
    }
    return e;
  }

  // ─── CONTRATS ────────────────────────────────────────────────────────────────

  async findContratsEmploye(id_employe: number) {
    return this.contratRepo.find({
      where: { id_employe },
      order: { date_debut: 'DESC' },
    });
  }

  async findAllContrats(statut?: StatutContrat, type?: TypeContrat) {
    const where: any = {};
    if (statut) where.statut = statut;
    if (type) where.type_contrat = type;
    return this.contratRepo.find({
      where,
      relations: ['employe', 'employe.agence'],
      order: { date_debut: 'DESC' },
      take: 300,
    });
  }

  async createContrat(dto: CreateContratDto) {
    // Alerte durée max CDD : 2 ans (Art. 14-20 CDT)
    if (dto.type_contrat === TypeContrat.CDD && dto.date_fin) {
      const diff = new Date(dto.date_fin).getTime() - new Date(dto.date_debut).getTime();
      const jours = diff / (1000 * 60 * 60 * 24);
      if (jours > 730) {
        throw new BadRequestException(
          'La durée maximale d\'un CDD est de 2 ans (Art. 14-20 CDT ivoirien). Veuillez réduire la durée ou convertir en CDI.',
        );
      }
    }
    const c = this.contratRepo.create(dto);
    return this.contratRepo.save(c);
  }

  async getCddExpirants(jours = 30) {
    const limite = new Date();
    limite.setDate(limite.getDate() + jours);
    return this.dataSource.query(
      `SELECT c.*, e.nom, e.prenoms, e.matricule, a.nom as agence_nom
       FROM rh_contrats c
       JOIN rh_employes e ON e.id = c.id_employe
       LEFT JOIN agences a ON a.id = e.id_agence
       WHERE c.type_contrat = 'CDD'
         AND c.statut = 'actif'
         AND c.date_fin IS NOT NULL
         AND c.date_fin::date BETWEEN CURRENT_DATE AND $1::date
       ORDER BY c.date_fin ASC`,
      [limite.toISOString().slice(0, 10)],
    );
  }

  // ─── TYPES DE CONGÉ ───────────────────────────────────────────────────────────

  async findCongeTypes() {
    return this.congeTypeRepo.find({ where: { est_actif: true }, order: { libelle: 'ASC' } });
  }

  // ─── DEMANDES DE CONGÉ ────────────────────────────────────────────────────────

  async findAllCongeRequests(statut?: StatutConge) {
    return this.congeRequestRepo.find({
      where: statut ? { statut } : {},
      relations: ['employe', 'employe.agence', 'type_conge', 'valideur_rh', 'valideur_manager'],
      order: { created_at: 'DESC' },
      take: 300,
    });
  }

  async findCongeRequestsEmploye(id_employe: number) {
    return this.congeRequestRepo.find({
      where: { id_employe },
      relations: ['type_conge'],
      order: { created_at: 'DESC' },
    });
  }

  async createCongeRequest(dto: CreateCongeRequestDto) {
    const employe = await this.employeRepo.findOne({ where: { id: dto.id_employe } });
    if (!employe) throw new NotFoundException(`Employé #${dto.id_employe} introuvable`);

    // Vérifier le solde (Art. 25 CDT : solde ne peut être négatif sans autorisation RH)
    const annee = new Date().getFullYear();
    const balance = await this.congeBalanceRepo.findOne({
      where: { id_employe: dto.id_employe, id_conge_type: dto.id_conge_type, annee },
    });
    if (balance && balance.jours_restants < dto.nb_jours) {
      throw new BadRequestException(
        `Solde insuffisant : ${balance.jours_restants} jours restants, ${dto.nb_jours} jours demandés. (Art. 25 CDT — solde négatif non autorisé sans validation RH)`,
      );
    }

    const req = this.congeRequestRepo.create({ ...dto, statut: StatutConge.EN_ATTENTE });
    return this.congeRequestRepo.save(req);
  }

  async validerCongeRH(id: number, valideurId: number, approuve: boolean, commentaire?: string) {
    const req = await this.congeRequestRepo.findOne({
      where: { id },
      relations: ['employe'],
    });
    if (!req) throw new NotFoundException(`Demande de congé #${id} introuvable`);

    req.statut = approuve ? StatutConge.APPROUVE_RH : StatutConge.REFUSE;
    req.id_valideur_rh = valideurId;
    req.date_validation_rh = new Date();
    req.commentaire_rh = commentaire ?? null;
    await this.congeRequestRepo.save(req);

    // Décrémenter le solde si approuvé
    if (approuve) {
      const annee = new Date(req.date_debut).getFullYear();
      let bal = await this.congeBalanceRepo.findOne({
        where: { id_employe: req.id_employe, id_conge_type: req.id_conge_type, annee },
      });
      if (!bal) {
        const droits = this.droitsCongesLegaux(req.employe.date_embauche);
        bal = this.congeBalanceRepo.create({
          id_employe: req.id_employe,
          id_conge_type: req.id_conge_type,
          annee,
          jours_acquis: droits,
          jours_pris: 0,
          jours_restants: droits,
        });
      }
      bal.jours_pris = Number(bal.jours_pris) + req.nb_jours;
      bal.jours_restants = Number(bal.jours_acquis) - Number(bal.jours_pris);
      await this.congeBalanceRepo.save(bal);
    }
    return req;
  }

  async getCongeBalancesEmploye(id_employe: number, annee?: number) {
    return this.congeBalanceRepo.find({
      where: { id_employe, ...(annee ? { annee } : {}) },
      relations: ['type_conge'],
      order: { annee: 'DESC' },
    });
  }

  // ─── HISTORIQUE POSTES ───────────────────────────────────────────────────────

  async getHistoriquePostes(id_employe: number): Promise<RhHistoriquePoste[]> {
    return this.historiquePosteRepo.find({
      where: { id_employe },
      order: { date_effet: 'DESC' },
      relations: ['auteur'],
    });
  }

  // ─── DASHBOARD RH ─────────────────────────────────────────────────────────────

  async getDashboard() {
    const [effectif, actifs, cdd, congesEnAttente] = await Promise.all([
      this.employeRepo.count(),
      this.employeRepo.count({ where: { statut: StatutEmploye.ACTIF } }),
      this.contratRepo.count({ where: { type_contrat: TypeContrat.CDD, statut: StatutContrat.ACTIF } }),
      this.congeRequestRepo.count({ where: { statut: StatutConge.EN_ATTENTE } }),
    ]);
    const cddExpirants30 = await this.getCddExpirants(30);
    const cddExpirants7 = await this.getCddExpirants(7);

    const parStatut: Record<string, number> = await this.dataSource
      .query(`SELECT statut, COUNT(*)::int as n FROM rh_employes GROUP BY statut`)
      .then((rows: { statut: string; n: number }[]) =>
        rows.reduce((acc, r) => { acc[r.statut] = r.n; return acc; }, {} as Record<string, number>),
      );

    const parTypeContrat: Record<string, number> = await this.dataSource
      .query(`SELECT type_contrat_actuel as type, COUNT(*)::int as n FROM rh_employes WHERE statut = 'actif' GROUP BY type_contrat_actuel`)
      .then((rows: { type: string; n: number }[]) =>
        rows.reduce((acc, r) => { acc[r.type] = r.n; return acc; }, {} as Record<string, number>),
      );

    const parAgence: { agence_nom: string; n: number }[] = await this.dataSource.query(
      `SELECT COALESCE(a.nom, 'Sans agence') as agence_nom, COUNT(e.id)::int as n
       FROM rh_employes e LEFT JOIN agences a ON a.id = e.id_agence
       WHERE e.statut = 'actif' GROUP BY a.nom ORDER BY n DESC LIMIT 10`,
    );

    return {
      effectif_total: effectif,
      effectif_actif: actifs,
      cdd_actifs: cdd,
      conges_en_attente: congesEnAttente,
      cdd_expirant_7j: cddExpirants7.length,
      cdd_expirant_30j: cddExpirants30.length,
      par_statut: parStatut,
      par_type_contrat: parTypeContrat,
      par_agence: parAgence,
      alertes_cdd: cddExpirants7,
    };
  }
}
