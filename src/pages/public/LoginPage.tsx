import React, { useState, useEffect } from 'react'
import { Form, Input, Button, Typography, Space, Card, Switch, Tooltip, Alert, message } from 'antd'
import {
  UserOutlined,
  LockOutlined,
  MoonOutlined,
  SunOutlined,
  ThunderboltOutlined,
  SafetyOutlined,
} from '@ant-design/icons'
import { useAuth } from '@hooks/useAuth'
import { apiService } from '@services/api.service'
import './LoginPage.css'

const { Title, Text } = Typography

export const LoginPage: React.FC = () => {
  const [loading, setLoading] = useState(false)
  const [darkMode, setDarkMode] = useState(false)
  const [mfaStep, setMfaStep] = useState(false)
  const [mfaSessionToken, setMfaSessionToken] = useState('')
  const { login } = useAuth()

  useEffect(() => {
    if (darkMode) {
      document.body.classList.add('dark-mode')
    } else {
      document.body.classList.remove('dark-mode')
    }
  }, [darkMode])

  const onFinish = async (values: { username: string; password: string }) => {
    try {
      setLoading(true)
      const response: any = await apiService.post('/auth/login', values)
      if (response?.mfa_required) {
        setMfaSessionToken(response.mfa_session_token)
        setMfaStep(true)
        message.info('Saisissez votre code MFA (Google Authenticator)')
      } else {
        await login(values)
      }
    } catch (error) {
      console.error('Login error:', error)
    } finally {
      setLoading(false)
    }
  }

  const onMfaFinish = async (values: { token: string }) => {
    try {
      setLoading(true)
      const resp: any = await apiService.post('/auth/mfa/challenge', {
        mfa_session_token: mfaSessionToken,
        token: values.token,
      })
      // Simulate full login by storing the token directly
      if (resp?.token) {
        sessionStorage.setItem('lbp_token', resp.token)
        if (resp.refresh_token) sessionStorage.setItem('lbp_refresh_token', resp.refresh_token)
        if (resp.permissions) sessionStorage.setItem('lbp_permissions', JSON.stringify(resp.permissions))
        window.location.href = '/'
      }
    } catch (error) {
      message.error('Code MFA invalide ou expiré')
      console.error('MFA error:', error)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className={`premium-login-container ${darkMode ? 'dark' : 'light'}`}>
      {/* Animated Background */}
      <div className="animated-background">
        <div className="gradient-blob blob-1"></div>
        <div className="gradient-blob blob-2"></div>
        <div className="gradient-blob blob-3"></div>
        <div className="grid-pattern"></div>
      </div>

      {/* Theme Toggle */}
      <div className="theme-toggle-wrapper">
        <Tooltip title={darkMode ? 'Mode clair' : 'Mode sombre'}>
          <Switch
            checked={darkMode}
            onChange={setDarkMode}
            checkedChildren={<MoonOutlined />}
            unCheckedChildren={<SunOutlined />}
            className="theme-toggle"
          />
        </Tooltip>
      </div>

      {/* Main Content */}
      <div className="premium-login-content">
        {/* Left Panel - Branding with SVG Illustration */}
        <div className="premium-left-panel">
          <div className="left-panel-inner">
            <div className="brand-section-premium">
              <div className="logo-glass-container">
                <img
                  src="/logo_lbp.png"
                  alt="La Belle Porte"
                  className="premium-logo"
                />
                <div className="glass-shine"></div>
              </div>
              <Title level={1} className="brand-title-premium">
                La Belle Porte
              </Title>
              <Text className="brand-subtitle-premium">
                Système de Gestion de Colis Professionnel
              </Text>
            </div>

            {/* SVG Illustration */}
            <div className="svg-illustration-container">
              <svg
                viewBox="0 0 400 300"
                className="login-illustration"
                xmlns="http://www.w3.org/2000/svg"
              >
                {/* Animated Background Shapes */}
                <defs>
                  <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stopColor="#3b82f6" stopOpacity="0.3">
                      <animate attributeName="stop-opacity" values="0.3;0.6;0.3" dur="3s" repeatCount="indefinite" />
                    </stop>
                    <stop offset="100%" stopColor="#8b5cf6" stopOpacity="0.3">
                      <animate attributeName="stop-opacity" values="0.3;0.6;0.3" dur="3s" repeatCount="indefinite" />
                    </stop>
                  </linearGradient>
                  <linearGradient id="grad2" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stopColor="#06b6d4" stopOpacity="0.2" />
                    <stop offset="100%" stopColor="#3b82f6" stopOpacity="0.2" />
                  </linearGradient>
                </defs>

                {/* Floating Shapes */}
                <circle cx="80" cy="80" r="40" fill="url(#grad1)" className="float-shape">
                  <animateTransform
                    attributeName="transform"
                    type="translate"
                    values="0,0; 20,-20; 0,0"
                    dur="4s"
                    repeatCount="indefinite"
                  />
                </circle>
                <circle cx="320" cy="120" r="30" fill="url(#grad2)" className="float-shape">
                  <animateTransform
                    attributeName="transform"
                    type="translate"
                    values="0,0; -15,15; 0,0"
                    dur="5s"
                    repeatCount="indefinite"
                  />
                </circle>

                {/* Main Illustration - Secure Box/Shield */}
                <g className="main-illustration">
                  {/* Shield */}
                  <path
                    d="M200 50 L250 70 L250 150 Q250 200 200 220 Q150 200 150 150 L150 70 Z"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="3"
                    className="shield-path"
                  />
                  {/* Lock Icon */}
                  <rect x="185" y="120" width="30" height="25" rx="2" fill="currentColor" className="lock-body" />
                  <path
                    d="M185 120 Q200 110 215 120"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="3"
                    className="lock-arch"
                  />
                  {/* Checkmark */}
                  <path
                    d="M190 140 L205 155 L230 130"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="4"
                    strokeLinecap="round"
                    className="checkmark"
                  >
                    <animate
                      attributeName="stroke-dasharray"
                      values="0,100;50,50;100,0"
                      dur="2s"
                      repeatCount="indefinite"
                    />
                  </path>
                </g>
              </svg>
            </div>

          </div>
        </div>

        {/* Right Panel - Login Form with Glassmorphism */}
        <div className="premium-right-panel">
          <div className="glass-form-container">
            <div className="form-header-premium">
              <Title level={2} className="form-title-premium">
                Connexion
              </Title>
              <Text className="form-subtitle-premium">
                Accédez à votre espace sécurisé
              </Text>
            </div>

            <Card className="dev-mode-card-premium" size="small">
              <Alert
                type="info"
                showIcon
                message="Connexion sécurisée"
                description="Utilisez vos identifiants professionnels. Lors de la première connexion, un mot de passe temporaire vous est communiqué, puis vous devez le modifier et sélectionner votre agence."
              />
            </Card>

            {!mfaStep ? (
              <Form
                name="login"
                onFinish={onFinish}
                autoComplete="off"
                size="large"
                className="premium-login-form"
                layout="vertical"
              >
                <Form.Item
                  name="username"
                  label={<Text strong className="form-label-premium">Nom d'utilisateur</Text>}
                  rules={[{ required: true, message: "Veuillez saisir votre nom d'utilisateur" }]}
                >
                  <Input
                    prefix={<UserOutlined className="input-prefix-icon-premium" />}
                    placeholder="Entrez votre nom d'utilisateur"
                    className="premium-input glass-input"
                  />
                </Form.Item>

                <Form.Item
                  name="password"
                  label={<Text strong className="form-label-premium">Mot de passe</Text>}
                  rules={[{ required: true, message: 'Veuillez saisir votre mot de passe' }]}
                >
                  <Input.Password
                    prefix={<LockOutlined className="input-prefix-icon-premium" />}
                    placeholder="Entrez votre mot de passe"
                    className="premium-input glass-input"
                  />
                </Form.Item>

                <Form.Item className="submit-form-item-premium">
                  <Button
                    type="primary"
                    htmlType="submit"
                    loading={loading}
                    block
                    className="premium-login-button glass-button"
                    size="large"
                    icon={loading ? null : <ThunderboltOutlined />}
                  >
                    {loading ? 'Connexion en cours...' : 'Se connecter'}
                  </Button>
                </Form.Item>
              </Form>
            ) : (
              <Form
                name="mfa"
                onFinish={onMfaFinish}
                autoComplete="off"
                size="large"
                className="premium-login-form"
                layout="vertical"
              >
                <Alert
                  type="warning"
                  showIcon
                  icon={<SafetyOutlined />}
                  message="Authentification à deux facteurs"
                  description="Ouvrez Google Authenticator et saisissez le code à 6 chiffres."
                  style={{ marginBottom: 16 }}
                />
                <Form.Item
                  name="token"
                  label={<Text strong className="form-label-premium">Code MFA (6 chiffres)</Text>}
                  rules={[{ required: true, len: 6, message: 'Code à 6 chiffres requis' }]}
                >
                  <Input
                    prefix={<SafetyOutlined className="input-prefix-icon-premium" />}
                    placeholder="000000"
                    maxLength={6}
                    className="premium-input glass-input"
                    style={{ letterSpacing: 8, fontSize: 20, textAlign: 'center' }}
                  />
                </Form.Item>
                <Form.Item className="submit-form-item-premium">
                  <Button
                    type="primary"
                    htmlType="submit"
                    loading={loading}
                    block
                    className="premium-login-button glass-button"
                    size="large"
                    icon={<SafetyOutlined />}
                  >
                    {loading ? 'Vérification...' : 'Valider le code'}
                  </Button>
                </Form.Item>
                <Button type="link" block onClick={() => setMfaStep(false)}>
                  Retour à la connexion
                </Button>
              </Form>
            )}

            <div className="login-footer-premium">
              <Text type="secondary" className="footer-text-premium">
                © 2024 La Belle Porte. Tous droits réservés.
              </Text>
              <Text type="secondary" className="footer-version-premium">
                Version 1.0.0
              </Text>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
