import React from 'react'
import { Button, Tooltip } from 'antd'

const DEFAULT_HINT =
  'Cette action est enregistrée dans le journal d’audit (horodatage, utilisateur, détails métier).'

export type TracedActionButtonProps = React.ComponentProps<typeof Button> & {
  /** Texte du survol ; par défaut message générique d’audit */
  traceHint?: string
}

/**
 * Bouton avec rappel UX : l’action correspond à une trace côté serveur (audit métier ou HTTP).
 */
export const TracedActionButton = React.forwardRef<HTMLButtonElement, TracedActionButtonProps>(
  ({ traceHint, children, ...props }, ref) => {
    return (
      <Tooltip title={traceHint ?? DEFAULT_HINT} placement="top">
        <span style={{ display: 'inline-block' }}>
          <Button ref={ref} {...props}>
            {children}
          </Button>
        </span>
      </Tooltip>
    )
  },
)

TracedActionButton.displayName = 'TracedActionButton'
