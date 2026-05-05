import {
  Entity, PrimaryGeneratedColumn, Column,
  CreateDateColumn, UpdateDateColumn, ManyToOne, JoinColumn,
} from 'typeorm';
import { RhEmploye } from './rh-employe.entity';
import { User } from '../../users/entities/user.entity';

export enum TypeFormation {
  PRESENTIEL = 'presentiel',
  DISTANCIEL = 'distanciel',
  ELEARNING = 'elearning',
  MIXTE = 'mixte',
}

export enum StatutInscription {
  EN_ATTENTE = 'en_attente',
  CONFIRME = 'confirme',
  TERMINE = 'termine',
  ANNULE = 'annule',
}

@Entity('rh_formations')
export class RhFormation {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ length: 200 })
  titre: string;

  @Column({ type: 'text', nullable: true })
  description: string | null;

  @Column({ type: 'enum', enum: TypeFormation, default: TypeFormation.PRESENTIEL })
  type: TypeFormation;

  @Column({ type: "varchar", length: 150, nullable: true })
  organisme: string | null;

  @Column({ type: 'date', nullable: true })
  date_debut: string | null;

  @Column({ type: 'date', nullable: true })
  date_fin: string | null;

  @Column({ type: 'int', nullable: true })
  duree_heures: number | null;

  @Column({ type: 'numeric', precision: 15, scale: 2, nullable: true })
  cout: number | null;

  @Column({ type: 'int', default: 0 })
  places_max: number;

  @Column({ type: 'boolean', default: true })
  est_actif: boolean;

  @Column({ type: 'int', nullable: true })
  annee_plan: number | null;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}

@Entity('rh_inscriptions_formation')
export class RhInscriptionFormation {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => RhFormation, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'id_formation' })
  formation: RhFormation;

  @Column()
  id_formation: number;

  @ManyToOne(() => RhEmploye, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'id_employe' })
  employe: RhEmploye;

  @Column()
  id_employe: number;

  @Column({ type: 'enum', enum: StatutInscription, default: StatutInscription.EN_ATTENTE })
  statut: StatutInscription;

  @Column({ type: 'numeric', precision: 5, scale: 2, nullable: true })
  note_satisfaction: number | null;

  @Column({ type: 'text', nullable: true })
  commentaire: string | null;

  @ManyToOne(() => User, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_validateur_manager' })
  validateur_manager: User | null;

  @Column({ nullable: true })
  id_validateur_manager: number | null;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
