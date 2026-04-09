import React, { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { Button, Space, Spin, message } from 'antd'
import { ArrowLeftOutlined, PrinterOutlined, FilePdfOutlined, ShareAltOutlined } from '@ant-design/icons'
import { FactureTemplate } from '@components/factures/FactureTemplate'
import { FactureColis } from '@/types'
import { facturesService } from '@services/factures.service'
import { useColis } from '@hooks/useColis'
import toast from 'react-hot-toast'
import './FacturePreviewPage.css'

export const FacturePreviewPage: React.FC = () => {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const [facture, setFacture] = useState<FactureColis | null>(null)
  const [loading, setLoading] = useState(true)

  // Récupérer les détails du colis si nécessaire
  const { data: colisData } = useColis(facture?.id_colis || null)

  useEffect(() => {
    if (id) {
      loadFacture(parseInt(id))
    }
  }, [id])

  const loadFacture = async (factureId: number) => {
    try {
      setLoading(true)
      const data = await facturesService.getFactureById(factureId)
      setFacture(data)
    } catch (error: any) {
      console.error('Erreur lors du chargement de la facture:', error)
      message.error('Erreur lors du chargement de la facture')
      navigate('/factures')
    } finally {
      setLoading(false)
    }
  }

  const handleDownloadPDF = async () => {
    if (!facture) return

    try {
      await facturesService.downloadPDF(
        facture.id,
        `facture-${facture.num_facture}.pdf`
      )
      toast.success('Facture téléchargée avec succès')
    } catch (error) {
      console.error('Erreur lors du téléchargement:', error)
      toast.error('Erreur lors du téléchargement de la facture')
    }
  }

  const handlePrint = async () => {
    if (!facture) return

    try {
      await facturesService.printFacture(facture.id)
    } catch (error) {
      console.error('Erreur lors de l\'impression:', error)
      toast.error('Erreur lors de l\'impression de la facture')
    }
  }

  const handlePrintBrowser = () => {
    window.print()
  }

  if (loading) {
    return (
      <div className="facture-preview-loading">
        <Spin size="large" />
        <p>Chargement de la facture...</p>
      </div>
    )
  }

  if (!facture) {
    return (
      <div className="facture-preview-error">
        <p>Facture non trouvée</p>
        <Button onClick={() => navigate('/factures')}>
          Retour à la liste
        </Button>
      </div>
    )
  }

  return (
    <div className="facture-preview-container">
      {/* Barre d'actions */}
      <div className="facture-preview-actions">
        <Space>
          <Button
            icon={<ArrowLeftOutlined />}
            onClick={() => navigate('/factures')}
          >
            Retour
          </Button>
          <Button
            type="primary"
            icon={<FilePdfOutlined />}
            onClick={handleDownloadPDF}
          >
            Télécharger PDF
          </Button>
          <Button
            icon={<PrinterOutlined />}
            onClick={handlePrintBrowser}
          >
            Imprimer
          </Button>
          <Button
            icon={<ShareAltOutlined />}
            onClick={() => {
              if (navigator.share) {
                navigator.share({
                  title: `Facture ${facture.num_facture}`,
                  text: `Facture ${facture.num_facture} - ${facture.montant_ttc} FCFA`,
                }).catch((err) => console.error('Erreur partage:', err))
              } else {
                navigator.clipboard.writeText(window.location.href)
                toast.success('Lien copié dans le presse-papiers')
              }
            }}
          >
            Partager
          </Button>
        </Space>
      </div>

      {/* Zone d'impression - masquée à l'écran mais visible à l'impression */}
      <div className="facture-preview-content print-only">
        <FactureTemplate
          facture={facture}
          colis={
            colisData
              ? {
                client_colis: colisData.client_colis,
                marchandises: (colisData as any).marchandises,
                nom_destinataire: colisData.nom_destinataire,
                lieu_dest: colisData.lieu_dest,
                tel_dest: colisData.tel_dest,
                email_dest: colisData.email_dest,
                nom_recup: colisData.nom_recup,
                adresse_recup: colisData.adresse_recup,
                tel_recup: colisData.tel_recup,
                email_recup: colisData.email_recup,
                nom_marchandise: colisData.nom_marchandise,
                nbre_colis: colisData.nbre_colis,
                nbre_articles: colisData.nbre_articles,
                poids_total: colisData.poids_total,
                prix_unit: colisData.prix_unit,
                prix_emballage: colisData.prix_emballage,
                prix_assurance: colisData.prix_assurance,
                prix_agence: colisData.prix_agence,
                total_montant: colisData.total_montant,
              }
              : undefined
          }
          mode="print"
        />
      </div>

      {/* Zone d'aperçu à l'écran */}
      <div className="facture-preview-content screen-only">
        <div className="facture-preview-wrapper">
          <FactureTemplate
            facture={facture}
            colis={
              colisData
                ? {
                  client_colis: colisData.client_colis,
                  marchandises: (colisData as any).marchandises,
                  nom_destinataire: colisData.nom_destinataire,
                  lieu_dest: colisData.lieu_dest,
                  tel_dest: colisData.tel_dest,
                  email_dest: colisData.email_dest,
                  nom_recup: colisData.nom_recup,
                  adresse_recup: colisData.adresse_recup,
                  tel_recup: colisData.tel_recup,
                  email_recup: colisData.email_recup,
                  nom_marchandise: colisData.nom_marchandise,
                  nbre_colis: colisData.nbre_colis,
                  nbre_articles: colisData.nbre_articles,
                  poids_total: colisData.poids_total,
                  prix_unit: colisData.prix_unit,
                  prix_emballage: colisData.prix_emballage,
                  prix_assurance: colisData.prix_assurance,
                  prix_agence: colisData.prix_agence,
                  total_montant: colisData.total_montant,
                }
                : undefined
            }
            mode="preview"
          />
        </div>
      </div>
    </div>
  )
}
