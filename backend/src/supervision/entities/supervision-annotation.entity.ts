import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  CreateDateColumn,
  UpdateDateColumn,
  ManyToOne,
  JoinColumn,
} from 'typeorm';
import { User } from '../../users/entities/user.entity';

@Entity('lbp_supervision_annotations')
export class SupervisionAnnotation {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ name: 'id_auteur' })
  id_auteur: number;

  @ManyToOne(() => User, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'id_auteur' })
  auteur: User;

  @Column({ length: 40, default: 'operation' })
  cible: string;

  @Column({ name: 'cible_id', length: 64 })
  cible_id: string;

  @Column({ type: 'text' })
  contenu: string;

  @Column({ length: 20, default: 'direction' })
  visibilite: string;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
