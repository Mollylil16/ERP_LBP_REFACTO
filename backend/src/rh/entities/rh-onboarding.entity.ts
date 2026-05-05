import {
  Entity, PrimaryGeneratedColumn, Column,
  CreateDateColumn, UpdateDateColumn, ManyToOne, JoinColumn,
} from 'typeorm';
import { RhEmploye } from './rh-employe.entity';
import { User } from '../../users/entities/user.entity';

@Entity('rh_onboarding_checklists')
export class RhOnboardingChecklist {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => RhEmploye, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'id_employe' })
  employe: RhEmploye;

  @Column()
  id_employe: number;

  @Column({ type: 'date', nullable: true })
  date_prise_poste: string | null;

  @Column({ type: 'date', nullable: true })
  date_visite_medicale: string | null;

  @Column({ type: 'boolean', default: false })
  visite_medicale_faite: boolean;

  @Column({ type: 'boolean', default: false })
  materiel_fourni: boolean;

  @Column({ type: 'boolean', default: false })
  acces_systemes: boolean;

  @Column({ type: 'boolean', default: false })
  badge_remis: boolean;

  @Column({ type: 'boolean', default: false })
  livret_accueil: boolean;

  @Column({ type: 'boolean', default: false })
  formation_securite: boolean;

  @Column({ type: 'jsonb', nullable: true })
  taches_custom: Array<{ label: string; fait: boolean }> | null;

  @Column({ type: 'text', nullable: true })
  notes: string | null;

  @ManyToOne(() => User, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'id_referent' })
  referent: User | null;

  @Column({ nullable: true })
  id_referent: number | null;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
