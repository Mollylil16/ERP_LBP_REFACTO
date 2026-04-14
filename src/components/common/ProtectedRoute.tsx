import React from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '@hooks/useAuth'
import { usePermissions } from '@hooks/usePermissions'
import { Spin } from 'antd'

interface ProtectedRouteProps {
  children: React.ReactNode
  requiredPermission?: string | string[]
  requireAll?: boolean
}

export const ProtectedRoute: React.FC<ProtectedRouteProps> = ({
  children,
  requiredPermission,
  requireAll = false,
}) => {
  const { isAuthenticated, isLoading: isAuthLoading, user } = useAuth()
  const { isLoading: isPermsLoading, hasPermission, hasAnyPermission, hasAllPermissions } = usePermissions()
  const location = useLocation()

  const isLoading = isAuthLoading || isPermsLoading

  // Afficher un loader pendant la vérification de l'authentification et des permissions
  if (isLoading) {
    return (
      <div style={{
        display: 'flex',
        justifyContent: 'center',
        alignItems: 'center',
        height: '100vh',
      }}>
        <Spin size="large" tip="Chargement...">
          <div style={{ padding: 50 }} />
        </Spin>
      </div>
    )
  }

  // Vérifier aussi le token dans localStorage comme fallback
  const hasToken = !!(sessionStorage.getItem('lbp_token') ?? localStorage.getItem('lbp_token'));

  // Si on a un token, on considère qu'on est authentifié même si user n'est pas encore défini
  // (cela peut arriver juste après le login avant que React ne mette à jour l'état)
  const shouldBeAuthenticated = isAuthenticated || hasToken;

  // Rediriger vers login UNIQUEMENT si on n'a ni utilisateur ni token ET qu'on n'est pas en train de charger
  if (!shouldBeAuthenticated && !hasToken && !isLoading) {
    return <Navigate to="/login" replace />
  }

  // Si on a un token mais pas d'utilisateur encore, attendre (cela peut arriver juste après le login)
  if (hasToken && !user && !isLoading) {
    return (
      <div style={{
        display: 'flex',
        justifyContent: 'center',
        alignItems: 'center',
        height: '100vh',
      }}>
        <Spin size="large" tip="Chargement..." />
      </div>
    )
  }

  if (requiredPermission && user) {
    const list = Array.isArray(requiredPermission) ? requiredPermission : [requiredPermission]

    // Correctif "définitif" : tout utilisateur authentifié peut ouvrir le Dashboard.
    // Côté backend on garantit dashboard.view au minimum (même si RBAC vide),
    // mais côté front il peut y avoir un délai/cache permissions → évite un 403 post-login.
    if (list.length === 1 && list[0] === 'dashboard.view' && shouldBeAuthenticated) {
      return <>{children}</>
    }

    const allowed = requireAll
      ? hasAllPermissions(list)
      : list.length === 1
        ? hasPermission(list[0])
        : hasAnyPermission(list)
    if (!allowed) {
      return <Navigate to="/forbidden" replace state={{ from: location.pathname }} />
    }
  }

  return <>{children}</>
}
