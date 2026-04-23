import { exportMultiSheetToExcel, exportTableToExcel } from '@utils/export/excel'
import type { TableData } from '@utils/export/types'
import type { Dayjs } from 'dayjs'
import type { SupervisionAgenceRow } from '@services/supervision.service'

function money(v: number): string {
  return Number(v).toLocaleString('fr-FR', { maximumFractionDigits: 2 })
}

type PilotageKpis = {
  periode: { debut: string; fin: string; label: string }
  colisCrees: number
  facturesEmises: number
  encaissementsValides: number
  nouveauxClients: number
  nbAgences: number
}

type ActivityPoint = { point: string; colis: number; factures: number }
type RevenueRow = { annee: number; encaissements_valides: number; nb_paiements: number }
type Compare = {
  annees: number[]
  encaissements: Record<number, number>
  ecart_pourcent: number | null
}
type Projection = {
  methodologie: string
  base_moyenne_mensuelle: number
  encaissement_annee_reference_estime: number
  avertissement: string
}

/**
 * Onglet Pilotage : KPI, courbe d’activité, encaissements par année, comparatif années, aperçu agences.
 */
export async function exportSupervisionPilotageExcel(opts: {
  range: [Dayjs, Dayjs]
  insKpis: PilotageKpis
  activity: ActivityPoint[] | undefined
  revenue: RevenueRow[] | undefined
  a1: number
  a2: number
  compare: Compare | undefined
  projection: Projection | undefined
  agences: SupervisionAgenceRow[] | undefined
}): Promise<void> {
  const d0 = opts.range[0].format('YYYY-MM-DD')
  const d1 = opts.range[1].format('YYYY-MM-DD')
  const k = opts.insKpis
  const sheets: Array<{ name: string; data: TableData }> = [
    {
      name: 'KPI période',
      data: {
        headers: [
          'Période',
          'Colis',
          'Factures',
          'Encaissements (F)',
          'Nouveaux clients',
          'Agences',
        ],
        rows: [
          [
            k.periode?.label ?? `${d0} → ${d1}`,
            k.colisCrees,
            k.facturesEmises,
            money(k.encaissementsValides),
            k.nouveauxClients,
            k.nbAgences,
          ],
        ],
      },
    },
    {
      name: 'Activité',
      data: {
        headers: ['Point', 'Colis', 'Factures'],
        rows: (opts.activity ?? []).map((p) => [p.point, p.colis, p.factures]),
      },
    },
    {
      name: 'Encaissements / an',
      data: {
        headers: ['Année', 'Encaissements (F)', 'Nb paiements'],
        rows: (opts.revenue ?? []).map((r) => [r.annee, r.encaissements_valides, r.nb_paiements]),
      },
    },
  ]

  if (opts.compare) {
    const e1 = opts.compare.encaissements[opts.a1] ?? 0
    const e2 = opts.compare.encaissements[opts.a2] ?? 0
    sheets.push({
      name: 'Comparatif années',
      data: {
        headers: [
          'Année 1',
          'Enc. année 1 (F)',
          'Année 2',
          'Enc. année 2 (F)',
          'Écart %',
        ],
        rows: [
          [
            opts.a1,
            e1,
            opts.a2,
            e2,
            opts.compare.ecart_pourcent == null
              ? '—'
              : Number(opts.compare.ecart_pourcent).toFixed(1),
          ],
        ],
      },
    })
  }

  if (opts.projection) {
    const p = opts.projection
    sheets.push({
      name: 'Projection (indic.)',
      data: {
        headers: [
          'Moy. mensuelle (F)',
          'Est. 12 mois (F)',
          'Avertissement',
        ],
        rows: [
          [
            p.base_moyenne_mensuelle,
            p.encaissement_annee_reference_estime,
            p.avertissement,
          ],
        ],
      },
    })
  }

  const ag = opts.agences ?? []
  sheets.push({
    name: 'Aperçu agences (jour)',
    data: {
      headers: ['Code', 'Agence', 'Colis (jour)', 'Solde caisse (F)', 'Statut'],
      rows: ag.map((r) => [
        r.agence.code,
        r.agence.nom,
        r.colis_aujourdhui,
        r.solde_caisse,
        r.statut,
      ]),
    },
  })

  await exportMultiSheetToExcel(sheets, `supervision-pilotage_${d0}_${d1}`, {
    title: `Supervision — pilotage (${d0} → ${d1})`,
  })
}

type ProdUser = {
  username: string
  nom_complet: string
  role_code: string
  agence_nom: string | null
  colis_saisis: number
  factures_saisies: number
  indice_activite: number
  niveau_activite: string
}

type HeadcountRow = { id_agence: number; nom_agence: string; role_code: string; n: number }

export async function exportSupervisionPerformanceExcel(opts: {
  range: [Dayjs, Dayjs]
  utilisateurs: ProdUser[] | undefined
  parAgenceRole: HeadcountRow[] | undefined
}): Promise<void> {
  const d0 = opts.range[0].format('YYYY-MM-DD')
  const d1 = opts.range[1].format('YYYY-MM-DD')
  const sheets: Array<{ name: string; data: TableData }> = [
    {
      name: 'Productivité',
      data: {
        headers: [
          'Utilisateur',
          'Nom',
          'Rôle',
          'Agence',
          'Colis',
          'Factures',
          'Indice',
          'Niveau',
        ],
        rows: (opts.utilisateurs ?? []).map((u) => [
          u.username,
          u.nom_complet,
          u.role_code,
          u.agence_nom ?? '—',
          u.colis_saisis,
          u.factures_saisies,
          u.indice_activite,
          u.niveau_activite,
        ]),
      },
    },
    {
      name: 'Effectifs',
      data: {
        headers: ['Agence', 'Rôle', 'Effectif'],
        rows: (opts.parAgenceRole ?? []).map((r) => [r.nom_agence, r.role_code, r.n]),
      },
    },
  ]
  await exportMultiSheetToExcel(sheets, `supervision-performance_${d0}_${d1}`, {
    title: `Supervision — performance (${d0} → ${d1})`,
  })
}

type CaisseLigne = {
  agence: { id: number; code: string; nom: string }
  nom_caisse: string
  est_caisse_principale: boolean
  solde_actuel: number
  volume_entrees_periode: number
}

export async function exportSupervisionCaisseExcel(opts: {
  range: [Dayjs, Dayjs]
  caisses: CaisseLigne[] | undefined
}): Promise<void> {
  const d0 = opts.range[0].format('YYYY-MM-DD')
  const d1 = opts.range[1].format('YYYY-MM-DD')
  const data: TableData = {
    headers: [
      'Agence',
      'Caisse',
      'Principale',
      'Solde (F)',
      'Entrées période (F)',
    ],
    rows: (opts.caisses ?? []).map((r) => [
      `${r.agence.code} — ${r.agence.nom}`,
      r.nom_caisse,
      r.est_caisse_principale ? 'Oui' : 'Non',
      r.solde_actuel,
      r.volume_entrees_periode,
    ]),
  }
  await exportTableToExcel(data, `supervision-caisse_${d0}_${d1}`, {
    title: `Caisse réseau — ${d0} → ${d1}`,
  })
}
