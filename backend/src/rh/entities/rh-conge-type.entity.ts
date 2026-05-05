import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  CreateDateColumn,
  UpdateDateColumn,
} from 'typeorm';

@Entity('rh_conge_types')
export class RhCongeType {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ unique: true, length: 40 })
  code: string;

  @Column({ length: 100 })
  libelle: string;

  // Nombre de jours accordés par an (0 = illimité/sur justificatif)
  @Column({ type: 'int', default: 0 })
  jours_par_an: number;

  @Column({ type: 'boolean', default: true })
  est_paye: boolean;

  // Doit être justifié (maladie, maternité, etc.)
  @Column({ type: 'boolean', default: false })
  necessite_justificatif: boolean;

  @Column({ type: 'text', nullable: true })
  description: string | null;

  @Column({ type: 'boolean', default: true })
  est_actif: boolean;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
