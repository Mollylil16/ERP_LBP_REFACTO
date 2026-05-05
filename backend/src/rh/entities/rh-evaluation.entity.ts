import {
  Entity, PrimaryGeneratedColumn, Column,
  CreateDateColumn, UpdateDateColumn, ManyToOne, JoinColumn,
} from 'typeorm';
import { RhEmploye } from './rh-employe.entity';
import { User } from '../../users/entities/user.entity';

export enum TypeEvaluation {
  ANNUELLE = 'annuelle',
  SEMESTRIELLE = 'semestrielle',
  TRIMESTRIELLE = 'trimestrielle',
  FIN_ESSAI = 'fin_essai',
}

export enum StatutEvaluation {
  BROUILLON = 'brouillon',
  EN_COURS = 'en_cours',
  SIGNE_EVALUE = 'signe_evalue',
  SIGNE_EVALUATEUR = 'signe_evaluateur',
  VALIDE_RH = 'valide_rh',
  CLOTURE = 'cloture',
}

@Entity('rh_evaluations')
export class RhEvaluation {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => RhEmploye, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'id_employe' })
  employe: RhEmploye;

  @Column()
  id_employe: number;

  @ManyToOne(() => User, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_evaluateur' })
  evaluateur: User | null;

  @Column({ nullable: true })
  id_evaluateur: number | null;

  @Column({ type: 'enum', enum: TypeEvaluation })
  type: TypeEvaluation;

  @Column({ length: 7 })
  periode: string; // 'YYYY-QX' ou 'YYYY-S1/S2' ou 'YYYY'

  @Column({ type: 'enum', enum: StatutEvaluation, default: StatutEvaluation.BROUILLON })
  statut: StatutEvaluation;

  // Scores pondérés (sur 100)
  @Column({ type: 'numeric', precision: 5, scale: 2, nullable: true })
  score_resultats: number | null; // 40%

  @Column({ type: 'numeric', precision: 5, scale: 2, nullable: true })
  score_competences_metier: number | null; // 25%

  @Column({ type: 'numeric', precision: 5, scale: 2, nullable: true })
  score_comportement: number | null; // 20%

  @Column({ type: 'numeric', precision: 5, scale: 2, nullable: true })
  score_conformite: number | null; // 10%

  @Column({ type: 'numeric', precision: 5, scale: 2, nullable: true })
  score_developpement: number | null; // 5%

  @Column({ type: 'numeric', precision: 5, scale: 2, nullable: true })
  note_globale: number | null;

  @Column({ type: 'text', nullable: true })
  commentaire_evaluateur: string | null;

  @Column({ type: 'text', nullable: true })
  commentaire_employe: string | null;

  @Column({ type: 'text', nullable: true })
  plan_developpement: string | null;

  @Column({ type: 'jsonb', nullable: true })
  objectifs: Array<{ description: string; mesure: string; statut: string; score: number }> | null;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
