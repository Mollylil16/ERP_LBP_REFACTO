import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { Tour, Typography } from 'antd'
import { useAuth } from '@hooks/useAuth'
import { usePermissions } from '@hooks/usePermissions'
import { PERMISSIONS } from '@constants/permissions'

const { Paragraph } = Typography

const STORAGE_PREFIX = 'lbp_exports_tour_v1_'

type ExportsTourCtx = {
  startExportsTour: () => void
  resetExportsTourForCurrentUser: () => void
}

const Ctx = createContext<ExportsTourCtx | null>(null)

export function useExportsTour(): ExportsTourCtx {
  const v = useContext(Ctx)
  if (!v) {
    throw new Error('useExportsTour doit être utilisé sous ExportsTourProvider')
  }
  return v
}

function storageKey(userId: number) {
  return `${STORAGE_PREFIX}${userId}`
}

/**
 * Visite guidée dédiée aux exports/états.
 * Objectif: montrer uniquement "où aller" + "où cliquer" pour générer PDF/Excel + soumission direction.
 */
export const ExportsTourProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { user, isAuthenticated } = useAuth()
  const { hasPermission, isLoading: permsLoading } = usePermissions()
  const canUse = hasPermission(PERMISSIONS.RAPPORTS.VIEW)
  const [open, setOpen] = useState(false)

  const startExportsTour = useCallback(() => setOpen(true), [])
  const resetExportsTourForCurrentUser = useCallback(() => {
    if (user?.id) localStorage.removeItem(storageKey(user.id))
    setOpen(true)
  }, [user?.id])

  useEffect(() => {
    if (!isAuthenticated || !user?.id || permsLoading) return
    if (!canUse) return
    if (localStorage.getItem(storageKey(user.id)) === '1') return
    const t = window.setTimeout(() => setOpen(true), 900)
    return () => window.clearTimeout(t)
  }, [isAuthenticated, user?.id, permsLoading, canUse])

  const steps = useMemo((): React.ComponentProps<typeof Tour>['steps'] => {
    const q = (sel: string) => document.querySelector(sel) as HTMLElement | null
    return [
      {
        title: 'Tuto États & exports',
        description: (
          <div>
            <Paragraph style={{ marginBottom: 8 }}>
              Cette visite montre <strong>uniquement</strong> comment tirer vos états (PDF/Excel) et où soumettre
              à la direction.
            </Paragraph>
            <Paragraph type="secondary" style={{ marginBottom: 0, fontSize: 13 }}>
              Si un bouton n’apparaît pas, c’est souvent lié aux permissions de votre compte.
            </Paragraph>
          </div>
        ),
        target: null,
        placement: 'center',
      },
      {
        title: 'Raccourci “État du jour”',
        description:
          'Depuis le tableau de bord, ce bouton ouvre directement un état prêt à exporter en PDF/Excel.',
        target: () => q('[data-onboarding="etat-jour-btn"]') ?? q('[data-onboarding="main-content"]') ?? document.body,
        placement: 'bottom',
        scrollIntoViewOptions: { block: 'center' },
      },
      {
        title: 'Où trouver “État agence complet”',
        description:
          'Dans le menu “Rapports & analyse”, ouvrez “État agence complet”.',
        target: () => q('[data-onboarding="sidebar"]') ?? q('[data-onboarding="main-content"]') ?? document.body,
        placement: 'right',
      },
      {
        title: 'Générer (obligatoire avant export)',
        description:
          'Choisissez la période puis cliquez “Générer”. Les exports s’activent ensuite.',
        target: () => q('[data-onboarding="etat-agence-generate"]') ?? q('[data-onboarding="etat-agence-form"]') ?? q('[data-onboarding="main-content"]') ?? document.body,
        placement: 'top',
        scrollIntoViewOptions: { block: 'center' },
      },
      {
        title: 'Exporter PDF / Excel',
        description:
          'Utilisez ces deux boutons pour télécharger un PDF unique (brandé LBP) ou un Excel unique (multi-feuilles).',
        target: () => q('[data-onboarding="etat-agence-export-pdf"]') ?? q('[data-onboarding="etat-agence-form"]') ?? q('[data-onboarding="main-content"]') ?? document.body,
        placement: 'top',
        scrollIntoViewOptions: { block: 'center' },
      },
      {
        title: 'Soumettre à la direction',
        description:
          'Dans “Supervision réseau → Rapports direction”, remplissez le formulaire puis cliquez “Soumettre au directeur”.',
        target: () => q('[data-onboarding="soumission-direction-card"]') ?? q('[data-onboarding="main-content"]') ?? document.body,
        placement: 'top',
        scrollIntoViewOptions: { block: 'center' },
      },
    ]
  }, [])

  const handleFinish = () => {
    if (user?.id) localStorage.setItem(storageKey(user.id), '1')
    setOpen(false)
  }

  return (
    <Ctx.Provider value={{ startExportsTour, resetExportsTourForCurrentUser }}>
      {children}
      <Tour
        open={open}
        onClose={() => setOpen(false)}
        onFinish={handleFinish}
        steps={steps}
        type="primary"
        zIndex={11000}
        rootClassName="lbp-exports-tour"
        mask={{ color: 'rgba(15, 23, 42, 0.55)' }}
      />
    </Ctx.Provider>
  )
}

