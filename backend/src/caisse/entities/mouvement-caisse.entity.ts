import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  ManyToOne,
  JoinColumn,
} from 'typeorm';
import { Caisse } from './caisse.entity';

export enum MouvementType {
  APPRO = 'APPRO',
  DECAISSEMENT = 'DECAISSEMENT',
  ENTREE_CHEQUE = 'ENTREE_CHEQUE',
  ENTREE_ESPECE = 'ENTREE_ESPECE',
  ENTREE_VIREMENT = 'ENTREE_VIREMENT',
}

@Entity('lbp_mouvements_caisse')
export class MouvementCaisse {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({
    type: 'enum',
    enum: MouvementType,
  })
  type: MouvementType;

  @Column()
  libelle: string;

  @Column({ type: 'decimal', precision: 12, scale: 2 })
  montant: number;

  @Column({ type: 'date' })
  date_mouvement: Date;

  @Column({ type: 'varchar', nullable: true })
  mode_retrait: string; // ESPECE, WAVE, ORANGE_MONEY, VIREMENT_BANCAIRE

  @ManyToOne(() => Caisse, (caisse) => caisse.mouvements)
  @JoinColumn({ name: 'id_caisse' })
  caisse: Caisse;

  @Column({ nullable: true })
  code_user: string;

  @Column({ type: 'jsonb', nullable: true })
  details: any; // Pour stocker dossier_num, type_paiement, etc.

  @CreateDateColumn()
  created_at: Date;
}
