import { Injectable, NotFoundException, OnApplicationBootstrap, BadRequestException, ForbiddenException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, Between, In } from 'typeorm';
import { Caisse } from './entities/caisse.entity';
import { MouvementCaisse, MouvementType } from './entities/mouvement-caisse.entity';
import { Agence } from '../agences/entities/agence.entity';
import { CaisseSession, CaisseSessionStatus } from './entities/caisse-session.entity';
import { CaisseMouvementWorkflow, WorkflowStatus } from './entities/caisse-mouvement-workflow.entity';
import { CaisseAuditLog } from './entities/caisse-audit-log.entity';
import { Paiement } from '../paiements/entities/paiement.entity';
import { Facture } from '../factures/entities/facture.entity';

@Injectable()
export class CaisseService implements OnApplicationBootstrap {
    constructor(
        @InjectRepository(Caisse)
        private caisseRepository: Repository<Caisse>,
        @InjectRepository(MouvementCaisse)
        private mouvementRepository: Repository<MouvementCaisse>,
        @InjectRepository(Agence)
        private agenceRepository: Repository<Agence>,
        @InjectRepository(CaisseSession)
        private sessionRepository: Repository<CaisseSession>,
        @InjectRepository(CaisseMouvementWorkflow)
        private workflowRepository: Repository<CaisseMouvementWorkflow>,
        @InjectRepository(CaisseAuditLog)
        private caisseAuditRepository: Repository<CaisseAuditLog>,
        @InjectRepository(Paiement)
        private paiementRepository: Repository<Paiement>,
        @InjectRepository(Facture)
        private factureRepository: Repository<Facture>,
    ) { }

    async onApplicationBootstrap() {
        // S'assurer qu'il existe une caisse pour chaque agence existante
        const agences = await this.agenceRepository.find();
        for (const agence of agences) {
            const existing = await this.caisseRepository.findOne({ where: { agence: { id: agence.id } } });
            if (!existing) {
                await this.caisseRepository.save({
                    nom: `Caisse - ${agence.nom}`,
                    solde_initial: 0,
                    agence: agence,
                });
                console.log(`Cash register created for agency: ${agence.nom}`);
            }
        }
    }

    async createMovement(data: any, type: MouvementType, userId: string, agenceId?: number): Promise<MouvementCaisse> {
        let caisseId = data.id_caisse;

        if (!caisseId && agenceId) {
            const caisse = await this.caisseRepository.findOne({ where: { agence: { id: agenceId } } });
            caisseId = caisse?.id;
        }

        const caisse = await this.caisseRepository.findOne({ where: { id: caisseId || 1 } });

        if (!caisse) {
            throw new NotFoundException(`Caisse #${caisseId} not found`);
        }

        const activeSession = await this.getActiveSession(caisse.id);
        if (!activeSession) {
            throw new BadRequestException('Aucune session de caisse ouverte. Ouvrez la caisse avant tout mouvement.');
        }

        const mouvement = this.mouvementRepository.create({
            ...data,
            type,
            caisse,
            code_user: userId,
            date_mouvement: data.date_mouvement ? new Date(data.date_mouvement) : new Date(),
        } as MouvementCaisse);
        const saved = await this.mouvementRepository.save(mouvement);

        const requiredLevel = this.getValidationLevelRequired(type, Number(saved.montant));
        const initialStatus = data?.status === WorkflowStatus.DRAFT ? WorkflowStatus.DRAFT : WorkflowStatus.SUBMITTED;
        const justificatifRequired = this.isJustificatifRequired(type, Number(saved.montant));
        const justificatifUrl = data?.justificatif_url ?? null;

        if (justificatifRequired && initialStatus !== WorkflowStatus.DRAFT && !justificatifUrl) {
            throw new BadRequestException(
                `Pièce justificative obligatoire pour ce mouvement (seuil ${this.getJustificatifThreshold().toLocaleString('fr-FR')} FCFA).`
            );
        }

        await this.workflowRepository.save(
            this.workflowRepository.create({
                mouvement_id: saved.id,
                mouvement_type: type,
                status: initialStatus,
                validation_level_required: requiredLevel,
                validation_level_current: initialStatus === WorkflowStatus.SUBMITTED ? 0 : 0,
                submitted_by: initialStatus === WorkflowStatus.SUBMITTED ? userId : null,
                submitted_at: initialStatus === WorkflowStatus.SUBMITTED ? new Date() : null,
                justificatif_url: justificatifUrl,
            }),
        );

        await this.auditCaisse('MOUVEMENT_CREATE', saved.id, activeSession.id, userId, null, {
            type,
            montant: saved.montant,
            libelle: saved.libelle,
            status: initialStatus,
            validation_level_required: requiredLevel,
        });

        return saved;
    }

