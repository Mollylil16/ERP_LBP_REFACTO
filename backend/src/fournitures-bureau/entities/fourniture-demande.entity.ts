import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  UpdateDateColumn,
  ManyToOne,
  OneToMany,
  JoinColumn,
} from 'typeorm';
import { Agence } from '../../agences/entities/agence.entity';
import { User } from '../../users/entities/user.entity';
import { FournitureDemandeLigne } from './fourniture-demande-ligne.entity';

@Entity('lbp_fournitures_demandes')
export class FournitureDemande {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => Agence, { nullable: false })
  @JoinColumn({ name: 'id_agence' })
  agence: Agence;

  @ManyToOne(() => User, { nullable: false })
  @JoinColumn({ name: 'id_demandeur' })
  demandeur: User;

  @Column({ length: 20, default: 'BROUILLON' })
  statut: string;

  @Column({ type: 'text', nullable: true })
  observations: string | null;

  @Column({ type: 'text', nullable: true })
  motif_refus: string | null;

  @ManyToOne(() => User, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_valideur' })
  valideur: User | null;

  @Column({ type: 'timestamptz', nullable: true })
  date_validation: Date | null;

  @ManyToOne(() => User, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_livreur' })
  livreur: User | null;

  @Column({ type: 'timestamptz', nullable: true })
  date_livraison: Date | null;

  @OneToMany(() => FournitureDemandeLigne, (l) => l.demande, {
    cascade: ['insert', 'update'],
  })
  lignes: FournitureDemandeLigne[];

  @CreateDateColumn({ type: 'timestamptz' })
  created_at: Date;

  @UpdateDateColumn({ type: 'timestamptz' })
  updated_at: Date;
}
