import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  CreateDateColumn,
  UpdateDateColumn,
  ManyToOne,
  JoinColumn,
  Unique,
} from 'typeorm';
import { RhEmploye } from './rh-employe.entity';
import { RhCongeType } from './rh-conge-type.entity';

@Entity('rh_conge_balances')
@Unique(['id_employe', 'id_conge_type', 'annee'])
export class RhCongeBalance {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => RhEmploye, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'id_employe' })
  employe: RhEmploye;

  @Column()
  id_employe: number;

  @ManyToOne(() => RhCongeType, { onDelete: 'RESTRICT' })
  @JoinColumn({ name: 'id_conge_type' })
  type_conge: RhCongeType;

  @Column()
  id_conge_type: number;

  @Column({ type: 'int' })
  annee: number;

  @Column({ type: 'numeric', precision: 8, scale: 2, default: 0 })
  jours_acquis: number;

  @Column({ type: 'numeric', precision: 8, scale: 2, default: 0 })
  jours_pris: number;

  @Column({ type: 'numeric', precision: 8, scale: 2, default: 0 })
  jours_restants: number;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
