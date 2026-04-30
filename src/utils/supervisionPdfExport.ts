import jsPDF from 'jspdf'
import autoTable from 'jspdf-autotable'
import type { Dayjs } from 'dayjs'
import {
  fmtPdf, fmtPdfNum,
  loadLogoBase64,
  drawLBPHeader, drawLBPFooters,
  LBP_TABLE_HEAD_STYLES, LBP_TABLE_ALT_ROW,
} from './pdfHelpers'

export type SupervisionPdfBundle = {
  titre: string
  periode: [Dayjs, Dayjs]
  kpis?: {
    colisCrees: number
    facturesEmises: number
    encaissementsValides: number
    nouveauxClients: number
    nbAgences: number
  }
  comparatifAnnees?: { a1: number; a2: number; e1: number; e2: number; ecartPct: number | null }
  projection?: { baseMensuelle: number; estimeAnnee: string }
}

/**
 * Synthèse PDF pour la Superviseure générale — remise au DG / impression.
 */
export async function exportSupervisionSynthesePdf(bundle: SupervisionPdfBundle): Promise<void> {
  const [d0, d1] = bundle.periode
  const perStr   = `${d0.format('DD/MM/YYYY')} - ${d1.format('DD/MM/YYYY')}`
  const logo     = await loadLogoBase64()

  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ml  = 14

  let y = drawLBPHeader(doc, {
    title:     'Supervision reseau',
    subtitle:  bundle.titre,
    rightInfo: `Periode : ${perStr}`,
    logoBase64: logo,
  })

  if (bundle.kpis) {
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(10)
    doc.setTextColor(30, 40, 55)
    doc.text('Indicateurs consolidés', ml, y)
    y += 5
    autoTable(doc, {
      startY: y,
      head: [['Indicateur', 'Valeur']],
      body: [
        ['Colis créés',                    String(bundle.kpis.colisCrees)],
        ['Factures émises',                String(bundle.kpis.facturesEmises)],
        ['Encaissements validés',          fmtPdf(bundle.kpis.encaissementsValides)],
        ['Nouveaux clients',               String(bundle.kpis.nouveauxClients)],
        ["Nombre d'agences (ref.)",        String(bundle.kpis.nbAgences)],
      ],
      styles: { fontSize: 9, cellPadding: 3 },
      headStyles: LBP_TABLE_HEAD_STYLES,
      alternateRowStyles: LBP_TABLE_ALT_ROW,
      columnStyles: { 1: { halign: 'right', fontStyle: 'bold' } },
      margin: { left: ml },
    })
    y = (doc as any).lastAutoTable.finalY + 10
  }

  if (bundle.comparatifAnnees) {
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(10)
    doc.setTextColor(30, 40, 55)
    doc.text(
      `Comparatif encaissements ${bundle.comparatifAnnees.a1} / ${bundle.comparatifAnnees.a2}`,
      ml, y,
    )
    y += 5
    const ec = bundle.comparatifAnnees.ecartPct == null
      ? '-'
      : `${bundle.comparatifAnnees.ecartPct.toFixed(1)} %`
    autoTable(doc, {
      startY: y,
      head: [['Année', 'Encaissements validés', 'Ecart']],
      body: [
        [String(bundle.comparatifAnnees.a1), fmtPdf(bundle.comparatifAnnees.e1), '-'],
        [String(bundle.comparatifAnnees.a2), fmtPdf(bundle.comparatifAnnees.e2), ec],
      ],
      styles: { fontSize: 9, cellPadding: 3 },
      headStyles: LBP_TABLE_HEAD_STYLES,
      alternateRowStyles: LBP_TABLE_ALT_ROW,
      columnStyles: { 1: { halign: 'right', fontStyle: 'bold' }, 2: { halign: 'center' } },
      margin: { left: ml },
    })
    y = (doc as any).lastAutoTable.finalY + 10
  }

  if (bundle.projection) {
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(9.5)
    doc.setTextColor(30, 40, 55)
    doc.text('Indicateur de tendance (indicatif)', ml, y)
    y += 5
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(9)
    doc.text(
      `Moyenne mensuelle de reference : ${fmtPdf(bundle.projection.baseMensuelle)}`,
      ml, y,
    )
    y += 5
    doc.text(
      `Estimation ordre de grandeur sur 12 mois : ${bundle.projection.estimeAnnee}`,
      ml, y,
    )
  }

  drawLBPFooters(doc)
  doc.save(`LBP-supervision-${d0.format('YYYYMMDD')}-${d1.format('YYYYMMDD')}.pdf`)
}

// Re-export pour éviter de casser les imports existants qui utilisent fmtPdfNum
export { fmtPdf, fmtPdfNum }
