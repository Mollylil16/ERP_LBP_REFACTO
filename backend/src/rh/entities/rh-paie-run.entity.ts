import {
  Entity, PrimaryGeneratedColumn, Column,
  CreateDateColumn, UpdateDateColumn, OneToMany, ManyToOne, JoinColumn,
} from 'typeorm';
import { User } from '../../users/entities/user.entity';
import { RhPaieLigne } from './rh-paie-ligne.entity';

export enum StatutPaieRun {
  BROUILLON = 'brouillon',
  CALCULE = 'calcule',
  VALIDE_RH = 'valide_rh',
  VALIDE_DAF = 'valide_daf',
  CLOTURE = 'cloture',
}

@Entity('rh_paie_runs')
export class RhPaieRun {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ length: 7 })
  periode: string; // 'YYYY-MM'

  @Column({ type: 'enum', enum: StatutPaieRun, default: StatutPaieRun.BROUILLON })
  statut: StatutPaieRun;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  total_brut: number;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  total_net: number;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  total_charges_salariales: number;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  total_charges_patronales: number;

  @Column({ type: 'int', default: 0 })
  nb_employes: number;

  @Column({ type: 'text', nullable: true })
  notes: string | null;

  @ManyToOne(() => User, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_valideur_rh' })
  valideur_rh: User | null;

  @Column({ nullable: true })
  id_valideur_rh: number | null;

  @Column({ type: 'timestamptz', nullable: true })
  date_validation_rh: Date | null;

  @ManyToOne(() => User, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_valideur_daf' })
  valideur_daf: User | null;

  @Column({ nullable: true })
  id_valideur_daf: number | null;

  @Column({ type: 'timestamptz', nullable: true })
  date_validation_daf: Date | null;

  @OneToMany(() => RhPaieLigne, (l) => l.run)
  lignes: RhPaieLigne[];

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
