import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  UpdateDateColumn,
  ManyToOne,
  JoinColumn,
} from 'typeorm';
import { Caisse } from './caisse.entity';

export enum CaisseSessionStatus {
  OPEN = 'OPEN',
  CLOSED = 'CLOSED',
}

@Entity('lbp_caisse_sessions')
export class CaisseSession {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => Caisse, { nullable: false })
  @JoinColumn({ name: 'id_caisse' })
  caisse: Caisse;

  @Column({
    type: 'enum',
    enum: CaisseSessionStatus,
    default: CaisseSessionStatus.OPEN,
  })
  status: CaisseSessionStatus;

  @Column({ type: 'date' })
  date_journee: Date;

  @Column({ type: 'decimal', precision: 12, scale: 2, default: 0 })
  solde_ouverture_theorique: number;

  @Column({ type: 'decimal', precision: 12, scale: 2, default: 0 })
  solde_ouverture_reel: number;

  @Column({ type: 'decimal', precision: 12, scale: 2, nullable: true })
  solde_fermeture_theorique: number | null;

  @Column({ type: 'decimal', precision: 12, scale: 2, nullable: true })
  solde_fermeture_reel: number | null;

  @Column({ type: 'decimal', precision: 12, scale: 2, nullable: true })
  ecart_ouverture: number | null;

  @Column({ type: 'decimal', precision: 12, scale: 2, nullable: true })
  ecart_fermeture: number | null;

  @Column({ type: 'varchar', length: 100, nullable: true })
  opened_by: string | null;

  @Column({ type: 'varchar', length: 100, nullable: true })
  closed_by: string | null;

  @Column({ type: 'text', nullable: true })
  note_ouverture: string | null;

  @Column({ type: 'text', nullable: true })
  note_fermeture: string | null;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
