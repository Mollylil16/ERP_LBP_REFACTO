import {
  Entity, PrimaryGeneratedColumn, Column,
  CreateDateColumn, ManyToOne, JoinColumn,
} from 'typeorm';
import { RhEmploye } from './rh-employe.entity';
import { User } from '../../users/entities/user.entity';

@Entity('rh_historique_postes')
export class RhHistoriquePoste {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => RhEmploye, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'id_employe' })
  employe: RhEmploye;

  @Column()
  id_employe: number;

  @Column({ type: "varchar", length: 100, nullable: true })
  ancien_poste: string | null;

  @Column({ type: "varchar", length: 100, nullable: true })
  nouveau_poste: string | null;

  @Column({ type: "varchar", length: 100, nullable: true })
  ancien_departement: string | null;

  @Column({ type: "varchar", length: 100, nullable: true })
  nouveau_departement: string | null;

  @Column({ type: "varchar", length: 60, nullable: true })
  ancienne_categorie: string | null;

  @Column({ type: "varchar", length: 60, nullable: true })
  nouvelle_categorie: string | null;

  @Column({ type: 'numeric', precision: 15, scale: 2, nullable: true })
  ancien_salaire: number | null;

  @Column({ type: 'numeric', precision: 15, scale: 2, nullable: true })
  nouveau_salaire: number | null;

  @Column({ type: 'date' })
  date_effet: string;

  @Column({ type: 'text', nullable: true })
  motif: string | null;

  @ManyToOne(() => User, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_auteur' })
  auteur: User | null;

  @Column({ nullable: true })
  id_auteur: number | null;

  @CreateDateColumn()
  created_at: Date;
}
