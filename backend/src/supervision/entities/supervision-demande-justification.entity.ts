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

@Entity('lbp_supervision_demandes_justification')
export class SupervisionDemandeJustification {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ name: 'id_demandeur' })
  id_demandeur: number;

  @ManyToOne(() => User, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'id_demandeur' })
  demandeur: User;

  @Column({ name: 'id_destinataire', nullable: true })
  id_destinataire: number | null;

  @ManyToOne(() => User, { onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_destinataire' })
  destinataire: User | null;

  @Column({ name: 'id_agence', nullable: true })
  id_agence: number | null;

  @ManyToOne(() => Agence, { onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_agence' })
  agence: Agence | null;

  @Column({ name: 'id_operation', type: 'varchar', length: 64, nullable: true })
  id_operation: string | null;

  @Column({ type: 'text' })
  motif: string;

  @Column({ length: 20, default: 'en_attente' })
  statut: string;

  @Column({ type: 'text', nullable: true })
  reponse: string | null;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
