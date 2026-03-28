import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  CreateDateColumn,
  Index,
} from 'typeorm';

export type CallCenterDirection = 'in' | 'out';
export type CallCenterChannel = 'sms' | 'whatsapp';

@Entity('callcenter_messages')
@Index(['conversation_id', 'created_at'])
@Index(['provider', 'provider_message_id'], { unique: false })
export class CallCenterMessage {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ type: 'int' })
  conversation_id: number;

  @Column({ type: 'varchar', length: 20 })
  channel: CallCenterChannel;

  @Column({ type: 'varchar', length: 5 })
  direction: CallCenterDirection;

  @Column({ type: 'varchar', length: 40 })
  from_phone: string;

  @Column({ type: 'varchar', length: 40 })
  to_phone: string;

  @Column({ type: 'text' })
  message: string;

  @Column({ type: 'varchar', length: 50, nullable: true })
  provider: string | null;

  @Column({ type: 'varchar', length: 100, nullable: true })
  provider_message_id: string | null;

  @Column({ type: 'jsonb', nullable: true })
  raw_payload: any | null;

  @CreateDateColumn()
  created_at: Date;
}