    async getMouvements(params: any, agenceId?: number): Promise<MouvementCaisse[]> {
        const { id_caisse, date_debut, date_fin, type } = params;
        const where: any = {};

        if (id_caisse) {
            where.caisse = { id: id_caisse };
        } else if (agenceId) {
            where.caisse = { agence: { id: agenceId } };
        }

        if (type) where.type = type;
        if (date_debut && date_fin) {
            where.date_mouvement = Between(new Date(date_debut), new Date(date_fin));
        }

        const rows = await this.mouvementRepository.find({
            where,
            order: { created_at: 'DESC' },
            relations: ['caisse'],
        });

        const ids = rows.map((r) => r.id);
        const workflows = ids.length
            ? await this.workflowRepository.find({ where: { mouvement_id: In(ids) } })
            : [];
        const wfMap = new Map<number, CaisseMouvementWorkflow>();
        workflows.forEach((w) => wfMap.set(w.mouvement_id, w));

        return rows.map((r: any) => {
            const wf = wfMap.get(r.id);
            return {
                ...r,
                workflow_status: wf?.status ?? null,
                validation_level_required: wf?.validation_level_required ?? 1,
                validation_level_current: wf?.validation_level_current ?? 0,
                justificatif_url: wf?.justificatif_url ?? null,
            };
        });
    }

    async openSession(idCaisse: number, openedBy: string, soldeOuvertureReel: number, note?: string) {
        const caisse = await this.caisseRepository.findOne({ where: { id: idCaisse } });
        if (!caisse) throw new NotFoundException(`Caisse #${idCaisse} not found`);

        const existing = await this.getActiveSession(idCaisse);
        if (existing) throw new BadRequestException('Une session est déjà ouverte pour cette caisse.');

        const soldeTheorique = await this.getSolde(idCaisse);
        const ecart = Number(soldeOuvertureReel) - Number(soldeTheorique);

        const today = new Date();
        const dateJournee = new Date(today.getFullYear(), today.getMonth(), today.getDate());

        const session = await this.sessionRepository.save(
            this.sessionRepository.create({
                caisse,
                status: CaisseSessionStatus.OPEN,
                date_journee: dateJournee,
                solde_ouverture_theorique: soldeTheorique,
                solde_ouverture_reel: soldeOuvertureReel,
                ecart_ouverture: ecart,
                opened_by: openedBy,
                note_ouverture: note ?? null,
            }),
        );

        await this.auditCaisse('SESSION_OPEN', null, session.id, openedBy, null, session);
        return session;
    }

    async closeSession(sessionId: number, closedBy: string, soldeFermetureReel: number, note?: string) {
        const session = await this.sessionRepository.findOne({
            where: { id: sessionId },
            relations: ['caisse'],
        });
        if (!session) throw new NotFoundException('Session de caisse introuvable');
        if (session.status !== CaisseSessionStatus.OPEN) {
            throw new BadRequestException('Cette session est déjà clôturée.');
        }

        const soldeFermetureTheorique = await this.getSolde(session.caisse.id);
        const ecartFermeture = Number(soldeFermetureReel) - Number(soldeFermetureTheorique);
        const before = { ...session };

        session.status = CaisseSessionStatus.CLOSED;
        session.solde_fermeture_theorique = soldeFermetureTheorique;
        session.solde_fermeture_reel = soldeFermetureReel;
        session.ecart_fermeture = ecartFermeture;
        session.closed_by = closedBy;
        session.note_fermeture = note ?? null;

        const saved = await this.sessionRepository.save(session);
        await this.auditCaisse('SESSION_CLOSE', null, session.id, closedBy, before, saved);
        return saved;
    }

