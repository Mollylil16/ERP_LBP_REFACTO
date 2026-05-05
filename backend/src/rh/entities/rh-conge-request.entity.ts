import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  CreateDateColumn,
  UpdateDateColumn,
  ManyToOne,
  JoinColumn,
} from 'typeorm';
import { RhEmploye } from './rh-employe.entity';
import { RhCongeType } from './rh-conge-type.entity';
import { User } from '../../users/entities/user.entity';

export enum StatutConge {
  EN_ATTENTE = 'en_attente',
  APPROUVE_MANAGER = 'approuve_manager',
  APPROUVE_RH = 'approuve',
  REFUSE = 'refuse',
  ANNULE = 'annule',
}

@Entity('rh_conge_requests')
export class RhCongeRequest {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => RhEmploye, (e) => e.conge_requests, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'id_employe' })
  employe: RhEmploye;

  @Column()
  id_employe: number;

  @ManyToOne(() => RhCongeType, { onDelete: 'RESTRICT' })
  @JoinColumn({ name: 'id_conge_type' })
  type_conge: RhCongeType;

  @Column()
  id_conge_type: number;

  @Column({ type: 'date' })
  date_debut: string;

  @Column({ type: 'date' })
  date_fin: string;

  @Column({ type: 'int' })
  nb_jours: number;

  @Column({ type: 'text', nullable: true })
  motif: string | null;

  @Column({ type: 'enum', enum: StatutConge, default: StatutConge.EN_ATTENTE })
  statut: StatutConge;

  // Validation manager
  @ManyToOne(() => User, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_valideur_manager' })
  valideur_manager: User | null;

  @Column({ nullable: true })
  id_valideur_manager: number | null;

  @Column({ type: 'timestamptz', nullable: true })
  date_validation_manager: Date | null;

  @Column({ type: 'text', nullable: true })
  commentaire_manager: string | null;

  // Validation RH
  @ManyToOne(() => User, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_valideur_rh' })
  valideur_rh: User | null;

  @Column({ nullable: true })
  id_valideur_rh: number | null;

  @Column({ type: 'timestamptz', nullable: true })
  date_validation_rh: Date | null;

  @Column({ type: 'text', nullable: true })
  commentaire_rh: string | null;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
