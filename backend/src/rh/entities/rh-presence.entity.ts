import {
  Entity, PrimaryGeneratedColumn, Column,
  CreateDateColumn, UpdateDateColumn, ManyToOne, JoinColumn,
} from 'typeorm';
import { RhEmploye } from './rh-employe.entity';
import { User } from '../../users/entities/user.entity';

export enum TypePointage {
  BADGEUSE = 'badgeuse',
  MOBILE = 'mobile',
  MANUEL = 'manuel',
  BIOMETRIE = 'biometrie',
}

export enum StatutPresence {
  PRESENT = 'present',
  ABSENT = 'absent',
  RETARD = 'retard',
  MISSION = 'mission',
  CONGE = 'conge',
  FERIE = 'ferie',
}

@Entity('rh_presences')
export class RhPresence {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => RhEmploye, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'id_employe' })
  employe: RhEmploye;

  @Column()
  id_employe: number;

  @Column({ type: 'date' })
  date_presence: string;

  @Column({ type: 'time', nullable: true })
  heure_entree: string | null;

  @Column({ type: 'time', nullable: true })
  heure_sortie: string | null;

  @Column({ type: 'numeric', precision: 5, scale: 2, default: 0 })
  heures_travaillees: number;

  @Column({ type: 'numeric', precision: 5, scale: 2, default: 0 })
  heures_sup: number;

  @Column({ type: 'numeric', precision: 5, scale: 2, default: 0 })
  retard_minutes: number;

  @Column({ type: 'enum', enum: StatutPresence, default: StatutPresence.PRESENT })
  statut: StatutPresence;

  @Column({ type: 'enum', enum: TypePointage, default: TypePointage.MANUEL })
  type_pointage: TypePointage;

  @Column({ type: 'text', nullable: true })
  justificatif: string | null;

  @Column({ type: 'boolean', default: false })
  est_valide: boolean;

  @ManyToOne(() => User, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_validateur' })
  validateur: User | null;

  @Column({ nullable: true })
  id_validateur: number | null;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
