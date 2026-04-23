import {
  Column,
  CreateDateColumn,
  Entity,
  JoinColumn,
  ManyToOne,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from 'typeorm';
import { Groupeur } from './groupeur.entity';

export type DevisStatut =
  | 'brouillon'
  | 'envoye'
  | 'accepte'
  | 'refuse'
  | 'expire';

@Entity('lbp_groupeur_devis')
export class GroupeurDevis {
  @PrimaryGeneratedColumn('uuid')
  id: string;

  @Column({ type: 'uuid' })
  groupeur_id: string;

  @ManyToOne(() => Groupeur, (g) => g.devis, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'groupeur_id' })
  groupeur: Groupeur;

  @Column({ type: 'varchar', length: 30, unique: true })
  numero: string;

  @Column({ type: 'varchar', length: 150 })
  client_nom: string;

  @Column({ type: 'varchar', length: 120, nullable: true })
  client_contact: string | null;

  @Column({ type: 'varchar', length: 100 })
  origine: string;

  @Column({ type: 'varchar', length: 100 })
  destination: string;

  @Column({ type: 'varchar', length: 20, nullable: true })
  mode_transport: string | null;

  @Column({ type: 'varchar', length: 10, nullable: true })
  type_chargement: string | null;

  @Column({ type: 'text', nullable: true })
  marchandise: string | null;

  @Column({ type: 'numeric', precision: 10, scale: 2, nullable: true })
  poids_kg: number | null;

  @Column({ type: 'numeric', precision: 10, scale: 2, nullable: true })
  volume_m3: number | null;

  @Column({ type: 'numeric', precision: 15, scale: 2, nullable: true })
  prix_propose: number | null;

  @Column({ type: 'varchar', length: 5, default: 'XOF' })
  devise: string;

  @Column({ type: 'int', default: 15 })
  validite_jours: number;

  @Column({ type: 'varchar', length: 20, default: 'brouillon' })
  statut: DevisStatut;

  @Column({ type: 'text', nullable: true })
  notes: string | null;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
