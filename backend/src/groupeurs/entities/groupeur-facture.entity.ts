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
import { GroupeurExpedition } from './groupeur-expedition.entity';

export type FacturePaiementStatut =
  | 'en_attente'
  | 'partiel'
  | 'paye'
  | 'en_retard'
  | 'annule';

@Entity('lbp_groupeur_factures')
export class GroupeurFacture {
  @PrimaryGeneratedColumn('uuid')
  id: string;

  @Column({ type: 'uuid' })
  groupeur_id: string;

  @ManyToOne(() => Groupeur, (g) => g.factures, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'groupeur_id' })
  groupeur: Groupeur;

  @Column({ type: 'uuid', nullable: true })
  expedition_id: string | null;

  @ManyToOne(() => GroupeurExpedition, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'expedition_id' })
  expedition: GroupeurExpedition | null;

  @Column({ type: 'varchar', length: 30, unique: true })
  numero_facture: string;

  @Column({ type: 'varchar', length: 150 })
  client_nom: string;

  @Column({ type: 'varchar', length: 120, nullable: true })
  client_contact: string | null;

  @Column({ type: 'date', default: () => 'CURRENT_DATE' })
  date_emission: string;

  @Column({ type: 'date', nullable: true })
  date_echeance: string | null;

  @Column({ type: 'jsonb', default: () => "'[]'" })
  lignes: unknown[];

  @Column({ type: 'numeric', precision: 15, scale: 2 })
  sous_total: number;

  @Column({ type: 'numeric', precision: 5, scale: 2, default: 18 })
  tva_pct: number;

  @Column({ type: 'numeric', precision: 15, scale: 2, nullable: true })
  tva_montant: number | null;

  @Column({ type: 'numeric', precision: 15, scale: 2 })
  total_ttc: number;

  @Column({ type: 'varchar', length: 5, default: 'XOF' })
  devise: string;

  @Column({ type: 'varchar', length: 20, default: 'en_attente' })
  statut_paiement: FacturePaiementStatut;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  montant_recu: number;

  @Column({ type: 'date', nullable: true })
  date_paiement: string | null;

  @Column({ type: 'varchar', length: 30, nullable: true })
  mode_paiement: string | null;

  @Column({ type: 'text', nullable: true })
  notes: string | null;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
