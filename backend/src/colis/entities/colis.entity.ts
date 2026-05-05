import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  UpdateDateColumn,
  ManyToOne,
  OneToMany,
  JoinColumn,
} from 'typeorm';
import { Client } from '../../clients/entities/client.entity';
import { Tarif } from '../../tarifs/entities/tarif.entity';
import { Agence } from '../../agences/entities/agence.entity';
import { Expedition } from './expedition.entity';

export enum ColisStatutSuivi {
  EMBALLE = 'EMBALLE',
  EXPEDIE = 'EXPEDIE',
  REC_BOBIGNY = 'REC_BOBIGNY',
  EN_LIVRAISON = 'EN_LIVRAISON',
  LIVRE = 'LIVRE',
}

@Entity('lbp_colis')
export class Colis {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ unique: true })
  ref_colis: string; // format généré: LBP-MMYY-XXX

  @Column()
  trafic_envoi: string; // ex: Groupage Aérien, Colis Rapide, etc.

  @Column({ default: 'groupage' })
  forme_envoi: string; // groupage, autres_envoi

  @Column({ nullable: true })
  mode_envoi: string; // ex: DHL, etc.

  /** ID du traceur GPS physique fixé sur ce colis/groupage (ex: SPOT-001) */
  @Column({ nullable: true, length: 100 })
  tracker_id: string;

  @Column({ type: 'boolean', default: false })
  livraison: boolean; // true = livraison à domicile demandée

  @Column({ type: 'date' })
  date_envoi: Date;

  @ManyToOne(() => Client, (client) => client.colis, { nullable: true })
  @JoinColumn({ name: 'id_client' })
  client: Client;

  // Receveur / Destinataire
  @Column()
  nom_dest: string;

  @Column({ nullable: true })
  lieu_dest: string;

  @Column({ nullable: true })
  tel_dest: string;

  @Column({ nullable: true })
  email_dest: string;

  // Récupérateur (si différent)
  @Column({ nullable: true })
  nom_recup: string;

  @Column({ nullable: true })
  adresse_recup: string;

  @Column({ nullable: true })
  tel_recup: string;

  @Column({ nullable: true })
  email_recup: string;

  @Column({ default: 0 })
  etat_validation: number; // 0: Brouillon, 1: Validé

  @Column({ nullable: true })
  code_user: string;

  @ManyToOne(() => Agence, (agence) => agence.colis, { nullable: true })
  @JoinColumn({ name: 'id_agence' })
  agence: Agence;

  @ManyToOne(() => Expedition, (expedition) => expedition.colis, {
    nullable: true,
  })
  @JoinColumn({ name: 'id_expedition' })
  expedition: Expedition;

  @OneToMany(() => Marchandise, (marchandise) => marchandise.colis, {
    cascade: true,
  })
  marchandises: Marchandise[];

  @Column({
    type: 'enum',
    enum: ColisStatutSuivi,
    default: ColisStatutSuivi.EMBALLE,
  })
  statut_suivi: ColisStatutSuivi;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}

@Entity('lbp_marchandises')
export class Marchandise {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  nom_marchandise: string;

  @Column({ type: 'int', default: 1 })
  nbre_colis: number;

  @Column({ type: 'int', default: 1 })
  nbre_articles: number;

  @Column({ type: 'decimal', precision: 10, scale: 2, default: 0 })
  poids_total: number;

  @Column({ type: 'decimal', precision: 10, scale: 2, default: 0 })
  prix_unit: number;

  @Column({ type: 'decimal', precision: 10, scale: 2, default: 0 })
  prix_emballage: number;

  @Column({ type: 'decimal', precision: 10, scale: 2, default: 0 })
  prix_assurance: number;

  @Column({ type: 'decimal', precision: 10, scale: 2, default: 0 })
  prix_agence: number;

  @ManyToOne(() => Tarif, (tarif) => tarif.marchandises, { nullable: true })
  @JoinColumn({ name: 'id_tarif' })
  tarif: Tarif;

  @Column({ type: 'jsonb', nullable: true })
  type_emballage: string[] | null;

  @Column({ type: 'int', default: 1 })
  nbre_emballage: number;

  @Column({ type: 'decimal', precision: 10, scale: 2, default: 0 })
  cout_reel: number; // Snapshot du coût transporteur au moment de la création

  @Column({ type: 'decimal', precision: 10, scale: 2, default: 0 })
  charges_reelles: number; // Snapshot des charges fixes au moment de la création

  @ManyToOne(() => Colis, (colis) => colis.marchandises)
  @JoinColumn({ name: 'id_colis' })
  colis: Colis;
}