    async submitMouvement(mouvementId: number, username: string) {
        const workflow = await this.workflowRepository.findOne({ where: { mouvement_id: mouvementId } });
        if (!workflow) throw new NotFoundException('Workflow de mouvement introuvable');
        if (workflow.status === WorkflowStatus.VALIDATED) {
            throw new BadRequestException('Ce mouvement est déjà validé.');
        }
        const mouvement = await this.mouvementRepository.findOne({ where: { id: mouvementId } });
        if (!mouvement) throw new NotFoundException('Mouvement introuvable');
        if (this.isJustificatifRequired(mouvement.type, Number(mouvement.montant)) && !workflow.justificatif_url) {
            throw new BadRequestException('Pièce justificative obligatoire avant soumission.');
        }

        const before = { ...workflow };
        workflow.status = WorkflowStatus.SUBMITTED;
        workflow.submitted_by = username;
        workflow.submitted_at = new Date();
        workflow.rejection_reason = null;
        const saved = await this.workflowRepository.save(workflow);
        await this.auditCaisse('MOUVEMENT_SUBMIT', mouvementId, null, username, before, saved);
        return saved;
    }

    async attachJustificatif(mouvementId: number, justificatifUrl: string, username: string) {
        if (!justificatifUrl || !justificatifUrl.trim()) {
            throw new BadRequestException('URL ou chemin du justificatif requis.');
        }
        const workflow = await this.workflowRepository.findOne({ where: { mouvement_id: mouvementId } });
        if (!workflow) throw new NotFoundException('Workflow de mouvement introuvable');

        const before = { ...workflow };
        workflow.justificatif_url = justificatifUrl.trim();
        const saved = await this.workflowRepository.save(workflow);
        await this.auditCaisse('MOUVEMENT_ATTACH_JUSTIFICATIF', mouvementId, null, username, before, saved);
        return saved;
    }

    async validateMouvement(mouvementId: number, username: string, role: string, approve: boolean, reason?: string) {
        const workflow = await this.workflowRepository.findOne({ where: { mouvement_id: mouvementId } });
        if (!workflow) throw new NotFoundException('Workflow de mouvement introuvable');
        if (![WorkflowStatus.SUBMITTED, WorkflowStatus.REJECTED].includes(workflow.status)) {
            throw new BadRequestException('Mouvement non soumis pour validation.');
        }

        const normalizedRole = String(role || '').toUpperCase();
        const canValidate = ['ADMIN', 'DIRECTEUR', 'SUPER_ADMIN', 'MANAGER'].includes(normalizedRole);
        if (!canValidate) {
            throw new ForbiddenException('Vous n’avez pas le droit de valider ce mouvement.');
        }

        const before = { ...workflow };
        if (!approve) {
            workflow.status = WorkflowStatus.REJECTED;
            workflow.rejection_reason = reason || 'Rejeté sans motif';
            const rejected = await this.workflowRepository.save(workflow);
            await this.auditCaisse('MOUVEMENT_REJECT', mouvementId, null, username, before, rejected);
            return rejected;
        }

        workflow.validation_level_current += 1;
        if (workflow.validation_level_current >= workflow.validation_level_required) {
            workflow.status = WorkflowStatus.VALIDATED;
            workflow.approved_by = username;
            workflow.approved_at = new Date();
        } else {
            workflow.status = WorkflowStatus.SUBMITTED;
        }
        const saved = await this.workflowRepository.save(workflow);
        await this.auditCaisse('MOUVEMENT_VALIDATE', mouvementId, null, username, before, saved);
        return saved;
    }

    async getWorkflow(mouvementId: number) {
        const workflow = await this.workflowRepository.findOne({ where: { mouvement_id: mouvementId } });
        if (!workflow) throw new NotFoundException('Workflow de mouvement introuvable');
        return workflow;
    }

    async getActiveSession(idCaisse: number) {
        return this.sessionRepository.findOne({
            where: {
                caisse: { id: idCaisse },
                status: CaisseSessionStatus.OPEN,
            },
            relations: ['caisse'],
            order: { created_at: 'DESC' },
        });
    }

    async getSessionHistory(idCaisse: number, limit = 20) {
        return this.sessionRepository.find({
            where: { caisse: { id: idCaisse } },
            relations: ['caisse'],
            order: { created_at: 'DESC' },
            take: Math.max(1, Math.min(limit, 200)),
        });
    }

