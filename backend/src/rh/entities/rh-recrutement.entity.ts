import {
  Entity, PrimaryGeneratedColumn, Column,
  CreateDateColumn, UpdateDateColumn, ManyToOne, JoinColumn,
} from 'typeorm';
import { User } from '../../users/entities/user.entity';
import { Agence } from '../../agences/entities/agence.entity';

export enum StatutPoste {
  OUVERT = 'ouvert',
  EN_COURS = 'en_cours',
  POURVU = 'pourvu',
  ANNULE = 'annule',
}

export enum StatutCandidature {
  NOUVEAU = 'nouveau',
  PRESELECTIONNE = 'preselectionne',
  ENTRETIEN = 'entretien',
  RETENU = 'retenu',
  REFUSE = 'refuse',
  EMBAUCHE = 'embauche',
}

@Entity('rh_postes')
export class RhPoste {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ length: 150 })
  intitule: string;

  @Column({ type: 'varchar', length: 100, nullable: true })
  departement: string | null;

  @Column({ type: 'text', nullable: true })
  description: string | null;

  @Column({ type: 'text', nullable: true })
  competences_requises: string | null;

  @Column({ type: 'int', default: 1 })
  nb_postes: number;

  @Column({ type: 'enum', enum: StatutPoste, default: StatutPoste.OUVERT })
  statut: StatutPoste;

  @ManyToOne(() => Agence, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_agence' })
  agence: Agence | null;

  @Column({ nullable: true })
  id_agence: number | null;

  @Column({ type: 'date', nullable: true })
  date_limite: string | null;

  @Column({ type: 'boolean', default: true })
  publication_interne: boolean;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}

@Entity('rh_candidatures')
export class RhCandidature {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => RhPoste, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'id_poste' })
  poste: RhPoste;

  @Column()
  id_poste: number;

  @Column({ length: 100 })
  nom: string;

  @Column({ length: 200 })
  prenoms: string;

  @Column({ type: 'varchar', length: 100, nullable: true })
  email: string | null;

  @Column({ type: 'varchar', length: 20, nullable: true })
  telephone: string | null;

  @Column({ type: 'text', nullable: true })
  cv_url: string | null;

  @Column({ type: 'text', nullable: true })
  lettre_motivation_url: string | null;

  @Column({ type: 'enum', enum: StatutCandidature, default: StatutCandidature.NOUVEAU })
  statut: StatutCandidature;

  @Column({ type: 'text', nullable: true })
  notes_recruteur: string | null;

  @Column({ type: 'numeric', precision: 5, scale: 2, nullable: true })
  note_entretien: number | null;

  @ManyToOne(() => User, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_recruteur' })
  recruteur: User | null;

  @Column({ nullable: true })
  id_recruteur: number | null;

  @Column({ type: 'date', nullable: true })
  date_entretien: string | null;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
