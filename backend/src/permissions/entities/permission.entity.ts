import { Entity, Column, PrimaryGeneratedColumn, CreateDateColumn, UpdateDateColumn, OneToMany } from 'typeorm';
import { RolePermission } from './role-permission.entity';

export enum PermissionModule {
    EXPLOITATION = 'EXPLOITATION',
    FACTURATION = 'FACTURATION',
    OPERATION_CAISSE = 'OPERATION_CAISSE',
    GESTION_FONDS = 'GESTION_FONDS',
    RAPPORTS = 'RAPPORTS',
    STRUCTURES = 'STRUCTURES',
}

export enum PermissionAction {
    CREATE = 'CREATE',
    READ = 'READ',
    UPDATE = 'UPDATE',
    DELETE = 'DELETE',
}

@Entity('lbp_permissions')
export class Permission {
    @PrimaryGeneratedColumn()
    id: number;

    @Column({ length: 50, default: 'EXPLOITATION' })
    module: string;

    @Column({ length: 100 })
    fonctionnalite: string;

    @Column({ length: 50, nullable: true })
    action: string;

    @Column({ unique: true, length: 150 })
    code: string; // 'exploitation.groupage_colis.create'

    @Column({ type: 'text', nullable: true })
    description: string;

    @OneToMany(() => RolePermission, (rolePermission) => rolePermission.permission)
    rolePermissions: RolePermission[];

    @CreateDateColumn()
    created_at: Date;

    @UpdateDateColumn()
    updated_at: Date;
}
