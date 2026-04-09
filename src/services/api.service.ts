import axios, { AxiosInstance, AxiosError, InternalAxiosRequestConfig } from 'axios'
import { logger } from './logger.service'
import { handleApiError, getUserFriendlyErrorMessage } from '@utils/errorHandler'
import toast from 'react-hot-toast'

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:3001/api'

class ApiService {
  private api: AxiosInstance

  constructor() {
    this.api = axios.create({
      baseURL: API_BASE_URL,
      timeout: 30000,
      headers: {
        'Content-Type': 'application/json',
      },
    })

    // Intercepteur pour ajouter le token à chaque requête
    this.api.interceptors.request.use(
      (config: InternalAxiosRequestConfig) => {
        // sessionStorage = isolé par onglet (évite le “swap” de session entre comptes)
        const token =
          sessionStorage.getItem('lbp_token') ?? localStorage.getItem('lbp_token')
        if (token && config.headers) {
          config.headers.Authorization = `Bearer ${token}`
        }
        return config
      },
      (error: AxiosError) => {
        return Promise.reject(error)
      }
    )

    // Intercepteur pour gérer les erreurs de réponse
    this.api.interceptors.response.use(
      (response) => response,
      async (error: AxiosError) => {
        const appError = handleApiError(error)

        // Logger l'erreur
        logger.error('Erreur API', error, {
          url: error.config?.url,
          method: error.config?.method,
          status: error.response?.status,
          statusText: error.response?.statusText,
        })

        // Gérer les erreurs spécifiques
        if (error.response?.status === 401) {
          // Ne pas déconnecter si on est sur la page de login ou si c'est une requête de login
          const isLoginRequest = error.config?.url?.includes('/auth/login')
          // Support both BrowserRouter and HashRouter login routes
          const hashPath = window.location.hash.replace(/^#/, '')
          const isOnLoginPage =
            window.location.pathname === '/login' ||
            hashPath === '/login' ||
            hashPath.startsWith('/login?')
          const isAuthMeRequest = error.config?.url?.includes('/auth/me')

          // Ne pas déconnecter pour les requêtes d'authentification ou si on vient juste de se connecter
          if (!isLoginRequest && !isOnLoginPage && !isAuthMeRequest) {
            // Vérifier si on a un token valide (peut-être juste une requête qui a échoué)
            const token =
              sessionStorage.getItem('lbp_token') ?? localStorage.getItem('lbp_token')
            if (token) {
              // Token expiré ou invalide
              console.warn('[API] 401 error, removing token and redirecting')
              localStorage.removeItem('lbp_token')
              localStorage.removeItem('lbp_refresh_token')
              localStorage.removeItem('lbp_permissions')
              localStorage.removeItem('lbp_mock_user')
              sessionStorage.removeItem('lbp_token')
              sessionStorage.removeItem('lbp_refresh_token')
              sessionStorage.removeItem('lbp_permissions')
              sessionStorage.removeItem('lbp_mock_user')

              // Rediriger vers login si pas déjà sur la page de login
              toast.error('Votre session a expiré. Veuillez vous reconnecter.')
              setTimeout(() => {
                window.location.href = '/login'
              }, 1000)
            }
          } else {
            console.log('[API] 401 error but ignoring (login/auth request or on login page)')
          }
        } else if (error.response?.status === 403) {
          // Permission refusée
          toast.error('Vous n\'avez pas les permissions nécessaires pour cette action.')
        } else if (error.response?.status === 404) {
          // Resource non trouvée - ne pas afficher de toast, gérer dans le composant
          // toast.error('La ressource demandée n\'a pas été trouvée.')
        } else if (error.response?.status === 422) {
          // Erreur de validation - le composant gérera l'affichage des erreurs
          // Ne pas afficher de toast ici, laisser le composant gérer
        } else if (error.response?.status === 429) {
          // Trop de requêtes
          toast.error('Trop de requêtes. Veuillez patienter quelques instants.')
        } else if (error.response && error.response.status >= 500) {
          // Erreur serveur
          toast.error('Une erreur est survenue sur le serveur. Veuillez réessayer plus tard.')
        } else if (!error.response) {
          // Erreur réseau
          toast.error('Impossible de joindre le serveur. Vérifiez votre connexion internet.')
        }

        // Rejeter avec l'erreur formatée
        return Promise.reject(appError)
      }
    )
  }

  get instance(): AxiosInstance {
    return this.api
  }

  // Méthodes HTTP
  async get<T>(url: string, config?: any): Promise<T> {
    const response = await this.api.get<T>(url, config)
    return response.data
  }

  async post<T>(url: string, data?: any, config?: any): Promise<T> {
    const response = await this.api.post<T>(url, data, config)
    return response.data
  }

  async put<T>(url: string, data?: any, config?: any): Promise<T> {
    const response = await this.api.put<T>(url, data, config)
    return response.data
  }

  async patch<T>(url: string, data?: any, config?: any): Promise<T> {
    const response = await this.api.patch<T>(url, data, config)
    return response.data
  }

  async delete<T>(url: string, config?: any): Promise<T> {
    const response = await this.api.delete<T>(url, config)
    return response.data
  }
}

export const apiService = new ApiService()
