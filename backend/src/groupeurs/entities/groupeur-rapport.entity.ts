import {
  Column,
  CreateDateColumn,
  Entity,
  JoinColumn,
  ManyToOne,
  PrimaryGeneratedColumn,
} from 'typeorm';
import { User } from '../../users/entities/user.entity';
import { Groupeur } from './groupeur.entity';

export type RapportGroupeurType =
  | 'activite_groupeur'
  | 'financier'
  | 'expeditions'
  | 'anomalies'
  | 'performance_globale';

export type RapportPeriode =
  | 'jour'
  | 'semaine'
  | 'mois'
  | 'trimestre'
  | 'annee';
export type RapportLectureStatut = 'non_lu' | 'lu';

@Entity('lbp_groupeur_rapports')
export class GroupeurRapport {
  @PrimaryGeneratedColumn('uuid')
  id: string;

  @Column({ type: 'int', nullable: true })
  auteur_id: number | null;

  @ManyToOne(() => User, { nullable: true })
  @JoinColumn({ name: 'auteur_id' })
  auteur: User | null;

  @Column({ type: 'varchar', length: 50 })
  type: RapportGroupeurType;

  @Column({ type: 'varchar', length: 20, nullable: true })
  periode: RapportPeriode | null;

  @Column({ type: 'date', nullable: true })
  date_debut: string | null;

  @Column({ type: 'date', nullable: true })
  date_fin: string | null;

  @Column({ type: 'uuid', nullable: true })
  groupeur_id: string | null;

  @ManyToOne(() => Groupeur, { nullable: true })
  @JoinColumn({ name: 'groupeur_id' })
  groupeur: Groupeur | null;

  @Column({ type: 'text', nullable: true })
  commentaire: string | null;

  @Column({ type: 'varchar', length: 20, default: 'non_lu' })
  statut_lecture: RapportLectureStatut;

  @Column({ type: 'int', nullable: true })
  soumis_a: number | null;

  @ManyToOne(() => User, { nullable: true })
  @JoinColumn({ name: 'soumis_a' })
  destinataire: User | null;

  @CreateDateColumn()
  created_at: Date;
}
