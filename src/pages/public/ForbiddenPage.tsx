import React from 'react'
import { useNavigate, useLocation } from 'react-router-dom'
import { Button, Result } from 'antd'
import { HomeOutlined, ArrowLeftOutlined } from '@ant-design/icons'

export const ForbiddenPage: React.FC = () => {
  const navigate = useNavigate()
  const location = useLocation()
  const from = (location.state as { from?: string } | null)?.from

  return (
    <div style={{ minHeight: '60vh', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 24 }}>
      <Result
        status="403"
        title="403"
        subTitle="Vous n'avez pas les droits nécessaires pour accéder à cette page."
        extra={[
          <Button type="primary" key="home" icon={<HomeOutlined />} onClick={() => navigate('/dashboard', { replace: true })}>
            Tableau de bord
          </Button>,
          from ? (
            <Button key="back" icon={<ArrowLeftOutlined />} onClick={() => navigate(-1)}>
              Retour
            </Button>
          ) : null,
        ].filter(Boolean)}
      />
    </div>
  )
}