    async reconcileDaily(date: string, idCaisse?: number) {
        const target = date ? new Date(date) : new Date();
        const start = new Date(target);
        start.setHours(0, 0, 0, 0);
        const end = new Date(target);
        end.setHours(23, 59, 59, 999);

        const mouvementWhere: any = { date_mouvement: Between(start, end) };
        if (idCaisse) {
            mouvementWhere.caisse = { id: idCaisse };
        }

        const mouvements = await this.mouvementRepository.find({
            where: mouvementWhere,
            relations: ['caisse', 'caisse.agence'],
        });
        const workflows = mouvements.length
            ? await this.workflowRepository.find({ where: { mouvement_id: In(mouvements.map((m) => m.id)) } })
            : [];
        const validatedIds = new Set(
            workflows.filter((w) => w.status === WorkflowStatus.VALIDATED).map((w) => w.mouvement_id),
        );

        const mouvementsValides = mouvements.filter((m) => validatedIds.has(m.id));
        const entreesCaisse = mouvementsValides
            .filter((m) => m.type !== MouvementType.DECAISSEMENT)
            .reduce((sum, m) => sum + Number(m.montant), 0);
        const sortiesCaisse = mouvementsValides
            .filter((m) => m.type === MouvementType.DECAISSEMENT)
            .reduce((sum, m) => sum + Number(m.montant), 0);

        const paiementsQb = this.paiementRepository
            .createQueryBuilder('p')
            .leftJoinAndSelect('p.facture', 'f')
            .leftJoinAndSelect('f.colis', 'c')
            .where('p.date_paiement BETWEEN :start AND :end', { start, end })
            .andWhere('p.etat_validation = 1');
        if (idCaisse) {
            paiementsQb
                .leftJoin('c.agence', 'a')
                .andWhere('a.id = (SELECT id_agence FROM lbp_caisses WHERE id = :idCaisse)', { idCaisse });
        }
        const paiements = await paiementsQb.getMany();
        const totalPaiements = paiements.reduce((sum, p) => sum + Number(p.montant), 0);

        const facturesQb = this.factureRepository
            .createQueryBuilder('f')
            .leftJoinAndSelect('f.colis', 'c')
            .where('f.date_facture BETWEEN :start AND :end', { start, end })
            .andWhere('f.etat != 2');
        if (idCaisse) {
            facturesQb
                .leftJoin('c.agence', 'a')
                .andWhere('a.id = (SELECT id_agence FROM lbp_caisses WHERE id = :idCaisse)', { idCaisse });
        }
        const factures = await facturesQb.getMany();
        const totalFactureTTC = factures.reduce((sum, f) => sum + Number(f.montant_ttc), 0);

        const ecartPaiementsVsEntrees = Number((totalPaiements - entreesCaisse).toFixed(2));
        const ecartFacturesVsPaiements = Number((totalFactureTTC - totalPaiements).toFixed(2));

        return {
            date: start.toISOString().slice(0, 10),
            id_caisse: idCaisse || null,
            totals: {
                entrees_caisse_validees: entreesCaisse,
                sorties_caisse_validees: sortiesCaisse,
                paiements_valides: totalPaiements,
                factures_ttc: totalFactureTTC,
            },
            ecarts: {
                paiements_vs_entrees_caisse: ecartPaiementsVsEntrees,
                factures_vs_paiements: ecartFacturesVsPaiements,
            },
            counts: {
                mouvements_total: mouvements.length,
                mouvements_valides: mouvementsValides.length,
                paiements: paiements.length,
                factures: factures.length,
            },
        };
    }

