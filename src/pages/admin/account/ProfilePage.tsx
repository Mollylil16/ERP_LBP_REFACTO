import React, { useEffect, useState } from 'react'
import { Typography, Card, Form, Input, Button, message, Descriptions } from 'antd'
import { SaveOutlined } from '@ant-design/icons'
import { useAuth } from '@hooks/useAuth'
import { usersService } from '@services/users.service'
import { RoleSummary } from '@components/onboarding/RoleSummary'

const { Title } = Typography

export const ProfilePage: React.FC = () => {
  const { user, refreshUser } = useAuth()
  const [form] = Form.useForm()
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    if (!user?.id) return
    usersService
      .getById(user.id)
      .then((u: any) => {
        form.setFieldsValue({
          email: u.email ?? '',
          phone: u.phone ?? '',
        })
      })
      .catch(() => {
        form.setFieldsValue({
          email: user.email ?? '',
          phone: user.phone ?? '',
        })
      })
  }, [user?.id, user?.email, user?.phone, form])

  const onFinish = async (values: { email?: string; phone?: string }) => {
    if (!user?.id) return
    setLoading(true)
    try {
      await usersService.updateMyProfile({
        email: values.email?.trim() || null,
        phone: values.phone?.trim() || null,
      })
      message.success('Profil mis à jour')
      await refreshUser()
    } catch (e: any) {
      message.error(e?.message || 'Impossible de mettre à jour le profil')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="page-container">
      <Title level={2}>Mon profil</Title>
      <Card style={{ maxWidth: 560 }}>
        <Descriptions column={1} size="small" style={{ marginBottom: 24 }}>
          <Descriptions.Item label="Nom complet">{user?.nom_complet}</Descriptions.Item>
          <Descriptions.Item label="Identifiant">{user?.username}</Descriptions.Item>
          <Descriptions.Item label="Rôle">
            {typeof user?.role === 'string' ? user.role : user?.role?.name}
          </Descriptions.Item>
        </Descriptions>
        <Form form={form} layout="vertical" onFinish={onFinish}>
          <Form.Item
            name="email"
            label="E-mail"
            rules={[{ type: 'email', message: 'E-mail invalide' }]}
          >
            <Input type="email" placeholder="optionnel" />
          </Form.Item>
          <Form.Item name="phone" label="Téléphone / contact">
            <Input placeholder="+225 …" />
          </Form.Item>
          <Button type="primary" htmlType="submit" icon={<SaveOutlined />} loading={loading}>
            Enregistrer
          </Button>
        </Form>
      </Card>

      <Card title="Vos accès (résumé)" style={{ maxWidth: 720, marginTop: 24 }}>
        <RoleSummary variant="full" />
      </Card>
    </div>
  )
}
