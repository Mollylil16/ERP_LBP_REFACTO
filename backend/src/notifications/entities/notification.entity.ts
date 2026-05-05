import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  UpdateDateColumn,
  ManyToOne,
  JoinColumn,
} from 'typeorm';
import { User } from '../../users/entities/user.entity';

export enum NotificationType {
  INFO = 'INFO',
  SUCCESS = 'SUCCESS',
  WARNING = 'WARNING',
  ERROR = 'ERROR',
}

export enum NotificationCategory {
  CAISSE = 'CAISSE',
  FACTURATION = 'FACTURATION',
  COLIS = 'COLIS',
  PAIEMENT = 'PAIEMENT',
  SYSTEM = 'SYSTEM',
}

@Entity('lbp_notifications')
export class Notification {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  title: string;

  @Column({ type: 'text' })
  message: string;

  @Column({ type: 'text', nullable: true })
  problem: string;

  @Column({ type: 'text', nullable: true })
  solution: string;

  @Column({
    type: 'enum',
    enum: NotificationType,
    default: NotificationType.INFO,
  })
  type: NotificationType;

  @Column({
    type: 'enum',
    enum: NotificationCategory,
    default: NotificationCategory.SYSTEM,
  })
  category: NotificationCategory;

  @Column({ default: false })
  read: boolean;

  @Column({ type: 'jsonb', nullable: true })
  audit_data: any;

  @Column({ nullable: true })
  action_url: string;

  /** Destinataire : si null, notification historique « globale » (comportement legacy). */
  @ManyToOne(() => User, { nullable: true, onDelete: 'CASCADE' })
  @JoinColumn({ name: 'user_id' })
  user: User | null;

  /** Agence concernée : si renseignée, seuls les utilisateurs de cette agence voient la notif (user_id NULL). */
  @Column({ type: 'int', nullable: true, name: 'id_agence' })
  id_agence: number | null;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
