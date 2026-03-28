import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
} from 'typeorm';

@Entity('lbp_caisse_audit_logs')
export class CaisseAuditLog {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ type: 'varchar', length: 100 })
  action: string;

  @Column({ type: 'int', nullable: true })
  mouvement_id: number | null;

  @Column({ type: 'int', nullable: true })
  session_id: number | null;

  @Column({ type: 'varchar', length: 100, nullable: true })
  actor_username: string | null;

  @Column({ type: 'jsonb', nullable: true })
  before_data: any;

  @Column({ type: 'jsonb', nullable: true })
  after_data: any;

  @Column({ type: 'varchar', length: 45, nullable: true })
  ip_address: string | null;

  @CreateDateColumn()
  created_at: Date;
}
