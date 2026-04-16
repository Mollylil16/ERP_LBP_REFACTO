/**
 * Utilitaires de formatage
 */

import { format, parse, parseISO, isValid } from 'date-fns'
import { fr } from 'date-fns/locale'
import { APP_CONFIG } from '@constants/application'

/**
 * Normalise une date « calendrier » (SQL, ISO, héritée) vers `YYYY-MM-DD` pour affichage cohérent.
 * Utilisé par les adaptateurs colis / factures pour éviter les « — » quand le backend renvoie une autre forme.
 */
export function normalizeCalendarDate(raw: unknown): string | undefined {
  if (raw == null || raw === '') return undefined
  if (typeof raw === 'string') {
    const m = raw.match(/^(\d{4}-\d{2}-\d{2})/)
    if (m) return m[1]
    const fr = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})$/)
    if (fr) return `${fr[3]}-${fr[2]}-${fr[1]}`
  }
  try {
    const d = new Date(raw as string | Date)
    if (Number.isNaN(d.getTime())) return undefined
    return d.toISOString().split('T')[0]
  } catch {
    return undefined
  }
}

function toDateSafe(input: string | Date): Date | null {
  if (input instanceof Date) return isValid(input) ? input : null
  const s = String(input).trim()
  if (!s) return null

  // Date calendrier seule (évite le décalage jour avec parseISO / UTC minuit)
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
    const [y, m, d] = s.split('-').map(Number)
    const local = new Date(y, m - 1, d)
    return isValid(local) ? local : null
  }

  // 1) ISO / ISO datetime
  try {
    const d = parseISO(s)
    if (isValid(d)) return d
  } catch {
    // ignore
  }

  // 2) "DD/MM/YYYY"
  try {
    const d = parse(s, 'dd/MM/yyyy', new Date())
    if (isValid(d)) return d
  } catch {
    // ignore
  }

  // 3) Fallback Date constructor (e.g. "2026-04-09 10:15:00")
  const d = new Date(s)
  return isValid(d) ? d : null
}

/**
 * Formate une date au format DD/MM/YYYY
 */
export function formatDate(date: string | Date | null | undefined): string {
  if (!date) return '-'

  try {
    const dateObj = toDateSafe(date)
    if (!dateObj) return '-'
    return format(dateObj, APP_CONFIG.dateFormat, { locale: fr })
  } catch {
    return '-'
  }
}

/**
 * Formate une date avec heure au format DD/MM/YYYY HH:mm
 */
export function formatDateTime(date: string | Date | null | undefined): string {
  if (!date) return '-'

  try {
    const dateObj = toDateSafe(date)
    if (!dateObj) return '-'
    return format(dateObj, APP_CONFIG.dateTimeFormat, { locale: fr })
  } catch {
    return '-'
  }
}

/**
 * Formate un montant en FCFA avec séparateurs
 */
export function formatMontant(montant: number | string | null | undefined): string {
  if (montant === null || montant === undefined) return '0'

  const num = typeof montant === 'string' ? parseFloat(montant) : montant

  if (isNaN(num)) return '0'

  // Utiliser des points comme séparateurs de milliers pour répondre à la demande utilisateur (1.067.500)
  return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".")
}

/**
 * Formate un montant avec devise
 */
export function formatMontantWithDevise(montant: number | string | null | undefined, devise?: string): string {
  return `${formatMontant(montant)} ${devise || APP_CONFIG.devise}`
}

/**
 * Formate un numéro de téléphone
 */
export function formatPhone(phone: string | null | undefined): string {
  if (!phone) return '-'

  // Format: +225 XX XX XX XX XX
  const cleaned = phone.replace(/\D/g, '')
  if (cleaned.length === 10) {
    return cleaned.replace(/(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/, '+225 $1 $2 $3 $4 $5')
  }
  if (cleaned.length === 13 && cleaned.startsWith('225')) {
    return cleaned.replace(/(\d{3})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/, '+$1 $2 $3 $4 $5 $6')
  }

  return phone
}

/**
 * Formate un email (masquage partiel si nécessaire)
 */
export function formatEmail(email: string | null | undefined): string {
  if (!email) return '-'
  return email
}

/**
 * Formate une référence colis
 */
export function formatRefColis(ref: string | null | undefined): string {
  if (!ref) return '-'
  return ref.toUpperCase()
}

/**
 * Tronque un texte avec ellipsis
 */
export function truncate(text: string | null | undefined, maxLength: number = 50): string {
  if (!text) return '-'
  if (text.length <= maxLength) return text
  return text.substring(0, maxLength) + '...'
}

/**
 * Formate un statut pour affichage
 */
export function formatStatus(status: string | number): string {
  if (typeof status === 'number') {
    return status === 1 ? 'Actif' : 'Inactif'
  }

  return status
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
    .join(' ')
}