    async detectAnomalies(dateDebut?: string, dateFin?: string) {
        const start = dateDebut ? new Date(dateDebut) : new Date('2000-01-01');
        const end = dateFin ? new Date(dateFin) : new Date();
        end.setHours(23, 59, 59, 999);

        const doublePaiements = await this.paiementRepository
            .createQueryBuilder('p')
            .select('p.id_facture', 'id_facture')
            .addSelect('p.montant', 'montant')
            .addSelect('p.mode_paiement', 'mode_paiement')
            .addSelect('p.date_paiement', 'date_paiement')
            .addSelect('COUNT(*)', 'occurrences')
            .where('p.date_paiement BETWEEN :start AND :end', { start, end })
            .groupBy('p.id_facture, p.montant, p.mode_paiement, p.date_paiement')
            .having('COUNT(*) > 1')
            .getRawMany();

        const incoherencesFactures = await this.factureRepository
            .createQueryBuilder('f')
            .where('(f.montant_paye > f.montant_ttc OR f.montant_paye < 0)')
            .getMany();

        const allFactures = await this.factureRepository.find({ order: { num_facture: 'ASC' } });
        const sequenceGaps: Array<{ prefix: string; missing: number[] }> = [];
        const grouped = new Map<string, number[]>();
        allFactures.forEach((f) => {
            const parts = f.num_facture.split('-');
            if (parts.length >= 3) {
                const prefix = `${parts[0]}-${parts[1]}`;
                const seq = Number(parts[2]);
                if (!Number.isNaN(seq)) {
                    grouped.set(prefix, [...(grouped.get(prefix) || []), seq]);
                }
            }
        });
        grouped.forEach((seqs, prefix) => {
            const sorted = [...seqs].sort((a, b) => a - b);
            const missing: number[] = [];
            for (let i = sorted[0]; i <= sorted[sorted.length - 1]; i += 1) {
                if (!sorted.includes(i)) missing.push(i);
            }
            if (missing.length > 0) {
                sequenceGaps.push({ prefix, missing: missing.slice(0, 50) });
            }
        });

        return {
            range: {
                date_debut: start.toISOString().slice(0, 10),
                date_fin: end.toISOString().slice(0, 10),
            },
            anomalies: {
                doublons_paiements: doublePaiements,
                incoherences_montants_factures: incoherencesFactures.map((f) => ({
                    id: f.id,
                    num_facture: f.num_facture,
                    montant_ttc: Number(f.montant_ttc),
                    montant_paye: Number(f.montant_paye),
                })),
                trous_sequence_factures: sequenceGaps,
            },
            summary: {
                doublons: doublePaiements.length,
                incoherences: incoherencesFactures.length,
                sequences_avec_trous: sequenceGaps.length,
            },
        };
    }

    async getSolde(id_caisse: number = 1): Promise<number> {
        const caisse = await this.caisseRepository.findOne({ where: { id: id_caisse } });
        if (!caisse) return 0;

        const mouvements = await this.mouvementRepository.find({
            where: { caisse: { id: id_caisse } }
        });

        const total = mouvements.reduce((acc, mv) => {
            // Les décaissements diminuent le solde
            if (mv.type === MouvementType.DECAISSEMENT) {
                return acc - Number(mv.montant);
            }
            // Tous les autres types (APPRO, ENTREE_*) augmentent le solde
            return acc + Number(mv.montant);
        }, Number(caisse.solde_initial || 0));

        return total;
    }

    async getPointCaisse(date?: string, id_caisse: number = 1): Promise<any> {
        const targetDate = date ? new Date(date) : new Date();
        const startOfDay = new Date(targetDate.setHours(0, 0, 0, 0));
        const endOfDay = new Date(targetDate.setHours(23, 59, 59, 999));

        const mouvements = await this.mouvementRepository.find({
            where: {
                caisse: { id: id_caisse },
                date_mouvement: Between(startOfDay, endOfDay),
            }
        });

        const entrees = mouvements
            .filter(m => m.type !== MouvementType.DECAISSEMENT)
            .reduce((sum, m) => sum + Number(m.montant), 0);

        const sorties = mouvements
            .filter(m => m.type === MouvementType.DECAISSEMENT)
            .reduce((sum, m) => sum + Number(m.montant), 0);

        const solde = await this.getSolde(id_caisse);

        return {
            date: startOfDay,
            entrees,
            sorties,
            solde,
            mouvementsCount: mouvements.length,
        };
    }

    async findAllCaisses(agenceId?: number): Promise<any[]> {
        const caisses = agenceId
            ? await this.caisseRepository.find({ where: { agence: { id: agenceId } } })
            : await this.caisseRepository.find();

        const results: any[] = [];
        for (const caisse of caisses) {
            const solde_actuel = await this.getSolde(caisse.id);
            results.push({
                ...caisse,
                libelle: caisse.nom,
                montant_initial: caisse.solde_initial,
                solde_actuel: solde_actuel,
            });
        }
        return results;
    }

