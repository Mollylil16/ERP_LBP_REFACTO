import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  CreateDateColumn,
  UpdateDateColumn,
  ManyToOne,
  JoinColumn,
} from 'typeorm';
import { Agence } from '../../agences/entities/agence.entity';
import { User } from '../../users/entities/user.entity';

@Entity('lbp_supervision_signalements')
export class SupervisionSignalement {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ name: 'id_agence', nullable: true })
  id_agence: number | null;

  @ManyToOne(() => Agence, { onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_agence' })
  agence: Agence | null;

  @Column({ name: 'id_auteur' })
  id_auteur: number;

  @ManyToOne(() => User, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'id_auteur' })
  auteur: User;

  @Column({ length: 80 })
  type: string;

  @Column({ type: 'text', nullable: true })
  description: string | null;

  @Column({ length: 20, default: 'moyen' })
  gravite: string;

  @Column({ length: 20, default: 'ouvert' })
  statut: string;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
