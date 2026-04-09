import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  CreateDateColumn,
  ManyToOne,
  JoinColumn,
  Index,
} from 'typeorm';
import { User } from '../../users/entities/user.entity';

@Entity('auth_refresh_tokens')
export class RefreshToken {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => User, { onDelete: 'CASCADE', eager: false })
  @JoinColumn({ name: 'user_id' })
  user: User;

  @Index()
  @Column({ type: 'varchar', length: 64, unique: true })
  token_id: string; // identifiant public (jti) associé au token opaque

  @Column({ type: 'text' })
  token_hash: string; // SHA-256 du token opaque

  @Index()
  @Column({ type: 'timestamptz' })
  expires_at: Date;

  @Index()
  @Column({ type: 'timestamptz', nullable: true })
  revoked_at: Date | null;

  @Column({ type: 'varchar', length: 45, nullable: true })
  created_ip: string | null;

  @Column({ type: 'varchar', length: 512, nullable: true })
  created_user_agent: string | null;

  @Column({ type: 'varchar', length: 45, nullable: true })
  rotated_from_ip: string | null;

  @Column({ type: 'varchar', length: 512, nullable: true })
  rotated_from_user_agent: string | null;

  @CreateDateColumn({ type: 'timestamptz' })
  created_at: Date;
}

