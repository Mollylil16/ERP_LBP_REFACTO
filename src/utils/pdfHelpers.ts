/**
 * Utilitaires partagés pour la génération de PDFs LBP.
 *
 * - fmtPdf / fmtPdfNum : formateurs montants sûrs pour jsPDF
 *   (toLocaleString('fr-FR') produit U+202F → rendu "/" dans Helvetica)
 * - loadLogoBase64       : charge le logo public/logo_lbp.png en base64 (mis en cache)
 * - drawLBPHeader        : en-tête professionnel avec logo, titre, sous-titre
 * - drawLBPFooters       : pied de page avec numérotation sur toutes les pages
 */

import jsPDF from 'jspdf'
import dayjs from 'dayjs'

// ─── Palette LBP ──────────────────────────────────────────────────────────────
export const C_DARK:  [number, number, number] = [10,  37,  64]   // bleu marine foncé
export const C_BLUE:  [number, number, number] = [22,  119, 255]  // bleu LBP
export const C_GOLD:  [number, number, number] = [180, 130, 20]   // accent doré
export const C_ROW:   [number, number, number] = [237, 244, 255]  // ligne alternée
export const C_TEXT:  [number, number, number] = [30,  40,  55]   // texte principal

// ─── Formateurs sûrs pour jsPDF ───────────────────────────────────────────────
// toLocaleString('fr-FR') => U+202F (espace fine insécable) non pris en charge
// par Helvetica/Times intégré dans jsPDF → affiché comme "/" ou "?".
// On utilise une espace ASCII ordinaire comme séparateur de milliers.

export function fmtPdf(n: number | string | null | undefined): string {
  const num = Math.round(Number(n ?? 0))
  if (isNaN(num)) return '0 FCFA'
  return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA'
}

export function fmtPdfNum(n: number | string | null | undefined): string {
  const num = Math.round(Number(n ?? 0))
  if (isNaN(num)) return '0'
  return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ')
}

// ─── Chargement du logo (mis en cache après le premier appel) ─────────────────
let _logoCache: string | null | undefined = undefined

export async function loadLogoBase64(): Promise<string | null> {
  if (_logoCache !== undefined) return _logoCache
  try {
    const res = await fetch('/logo_lbp.png')
    if (!res.ok) { _logoCache = null; return null }
    const blob = await res.blob()
    return new Promise<string | null>((resolve) => {
      const reader = new FileReader()
      reader.onload  = () => { _logoCache = reader.result as string; resolve(_logoCache) }
      reader.onerror = () => { _logoCache = null; resolve(null) }
      reader.readAsDataURL(blob)
    })
  } catch {
    _logoCache = null
    return null
  }
}

// ─── En-tête professionnel LBP ────────────────────────────────────────────────
export interface LBPHeaderOpts {
  title:     string
  subtitle?: string
  rightInfo?: string   // ex: "ÉTAT DE CAISSE — 01/01/2026"
  logoBase64?: string | null
}

/**
 * Dessine l'en-tête LBP et retourne la coordonnée Y à utiliser pour
 * le premier contenu (tableaux, textes…).
 */
export function drawLBPHeader(doc: jsPDF, opts: LBPHeaderOpts): number {
  const pw  = doc.internal.pageSize.getWidth()
  const ml  = 14
  const mr  = 14
  const H   = 22   // hauteur de la bande principale
  const H2  = 1.5  // épaisseur de l'accent doré

  // Bande bleu marine
  doc.setFillColor(...C_DARK)
  doc.rect(0, 0, pw, H, 'F')

  // Accent doré
  doc.setFillColor(...C_GOLD)
  doc.rect(0, H, pw, H2, 'F')

  // Logo ou fallback texte
  if (opts.logoBase64) {
    try {
      doc.addImage(opts.logoBase64, 'PNG', ml, 4, 28, 14)
    } catch {
      _drawFallbackBrand(doc, ml)
    }
  } else {
    _drawFallbackBrand(doc, ml)
  }

  // Titre (droite, ligne 1)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(10.5)
  doc.setTextColor(255, 255, 255)
  doc.text(opts.title.toUpperCase(), pw - mr, 9.5, { align: 'right' })

  // Sous-titre / info droite (ligne 2 + 3)
  if (opts.subtitle) {
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(8)
    doc.setTextColor(190, 215, 255)
    doc.text(opts.subtitle, pw - mr, 15, { align: 'right' })
  }
  if (opts.rightInfo) {
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(7)
    doc.setTextColor(160, 195, 245)
    doc.text(opts.rightInfo, pw - mr, 20.5, { align: 'right' })
  }

  doc.setTextColor(...C_TEXT)
  doc.setFont('helvetica', 'normal')

  return H + H2 + 6   // Y de départ pour le contenu
}

function _drawFallbackBrand(doc: jsPDF, ml: number) {
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(12)
  doc.setTextColor(255, 255, 255)
  doc.text('LA BELLE PORTE', ml, 10.5)
  doc.setFont('helvetica', 'normal')
  doc.setFontSize(7)
  doc.setTextColor(190, 215, 255)
  doc.text('LBP — Gestion de colis', ml, 17)
}

// ─── Pied de page sur toutes les pages ────────────────────────────────────────
export function drawLBPFooters(doc: jsPDF): void {
  const pw    = doc.internal.pageSize.getWidth()
  const ph    = doc.internal.pageSize.getHeight()
  const total = (doc.internal as any).getNumberOfPages()
  const now   = dayjs().format('DD/MM/YYYY HH:mm')

  for (let p = 1; p <= total; p++) {
    doc.setPage(p)
    doc.setDrawColor(180, 190, 210)
    doc.line(14, ph - 12, pw - 14, ph - 12)
    doc.setFontSize(6.5)
    doc.setTextColor(130, 140, 160)
    doc.setFont('helvetica', 'normal')
    doc.text(
      `LA BELLE PORTE (LBP) — Document confidentiel — Genere le ${now}`,
      pw / 2, ph - 7, { align: 'center' },
    )
    doc.text(`Page ${p} / ${total}`, pw - 14, ph - 7, { align: 'right' })
  }
}

// ─── Styles autoTable standards LBP ──────────────────────────────────────────
export const LBP_TABLE_HEAD_STYLES = {
  fillColor: C_DARK,
  textColor: [255, 255, 255] as [number, number, number],
  fontStyle: 'bold' as const,
  fontSize: 8.5,
}

export const LBP_TABLE_ALT_ROW = {
  fillColor: C_ROW,
}
