import React, { useMemo, useState } from 'react'
import { Button, Card, DatePicker, Form, Select, Space, Typography, message } from 'antd'
import { FilePdfOutlined, FileExcelOutlined, ReloadOutlined } from '@ant-design/icons'
import dayjs, { type Dayjs } from 'dayjs'
import jsPDF from 'jspdf'
import autoTable from 'jspdf-autotable'
import { rapportsService } from '@services/rapports.service'
import { exportMultiSheetToExcel, type ExcelExportOptions } from '@utils/export/excel'
import { fmtPdf, fmtPdfNum, loadLogoBase64, drawLBPHeader, drawLBPFooters, LBP_TABLE_HEAD_STYLES, LBP_TABLE_ALT_ROW, C_BLUE } from '@utils/pdfHelpers'
import { supervisionService } from '@services/supervision.service'
import { usePermissions } from '@hooks/usePermissions'
import { PERMISSIONS } from '@constants/permissions'

const { Title, Paragraph, Text } = Typography
const { RangePicker } = DatePicker

type EtatAgenceComplet = any

export const EtatAgenceCompletPage: React.FC = () => {
  const { hasPermission } = usePermissions()
  const canSeeNetwork = hasPermission(PERMISSIONS.SUPERVISION.DASHBOARD_READ)
  const [form] = Form.useForm()
  const [loading, setLoading] = useState(false)
  const [data, setData] = useState<EtatAgenceComplet | null>(null)

  const [agences, setAgences] = useState<Array<{ value: number; label: string }>>([])
  React.useEffect(() => {
    if (!canSeeNetwork) return
    supervisionService
      .getEtatAgences()
      .then((rows) => {
        setAgences(
          (rows ?? []).map((r: any) => ({
            value: r.agence.id,
            label: `${r.agence.code} — ${r.agence.nom}`,
          })),
        )
      })
      .catch(() => setAgences([]))
  }, [canSeeNetwork])

  const initialRange = useMemo<[Dayjs, Dayjs]>(() => [dayjs(), dayjs()], [])

  const load = async () => {
    const v = await form.validateFields()
    const [d0, d1] = v.range as [Dayjs, Dayjs]
    setLoading(true)
    try {
      const res = await rapportsService.getEtatAgenceComplet({
        debut: d0.format('YYYY-MM-DD'),
        fin: d1.format('YYYY-MM-DD'),
        agence_id: v.agence_id ?? undefined,
      })
      setData(res)
    } catch (e: any) {
      message.error(e?.message || 'Chargement impossible')
      setData(null)
    } finally {
      setLoading(false)
    }
  }

  const exportPdf = async () => {
    if (!data) return
    try {
      const logo = await loadLogoBase64()
      const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
      const ml = 14
      const mr = 14
      const meta = data.meta ?? {}
      const k = data.kpis ?? {}
      const agenceLabel = meta?.agence ? `${meta.agence.code} — ${meta.agence.nom}` : 'Réseau'

      let y = drawLBPHeader(doc, {
        title: 'État agence complet',
        subtitle: `Période : ${meta?.periode?.label ?? '—'}`,
        rightInfo: `Agence : ${agenceLabel}`,
        logoBase64: logo,
      })

      // Résumé KPIs
      autoTable(doc, {
        startY: y,
        head: [['Résumé', 'Valeur']],
        body: [
          ['Colis (total)', String(k.colis_count ?? 0)],
          ['Groupage', String(k.colis_groupage ?? 0)],
          ['Autres envois', String(k.colis_autres ?? 0)],
          ['Nombre colis (physiques)', String(k.nb_colis_physiques ?? 0)],
          ['Poids total (Kg)', fmtPdfNum(k.poids_total_kg ?? 0)],
          ['Montant total colis', fmtPdf(k.montant_total_colis ?? 0)],
          ['Factures', String(k.factures_count ?? 0)],
          ['Paiements validés (période)', fmtPdf(k.paiements_total_valides ?? 0)],
          ['Paiements en attente (période)', fmtPdf(k.paiements_total_attente ?? 0)],
          ['Caisse entrées (période)', fmtPdf(k.caisse_entrees ?? 0)],
          ['Caisse sorties (période)', fmtPdf(k.caisse_sorties ?? 0)],
        ],
        headStyles: { ...LBP_TABLE_HEAD_STYLES, fillColor: C_BLUE },
        alternateRowStyles: LBP_TABLE_ALT_ROW,
        styles: { fontSize: 8.5, cellPadding: 2.8 },
        margin: { left: ml, right: mr },
        columnStyles: { 1: { halign: 'right', fontStyle: 'bold' } },
      })

      // Colis (détail)
      const nextY = (doc as any).lastAutoTable.finalY + 6
      const colisRows = (data.colis ?? []).slice(0, 300).map((c: any) => {
        const ms = Array.isArray(c.marchandises) ? c.marchandises : []
        const poids = ms.reduce((s: number, m: any) => s + Number(m.poids_total || 0), 0)
        const nb = ms.reduce((s: number, m: any) => s + Number(m.nbre_colis || 0), 0)
        const montant = ms.length
          ? ms.reduce((acc: number, m: any) => acc + Number(m.poids_total || 0) * Number(m.prix_unit || 0) + Number(m.prix_emballage || 0) + Number(m.prix_assurance || 0), 0)
          : Number(c.total_montant || 0)
        const f = c.facture
        const pr = c.paiements_resume
        const payStatus = f?.payment_status ?? '—'
        const payes = pr?.total_valides ?? (f?.montant_paye ?? 0)
        const ttc = f?.montant_ttc ?? 0
        const reste = Number(ttc) - Number(payes)
        return [
          c.ref_colis ?? '—',
          dayjs(c.date_envoi).format('DD/MM/YYYY'),
          c.forme_envoi === 'groupage' ? 'Groupage' : 'Autres',
          c.client?.nom_exp ?? '—',
          c.nom_dest ?? '—',
          fmtPdfNum(nb),
          fmtPdfNum(poids),
          fmtPdfNum(montant),
          f?.num_facture ?? '—',
          payStatus,
          fmtPdfNum(payes),
          fmtPdfNum(reste),
        ]
      })

      autoTable(doc, {
        startY: nextY,
        head: [[
          'Ref', 'Date', 'Type', 'Client', 'Dest.', 'Nb', 'Kg', 'Montant', 'Facture', 'Statut', 'Payé', 'Reste',
        ]],
        body: colisRows.length ? colisRows : [['—', '—', '—', '—', '—', '0', '0', '0', '—', '—', '0', '0']],
        styles: { fontSize: 6.6, cellPadding: 1.8 },
        headStyles: LBP_TABLE_HEAD_STYLES,
        alternateRowStyles: LBP_TABLE_ALT_ROW,
        margin: { left: ml, right: mr },
        columnStyles: {
          5: { halign: 'right' },
          6: { halign: 'right' },
          7: { halign: 'right' },
          10: { halign: 'right' },
          11: { halign: 'right' },
        },
      })

      drawLBPFooters(doc)
      doc.save(`etat-agence-complet_${meta?.periode?.debut ?? 'debut'}_${meta?.periode?.fin ?? 'fin'}.pdf`)
      message.success('PDF généré')
    } catch {
      message.error('Export PDF impossible')
    }
  }

  const exportExcel = async () => {
    if (!data) return
    try {
      const meta = data.meta ?? {}
      const k = data.kpis ?? {}
      const agenceLabel = meta?.agence ? `${meta.agence.code} — ${meta.agence.nom}` : 'Réseau'
      const opts: ExcelExportOptions = {
        title: `État agence complet — ${agenceLabel} — ${meta?.periode?.label ?? ''}`,
        sheetName: 'Résumé',
      }

      const colisRows = (data.colis ?? []).map((c: any) => {
        const ms = Array.isArray(c.marchandises) ? c.marchandises : []
        const poids = ms.reduce((s: number, m: any) => s + Number(m.poids_total || 0), 0)
        const nb = ms.reduce((s: number, m: any) => s + Number(m.nbre_colis || 0), 0)
        const montant = ms.length
          ? ms.reduce((acc: number, m: any) => acc + Number(m.poids_total || 0) * Number(m.prix_unit || 0) + Number(m.prix_emballage || 0) + Number(m.prix_assurance || 0), 0)
          : Number(c.total_montant || 0)
        const f = c.facture
        const pr = c.paiements_resume
        const payes = pr?.total_valides ?? (f?.montant_paye ?? 0)
        const ttc = f?.montant_ttc ?? 0
        const reste = Number(ttc) - Number(payes)
        return [
          c.ref_colis ?? '—',
          (c.date_envoi ? dayjs(c.date_envoi).format('YYYY-MM-DD') : '—'),
          c.forme_envoi === 'groupage' ? 'groupage' : 'autres',
          c.trafic_envoi ?? '—',
          c.mode_envoi ?? '—',
          c.client?.nom_exp ?? '—',
          c.nom_dest ?? '—',
          nb,
          poids,
          montant,
          f?.num_facture ?? '—',
          f?.payment_status ?? '—',
          Number(payes),
          Number(reste),
        ]
      })

      const factRows = (data.factures ?? []).map((f: any) => [
        f.num_facture ?? '—',
        f.colis?.ref_colis ?? '—',
        f.etat ?? 0,
        f.payment_status ?? '—',
        Number(f.montant_ttc ?? 0),
        Number(f.montant_paye ?? 0),
        f.devise ?? 'XOF',
        f.date_facture ? dayjs(f.date_facture).format('YYYY-MM-DD') : '—',
      ])

      const payRows = (data.paiements ?? []).map((p: any) => [
        p.facture?.num_facture ?? '—',
        p.facture?.colis?.ref_colis ?? '—',
        p.mode_paiement ?? '—',
        Number(p.montant ?? 0),
        Number(p.etat_validation ?? 0) === 1 ? 'VALIDE' : 'EN_ATTENTE',
        p.date_paiement ? dayjs(p.date_paiement).format('YYYY-MM-DD') : '—',
        p.code_user ?? '—',
        p.reference_paiement ?? '—',
      ])

      const mvRows = (data.mouvements_caisse ?? []).map((m: any) => [
        m.caisse?.nom ?? '—',
        m.caisse?.agence?.nom ?? '—',
        m.type ?? '—',
        m.libelle ?? '—',
        Number(m.montant ?? 0),
        m.mode_retrait ?? '—',
        m.date_mouvement ? dayjs(m.date_mouvement).format('YYYY-MM-DD') : '—',
        m.code_user ?? '—',
      ])

      await exportMultiSheetToExcel(
        [
          {
            name: 'Résumé',
            data: {
              headers: ['Indicateur', 'Valeur'],
              rows: [
                ['Période', meta?.periode?.label ?? '—'],
                ['Agence', agenceLabel],
                ['Colis total', Number(k.colis_count ?? 0)],
                ['Groupage', Number(k.colis_groupage ?? 0)],
                ['Autres', Number(k.colis_autres ?? 0)],
                ['Nb colis physiques', Number(k.nb_colis_physiques ?? 0)],
                ['Poids total (Kg)', Number(k.poids_total_kg ?? 0)],
                ['Montant total colis', Number(k.montant_total_colis ?? 0)],
                ['Factures', Number(k.factures_count ?? 0)],
                ['Paiements validés', Number(k.paiements_total_valides ?? 0)],
                ['Paiements en attente', Number(k.paiements_total_attente ?? 0)],
                ['Caisse entrées', Number(k.caisse_entrees ?? 0)],
                ['Caisse sorties', Number(k.caisse_sorties ?? 0)],
              ],
            },
          },
          {
            name: 'Colis',
            data: {
              headers: [
                'Ref', 'Date', 'Type', 'Trafic', 'Mode', 'Client', 'Destinataire',
                'Nb colis', 'Poids (Kg)', 'Montant', 'Facture', 'Statut paiement', 'Payé', 'Reste',
              ],
              rows: colisRows,
            },
          },
          {
            name: 'Factures',
            data: {
              headers: ['Num facture', 'Colis', 'État', 'Payment status', 'TTC', 'Payé', 'Devise', 'Date'],
              rows: factRows,
            },
          },
          {
            name: 'Paiements',
            data: {
              headers: ['Facture', 'Colis', 'Mode', 'Montant', 'Statut', 'Date', 'Saisi par', 'Référence'],
              rows: payRows,
            },
          },
          {
            name: 'Caisse',
            data: {
              headers: ['Caisse', 'Agence', 'Type', 'Libellé', 'Montant', 'Moyen', 'Date', 'Utilisateur'],
              rows: mvRows,
            },
          },
        ],
        `etat-agence-complet_${meta?.periode?.debut ?? 'debut'}_${meta?.periode?.fin ?? 'fin'}`,
        opts,
      )
      message.success('Fichier Excel généré')
    } catch {
      message.error('Export Excel impossible')
    }
  }

  return (
    <div style={{ padding: 24 }}>
      <Title level={2}>État agence complet (PDF/Excel)</Title>
      <Paragraph type="secondary" style={{ maxWidth: 900 }}>
        Cet écran génère un <strong>seul document</strong> (PDF ou Excel) qui regroupe : colis (groupage + autres),
        factures, paiements et mouvements de caisse sur une période. Pour un profil “agence”, le périmètre est
        automatiquement limité à votre agence.
      </Paragraph>

      <Card style={{ maxWidth: 1100 }} loading={loading}>
        <Form
          form={form}
          layout="inline"
          initialValues={{ range: initialRange }}
          onFinish={() => void load()}
          data-onboarding="etat-agence-form"
        >
          <Form.Item
            name="range"
            label="Période"
            rules={[{ required: true, message: 'Requis' }]}
          >
            <RangePicker
              format="DD/MM/YYYY"
              presets={[
                { label: 'Aujourd’hui', value: [dayjs(), dayjs()] },
                { label: '7 jours', value: [dayjs().subtract(6, 'day'), dayjs()] },
                { label: 'Mois en cours', value: [dayjs().startOf('month'), dayjs()] },
                { label: 'Année en cours', value: [dayjs().startOf('year'), dayjs()] },
              ]}
            />
          </Form.Item>

          {canSeeNetwork ? (
            <Form.Item name="agence_id" label="Agence (optionnel)">
              <Select
                allowClear
                showSearch
                optionFilterProp="label"
                style={{ width: 320 }}
                options={agences}
                placeholder="Tout le réseau"
              />
            </Form.Item>
          ) : null}

          <Form.Item>
            <Space>
              <Button icon={<ReloadOutlined />} htmlType="submit" data-onboarding="etat-agence-generate">
                Générer
              </Button>
              <Button
                icon={<FilePdfOutlined />}
                onClick={() => void exportPdf()}
                disabled={!data}
                data-onboarding="etat-agence-export-pdf"
              >
                Exporter PDF
              </Button>
              <Button
                icon={<FileExcelOutlined />}
                onClick={() => void exportExcel()}
                disabled={!data}
                data-onboarding="etat-agence-export-excel"
              >
                Exporter Excel
              </Button>
            </Space>
          </Form.Item>
        </Form>

        {!data ? (
          <Text type="secondary" style={{ display: 'block', marginTop: 16 }}>
            Choisissez une période puis cliquez “Générer”.
          </Text>
        ) : (
          <Text type="secondary" style={{ display: 'block', marginTop: 16 }}>
            Données chargées : {Number(data?.kpis?.colis_count ?? 0)} colis — {Number(data?.kpis?.factures_count ?? 0)} factures.
            Exportez maintenant en PDF ou Excel.
          </Text>
        )}
      </Card>
    </div>
  )
}

