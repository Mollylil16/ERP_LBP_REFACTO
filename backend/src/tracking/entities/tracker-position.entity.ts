import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  CreateDateColumn,
  ManyToOne,
  JoinColumn,
} from 'typeorm';
import { Colis } from '../../colis/entities/colis.entity';

@Entity('lbp_tracker_positions')
export class TrackerPosition {
  @PrimaryGeneratedColumn()
  id: number;

  /** ID unique du traceur physique (ex: SPOT-001, TIVE-ABC123) */
  @Column({ length: 100 })
  tracker_id: string;

  /** Référence du colis associé (ex: LBP-0226-007) */
  @Column({ nullable: true, length: 50 })
  ref_colis: string;

  @Column({ type: 'decimal', precision: 10, scale: 6 })
  latitude: number;

  @Column({ type: 'decimal', precision: 10, scale: 6 })
  longitude: number;

  /** Vitesse en km/h si fournie par le traceur */
  @Column({ type: 'decimal', precision: 6, scale: 2, nullable: true })
  vitesse: number;

  /** Altitude en mètres si fournie */
  @Column({ type: 'decimal', precision: 8, scale: 2, nullable: true })
  altitude: number;

  /** Niveau batterie du traceur (0-100) */
  @Column({ type: 'int', nullable: true })
  batterie: number;

  /** Statut transmis par le traceur */
  @Column({ nullable: true, length: 50 })
  statut: string;

  /** Date/heure de la mesure GPS (peut différer de created_at si retardée) */
  @Column({ type: 'timestamptz', nullable: true })
  timestamp_gps: Date;

  @CreateDateColumn()
  created_at: Date;
}
