/**
 * Utilitaires pour la gestion des erreurs
 */

import { AxiosError } from 'axios'
import { logger } from '@services/logger.service'

export interface AppError {
  message: string
  code?: string
  statusCode?: number
  details?: any
}

/**
 * Convertir une erreur Axios en AppError
 */
export function handleApiError(error: unknown): AppError {
  if (error instanceof AxiosError) {
    // Erreur de réseau (pas de réponse du serveur)
    if (!error.response) {
      logger.error('Erreur réseau', error)
      return {
        message: 'Impossible de joindre le serveur. Vérifiez votre connexion internet.',
        code: 'NETWORK_ERROR',
      }
    }

    const { status, data } = error.response
    const requestUrl = error.config?.url || ''

    // Erreur 401 - Non autorisé
    if (status === 401) {
      // Cas login: privilégier un message d'identifiants invalides
      if (requestUrl.includes('/auth/login')) {
        return {
          message: data?.message || 'Nom d\'utilisateur ou mot de passe incorrect.',
          code: 'UNAUTHORIZED_LOGIN',
          statusCode: 401,
        }
      }

      // Cas générique: garder le message backend s'il existe
      return {
        message: data?.message || 'Votre session a expiré. Veuillez vous reconnecter.',
        code: 'UNAUTHORIZED',
        statusCode: 401,
      }
    }

    // Erreur 403 - Accès interdit
    if (status === 403) {
      return {
        message: 'Vous n\'avez pas les permissions nécessaires pour effectuer cette action.',
        code: 'FORBIDDEN',
        statusCode: 403,
      }
    }

    // Erreur 404 - Non trouvé
    if (status === 404) {
      return {
        message: 'La ressource demandée n\'a pas été trouvée.',
        code: 'NOT_FOUND',
        statusCode: 404,
      }
    }

    // Erreur 422 - Validation
    if (status === 422) {
      const validationErrors = data?.errors || data?.message
      return {
        message: validationErrors || 'Les données fournies ne sont pas valides.',
        code: 'VALIDATION_ERROR',
        statusCode: 422,
        details: data?.errors,
      }
    }

    // Erreur 429 - Trop de requêtes
    if (status === 429) {
      return {
        message: 'Trop de requêtes. Veuillez patienter quelques instants.',
        code: 'TOO_MANY_REQUESTS',
        statusCode: 429,
      }
    }

    // Erreur 500 - Erreur serveur
    if (status >= 500) {
      logger.error('Erreur serveur', error, { status, data })
      return {
        message: 'Une erreur est survenue sur le serveur. Veuillez réessayer plus tard.',
        code: 'SERVER_ERROR',
        statusCode: status,
      }
    }

    // Autre erreur HTTP
    const message = data?.message || error.message || 'Une erreur est survenue.'
    return {
      message,
      code: data?.code || 'HTTP_ERROR',
      statusCode: status,
      details: data,
    }
  }

  // Erreur JavaScript standard
  if (error instanceof Error) {
    logger.error('Erreur JavaScript', error)
    return {
      message: error.message || 'Une erreur inattendue est survenue.',
      code: 'JAVASCRIPT_ERROR',
    }
  }

  // Erreur inconnue
  logger.error('Erreur inconnue', error)
  return {
    message: 'Une erreur inattendue est survenue.',
    code: 'UNKNOWN_ERROR',
  }
}

/**
 * Obtenir un message d'erreur utilisateur-friendly
 */
export function getUserFriendlyErrorMessage(error: unknown): string {
  const appError = handleApiError(error)
  return appError.message
}

/**
 * Vérifier si une erreur est récupérable
 */
export function isRecoverableError(error: AppError): boolean {
  // Erreurs récupérables : réseau, timeout, 429
  const recoverableCodes = ['NETWORK_ERROR', 'TIMEOUT', 'TOO_MANY_REQUESTS']
  return recoverableCodes.includes(error.code || '')
}
