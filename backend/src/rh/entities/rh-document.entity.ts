import {
  Entity, PrimaryGeneratedColumn, Column,
  CreateDateColumn, ManyToOne, JoinColumn,
} from 'typeorm';
import { RhEmploye } from './rh-employe.entity';
import { User } from '../../users/entities/user.entity';

export enum TypeDocumentRh {
  CONTRAT = 'contrat',
  AVENANT = 'avenant',
  CNI = 'cni',
  DIPLOME = 'diplome',
  CERTIFICAT = 'certificat',
  ATTESTATION = 'attestation',
  FICHE_PAIE = 'fiche_paie',
  VISITE_MEDICALE = 'visite_medicale',
  PHOTO = 'photo',
  AUTRE = 'autre',
}

@Entity('rh_documents')
export class RhDocument {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => RhEmploye, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'id_employe' })
  employe: RhEmploye;

  @Column()
  id_employe: number;

  @Column({ type: 'enum', enum: TypeDocumentRh, default: TypeDocumentRh.AUTRE })
  type: TypeDocumentRh;

  @Column({ length: 200 })
  nom_fichier: string;

  @Column({ type: 'text' })
  url_fichier: string;

  @Column({ type: 'bigint', nullable: true })
  taille_octets: number | null;

  @Column({ type: "varchar", length: 20, nullable: true })
  mime_type: string | null;

  @Column({ type: 'text', nullable: true })
  description: string | null;

  @Column({ type: 'date', nullable: true })
  date_expiration: string | null;

  @ManyToOne(() => User, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_uploader' })
  uploader: User | null;

  @Column({ nullable: true })
  id_uploader: number | null;

  @CreateDateColumn()
  created_at: Date;
}
