import { useContext } from 'react';
import { AuthContext } from '../contexts/AuthContext';
import { PermissionModule, PermissionAction } from '../types/roles.types';

export const usePermission = () => {
    const context = useContext(AuthContext);
    const user = context?.user;

    const hasPermission = (permissionCode: string): boolean => {
        if (!user || !user.roleEntity) {
            return false;
        }

        // Super admin a tous les droits
        if (user.role.code === 'SUPER_ADMIN') {
            return true;
        }

        // Vérifier si l'utilisateur a la permission
        const userPermissions = user.roleEntity.rolePermissions?.map(
            (rp: any) => rp.permission.code
        ) || [];

        return userPermissions.includes(permissionCode);
    };

    const hasAnyPermission = (permissionCodes: string[]): boolean => {
        return permissionCodes.some((code) => hasPermission(code));
    };

    const hasAllPermissions = (permissionCodes: string[]): boolean => {
        return permissionCodes.every((code) => hasPermission(code));
    };

    const hasRole = (roleCode: string): boolean => {
        if (!user) {
            return false;
        }

        // Vérifier l'ancien système de rôles
        if (user.role.code === roleCode) {
            return true;
        }

        // Vérifier le nouveau système de rôles
        if (user.roleEntity && user.roleEntity.code === roleCode) {
            return true;
        }

        return false;
    };

    const hasAnyRole = (roleCodes: string[]): boolean => {
        return roleCodes.some((code) => hasRole(code));
    };

    const canCreate = (module: PermissionModule, fonctionnalite: string): boolean => {
        const permissionCode = `${ module.toLowerCase() }.${ fonctionnalite }.${ PermissionAction.CREATE.toLowerCase() } `;
        return hasPermission(permissionCode);
    };

    const canRead = (module: PermissionModule, fonctionnalite: string): boolean => {
        const permissionCode = `${ module.toLowerCase() }.${ fonctionnalite }.${ PermissionAction.READ.toLowerCase() } `;
        return hasPermission(permissionCode);
    };

    const canUpdate = (module: PermissionModule, fonctionnalite: string): boolean => {
        const permissionCode = `${ module.toLowerCase() }.${ fonctionnalite }.${ PermissionAction.UPDATE.toLowerCase() } `;
        return hasPermission(permissionCode);
    };

    const canDelete = (module: PermissionModule, fonctionnalite: string): boolean => {
        const permissionCode = `${ module.toLowerCase() }.${ fonctionnalite }.${ PermissionAction.DELETE.toLowerCase() } `;
        return hasPermission(permissionCode);
    };

    const hasActionSpeciale = (actionCode: string): boolean => {
        if (!user || !user.actionsSpeciales) {
            return false;
        }

        return user.actionsSpeciales.some(
            (ua: any) => ua.actionSpeciale.code === actionCode
        );
    };

    const canSeeAllAgences = (): boolean => {
        if (!user) {
            return false;
        }

        // Super admin peut tout voir
        if (user.role.code === 'SUPER_ADMIN') {
            return true;
        }

        // Vérifier le flag direct
        if (user.peut_voir_toutes_agences) {
            return true;
        }

        // Vérifier l'action spéciale
        return hasActionSpeciale('voirToutesAgences');
    };

    return {
        hasPermission,
        hasAnyPermission,
        hasAllPermissions,
        hasRole,
        hasAnyRole,
        canCreate,
        canRead,
        canUpdate,
        canDelete,
        hasActionSpeciale,
        canSeeAllAgences,
        user,
    };
};
