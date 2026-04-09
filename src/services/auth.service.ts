import { User, LoginCredentials, AuthResponse } from '@types'
import { apiService } from './api.service'

// Désactiver le mode mock pour utiliser le vrai backend
const USE_MOCK_AUTH = false

class AuthService {
  async login(credentials: LoginCredentials): Promise<AuthResponse> {
    if (USE_MOCK_AUTH) {
      throw new Error('Mock auth disabled')
    }
    return apiService.post<AuthResponse>('/auth/login', credentials)
  }

  async getCurrentUser(): Promise<User> {
    return apiService.get<User>('/auth/me')
  }

  async getPermissions(): Promise<string[]> {
    return apiService.get<string[]>('/auth/permissions')
  }

  async logout(): Promise<void> {
    try {
      await apiService.post('/auth/logout')
    } catch (error) {
      console.error('Logout error:', error)
    }
  }

  async refreshToken(): Promise<{ token: string; refresh_token?: string }> {
    const refreshToken =
      sessionStorage.getItem('lbp_refresh_token') ??
      localStorage.getItem('lbp_refresh_token')
    return apiService.post('/auth/refresh', { refresh_token: refreshToken })
  }
}

export const authService = new AuthService()
