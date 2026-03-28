import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  UpdateDateColumn,
  ManyToOne,
  JoinColumn,
} from 'typeorm';
import { Facture } from '../../factures/entities/facture.entity';

export enum LienPaiementStatut {
  EN_ATTENTE = 'en_attente',
  PAYE = 'paye',
  EXPIRE = 'expire',
  ANNULE = 'annule',
}

@Entity('lbp_liens_paiement')
export class LienPaiement {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ unique: true })
  token: string;

  @ManyToOne(() => Facture)
  @JoinColumn({ name: 'id_facture' })
  facture: Facture;

  @Column({
    type: 'enum',
    enum: LienPaiementStatut,
    default: LienPaiementStatut.EN_ATTENTE,
  })
  statut: LienPaiementStatut;

  @Column({ type: 'decimal', precision: 12, scale: 2 })
  montant: number;

  @Column({ nullable: true })
  moyen_paiement: string; // 'orange_money' or 'wave'

  @Column({ type: 'jsonb', nullable: true })
  metadata: any; // Stocke les infos du provider (transaction_id, customer_name, etc.)

  @Column({ type: 'timestamp', nullable: true })
  date_paiement: Date;

  @Column({ type: 'timestamp' })
  date_expiration: Date;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
