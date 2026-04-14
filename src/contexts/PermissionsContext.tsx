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
  const { user, isAuthenticated, permissions: authPermissions } = useAuth()
  const [permissions, setPermissions] = useState<string[]>([])
  const [isLoading, setIsLoading] = useState(true)

  const loadPermissions = useCallback(async () => {
    try {
      setIsLoading(true)

      // Source unique: AuthContext (rempli dès le login, ou depuis le cache au refresh)
      if (Array.isArray(authPermissions) && authPermissions.length > 0) {
        setPermissions(authPermissions)
        return
      }

      // Fallback (rare): cache storage
      const cached =
        sessionStorage.getItem('lbp_permissions') ?? localStorage.getItem('lbp_permissions')
      if (cached) {
        try {
          const parsed = JSON.parse(cached)
          if (Array.isArray(parsed) && parsed.length > 0) {
            setPermissions(parsed)
            return
          }
        } catch {
          // ignore
        }
      }

      // Fallback ultime: API (si vraiment rien en mémoire)
      const userPermissions = await authService.getPermissions()
      setPermissions(userPermissions)
      sessionStorage.setItem('lbp_permissions', JSON.stringify(userPermissions))
      localStorage.removeItem('lbp_permissions')
    } catch (error) {
      console.error('Error loading permissions:', error)
      setPermissions([])
    } finally {
      setIsLoading(false)
    }
  }, [authPermissions])

  useEffect(() => {
    if (isAuthenticated && user) {
      loadPermissions()
    } else {
      setPermissions([])
      setIsLoading(false)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isAuthenticated, user?.id, authPermissions?.length]) // Utiliser user?.id au lieu de user pour éviter les re-renders

  /** Aligné backend : DIRECTEUR / ADMIN / code_acces 2 = accès total (évite 403 côté front). */
  const hasFullAppAccess = useMemo(() => {
    if (!user) return false
    if (user.username === 'admin') return true
    if (user.code_acces === 2) return true
    const r = user.role as string | { code?: string } | undefined
    const code =
      typeof r === 'string' ? r : r && typeof r === 'object' ? r.code : undefined
    if (!code) return false
    return (
      code === 'DIRECTEUR' ||
      code === 'ADMIN' ||
      code === 'SUPER_ADMIN' ||
      code.toLowerCase() === 'admin'
    )
  }, [user])

  const hasPermission = (permission: string): boolean => {
    if (hasFullAppAccess || permissions.includes('*')) {
      return true
    }

    return permissions.includes(permission)
  }

  const hasAnyPermission = (permissionList: string[]): boolean => {
    if (hasFullAppAccess || permissions.includes('*')) {
      return true
    }

    return permissionList.some((p) => permissions.includes(p))
  }

  const hasAllPermissions = (permissionList: string[]): boolean => {
    if (hasFullAppAccess || permissions.includes('*')) {
      return true
    }

    return permissionList.every((p) => permissions.includes(p))
  }

  // Debug log on change
  useEffect(() => {
    if (user) {
      console.log('[Permissions] Current User Role:', user.role);
      console.log('[Permissions] Current Permissions:', permissions);
      console.log('[Permissions] hasFullAppAccess?', hasFullAppAccess);
    }
  }, [user, permissions, hasFullAppAccess]);

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


