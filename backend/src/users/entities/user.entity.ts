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
import { Agence } from '../../agences/entities/agence.entity';
import { Role } from '../../roles/entities/role.entity';
import { UserActionSpeciale } from './user-action-speciale.entity';

export enum UserRole {
  DIRECTEUR = 'DIRECTEUR',
  MANAGER = 'MANAGER',
  SUPERVISEUR_REGIONAL = 'SUPERVISEUR_REGIONAL',
  AGENT_EXPLOITATION = 'AGENT_EXPLOITATION',
  AGENT_GROUPAGE = 'AGENT_GROUPAGE',
  CAISSIER = 'CAISSIER',
  CAISSIER_GROUPAGE = 'CAISSIER_GROUPAGE',
  AGENT_SUIVI = 'AGENT_SUIVI',
  ADMIN = 'ADMIN', // Conservé pour compatibilité technique
}

@Entity('lbp_users')
export class User {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ unique: true, type: 'varchar', length: 100 })
  username: string;

  @Column({ select: false, type: 'varchar', length: 255 }) // Hide password by default
  password: string;

  @Column({ name: 'fullname', type: 'varchar', length: 255 })
  nom_complet: string;

  @Column({
    type: 'enum',
    enum: UserRole,
    default: UserRole.AGENT_EXPLOITATION,
  })
  role: UserRole;

  @Column({ type: 'int', default: 2 }) // Mapping to CODEACCES from STTINTER
  code_acces: number;

  @Column({ name: 'isActive', type: 'boolean', default: true })
  actif: boolean;

  // Mot de passe temporaire en clair — visible par superadmin/DG
  // Effacé automatiquement dès que l'utilisateur change son mdp
  @Column({ type: 'text', nullable: true })
  password_plain: string | null;

  // Forcer le changement de mdp à la 1ère connexion
  @Column({ type: 'boolean', default: true })
  must_change_password: boolean;

  // L'utilisateur a-t-il choisi son agence ?
  @Column({ type: 'boolean', default: false })
  agence_selected: boolean;

  @Column({ type: 'varchar', length: 20, nullable: true })
  phone: string | null;

  @Column({ type: 'varchar', length: 100, nullable: true })
  email: string | null;

  @ManyToOne(() => Agence, (agence) => agence.users, { nullable: true })
  @JoinColumn({ name: 'id_agence' })
  agence: Agence | null;

  // Nouveau système de rôles et permissions
  @ManyToOne(() => Role, (role) => role.users, { nullable: true, eager: true })
  @JoinColumn({ name: 'role_id' })
  roleEntity: Role | null;

  @Column({ type: 'boolean', default: false })
  peut_voir_toutes_agences: boolean;

  @OneToMany(() => UserActionSpeciale, (userAction) => userAction.user, {
    eager: true,
  })
  actionsSpeciales: UserActionSpeciale[];

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
