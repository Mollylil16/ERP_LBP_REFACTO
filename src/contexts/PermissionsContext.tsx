import React, { createContext, useContext, useState, useEffect, useCallback, useMemo } from 'react'
import { useAuth } from '@hooks/useAuth'
import { authService } from '@services/auth.service'

interface PermissionsContextType {
  permissions: string[]
  hasPermission: (permission: string) => boolean
  hasAnyPermission: (permissions: string[]) => boolean
  hasAllPermissions: (permissions: string[]) => boolean
  isLoading: boolean
}

export const PermissionsContext = createContext<PermissionsContextType | undefined>(undefined)

export const PermissionsProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { user, isAuthenticated } = useAuth()
  const [permissions, setPermissions] = useState<string[]>([])
  const [isLoading, setIsLoading] = useState(true)

  const loadPermissions = useCallback(async () => {
    try {
      setIsLoading(true)

      // Vérifier d'abord le cache localStorage
      const cachedPermissions = localStorage.getItem('lbp_permissions')
      if (cachedPermissions) {
        try {
          const parsed = JSON.parse(cachedPermissions)
          setPermissions(parsed)
          setIsLoading(false)
        } catch (e) {
          // Cache invalide, charger depuis l'API
        }
      }

      // Charger depuis l'API
      const userPermissions = await authService.getPermissions()
      setPermissions(userPermissions)

      // Mettre à jour le cache
      localStorage.setItem('lbp_permissions', JSON.stringify(userPermissions))
    } catch (error) {
      console.error('Error loading permissions:', error)
      setPermissions([])
    } finally {
      setIsLoading(false)
    }
  }, [])

  useEffect(() => {
    if (isAuthenticated && user) {
      loadPermissions()
    } else {
      setPermissions([])
      setIsLoading(false)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isAuthenticated, user?.id]) // Utiliser user?.id au lieu de user pour éviter les re-renders

  const hasPermission = (permission: string): boolean => {
    // Debug permissions
    const role = user?.role;
    const isSuperAdmin =
      (role as any) === 'SUPER_ADMIN' ||
      (typeof role === 'object' && (role as any)?.code === 'SUPER_ADMIN') ||
      (role as any) === 'admin' ||
      (typeof role === 'object' && (role as any)?.code === 'ADMIN') ||
      user?.username === 'admin';

    if (isSuperAdmin || permissions.includes('*')) {
      return true;
    }

    return permissions.includes(permission);
  }

  const hasAnyPermission = (permissionList: string[]): boolean => {
    const role = user?.role;
    const isSuperAdmin =
      (role as any) === 'SUPER_ADMIN' ||
      (typeof role === 'object' && (role as any)?.code === 'SUPER_ADMIN') ||
      (role as any) === 'admin' ||
      (typeof role === 'object' && (role as any)?.code === 'ADMIN');

    if (isSuperAdmin || permissions.includes('*')) {
      return true;
    }

    return permissionList.some((p) => permissions.includes(p));
  }

  const hasAllPermissions = (permissionList: string[]): boolean => {
    const role = user?.role;
    const isSuperAdmin =
      (role as any) === 'SUPER_ADMIN' ||
      (typeof role === 'object' && (role as any)?.code === 'SUPER_ADMIN') ||
      (role as any) === 'admin' ||
      (typeof role === 'object' && (role as any)?.code === 'ADMIN');

    if (isSuperAdmin || permissions.includes('*')) {
      return true;
    }

    return permissionList.every((p) => permissions.includes(p));
  }

  // Debug log on change
  useEffect(() => {
    if (user) {
      console.log('[Permissions] Current User Role:', user.role);
      console.log('[Permissions] Current Permissions:', permissions);
      const isSA = (user.role as any) === 'SUPER_ADMIN' || (user.role as any) === 'admin';
      console.log('[Permissions] Is SUPER_ADMIN?', isSA);
    }
  }, [user, permissions]);

  const value: PermissionsContextType = {
    permissions,
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,
    isLoading,
  }

  return (
    <PermissionsContext.Provider value={value}>
      {children}
    </PermissionsContext.Provider>
  )
}


