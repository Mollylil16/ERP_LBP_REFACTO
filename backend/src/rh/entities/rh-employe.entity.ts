import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  CreateDateColumn,
  UpdateDateColumn,
  ManyToOne,
  OneToMany,
  JoinColumn,
} from 'typeorm';
import { Agence } from '../../agences/entities/agence.entity';
import { User } from '../../users/entities/user.entity';
import { RhContrat } from './rh-contrat.entity';
import { RhCongeRequest } from './rh-conge-request.entity';
import { TypeContrat } from './rh-enums';
export { TypeContrat } from './rh-enums';

export enum StatutEmploye {
  ACTIF = 'actif',
  SUSPENDU = 'suspendu',
  SORTI = 'sorti',
}

export enum Sexe {
  M = 'M',
  F = 'F',
}

export enum SituationFamiliale {
  CELIBATAIRE = 'celibataire',
  MARIE = 'marie',
  DIVORCE = 'divorce',
  VEUF = 'veuf',
}

@Entity('rh_employes')
export class RhEmploye {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ unique: true, length: 20 })
  matricule: string;

  // ── Identité ──────────────────────────────────────────────
  @Column({ length: 100 })
  nom: string;

  @Column({ length: 200 })
  prenoms: string;

  @Column({ type: 'date', nullable: true })
  date_naissance: string | null;

  @Column({ type: "varchar", length: 100, nullable: true })
  lieu_naissance: string | null;

  @Column({ type: "varchar", length: 80, nullable: true })
  nationalite: string | null;

  @Column({ type: 'enum', enum: Sexe, nullable: true })
  sexe: Sexe | null;

  @Column({ type: 'enum', enum: SituationFamiliale, nullable: true })
  situation_familiale: SituationFamiliale | null;

  @Column({ type: 'int', default: 0 })
  nb_enfants: number;

  @Column({ type: "varchar", length: 30, nullable: true })
  numero_cni: string | null;

  @Column({ type: "varchar", length: 30, nullable: true })
  numero_cnps: string | null;

  // ── Contact ───────────────────────────────────────────────
  @Column({ type: 'text', nullable: true })
  adresse: string | null;

  @Column({ type: "varchar", length: 20, nullable: true })
  telephone: string | null;

  @Column({ type: "varchar", length: 100, nullable: true })
  email_pro: string | null;

  @Column({ type: "varchar", length: 100, nullable: true })
  email_perso: string | null;

  // ── Professionnel ─────────────────────────────────────────
  @Column({ type: 'date' })
  date_embauche: string;

  @Column({ type: 'date', nullable: true })
  date_sortie: string | null;

  @Column({ type: "varchar", length: 100, nullable: true })
  intitule_poste: string | null;

  @Column({ type: "varchar", length: 60, nullable: true })
  categorie: string | null;

  @Column({ type: "varchar", length: 40, nullable: true })
  grade: string | null;

  @Column({ type: "varchar", length: 100, nullable: true })
  departement: string | null;

  @Column({ type: "varchar", length: 100, nullable: true })
  service: string | null;

  @Column({ type: 'enum', enum: TypeContrat, default: TypeContrat.CDI })
  type_contrat_actuel: TypeContrat;

  @Column({ type: 'enum', enum: StatutEmploye, default: StatutEmploye.ACTIF })
  statut: StatutEmploye;

  // ── Relations ─────────────────────────────────────────────
  @ManyToOne(() => Agence, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_agence' })
  agence: Agence | null;

  @Column({ nullable: true })
  id_agence: number | null;

  @ManyToOne(() => RhEmploye, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_responsable' })
  responsable: RhEmploye | null;

  @Column({ nullable: true })
  id_responsable: number | null;

  @ManyToOne(() => User, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_user' })
  user: User | null;

  @Column({ nullable: true })
  id_user: number | null;

  @OneToMany(() => RhContrat, (c) => c.employe)
  contrats: RhContrat[];

  @OneToMany(() => RhCongeRequest, (c) => c.employe)
  conge_requests: RhCongeRequest[];

  // ── Données médicales sensibles chiffrées (Art. 4 CDT CI) ───────────────
  // Contenu AES-256-GCM : JSON { statut_vih, handicap_type, taux_incapacite }
  // Accessible uniquement aux rôles RH autorisés via RhEncryptionService
  @Column({ type: 'text', nullable: true, select: false })
  situation_medicale_enc: string | null;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
