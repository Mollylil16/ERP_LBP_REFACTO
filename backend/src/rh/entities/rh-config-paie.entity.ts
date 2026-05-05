import { Entity, PrimaryGeneratedColumn, Column, UpdateDateColumn } from 'typeorm';

@Entity('rh_config_paie')
export class RhConfigPaie {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ unique: true, length: 10 })
  annee_mois: string; // '2026-01' — un enregistrement par mois ou 'DEFAULT'

  // SMIG mensuel (FCFA)
  @Column({ type: 'numeric', precision: 15, scale: 2, default: 75000 })
  smig_mensuel: number;

  // CNPS Retraite
  @Column({ type: 'numeric', precision: 5, scale: 4, default: 0.032 })
  cnps_retraite_salarial: number; // 3,2%

  @Column({ type: 'numeric', precision: 5, scale: 4, default: 0.077 })
  cnps_retraite_patronal: number; // 7,7%

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 1647315 })
  cnps_retraite_plafond_annuel: number;

  // CNPS AT (accident du travail) — patronal uniquement
  @Column({ type: 'numeric', precision: 5, scale: 4, default: 0.02 })
  cnps_at_patronal: number; // 2% à 5%

  // CNPS Famille — patronal uniquement
  @Column({ type: 'numeric', precision: 5, scale: 4, default: 0.0575 })
  cnps_famille_patronal: number; // 5,75%

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 70000 })
  cnps_famille_plafond_mensuel: number;

  // CMU
  @Column({ type: 'numeric', precision: 5, scale: 4, default: 0.02 })
  cmu_salarial: number; // 2%

  @Column({ type: 'numeric', precision: 5, scale: 4, default: 0.02 })
  cmu_patronal: number; // 2%

  // Contribution Nationale
  @Column({ type: 'numeric', precision: 5, scale: 4, default: 0.015 })
  cn_taux: number; // 1,5%

  // Tranches ITS (JSON : [{min, max, taux}])
  @Column({ type: 'jsonb', nullable: true })
  its_tranches: Array<{ min: number; max: number | null; taux: number }> | null;

  @UpdateDateColumn()
  updated_at: Date;
}
