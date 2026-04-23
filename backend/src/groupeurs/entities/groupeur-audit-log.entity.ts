import {
  Column,
  CreateDateColumn,
  Entity,
  JoinColumn,
  ManyToOne,
  PrimaryGeneratedColumn,
} from 'typeorm';
import { User } from '../../users/entities/user.entity';

@Entity('lbp_groupeur_audit_log')
export class GroupeurAuditLog {
  @PrimaryGeneratedColumn('uuid')
  id: string;

  @Column({ type: 'int', nullable: true })
  acteur_id: number | null;

  @ManyToOne(() => User, { nullable: true })
  @JoinColumn({ name: 'acteur_id' })
  acteur: User | null;

  @Column({ type: 'varchar', length: 50, nullable: true })
  acteur_role: string | null;

  @Column({ type: 'varchar', length: 80 })
  action: string;

  @Column({ type: 'varchar', length: 50, nullable: true })
  entite: string | null;

  @Column({ type: 'uuid', nullable: true })
  entite_id: string | null;

  @Column({ type: 'jsonb', nullable: true })
  detail: Record<string, unknown> | null;

  @Column({ type: 'varchar', length: 45, nullable: true })
  ip_address: string | null;

  @CreateDateColumn()
  created_at: Date;
}
