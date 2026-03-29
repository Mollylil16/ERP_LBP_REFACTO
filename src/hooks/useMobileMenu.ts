/**
 * Hook pour gérer le menu mobile
 * Ouvre/ferme la sidebar sur mobile avec overlay
 */

import { useState, useEffect, useCallback } from 'react'

interface UseMobileMenuOptions {
  defaultOpen?: boolean
  breakpoint?: number
}

/**
 * Hook pour gérer le menu mobile responsive
 */
export function useMobileMenu(options: UseMobileMenuOptions = {}) {
  /** ≤ breakpoint : navigation en tiroir (téléphone + tablette portrait / étroit) */
  const { defaultOpen = false, breakpoint = 1023 } = options
  const [isMobile, setIsMobile] = useState(false)
  const [isMenuOpen, setIsMenuOpen] = useState(defaultOpen)

  // Détecter si on est sur mobile
  useEffect(() => {
    const checkMobile = () => {
      setIsMobile(window.innerWidth <= breakpoint)
      if (window.innerWidth > breakpoint) {
        setIsMenuOpen(false) // Fermer le menu si on repasse en desktop
      }
    }

    checkMobile()
    window.addEventListener('resize', checkMobile)

    return () => {
      window.removeEventListener('resize', checkMobile)
    }
  }, [breakpoint])

  const openMenu = useCallback(() => setIsMenuOpen(true), [])
  const closeMenu = useCallback(() => setIsMenuOpen(false), [])
  const toggleMenu = useCallback(() => setIsMenuOpen((prev) => !prev), [])

  return {
    isMobile,
    isMenuOpen,
    openMenu,
    closeMenu,
    toggleMenu,
  }
}
