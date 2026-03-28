import React, { useMemo } from 'react'
import { Alert, Collapse, Space, Tag, Typography } from 'antd'
import { SafetyCertificateOutlined } from '@ant-design/icons'
import { useAuth } from '@hooks/useAuth'
import { usePermissions } from '@hooks/usePermissions'

const { Text, Paragraph } = Typography

export type RoleSummaryVariant = 'compact' | 'full'

function roleLabel(user: ReturnType<typeof useAuth>['user']): string {
  if (!user?.role) return '—'
  if (typeof user.role === 'string') return user.role
  return user.role.name || user.role.code || '—'
}

function groupPermissions(codes: string[]): Map<string, string[]> {
  const map = new Map<string, string[]>()
  for (const code of codes) {
    if (!code || code === '*') continue
    const dot = code.indexOf('.')
    const mod = dot > 0 ? code.slice(0, dot) : 'autre'
    const list = map.get(mod) ?? []
    list.push(code)
    map.set(mod, list)
  }
  for (const [, list] of map) {
    list.sort((a, b) => a.localeCompare(b, 'fr'))
  }
  return new Map([...map.entries()].sort((a, b) => a[0].localeCompare(b[0], 'fr')))
}

export interface RoleSummaryProps {
  variant?: RoleSummaryVariant
}

/**
 * Résumé des droits réellement chargés (API / cache), sans inventer de permissions.
 */
export const RoleSummary: React.FC<RoleSummaryProps> = ({ variant = 'full' }) => {
  const { user } = useAuth()
  const { permissions, isLoading } = usePermissions()

  const grouped = useMemo(() => groupPermissions(permissions), [permissions])
  const isFullAccess = permissions.includes('*')

  if (isLoading) {
    return <Text type="secondary">Chargement de vos accès…</Text>
  }

  const dense = variant === 'compact'

  return (
    <div className={dense ? 'role-summary role-summary--compact' : 'role-summary'}>
      <Space direction="vertical" size={dense ? 'small' : 'middle'} style={{ width: '100%' }}>
        <Space align="center" size="small" wrap>
          <SafetyCertificateOutlined style={{ color: 'var(--ant-color-primary)' }} />
          <Text strong>Rôle affiché :</Text>
          <Tag color="blue">{roleLabel(user)}</Tag>
          {user?.role && typeof user.role === 'object' && user.role.code ? (
            <Text type="secondary" style={{ fontSize: 12 }}>
              ({user.role.code})
            </Text>
          ) : null}
        </Space>

        {isFullAccess ? (
          <Alert
            type="info"
            showIcon
            message="Accès étendu"
            description="Votre compte dispose d’un accès complet aux modules configurés dans l’application. Les menus visibles restent filtrés par l’équipe produit."
          />
        ) : permissions.length === 0 ? (
          <Alert
            type="warning"
            showIcon
            message="Aucune permission listée"
            description="Si les menus sont vides, contactez un administrateur pour vérifier les droits attachés à votre rôle en base."
          />
        ) : (
          <>
            <Paragraph type="secondary" style={{ marginBottom: dense ? 4 : 8, fontSize: dense ? 13 : 14 }}>
              Ci-dessous, les <Text strong>codes permission</Text> renvoyés par le serveur pour votre
              session (ce à quoi le menu et les écrans se réfèrent).
            </Paragraph>
            <Collapse
              size="small"
              className="role-summary-collapse"
              items={[...grouped.entries()].map(([module, codes]) => ({
                key: module,
                label: (
                  <Text strong>
                    {module}
                    <Text type="secondary" style={{ marginLeft: 8, fontWeight: 400 }}>
                      ({codes.length})
                    </Text>
                  </Text>
                ),
                children: (
                  <Space size={[4, 4]} wrap>
                    {codes.map((c) => (
                      <Tag key={c} style={{ margin: 0, fontSize: 11 }}>
                        {c}
                      </Tag>
                    ))}
                  </Space>
                ),
              }))}
              defaultActiveKey={dense ? [] : [...grouped.keys()].slice(0, 3)}
            />
          </>
        )}
      </Space>
    </div>
  )
}
