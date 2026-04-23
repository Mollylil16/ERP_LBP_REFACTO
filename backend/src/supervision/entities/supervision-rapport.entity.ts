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

@Entity('lbp_supervision_rapports')
export class SupervisionRapport {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ name: 'id_auteur' })
  id_auteur: number;

  @ManyToOne(() => User, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'id_auteur' })
  auteur: User;

  @Column({ length: 50 })
  type: string;

  @Column({ length: 20 })
  periode: string;

  @Column({ name: 'id_agence', nullable: true })
  id_agence: number | null;

  @ManyToOne(() => Agence, { onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_agence' })
  agence: Agence | null;

  @Column({ name: 'date_debut', type: 'date', nullable: true })
  date_debut: string | null;

  @Column({ name: 'date_fin', type: 'date', nullable: true })
  date_fin: string | null;

  @Column({ type: 'text', nullable: true })
  commentaire: string | null;

  @Column({ name: 'statut_lecture', length: 20, default: 'non_lu' })
  statut_lecture: string;

  @Column({ name: 'soumis_a', nullable: true })
  soumis_a: number | null;

  @ManyToOne(() => User, { onDelete: 'SET NULL' })
  @JoinColumn({ name: 'soumis_a' })
  destinataire: User | null;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
