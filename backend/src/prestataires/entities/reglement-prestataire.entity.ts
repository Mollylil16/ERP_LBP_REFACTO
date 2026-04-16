import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  UpdateDateColumn,
  ManyToOne,
  JoinColumn,
} from 'typeorm';
import { FacturePrestataire } from './facture-prestataire.entity';

export type ReglementMode =
  | 'ESPECE'
  | 'VIREMENT'
  | 'CHEQUE'
  | 'MOBILE'
  | 'AUTRE';

export type OrigineFonds = 'CAISSE_PRINCIPALE' | 'AGENCE';

/**
 * hub_retrait_status :
 * - NA: non applicable (pas un paiement espèces agence)
 * - A_RETIRER: à retirer à la caisse principale
 * - RETIRE: retrait caisse principale tracé
 */
export type HubRetraitStatus = 'NA' | 'A_RETIRER' | 'RETIRE';

/**
 * hub_retrait_approval_status :
 * - NA: non applicable
 * - PENDING: demande d’approbation en attente (ASSISTANT_DG)
 * - APPROVED: approuvé par DIRECTEUR
 * - REJECTED: rejeté
 */
export type HubRetraitApprovalStatus =
  | 'NA'
  | 'PENDING'
  | 'APPROVED'
  | 'REJECTED';

@Entity('lbp_prestataires_reglements')
export class ReglementPrestataire {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => FacturePrestataire, { nullable: false, onDelete: 'CASCADE' })
  @JoinColumn({ name: 'facture_id' })
  facture: FacturePrestataire;

  @Column({ type: 'date' })
  date_reglement: string;

  @Column({ length: 30 })
  mode_reglement: ReglementMode;

  @Column({ type: 'decimal', precision: 12, scale: 2, default: 0 })
  montant: number;

  @Column({ length: 150, nullable: true })
  reference: string | null;

  @Column({ type: 'text', nullable: true })
  note: string | null;

  @Column({ length: 30, default: 'CAISSE_PRINCIPALE' })
  origine_fonds: OrigineFonds;

  @Column({ length: 20, default: 'NA' })
  hub_retrait_status: HubRetraitStatus;

  @Column({ type: 'timestamptz', nullable: true })
  hub_retrait_marked_at: Date | null;

  @Column({ length: 100, nullable: true })
  hub_retrait_marked_by: string | null;

  @Column({ length: 20, default: 'NA' })
  hub_retrait_approval_status: HubRetraitApprovalStatus;

  @Column({ type: 'timestamptz', nullable: true })
  hub_retrait_approval_requested_at: Date | null;

  @Column({ length: 100, nullable: true })
  hub_retrait_approval_requested_by: string | null;

  @Column({ type: 'timestamptz', nullable: true })
  hub_retrait_approval_decided_at: Date | null;

  @Column({ length: 100, nullable: true })
  hub_retrait_approval_decided_by: string | null;

  @Column({ length: 100, nullable: true })
  created_by: string | null;

  @CreateDateColumn({ type: 'timestamptz' })
  created_at: Date;

  @UpdateDateColumn({ type: 'timestamptz' })
  updated_at: Date;
}

