import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  CreateDateColumn,
  UpdateDateColumn,
  Index,
} from 'typeorm';

export type CallCenterChannel = 'sms' | 'whatsapp';

@Entity('callcenter_conversations')
@Index(['channel', 'customer_phone'], { unique: true })
export class CallCenterConversation {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ type: 'varchar', length: 20 })
  channel: CallCenterChannel;

  @Column({ type: 'varchar', length: 40 })
  customer_phone: string;

  @Column({ type: 'varchar', length: 40, nullable: true })
  callcenter_phone: string | null;

  @Column({ type: 'int', nullable: true })
  client_id: number | null;

  @Column({ type: 'int', nullable: true })
  last_facture_id: number | null;

  @Column({ type: 'int', nullable: true })
  last_litige_id: number | null;

  @Column({ type: 'int', default: 0 })
  unread_count: number;

  @Column({ type: 'timestamp', nullable: true })
  last_message_at: Date | null;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
