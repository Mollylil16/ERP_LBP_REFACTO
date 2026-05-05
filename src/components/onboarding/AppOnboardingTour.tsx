import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react'
import { Tour, Typography } from 'antd'
import { useAuth } from '@hooks/useAuth'
import { usePermissions } from '@hooks/usePermissions'
import { RoleSummary } from './RoleSummary'
import './AppOnboardingTour.css'

const { Paragraph } = Typography

const STORAGE_PREFIX = 'lbp_onboarding_tour_v1_'

type OnboardingCtx = {
  startTour: () => void
  resetTourForCurrentUser: () => void
  /** Alias UX: relancer la visite guidée depuis un bouton flottant */
  showTourHelp: () => void
}

const Ctx = createContext<OnboardingCtx | null>(null)

export function useOnboardingTour(): OnboardingCtx {
  const v = useContext(Ctx)
  if (!v) {
    throw new Error('useOnboardingTour doit être utilisé sous OnboardingTourProvider')
  }
  return v
}

function storageKey(userId: number) {
  return `${STORAGE_PREFIX}${userId}`
}

export const OnboardingTourProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { user, isAuthenticated } = useAuth()
  const { isLoading: permsLoading } = usePermissions()
  const [open, setOpen] = useState(false)

  const startTour = useCallback(() => setOpen(true), [])
  const showTourHelp = useCallback(() => setOpen(true), [])
  const resetTourForCurrentUser = useCallback(() => {
    if (user?.id) {
      localStorage.removeItem(storageKey(user.id))
    }
    setOpen(true)
  }, [user?.id])

  useEffect(() => {
    if (!isAuthenticated || !user?.id || permsLoading) return
    if (localStorage.getItem(storageKey(user.id)) === '1') return
    const t = window.setTimeout(() => setOpen(true), 700)
    return () => window.clearTimeout(t)
  }, [isAuthenticated, user?.id, permsLoading])

  const steps = useMemo((): React.ComponentProps<typeof Tour>['steps'] => {
    const q = (sel: string) => document.querySelector(sel) as HTMLElement | null

    return [
      {
        title: 'Bienvenue sur La Belle Porte',
        description: (
          <div>
            <Paragraph style={{ marginBottom: 8 }}>
              Cette visite courte vous montre où trouver l’essentiel : navigation, contenu, notifications
              et votre profil. Vous pourrez la relancer à tout moment depuis le menu utilisateur.
            </Paragraph>
            <Paragraph type="secondary" style={{ marginBottom: 0, fontSize: 13 }}>
              Astuce : les menus et boutons que vous voyez dépendent de{' '}
              <strong>vos droits réels</strong> (chargés depuis le serveur), pas seulement de votre intitulé
              de rôle.
            </Paragraph>
          </div>
        ),
        target: null,
        placement: 'center',
      },
      {
        title: 'Menu principal',
        description:
          'Toutes les sections métier (colis, facturation, caisse, litiges, etc.) sont regroupées ici. Seules les entrées autorisées pour votre compte apparaissent.',
        target: () => q('[data-onboarding="sidebar"]') ?? q('#main-content') ?? document.body,
        placement: 'right',
        scrollIntoViewOptions: { block: 'center' },
      },
      {
        title: 'Tirer un état (PDF/Excel) — le plus simple',
        description:
          "Sur le tableau de bord, utilisez ce raccourci pour ouvrir directement l'état du jour. Ensuite vous pourrez choisir PDF ou Excel.",
        target: () => q('[data-onboarding="etat-jour-btn"]') ?? q('#main-content') ?? document.body,
        placement: 'bottom',
        scrollIntoViewOptions: { block: 'center' },
      },
      {
        title: 'État agence complet (un seul document)',
        description:
          "Dans « Rapports & analyse », ouvrez « État agence complet ». C’est l’export unique qui regroupe colis + factures + paiements + caisse.",
        target: () => q('[data-onboarding="sidebar"]') ?? q('#main-content') ?? document.body,
        placement: 'right',
      },
      {
        title: 'Générer puis exporter',
        description:
          "1) Choisissez la période puis cliquez « Générer ». 2) Une fois les données chargées, cliquez « Exporter PDF » ou « Exporter Excel ».",
        target: () => q('[data-onboarding="etat-agence-form"]') ?? q('#main-content') ?? document.body,
        placement: 'top',
        scrollIntoViewOptions: { block: 'center' },
      },
      {
        title: 'Zone de travail',
        description:
          'Le contenu de la page sélectionnée s’affiche ici. Les tableaux, formulaires et graphiques sont mis à jour en direct selon vos actions.',
        target: () => q('[data-onboarding="main-content"]') ?? q('#main-content') ?? document.body,
        placement: 'left',
        scrollIntoViewOptions: { block: 'start' },
      },
      {
        title: 'Notifications',
        description:
          'Les alertes et rappels apparaissent ici. Cliquez sur la cloche pour consulter l’historique sans quitter votre travail en cours.',
        target: () => q('[data-onboarding="notifications"]') ?? q('#main-content') ?? document.body,
        placement: 'bottom',
      },
      {
        title: 'Profil et paramètres',
        description:
          'Accédez à votre profil, aux paramètres (si votre rôle le permet) et à la déconnexion. La visite guidée est aussi disponible dans ce menu.',
        target: () => q('[data-onboarding="user-menu"]') ?? q('#main-content') ?? document.body,
        placement: 'bottomRight',
      },
      {
        title: 'Soumettre à la direction (DG / Assistant DG)',
        description:
          "Dans « Supervision réseau », onglet « Rapports direction », vous pouvez soumettre un rapport. Le bouton ci-dessous déclenche la soumission et la notification.",
        target: () => q('[data-onboarding="soumission-direction-card"]') ?? q('#main-content') ?? document.body,
        placement: 'top',
        scrollIntoViewOptions: { block: 'center' },
      },
      {
        title: 'Vos accès (résumé)',
        description: (
          <div>
            <Paragraph style={{ marginBottom: 12 }}>
              Voici un aperçu factuel des permissions associées à votre session. C’est la même source que
              celle utilisée pour afficher ou masquer les menus.
            </Paragraph>
            <RoleSummary variant="compact" />
          </div>
        ),
        target: null,
        placement: 'center',
      },
    ]
  }, [])

  const handleFinish = () => {
    if (user?.id) {
      localStorage.setItem(storageKey(user.id), '1')
    }
    setOpen(false)
  }

  const handleClose = () => {
    setOpen(false)
  }

  const ctx = useMemo(
    () => ({ startTour, resetTourForCurrentUser, showTourHelp }),
    [startTour, resetTourForCurrentUser, showTourHelp],
  )

  return (
    <Ctx.Provider value={ctx}>
      {children}
      <Tour
        open={open}
        onClose={handleClose}
        onFinish={handleFinish}
        steps={steps}
        type="primary"
        zIndex={11000}
        rootClassName="lbp-onboarding-tour"
        mask={{ color: 'rgba(15, 23, 42, 0.55)' }}
      />
    </Ctx.Provider>
  )
}
