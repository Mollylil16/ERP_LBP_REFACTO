import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  UpdateDateColumn,
} from 'typeorm';
import { MouvementType } from './mouvement-caisse.entity';

export enum WorkflowStatus {
  DRAFT = 'DRAFT',
  SUBMITTED = 'SUBMITTED',
  VALIDATED = 'VALIDATED',
  REJECTED = 'REJECTED',
}

@Entity('lbp_caisse_mouvement_workflows')
export class CaisseMouvementWorkflow {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ type: 'int', unique: true })
  mouvement_id: number;

  @Column({ type: 'enum', enum: MouvementType })
  mouvement_type: MouvementType;

  @Column({ type: 'enum', enum: WorkflowStatus, default: WorkflowStatus.DRAFT })
  status: WorkflowStatus;

  @Column({ type: 'int', default: 1 })
  validation_level_required: number;

  @Column({ type: 'int', default: 0 })
  validation_level_current: number;

  @Column({ type: 'varchar', length: 100, nullable: true })
  submitted_by: string | null;

  @Column({ type: 'timestamp', nullable: true })
  submitted_at: Date | null;

  @Column({ type: 'varchar', length: 100, nullable: true })
  approved_by: string | null;

  @Column({ type: 'timestamp', nullable: true })
  approved_at: Date | null;

  @Column({ type: 'text', nullable: true })
  rejection_reason: string | null;

  @Column({ type: 'varchar', length: 500, nullable: true })
  justificatif_url: string | null;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
