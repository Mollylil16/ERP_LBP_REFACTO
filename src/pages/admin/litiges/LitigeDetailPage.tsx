import React, { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import {
  Button,
  Card,
  Descriptions,
  Input,
  Space,
  Spin,
  Tag,
  Typography,
  Checkbox,
} from 'antd'
import type { CheckboxChangeEvent } from 'antd/es/checkbox'
import { ArrowLeftOutlined } from '@ant-design/icons'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { litigesService } from '@services/litiges.service'
import { formatDate } from '@utils/format'
import { EmptyErrorState } from '@components/common/EmptyState'
import { usePermissions } from '@hooks/usePermissions'
import { PERMISSIONS } from '@constants/permissions'
import type { LitigeDetail, LitigeMessageItem } from '@types'

const { Title, Text } = Typography

function auteurLabel(m: LitigeMessageItem): string {
  const a = m.auteur
  if (!a) return 'Système'
  return a.nom_complet || a.username || `Utilisateur #${a.id}`
}

export const LitigeDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>()
  const litigeId = id ? Number.parseInt(id, 10) : NaN
  const queryClient = useQueryClient()
  const { hasPermission } = usePermissions()
  const canPost = hasPermission(PERMISSIONS.LITIGES.CREATE)
  const [draft, setDraft] = useState('')
  const [interne, setInterne] = useState(false)

  const { data, isLoading, error, refetch } = useQuery<LitigeDetail>({
    queryKey: ['litige', litigeId],
    queryFn: () => litigesService.getById(litigeId),
    enabled: Number.isFinite(litigeId),
  })

  const sendMutation = useMutation({
    mutationFn: () =>
      litigesService.addMessage(litigeId, {
        contenu: draft.trim(),
        interne,
      }),
    onSuccess: () => {
      setDraft('')
      setInterne(false)
      void queryClient.invalidateQueries({ queryKey: ['litige', litigeId] })
    },
  })

  if (!Number.isFinite(litigeId)) {
    return (
      <div>
        <Title level={2}>Litige</Title>
        <Text type="danger">Identifiant invalide.</Text>
      </div>
    )
  }

  if (error) {
    return <EmptyErrorState onRetry={() => refetch()} />
  }

  if (isLoading || !data) {
    return (
      <div style={{ textAlign: 'center', padding: 48 }}>
        <Spin size="large" />
      </div>
    )
  }

  const litige = data
  const messages = litige.messages ?? []

  return (
    <div>
      <Space style={{ marginBottom: 16 }}>
        <Link to="/litiges">
          <Button icon={<ArrowLeftOutlined />}>Retour à la liste</Button>
        </Link>
      </Space>
      <Title level={2}>
        Litige {litige.num_litige}
        <Tag style={{ marginLeft: 12 }}>{litige.statut}</Tag>
      </Title>

      <Card title="Fiche" style={{ marginBottom: 16 }}>
        <Descriptions column={{ xs: 1, sm: 2 }} size="small" bordered>
          <Descriptions.Item label="Type">{litige.type}</Descriptions.Item>
          <Descriptions.Item label="Priorité">{litige.priorite ?? '—'}</Descriptions.Item>
          <Descriptions.Item label="Objet" span={2}>
            {litige.objet}
          </Descriptions.Item>
          <Descriptions.Item label="Description" span={2}>
            {litige.description ?? '—'}
          </Descriptions.Item>
          <Descriptions.Item label="Client">
            {litige.client?.nom_exp ?? '—'}
          </Descriptions.Item>
          <Descriptions.Item label="Agence">
            {litige.agence?.nom ?? litige.agence?.code ?? '—'}
          </Descriptions.Item>
          <Descriptions.Item label="Colis">
            {litige.colis?.ref_colis ? `#${litige.colis.ref_colis}` : '—'}
          </Descriptions.Item>
          <Descriptions.Item label="Créé le">{formatDate(litige.created_at)}</Descriptions.Item>
          <Descriptions.Item label="Contact">
            {[litige.contact_nom, litige.contact_telephone, litige.contact_email]
              .filter(Boolean)
              .join(' · ') || '—'}
          </Descriptions.Item>
          <Descriptions.Item label="Assigné">
            {litige.assigne
              ? litige.assigne.nom_complet || litige.assigne.username || `#${litige.assigne.id}`
              : '—'}
          </Descriptions.Item>
        </Descriptions>
      </Card>

      <Card title="Fil de discussion">
        <Space direction="vertical" style={{ width: '100%' }} size="middle">
          {messages.length === 0 ? (
            <Text type="secondary">Aucun message pour l'instant.</Text>
          ) : (
            messages.map((m) => (
              <div
                key={m.id}
                style={{
                  padding: '10px 12px',
                  background: m.interne ? 'rgba(250, 173, 20, 0.08)' : '#fafafa',
                  borderRadius: 8,
                  borderLeft: m.interne ? '3px solid #faad14' : undefined,
                }}
              >
                <Space wrap size="small" style={{ marginBottom: 4 }}>
                  <Text strong>{auteurLabel(m)}</Text>
                  <Text type="secondary">{formatDate(m.created_at)}</Text>
                  <Tag>{m.type}</Tag>
                  {m.interne ? <Tag color="orange">Interne</Tag> : null}
                </Space>
                <div style={{ whiteSpace: 'pre-wrap' }}>{m.contenu}</div>
              </div>
            ))
          )}

          {canPost ? (
            <div style={{ marginTop: 16 }}>
              <Input.TextArea
                rows={3}
                value={draft}
                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setDraft(e.target.value)}
                placeholder="Ajouter un message…"
                maxLength={8000}
                showCount
              />
              <Space style={{ marginTop: 8 }} wrap>
                <Checkbox checked={interne} onChange={(e: CheckboxChangeEvent) => setInterne(e.target.checked)}>
                  Message interne (équipe uniquement)
                </Checkbox>
                <Button
                  type="primary"
                  loading={sendMutation.isPending}
                  disabled={!draft.trim()}
                  onClick={() => sendMutation.mutate()}
                >
                  Envoyer
                </Button>
              </Space>
            </div>
          ) : null}
        </Space>
      </Card>
    </div>
  )
}
