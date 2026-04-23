import {
  Column,
  CreateDateColumn,
  Entity,
  JoinColumn,
  ManyToOne,
  OneToMany,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from 'typeorm';
import { User } from '../../users/entities/user.entity';
import { GroupeurDevis } from './groupeur-devis.entity';
import { GroupeurExpedition } from './groupeur-expedition.entity';
import { GroupeurFacture } from './groupeur-facture.entity';
import { GroupeurDocument } from './groupeur-document.entity';

export type GroupeurType = 'groupeur' | 'grossiste' | 'mixte';
export type GroupeurStatut = 'actif' | 'suspendu' | 'archive';

@Entity('lbp_groupeurs')
export class Groupeur {
  @PrimaryGeneratedColumn('uuid')
  id: string;

  @Column({ type: 'int', nullable: true })
  user_id: number | null;

  @ManyToOne(() => User, { nullable: true })
  @JoinColumn({ name: 'user_id' })
  user: User | null;

  @Column({ type: 'varchar', length: 20, unique: true })
  code: string;

  @Column({ type: 'varchar', length: 150 })
  raison_sociale: string;

  @Column({ type: 'varchar', length: 150, nullable: true })
  nom_commercial: string | null;

  @Column({ type: 'varchar', length: 20, default: 'groupeur' })
  type: GroupeurType;

  @Column({ type: 'varchar', length: 80, nullable: true })
  pays: string | null;

  @Column({ type: 'varchar', length: 80, nullable: true })
  ville: string | null;

  @Column({ type: 'text', nullable: true })
  adresse: string | null;

  @Column({ type: 'varchar', length: 30, nullable: true })
  telephone: string | null;

  @Column({ type: 'varchar', length: 120, nullable: true })
  email_contact: string | null;

  @Column({ type: 'varchar', length: 60, nullable: true })
  numero_registre: string | null;

  /** Stocké en JSON string (simple) : ['Chine→CI', ...] */
  @Column({ type: 'text', nullable: true })
  corridors: string | null;

  /** Stocké en JSON string (simple) : ['maritime', ...] */
  @Column({ type: 'text', nullable: true })
  modes_transport: string | null;

  @Column({ type: 'varchar', length: 20, default: 'actif' })
  statut: GroupeurStatut;

  @Column({ type: 'int', nullable: true })
  cree_par: number | null;

  @ManyToOne(() => User, { nullable: true })
  @JoinColumn({ name: 'cree_par' })
  createur: User | null;

  @OneToMany(() => GroupeurDevis, (d) => d.groupeur)
  devis: GroupeurDevis[];

  @OneToMany(() => GroupeurExpedition, (e) => e.groupeur)
  expeditions: GroupeurExpedition[];

  @OneToMany(() => GroupeurFacture, (f) => f.groupeur)
  factures: GroupeurFacture[];

  @OneToMany(() => GroupeurDocument, (d) => d.groupeur)
  documents: GroupeurDocument[];

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
