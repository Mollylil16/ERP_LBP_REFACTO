import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  UpdateDateColumn,
  DeleteDateColumn,
  ManyToOne,
  OneToMany,
  JoinColumn,
} from 'typeorm';
import { Colis } from '../../colis/entities/colis.entity';
import { Facture } from '../../factures/entities/facture.entity';
import { Client } from '../../clients/entities/client.entity';
import { User } from '../../users/entities/user.entity';
import { Agence } from '../../agences/entities/agence.entity';
import { LitigeMessage } from './litige-message.entity';

export enum LitigeType {
  COLIS_PERDU = 'COLIS_PERDU',
  COLIS_ENDOMMAGE = 'COLIS_ENDOMMAGE',
  RETARD_LIVRAISON = 'RETARD_LIVRAISON',
  MONTANT_INCORRECT = 'MONTANT_INCORRECT',
  SERVICE_CLIENT = 'SERVICE_CLIENT',
  AUTRE = 'AUTRE',
}

export enum LitigeStatut {
  OUVERT = 'OUVERT',
  EN_COURS = 'EN_COURS',
  RESOLU = 'RESOLU',
  FERME = 'FERME',
  REJETE = 'REJETE',
}

export enum LitigePriorite {
  BASSE = 'BASSE',
  NORMALE = 'NORMALE',
  HAUTE = 'HAUTE',
  CRITIQUE = 'CRITIQUE',
}

@Entity('lbp_litiges')
export class Litige {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ unique: true })
  num_litige: string; // Format: LIT-MMYY-XXX

  @Column({
    type: 'enum',
    enum: LitigeType,
    default: LitigeType.AUTRE,
  })
  type: LitigeType;

  @Column({
    type: 'enum',
    enum: LitigeStatut,
    default: LitigeStatut.OUVERT,
  })
  statut: LitigeStatut;

  @Column({
    type: 'enum',
    enum: LitigePriorite,
    default: LitigePriorite.NORMALE,
  })
  priorite: LitigePriorite;

  @Column()
  objet: string; // Titre/sujet du litige

  @Column({ type: 'text' })
  description: string; // Description détaillée

  // Relations avec les entités principales
  @ManyToOne(() => Colis, { nullable: true })
  @JoinColumn({ name: 'id_colis' })
  colis: Colis;

  @ManyToOne(() => Facture, { nullable: true })
  @JoinColumn({ name: 'id_facture' })
  facture: Facture;

  @ManyToOne(() => Client)
  @JoinColumn({ name: 'id_client' })
  client: Client;

  @ManyToOne(() => Agence)
  @JoinColumn({ name: 'id_agence' })
  agence: Agence;

  // Utilisateurs impliqués
  @ManyToOne(() => User)
  @JoinColumn({ name: 'id_createur' })
  createur: User; // Qui a créé le litige

  @ManyToOne(() => User, { nullable: true })
  @JoinColumn({ name: 'id_assigne' })
  assigne: User; // Responsable assigné

  // Dates importantes
  @Column({ type: 'timestamp', nullable: true })
  date_premiere_reponse: Date;

  @Column({ type: 'timestamp', nullable: true })
  date_resolution: Date;

  @Column({ type: 'timestamp', nullable: true })
  date_fermeture: Date;

  // Informations de contact (si différent du client)
  @Column({ nullable: true })
  contact_nom: string;

  @Column({ nullable: true })
  contact_email: string;

  @Column({ nullable: true })
  contact_telephone: string;

  // Résolution
  @Column({ type: 'text', nullable: true })
  resolution: string;

  @Column({ type: 'decimal', precision: 10, scale: 2, nullable: true })
  montant_compensation: number;

  // Métadonnées
  @Column({ type: 'json', nullable: true })
  metadata: any; // Données additionnelles flexibles

  @Column({ default: false })
  escalade: boolean; // Indique si le litige a été escaladé

  @Column({ type: 'int', default: 0 })
  nb_relances: number; // Nombre de relances

  // Messages liés
  @OneToMany(() => LitigeMessage, (message) => message.litige, {
    cascade: true,
  })
  messages: LitigeMessage[];

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;

  @DeleteDateColumn({ type: 'timestamp', nullable: true })
  deleted_at: Date | null;

  // Méthodes calculées
  getDureeTraitement(): number | null {
    if (!this.date_resolution) return null;
    return Math.floor(
      (this.date_resolution.getTime() - this.created_at.getTime()) /
        (1000 * 60 * 60 * 24),
    );
  }

  estEnRetard(): boolean {
    const now = new Date();
    const delaiMax =
      this.priorite === LitigePriorite.CRITIQUE
        ? 1
        : this.priorite === LitigePriorite.HAUTE
          ? 2
          : 5; // jours
    const diffJours = Math.floor(
      (now.getTime() - this.created_at.getTime()) / (1000 * 60 * 60 * 24),
    );
    return (
      diffJours > delaiMax &&
      this.statut !== LitigeStatut.RESOLU &&
      this.statut !== LitigeStatut.FERME
    );
  }
}
