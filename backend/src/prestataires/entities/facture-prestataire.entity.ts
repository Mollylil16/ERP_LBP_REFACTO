import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  UpdateDateColumn,
  ManyToOne,
  JoinColumn,
  OneToMany,
} from 'typeorm';
import { Agence } from '../../agences/entities/agence.entity';
import { Prestataire } from './prestataire.entity';
import { ReglementPrestataire } from './reglement-prestataire.entity';

export type FacturePrestataireStatut =
  | 'A_PAYER'
  | 'BIENTOT_DU'
  | 'EN_RETARD'
  | 'PARTIEL'
  | 'PAYE'
  | 'ANNULEE';

@Entity('lbp_prestataires_factures')
export class FacturePrestataire {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => Agence, { nullable: false })
  @JoinColumn({ name: 'id_agence' })
  agence: Agence;

  /** Pays “opérationnel” (hérité de l’agence / sa région) — stocké pour filtres simples. */
  @Column({ type: 'varchar', length: 100, nullable: true })
  pays: string | null;

  @ManyToOne(() => Prestataire, { nullable: false })
  @JoinColumn({ name: 'prestataire_id' })
  prestataire: Prestataire;

  @Column({ type: 'date' })
  date_reception: string;

  @Column({ type: 'varchar', length: 100, nullable: true })
  numero_lta: string | null;

  @Column({ type: 'varchar', length: 100, nullable: true })
  numero_envoi: string | null;

  @Column({ length: 120 })
  numero_facture: string;

  @Column({ type: 'decimal', precision: 12, scale: 2, default: 0 })
  montant_total: number;

  @Column({ length: 10, default: 'XOF' })
  devise: string;

  @Column({ type: 'int', nullable: true })
  delai_reglement_jours: number | null;

  @Column({ type: 'date' })
  date_echeance: string;

  @Column({ length: 20, default: 'A_PAYER' })
  statut: FacturePrestataireStatut;

  @Column({ type: 'decimal', precision: 12, scale: 2, default: 0 })
  montant_regle: number;

  @Column({ type: 'decimal', precision: 12, scale: 2, default: 0 })
  reliquat: number;

  @Column({ type: 'text', nullable: true })
  note: string | null;

  @Column({ type: 'varchar', length: 100, nullable: true })
  created_by: string | null;

  @OneToMany(() => ReglementPrestataire, (r) => r.facture)
  reglements: ReglementPrestataire[];

  @CreateDateColumn({ type: 'timestamptz' })
  created_at: Date;

  @UpdateDateColumn({ type: 'timestamptz' })
  updated_at: Date;
}