    async getRapportGrandesLignes(params: {
        date_debut: string;
        date_fin: string;
        id_caisse?: number;
    }): Promise<any> {
        const { date_debut, date_fin, id_caisse = 1 } = params;
        const startDate = new Date(date_debut);
        const endDate = new Date(date_fin);
        endDate.setHours(23, 59, 59, 999);

        // Récupérer la caisse
        const caisse = await this.caisseRepository.findOne({ where: { id: id_caisse } });
        if (!caisse) {
            throw new NotFoundException(`Caisse #${id_caisse} not found`);
        }

        // Récupérer tous les mouvements dans la période
        const mouvements = await this.mouvementRepository.find({
            where: {
                caisse: { id: id_caisse },
                date_mouvement: Between(startDate, endDate),
            },
            order: { date_mouvement: 'ASC' },
        });

        // Calculer les totaux
        const totalAppro = mouvements
            .filter(m => m.type === MouvementType.APPRO)
            .reduce((sum, m) => sum + Number(m.montant), 0);

        const totalDecaissement = mouvements
            .filter(m => m.type === MouvementType.DECAISSEMENT)
            .reduce((sum, m) => sum + Number(m.montant), 0);

        const totalEntreesCheque = mouvements
            .filter(m => m.type === MouvementType.ENTREE_CHEQUE)
            .reduce((sum, m) => sum + Number(m.montant), 0);

        const totalEntreesEspece = mouvements
            .filter(m => m.type === MouvementType.ENTREE_ESPECE)
            .reduce((sum, m) => sum + Number(m.montant), 0);

        const totalEntreesVirement = mouvements
            .filter(m => m.type === MouvementType.ENTREE_VIREMENT)
            .reduce((sum, m) => sum + Number(m.montant), 0);

        const totalEntrees = totalEntreesCheque + totalEntreesEspece + totalEntreesVirement;

        // Solde initial (avant la période)
        const mouvementsAvant = await this.mouvementRepository.find({
            where: {
                caisse: { id: id_caisse },
                date_mouvement: Between(new Date('1900-01-01'), new Date(startDate.getTime() - 1)),
            },
        });

        const soldeInitial = mouvementsAvant.reduce((acc, mv) => {
            if (mv.type === MouvementType.DECAISSEMENT) {
                return acc - Number(mv.montant);
            }
            return acc + Number(mv.montant);
        }, Number(caisse.solde_initial));

        const soldeFinal = soldeInitial + totalAppro - totalDecaissement + totalEntrees;

        return {
            date_debut: startDate.toISOString(),
            date_fin: endDate.toISOString(),
            total_appro: totalAppro,
            total_decaissement: totalDecaissement,
            total_entrees_cheque: totalEntreesCheque,
            total_entrees_espece: totalEntreesEspece,
            total_entrees_virement: totalEntreesVirement,
            total_entrees: totalEntrees,
            solde_initial: soldeInitial,
            solde_final: soldeFinal,
        };
    }

    private getValidationLevelRequired(type: MouvementType, montant: number): number {
        const threshold = Number(process.env.CAISSE_DOUBLE_VALIDATION_THRESHOLD || 100000);
        if (type === MouvementType.DECAISSEMENT && montant >= threshold) {
            return 2;
        }
        return 1;
    }

    private isJustificatifRequired(type: MouvementType, montant: number): boolean {
        const threshold = this.getJustificatifThreshold();
        return type === MouvementType.DECAISSEMENT && montant >= threshold;
    }

    private getJustificatifThreshold(): number {
        return Number(process.env.CAISSE_JUSTIFICATIF_THRESHOLD || 50000);
    }

    private async auditCaisse(
        action: string,
        mouvementId: number | null,
        sessionId: number | null,
        actorUsername: string,
        beforeData: any,
        afterData: any,
    ) {
        await this.caisseAuditRepository.save(
            this.caisseAuditRepository.create({
                action,
                mouvement_id: mouvementId,
                session_id: sessionId,
                actor_username: actorUsername,
                before_data: beforeData,
                after_data: afterData,
            }),
        );
    }

}