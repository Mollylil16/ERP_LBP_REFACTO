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
import { GroupeurDevis } from './groupeur-devis.entity';

export type ExpeditionStatut =
  | 'en_preparation'
  | 'merchandise_recue'
  | 'en_transit'
  | 'arrive_port'
  | 'en_dedouanement'
  | 'livre'
  | 'litige';

@Entity('lbp_groupeur_expeditions')
export class GroupeurExpedition {
  @PrimaryGeneratedColumn('uuid')
  id: string;

  @Column({ type: 'uuid' })
  groupeur_id: string;

  @ManyToOne(() => Groupeur, (g) => g.expeditions, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'groupeur_id' })
  groupeur: Groupeur;

  @Column({ type: 'uuid', nullable: true })
  devis_id: string | null;

  @ManyToOne(() => GroupeurDevis, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'devis_id' })
  devis: GroupeurDevis | null;

  @Column({ type: 'varchar', length: 30, unique: true })
  numero_expedition: string;

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

  @Column({ type: 'varchar', length: 30, nullable: true })
  numero_conteneur: string | null;

  @Column({ type: 'varchar', length: 10, nullable: true })
  taille_conteneur: string | null;

  @Column({ type: 'date', nullable: true })
  date_depart_prevu: string | null;

  @Column({ type: 'date', nullable: true })
  date_arrivee_prevu: string | null;

  @Column({ type: 'date', nullable: true })
  date_depart_reel: string | null;

  @Column({ type: 'date', nullable: true })
  date_arrivee_reelle: string | null;

  @Column({ type: 'varchar', length: 100, nullable: true })
  armateur: string | null;

  @Column({ type: 'varchar', length: 60, nullable: true })
  numero_bl_master: string | null;

  @Column({ type: 'varchar', length: 60, nullable: true })
  numero_bl_house: string | null;

  @Column({ type: 'varchar', length: 30, default: 'en_preparation' })
  statut: ExpeditionStatut;

  @Column({ type: 'text', nullable: true })
  notes: string | null;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
