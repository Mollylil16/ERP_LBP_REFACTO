import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  UpdateDateColumn,
  OneToMany,
} from 'typeorm';
import { FacturePrestataire } from './facture-prestataire.entity';

@Entity('lbp_prestataires')
export class Prestataire {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ length: 200 })
  nom: string;

  /** Pays “référence” (optionnel) — utile pour filtrer par zone. */
  @Column({ type: 'varchar', length: 100, nullable: true })
  pays: string | null;

  @Column({ default: true })
  actif: boolean;

  @Column({ type: 'varchar', length: 150, nullable: true })
  contact_nom: string | null;

  @Column({ type: 'varchar', length: 50, nullable: true })
  contact_tel: string | null;

  @Column({ type: 'varchar', length: 200, nullable: true })
  contact_email: string | null;

  @OneToMany(() => FacturePrestataire, (f) => f.prestataire)
  factures: FacturePrestataire[];

  @CreateDateColumn({ type: 'timestamptz' })
  created_at: Date;

  @UpdateDateColumn({ type: 'timestamptz' })
  updated_at: Date;
}

