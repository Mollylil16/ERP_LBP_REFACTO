import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  UpdateDateColumn,
  OneToMany,
  ManyToOne,
  JoinColumn,
} from 'typeorm';
import { User } from '../../users/entities/user.entity';
import { Colis } from '../../colis/entities/colis.entity';

@Entity('agences')
export class Agence {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ unique: true, length: 20 })
  code: string;

  @Column({ length: 100 })
  nom: string;

  @Column({ length: 50, default: "Côte d''Ivoire" })
  pays: string;

  @Column({ length: 50 })
  ville: string;

  @Column({ length: 255, nullable: true })
  adresse: string;

  @Column({ length: 20, nullable: true })
  telephone: string;

  @Column({ length: 100, nullable: true })
  email: string;

  @Column({ length: 100, nullable: true })
  nom_responsable: string;

  @Column({ length: 20, nullable: true })
  tel_responsable: string;

  /**
   * Chef d'agence (référence utilisateur).
   * Utilisé pour restreindre la sélection d'agence et pour les workflows (points journaliers).
   */
  @ManyToOne(() => User, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_chef_agence' })
  chefAgence: User | null;

  @Column({ default: true })
  actif: boolean;

  @Column({ length: 10, default: 'FCFA' })
  devise: string; // Ajouté pour le support multi-devises

  // Coordonnées GPS (remplies automatiquement via Nominatim)
  @Column({ type: 'double precision', nullable: true })
  latitude: number | null;

  @Column({ type: 'double precision', nullable: true })
  longitude: number | null;

  @Column({ type: 'varchar', length: 255, nullable: true })
  place_id: string | null; // ID OpenStreetMap

  @OneToMany(() => User, (user) => user.agence)
  users: User[];

  @OneToMany(() => Colis, (colis) => colis.agence)
  colis: Colis[];

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
