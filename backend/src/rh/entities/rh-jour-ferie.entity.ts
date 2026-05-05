import { Entity, PrimaryGeneratedColumn, Column, CreateDateColumn } from 'typeorm';

@Entity('rh_jours_feries')
export class RhJourFerie {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ type: 'date', unique: true })
  date: string;

  @Column({ length: 150 })
  libelle: string;

  @Column({ type: 'boolean', default: false })
  est_islamique: boolean; // Calculé annuellement

  @Column({ type: 'int' })
  annee: number;

  @CreateDateColumn()
  created_at: Date;
}
