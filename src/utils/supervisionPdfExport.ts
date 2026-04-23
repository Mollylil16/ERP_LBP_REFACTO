import jsPDF from 'jspdf'
import autoTable from 'jspdf-autotable'
import type { Dayjs } from 'dayjs'

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
export function exportSupervisionSynthesePdf(bundle: SupervisionPdfBundle): void {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const [d0, d1] = bundle.periode
  const perStr = `${d0.format('DD/MM/YYYY')} – ${d1.format('DD/MM/YYYY')}`

  doc.setFontSize(16)
  doc.text('LBP — Supervision réseau', 14, 18)
  doc.setFontSize(11)
  doc.setTextColor(80, 80, 80)
  doc.text(bundle.titre, 14, 26)
  doc.text(`Période : ${perStr}`, 14, 32)
  doc.setTextColor(0, 0, 0)

  let y = 42
  if (bundle.kpis) {
    doc.setFontSize(12)
    doc.text('Indicateurs consolidés', 14, y)
    y += 6
    autoTable(doc, {
      startY: y,
      head: [['Indicateur', 'Valeur']],
      body: [
        ['Colis créés', String(bundle.kpis.colisCrees)],
        ['Factures émises', String(bundle.kpis.facturesEmises)],
        ['Encaissements validés (FCFA)', String(Math.round(bundle.kpis.encaissementsValides))],
        ['Nouveaux clients', String(bundle.kpis.nouveauxClients)],
        ['Nombre d’agences (réf.)', String(bundle.kpis.nbAgences)],
      ],
      styles: { fontSize: 9 },
      headStyles: { fillColor: [22, 119, 255] },
    })
    y = (doc as any).lastAutoTable.finalY + 10
  }

  if (bundle.comparatifAnnees) {
    doc.setFontSize(12)
    doc.text(
      `Comparatif encaissements ${bundle.comparatifAnnees.a1} / ${bundle.comparatifAnnees.a2}`,
      14,
      y,
    )
    y += 6
    const ec =
      bundle.comparatifAnnees.ecartPct == null
        ? '—'
        : `${bundle.comparatifAnnees.ecartPct.toFixed(1)} %`
    autoTable(doc, {
      startY: y,
      head: [['Année', 'Encaissements validés (FCFA)', 'Écart']],
      body: [
        [
          String(bundle.comparatifAnnees.a1),
          String(Math.round(bundle.comparatifAnnees.e1)),
          '—',
        ],
        [
          String(bundle.comparatifAnnees.a2),
          String(Math.round(bundle.comparatifAnnees.e2)),
          ec,
        ],
      ],
      styles: { fontSize: 9 },
      headStyles: { fillColor: [22, 119, 255] },
    })
    y = (doc as any).lastAutoTable.finalY + 10
  }

  if (bundle.projection) {
    doc.setFontSize(10)
    doc.text('Indicateur de tendance (indicatif)', 14, y)
    y += 5
    doc.setFontSize(9)
    doc.text(
      `Moyenne mensuelle de référence : ${bundle.projection.baseMensuelle.toLocaleString('fr-FR')} FCFA`,
      14,
      y,
    )
    y += 5
    doc.text(
      `Estimation ordre de grandeur sur 12 mois : ${bundle.projection.estimeAnnee}`,
      14,
      y,
    )
    y += 8
  }

  doc.setFontSize(8)
  doc.setTextColor(120, 120, 120)
  doc.text(
    'Document généré pour suivi interne — ne se substitue pas aux obligations comptables et fiscales.',
    14,
    280,
    { maxWidth: 180 },
  )

  doc.save(`LBP-supervision-${d0.format('YYYYMMDD')}-${d1.format('YYYYMMDD')}.pdf`)
}
