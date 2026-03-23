import { Entity, Column, PrimaryGeneratedColumn, CreateDateColumn, UpdateDateColumn, ManyToOne, OneToMany, JoinColumn } from 'typeorm';
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

    @Column({ unique: true })
    username: string;

    @Column({ select: false }) // Hide password by default
    password: string;

    @Column({ name: 'fullname' })
    nom_complet: string;

    @Column({
        type: 'enum',
        enum: UserRole,
        default: UserRole.AGENT_EXPLOITATION,
    })
    role: UserRole;

    @Column({ type: 'int', default: 2 }) // Mapping to CODEACCES from STTINTER
    code_acces: number;

    @Column({ name: 'isActive', default: true })
    actif: boolean;

    // Mot de passe temporaire en clair — visible par superadmin/DG
    // Effacé automatiquement dès que l'utilisateur change son mdp
    @Column({ nullable: true, type: 'text' })
    password_plain: string | null;

    // Forcer le changement de mdp à la 1ère connexion
    @Column({ default: true })
    must_change_password: boolean;

    // L'utilisateur a-t-il choisi son agence ?
    @Column({ default: false })
    agence_selected: boolean;

    @Column({ nullable: true, length: 20 })
    phone: string | null;

    @Column({ nullable: true, length: 100 })
    email: string | null;

    @ManyToOne(() => Agence, (agence) => agence.users, { nullable: true })
    @JoinColumn({ name: 'id_agence' })
    agence: Agence | null;

    // Nouveau système de rôles et permissions
    @ManyToOne(() => Role, (role) => role.users, { nullable: true, eager: true })
    @JoinColumn({ name: 'role_id' })
    roleEntity: Role;

    @Column({ default: false })
    peut_voir_toutes_agences: boolean;

    @OneToMany(() => UserActionSpeciale, (userAction) => userAction.user, { eager: true })
    actionsSpeciales: UserActionSpeciale[];

    @CreateDateColumn()
    created_at: Date;

    @UpdateDateColumn()
    updated_at: Date;
}
