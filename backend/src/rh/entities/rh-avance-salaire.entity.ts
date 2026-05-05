import {
  Entity, PrimaryGeneratedColumn, Column,
  CreateDateColumn, UpdateDateColumn, ManyToOne, JoinColumn,
} from 'typeorm';
import { RhEmploye } from './rh-employe.entity';
import { User } from '../../users/entities/user.entity';

export enum StatutAvance {
  EN_ATTENTE = 'en_attente',
  APPROUVE = 'approuve',
  REFUSE = 'refuse',
  REMBOURSEE = 'remboursee',
}

@Entity('rh_avances_salaire')
export class RhAvanceSalaire {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => RhEmploye, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'id_employe' })
  employe: RhEmploye;

  @Column()
  id_employe: number;

  @Column({ type: 'numeric', precision: 15, scale: 2 })
  montant: number;

  @Column({ length: 7 })
  mois_deduction: string; // 'YYYY-MM' — mois sur lequel déduire

  @Column({ type: 'enum', enum: StatutAvance, default: StatutAvance.EN_ATTENTE })
  statut: StatutAvance;

  @Column({ type: 'text', nullable: true })
  motif: string | null;

  @ManyToOne(() => User, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_approbateur' })
  approbateur: User | null;

  @Column({ nullable: true })
  id_approbateur: number | null;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
