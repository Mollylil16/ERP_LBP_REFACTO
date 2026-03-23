import React from 'react'
import ReactDOM from 'react-dom/client'
import { HashRouter } from 'react-router-dom'
import { PersistQueryClientProvider } from '@tanstack/react-query-persist-client'
import { Toaster } from 'react-hot-toast'

import App from './App'
import './i18n'
import { AuthProvider } from './contexts/AuthContext'
import { PermissionsProvider } from './contexts/PermissionsContext'
import { ThemeProvider } from './contexts/ThemeContext'
import { NotificationsProvider } from './contexts/NotificationsContext'
import { ErrorBoundary } from './components/common/ErrorBoundary'
import { queryClient, idbPersister } from './config/queryClient'

import './index.css'
import './styles/responsive.css'
import './styles/dark-mode.css'
import './styles/accessibility.css'

const AppWrapper = (
  <React.StrictMode>
    <ErrorBoundary>
      <HashRouter>
        <PersistQueryClientProvider
          client={queryClient}
          persistOptions={{
            persister: idbPersister,
            maxAge: 24 * 60 * 60 * 1000,       // 24 h de survie du cache
            buster: import.meta.env.VITE_APP_VERSION ?? 'v1',
          }}
        >
          <ThemeProvider>
            <AuthProvider>
              <PermissionsProvider>
                <NotificationsProvider>
                  <App />
                  <Toaster position="top-right" />
                </NotificationsProvider>
              </PermissionsProvider>
            </AuthProvider>
          </ThemeProvider>
        </PersistQueryClientProvider>
      </HashRouter>
    </ErrorBoundary>
  </React.StrictMode>
)

ReactDOM.createRoot(document.getElementById('root')!).render(AppWrapper)
