/**
 * Error Boundary React pour capturer les erreurs UI
 * Affiche un écran d'erreur au lieu de faire planter toute l'application
 */

import React, { Component, ErrorInfo, ReactNode } from 'react'
import { Result, Button } from 'antd'
import { ReloadOutlined, HomeOutlined } from '@ant-design/icons'
import { logger } from '@services/logger.service'
import './ErrorBoundary.css'

interface Props {
  children: ReactNode
  fallback?: ReactNode
}

interface State {
  hasError: boolean
  error: Error | null
  errorInfo: ErrorInfo | null
}

export class ErrorBoundary extends Component<Props, State> {
  constructor(props: Props) {
    super(props)
    this.state = {
      hasError: false,
      error: null,
      errorInfo: null,
    }
  }

  static getDerivedStateFromError(error: Error): Partial<State> {
    // Mettre à jour l'état pour afficher l'UI d'erreur
    return {
      hasError: true,
      error,
    }
  }

  componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    // Toujours exposer l'erreur dans la console pour diagnostic rapide,
    // même en production (l'écran "Oups" est sinon aveugle).
    // eslint-disable-next-line no-console
    console.error('[ErrorBoundary] Unhandled UI error:', error)
    // eslint-disable-next-line no-console
    if (errorInfo?.componentStack) console.error('[ErrorBoundary] Component stack:', errorInfo.componentStack)

    // Logger l'erreur
    logger.error('Erreur capturée par ErrorBoundary', error, {
      componentStack: errorInfo.componentStack,
      errorBoundary: true,
    })

    // Mettre à jour l'état avec les détails de l'erreur
    this.setState({
      error,
      errorInfo,
    })
  }

  handleReset = () => {
    // Réinitialiser l'état pour permettre un nouveau rendu
    this.setState({
      hasError: false,
      error: null,
      errorInfo: null,
    })
    
    // Recharger la page si nécessaire
    window.location.reload()
  }

  handleGoHome = () => {
    window.location.href = '/dashboard'
  }

  render() {
    if (this.state.hasError) {
      // Si un fallback personnalisé est fourni, l'utiliser
      if (this.props.fallback) {
        return this.props.fallback
      }

      // Sinon, afficher l'UI d'erreur par défaut
      const isDevelopment = import.meta.env.DEV
      const url = new URL(window.location.href)
      const debugEnabled =
        url.searchParams.get('debug') === '1' ||
        localStorage.getItem('lbp_debug_errors') === '1'

      return (
        <div className="error-boundary-container">
          <Result
            status="error"
            title="Oups ! Une erreur est survenue"
            subTitle={
              <div className="error-boundary-message">
                <p>
                  L'application a rencontré une erreur inattendue. 
                  Veuillez réessayer ou contacter le support si le problème persiste.
                </p>
                {(isDevelopment || debugEnabled) && this.state.error && (
                  <details className="error-boundary-details">
                    <summary>Détails techniques</summary>
                    <div className="error-boundary-stack">
                      <p><strong>Erreur:</strong> {this.state.error.message}</p>
                      {this.state.error.stack && (
                        <pre>{this.state.error.stack}</pre>
                      )}
                      {this.state.errorInfo?.componentStack && (
                        <div>
                          <strong>Stack du composant:</strong>
                          <pre>{this.state.errorInfo.componentStack}</pre>
                        </div>
                      )}
                    </div>
                  </details>
                )}
              </div>
            }
            extra={[
              <Button
                type="primary"
                key="reload"
                icon={<ReloadOutlined />}
                onClick={this.handleReset}
              >
                Recharger la page
              </Button>,
              <Button
                key="home"
                icon={<HomeOutlined />}
                onClick={this.handleGoHome}
              >
                Retour à l'accueil
              </Button>,
              ...(debugEnabled
                ? [
                    <Button
                      key="copy"
                      onClick={() => {
                        const payload = {
                          message: this.state.error?.message,
                          stack: this.state.error?.stack,
                          componentStack: this.state.errorInfo?.componentStack,
                          url: window.location.href,
                        }
                        void navigator.clipboard?.writeText(JSON.stringify(payload, null, 2))
                      }}
                    >
                      Copier détails
                    </Button>,
                  ]
                : []),
            ]}
          />
        </div>
      )
    }

    return this.props.children
  }
}
