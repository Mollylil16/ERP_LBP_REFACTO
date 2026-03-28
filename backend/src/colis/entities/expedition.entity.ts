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
import { Colis } from '../../colis/entities/colis.entity';
import { Agence } from '../../agences/entities/agence.entity';

export enum ExpeditionStatut {
  EN_PREPARATION = 'EN_PREPARATION',
  EN_TRANSIT = 'EN_TRANSIT',
  ARRIVE = 'ARRIVE',
  DEDOUANE = 'DEDOUANE',
  LIVRE = 'LIVRE',
}

export enum ExpeditionType {
  AERIEN = 'AERIEN',
  MARITIME = 'MARITIME',
}

@Entity('lbp_expeditions')
export class Expedition {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ unique: true })
  ref_expedition: string;

  @Column({ type: 'timestamp' })
  date_depart: Date;

  @Column({ type: 'timestamp', nullable: true })
  date_arrivee_prevue: Date;

  @Column({
    type: 'enum',
    enum: ExpeditionStatut,
    default: ExpeditionStatut.EN_PREPARATION,
  })
  statut: ExpeditionStatut;

  @Column({
    type: 'enum',
    enum: ExpeditionType,
  })
  type: ExpeditionType;

  @ManyToOne(() => Agence)
  @JoinColumn({ name: 'id_agence_depart' })
  agence_depart: Agence;

  @ManyToOne(() => Agence)
  @JoinColumn({ name: 'id_agence_destination' })
  agence_destination: Agence;

  @OneToMany(() => Colis, (colis) => colis.expedition)
  colis: Colis[];

  @Column({ nullable: true })
  numero_container: string;

  @Column({ nullable: true })
  compagnie_transport: string;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
