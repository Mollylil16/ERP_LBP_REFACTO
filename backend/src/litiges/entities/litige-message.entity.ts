import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  ManyToOne,
  JoinColumn,
} from 'typeorm';
import { Litige } from './litige.entity';
import { User } from '../../users/entities/user.entity';

export enum MessageType {
  MESSAGE = 'MESSAGE',
  CHANGEMENT_STATUT = 'CHANGEMENT_STATUT',
  ASSIGNATION = 'ASSIGNATION',
  ESCALADE = 'ESCALADE',
  RESOLUTION = 'RESOLUTION',
}

@Entity('lbp_litige_messages')
export class LitigeMessage {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => Litige, (litige) => litige.messages)
  @JoinColumn({ name: 'id_litige' })
  litige: Litige;

  @ManyToOne(() => User)
  @JoinColumn({ name: 'id_auteur' })
  auteur: User;

  @Column({
    type: 'enum',
    enum: MessageType,
    default: MessageType.MESSAGE,
  })
  type: MessageType;

  @Column({ type: 'text' })
  contenu: string;

  @Column({ type: 'json', nullable: true })
  pieces_jointes: {
    nom: string;
    url: string;
    type: string;
    taille: number;
  }[];

  @Column({ type: 'boolean', default: false })
  interne: boolean; // Message visible seulement par l'équipe

  @Column({ type: 'json', nullable: true })
  metadata: any; // Données additionnelles (ancien/nouveau statut, etc.)

  @CreateDateColumn()
  created_at: Date;
}
