import {
  Column,
  CreateDateColumn,
  Entity,
  JoinColumn,
  ManyToOne,
  PrimaryGeneratedColumn,
} from 'typeorm';
import { Groupeur } from './groupeur.entity';
import { GroupeurExpedition } from './groupeur-expedition.entity';
import { User } from '../../users/entities/user.entity';

export type DocumentStatut = 'valide' | 'expire' | 'annule';

@Entity('lbp_groupeur_documents')
export class GroupeurDocument {
  @PrimaryGeneratedColumn('uuid')
  id: string;

  @Column({ type: 'uuid' })
  groupeur_id: string;

  @ManyToOne(() => Groupeur, (g) => g.documents, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'groupeur_id' })
  groupeur: Groupeur;

  @Column({ type: 'uuid', nullable: true })
  expedition_id: string | null;

  @ManyToOne(() => GroupeurExpedition, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'expedition_id' })
  expedition: GroupeurExpedition | null;

  @Column({ type: 'varchar', length: 50 })
  type_document: string;

  @Column({ type: 'varchar', length: 200 })
  nom_fichier: string;

  @Column({ type: 'text' })
  url_fichier: string;

  @Column({ type: 'int', nullable: true })
  taille_octets: number | null;

  @Column({ type: 'varchar', length: 20, default: 'valide' })
  statut: DocumentStatut;

  @Column({ type: 'int', nullable: true })
  uploaded_par: number | null;

  @ManyToOne(() => User, { nullable: true })
  @JoinColumn({ name: 'uploaded_par' })
  uploader: User | null;

  @CreateDateColumn()
  created_at: Date;
}
