import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  UpdateDateColumn,
  OneToMany,
  ManyToOne,
  JoinColumn,
} from 'typeorm';
import { MouvementCaisse } from './mouvement-caisse.entity';
import { Agence } from '../../agences/entities/agence.entity';

@Entity('lbp_caisses')
export class Caisse {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  nom: string;

  @Column({ type: 'decimal', precision: 12, scale: 2, default: 0 })
  solde_initial: number;

  @Column({ default: true })
  isActive: boolean;

  @Column({ type: 'decimal', precision: 12, scale: 2, default: 50000 })
  seuil_alerte: number;

  @ManyToOne(() => Agence, { nullable: true })
  @JoinColumn({ name: 'id_agence' })
  agence: Agence;

  @OneToMany(
    () => MouvementCaisse,
    (mouvement: MouvementCaisse) => mouvement.caisse,
  )
  mouvements: MouvementCaisse[];

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
