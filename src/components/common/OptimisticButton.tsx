/**
 * Bouton avec feedback visuel optimiste
 * Affiche immédiatement un état de chargement avant la confirmation serveur
 */

import React from 'react'
import { Button, DatePicker } from 'antd'
import type { ButtonProps } from 'antd'
import { LoadingOutlined } from '@ant-design/icons'
import './OptimisticButton.css'

interface OptimisticButtonProps extends ButtonProps {
  isOptimistic?: boolean // Indique si l'action est en cours (optimiste)
  optimisticLabel?: string // Label à afficher pendant l'action optimiste
}

export const OptimisticButton: React.FC<OptimisticButtonProps> = ({
  isOptimistic = false,
  optimisticLabel,
  children,
  loading,
  disabled,
  icon,
  ...props
}) => {
  const displayLoading = isOptimistic || loading
  const displayDisabled = disabled || isOptimistic

  return (
    <Button
      {...props}
      loading={displayLoading}
      disabled={displayDisabled}
      icon={displayLoading ? <LoadingOutlined /> : icon}
      className={`optimistic-button ${isOptimistic ? 'optimistic-active' : ''} ${props.className || ''}`}
    >
      {displayLoading && optimisticLabel ? optimisticLabel : children}
    </Button>
  )
}
