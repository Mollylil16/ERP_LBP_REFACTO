import { Entity, Column, PrimaryGeneratedColumn, CreateDateColumn, UpdateDateColumn, OneToMany } from 'typeorm';
import { RolePermission } from '../../permissions/entities/role-permission.entity';
import { User } from '../../users/entities/user.entity';

@Entity('lbp_roles')
export class Role {
    @PrimaryGeneratedColumn()
    id: number;

    @Column({ unique: true, length: 50 })
    code: string; // 'DIRECTEUR', 'MANAGER', 'AGENT_EXPLOITATION', etc.

    @Column({ name: 'libelle', length: 100 })
    nom: string;

    @Column({ type: 'text', nullable: true })
    description: string;

    @Column({ type: 'int', default: 5 })
    niveau_hierarchique: number; // 1=Directeur, 2=Manager, 3=Superviseur, etc.

    @Column({ default: true })
    est_actif: boolean;

    @OneToMany(() => RolePermission, (rolePermission) => rolePermission.role)
    rolePermissions: RolePermission[];

    @OneToMany(() => User, (user) => user.roleEntity)
    users: User[];

    @CreateDateColumn()
    created_at: Date;

    @UpdateDateColumn()
    updated_at: Date;
}
