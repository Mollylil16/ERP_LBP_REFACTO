import {
  Entity, PrimaryGeneratedColumn, Column,
  CreateDateColumn, UpdateDateColumn, ManyToOne, JoinColumn,
} from 'typeorm';
import { RhEmploye } from './rh-employe.entity';
import { RhPaieRun } from './rh-paie-run.entity';

@Entity('rh_paie_lignes')
export class RhPaieLigne {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => RhPaieRun, (r) => r.lignes, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'id_run' })
  run: RhPaieRun;

  @Column()
  id_run: number;

  @ManyToOne(() => RhEmploye, { onDelete: 'RESTRICT' })
  @JoinColumn({ name: 'id_employe' })
  employe: RhEmploye;

  @Column()
  id_employe: number;

  // Rémunération
  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  salaire_base: number;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  prime_anciennete: number;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  prime_transport: number;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  heures_sup_montant: number;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  autres_primes: number;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  salaire_brut: number;

  // Déductions salariales
  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  cnps_retraite_salarial: number;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  cmu_salarial: number;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  its: number;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  cn: number;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  avances_deduites: number;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  absences_deduites: number;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  total_deductions_salariales: number;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  salaire_net: number;

  // Charges patronales
  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  cnps_retraite_patronal: number;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  cnps_at_patronal: number;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  cnps_famille_patronal: number;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  cmu_patronal: number;

  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  total_charges_patronales: number;

  // Coût total employeur
  @Column({ type: 'numeric', precision: 15, scale: 2, default: 0 })
  cout_total_employeur: number;

  // Alertes
  @Column({ type: 'boolean', default: false })
  alerte_smig: boolean;

  @Column({ type: 'jsonb', nullable: true })
  detail_calcul: Record<string, unknown> | null;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
