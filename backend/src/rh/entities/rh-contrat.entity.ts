import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  CreateDateColumn,
  UpdateDateColumn,
  ManyToOne,
  JoinColumn,
} from 'typeorm';
import { RhEmploye } from './rh-employe.entity';
import { TypeContrat } from './rh-enums';

export enum StatutContrat {
  ACTIF = 'actif',
  TERMINE = 'termine',
  RESILIE = 'resilie',
  ESSAI = 'essai',
}

@Entity('rh_contrats')
export class RhContrat {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => RhEmploye, (e) => e.contrats, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'id_employe' })
  employe: RhEmploye;

  @Column()
  id_employe: number;

  @Column({ type: 'enum', enum: TypeContrat })
  type_contrat: TypeContrat;

  @Column({ type: 'date' })
  date_debut: string;

  @Column({ type: 'date', nullable: true })
  date_fin: string | null;

  // Période d'essai (selon catégorie / Art. 14 CDT)
  @Column({ type: 'date', nullable: true })
  periode_essai_debut: string | null;

  @Column({ type: 'date', nullable: true })
  periode_essai_fin: string | null;

  @Column({ type: "varchar", length: 100, nullable: true })
  intitule_poste: string | null;

  @Column({ type: 'numeric', precision: 15, scale: 2, nullable: true })
  salaire_base: number | null;

  @Column({ type: 'enum', enum: StatutContrat, default: StatutContrat.ACTIF })
  statut: StatutContrat;

  @Column({ type: 'text', nullable: true })
  motif_fin: string | null;

  @Column({ type: 'text', nullable: true })
  notes: string | null;

  // Alerte envoyée (J-30, J-15, J-7) — pour éviter les doublons
  @Column({ type: 'int', default: 0 })
  alerte_envoyee_jours: number;

  // ── Signature électronique (CDC §6.3) ─────────────────────────────────
  @Column({ type: 'timestamptz', nullable: true })
  signe_salarie_at: Date | null;

  @Column({ type: 'timestamptz', nullable: true })
  signe_rh_at: Date | null;

  @Column({ type: 'int', nullable: true })
  signe_rh_user_id: number | null;

  @Column({ type: 'varchar', length: 20, default: 'PHYSIQUE' })
  signature_mode: string;

  @Column({ type: 'text', nullable: true })
  document_signe_url: string | null;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
