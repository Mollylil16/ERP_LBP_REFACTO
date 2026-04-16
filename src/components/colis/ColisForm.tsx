import React, { useState, useEffect } from 'react'
import {
  Form,
  Input,
  Select,
  DatePicker,
  Button,
  Card,
  Row,
  Col,
  Space,
  Divider,
  Typography,
  InputNumber,
  AutoComplete,
  Alert,
  Checkbox,
  Modal,
  Tag,
  Radio,
} from 'antd'
import { PlusOutlined, DeleteOutlined, SaveOutlined, UserAddOutlined, CheckCircleOutlined } from '@ant-design/icons'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import dayjs from 'dayjs'
import type { Dayjs } from 'dayjs'
import type { RadioChangeEvent } from 'antd/es/radio'
import { APP_CONFIG } from '@constants/application'
import { calculerTotalLigneMarchandise, calculerTotalMarchandises } from '@utils/calculations'
import { formatMontantWithDevise } from '@utils/format'
import { dayjsFromApiForPicker } from '@utils/dayjsPicker'
import type { CreateColisDto } from '@types'
import { clientsService } from '@services/clients.service'
import { tarifsService } from '@services/tarifs.service'
import { message } from 'antd'
import { useAuth } from '@/hooks/useAuth'
import { useProduitsCatalogue } from '@hooks/useProduitsCatalogue'
import {
  produitsCatalogueService,
  type ProduitCatalogue,
} from '@services/produits-catalogue.service'
import { useQueryClient } from '@tanstack/react-query'
import { WithPermission } from '@components/common/WithPermission'
import { PERMISSIONS } from '@constants/permissions'

const { Title, Text } = Typography
const { Option } = Select

// Schéma de validation Zod
const marchandiseSchema = z.object({
  nom_marchandise: z.string().min(1, 'Le nom de la marchandise est obligatoire'),
  nbre_colis: z.coerce.number().min(1, 'Le nombre de colis doit être au moins 1'),
  nbre_articles: z.coerce.number().min(1, 'Le nombre d\'articles doit être au moins 1'),
  poids_total: z.coerce.number().min(0.01, 'Le poids doit être supérieur à 0'),
  prix_unit: z.coerce.number().min(0, 'Le prix unitaire doit être positif'),
  prix_emballage: z.coerce.number().min(0).optional().default(0),
  prix_assurance: z.coerce.number().min(0).optional().default(0),
  prix_agence: z.coerce.number().min(0).optional().default(0),
  id_tarif: z.number().optional(),
  type_emballage: z.array(z.string()).optional().default([]),
  nbre_emballage: z.coerce.number().min(1, 'Le nombre d\'emballage doit être au moins 1'),
  // interne UI: ids des produits sélectionnés dans le catalogue (pas envoyé au backend)
  produits_catalogue_ids: z.array(z.number()).optional().default([]),
})

const colisFormSchema = z.object({
  trafic_envoi: z.string().min(1, 'Le trafic d\'envoi est obligatoire'),
  date_envoi: z.string().min(1, 'La date d\'envoi est obligatoire'),
  mode_envoi: z.string().min(1, 'Le mode d\'envoi est obligatoire'),

  // Client expéditeur
  client_colis: z.object({
    nom_exp: z.string().min(2, 'Le nom de l\'expéditeur est obligatoire'),
    type_piece_exp: z.string().optional().or(z.literal('')).default(''),
    num_piece_exp: z.string().optional().or(z.literal('')).default(''),
    tel_exp: z.string().min(10, 'Le téléphone est obligatoire'),
    email_exp: z.string().email('Email invalide').optional().or(z.literal('')),
  }),

  // Marchandise (au moins une ligne)
  marchandise: z.array(marchandiseSchema).min(1, 'Au moins une ligne de marchandise est requise'),

  // Destinataire
  nom_destinataire: z.string().min(2, 'Le nom du destinataire est obligatoire'),
  lieu_dest: z.string().min(2, 'Le lieu de destination est obligatoire'),
  tel_dest: z.string().min(10, 'Le téléphone du destinataire est obligatoire'),
  email_dest: z.string().email('Email invalide').optional().or(z.literal('')),

  // Récupérateur (optionnel)
  nom_recup: z.string().optional(),
  adresse_recup: z.string().optional(),
  tel_recup: z.string().optional(),
  email_recup: z.string().email('Email invalide').optional().or(z.literal('')),

  // Livraison à domicile
  livraison: z.boolean().optional().default(false),

  // ID traceur GPS physique (optionnel)
  tracker_id: z.string().optional(),
})

type ColisFormData = z.infer<typeof colisFormSchema>

const selectPopupToBody = () => document.body

const EMBALLAGE_TYPE_LABELS: Record<string, string> = {
  petit_carton: 'Petit carton',
  moyen_carton: 'Moyen carton',
  grand_carton: 'Grand carton',
  sac: 'Sac',
  valise: 'Valise',
  bôrô: 'Bôrô',
}

const UNITE_CATALOGUE_OPTIONS = [
  { value: 'kg', label: 'kg' },
  { value: 'unité', label: 'Unité' },
  { value: 'carton', label: 'Carton' },
  { value: 'litre', label: 'Litre' },
  { value: 'sac', label: 'Sac' },
]

const CATALOGUE_CATEGORY_OPTIONS: {
  value: ProduitCatalogue['categorie']
  label: string
}[] = [
  { value: 'DENREE', label: 'Denrée' },
  { value: 'HUILE_ET_KARITE', label: 'Huile & karité' },
  { value: 'DIVERS', label: 'Divers' },
  { value: 'COLIS_RAPIDE_EXPORT', label: 'Colis rapide export' },
]

type QuickCatalogueTarifMode = 'AU_KG' | 'FORFAIT' | 'FORFAIT_ET_KG'

interface ColisFormProps {
  formeEnvoi: 'groupage' | 'autres_envoi'
  onSubmit: (data: CreateColisDto) => void | Promise<void>
  onCancel?: () => void
  initialData?: Partial<ColisFormData>
  loading?: boolean
}

export const ColisForm: React.FC<ColisFormProps> = ({
  formeEnvoi,
  onSubmit,
  onCancel,
  initialData,
  loading = false,
}) => {
  const [marchandiseLines, setMarchandiseLines] = useState<number[]>([1])
  const [totalGeneral, setTotalGeneral] = useState(0)
  const [clients, setClients] = useState<any[]>([])
  const [tarifs, setTarifs] = useState<any[]>([])
  const [searching, setSearching] = useState(false)
  const [savedClient, setSavedClient] = useState<any>(null)  // client déjà sélectionné / créé
  const [creatingClient, setCreatingClient] = useState(false)
  // Avertissement doublons de nom
  const [duplicateWarning, setDuplicateWarning] = useState<string | null>(null)
  // Saisie téléphone pour désambiguation
  const [phoneSearch, setPhoneSearch] = useState<string>('')
  const [phoneMatchMsg, setPhoneMatchMsg] = useState<string | null>(null)
  const { user, getCurrency } = useAuth()
  const currency = getCurrency()
  const { data: produitsCatalogue = [], isLoading: loadingProduits } = useProduitsCatalogue()
  const queryClient = useQueryClient()

  const [produitSearchByRow, setProduitSearchByRow] = useState<Record<number, string>>({})
  const [quickAddRow, setQuickAddRow] = useState<{
    rowIndex: number
    nom: string
  } | null>(null)
  /** Prix au kg (modes AU_KG et FORFAIT_ET_KG) */
  const [quickAddPrix, setQuickAddPrix] = useState<number | null>(null)
  const [quickAddPrixForfait, setQuickAddPrixForfait] = useState<number | null>(null)
  const [quickAddPoidsMin, setQuickAddPoidsMin] = useState<number | null>(null)
  const [quickAddPoidsMax, setQuickAddPoidsMax] = useState<number | null>(null)
  const [quickAddCategorie, setQuickAddCategorie] =
    useState<ProduitCatalogue['categorie']>('DIVERS')
  const [quickAddMode, setQuickAddMode] = useState<QuickCatalogueTarifMode>('AU_KG')
  const [quickAddUnite, setQuickAddUnite] = useState('kg')
  const [quickAddSubmitting, setQuickAddSubmitting] = useState(false)

  const resetQuickAddModal = () => {
    setQuickAddRow(null)
    setQuickAddPrix(null)
    setQuickAddPrixForfait(null)
    setQuickAddPoidsMin(null)
    setQuickAddPoidsMax(null)
    setQuickAddCategorie('DIVERS')
    setQuickAddMode('AU_KG')
    setQuickAddUnite('kg')
  }

  const {
    control,
    handleSubmit,
    watch,
    setValue,
    getValues,
    formState: { errors },
  } = useForm<ColisFormData>({
    resolver: zodResolver(colisFormSchema),
    defaultValues: {
      mode_envoi: formeEnvoi === 'groupage' ? 'groupage' : '',
      marchandise: [
        {
          nom_marchandise: '',
          nbre_colis: 0,
          nbre_articles: 0,
          poids_total: 0,
          prix_unit: 0,
          prix_emballage: 0,
          prix_assurance: 0,
          prix_agence: 0,
          type_emballage: [],
          produits_catalogue_ids: [],
          nbre_emballage: 1,
        },
      ],
      ...initialData,
    },
  })

  // Observer les valeurs de marchandise pour calculer le total
  const marchandiseValues = watch('marchandise')

  /** Édition d’un colis existant : autoriser les dates d’envoi passées dans le DatePicker */
  const allowPastShipmentDate = Boolean(initialData?.date_envoi)

  /**
   * Applique la sélection catalogue (ids) : met à jour nom / prix si les prix sont cohérents.
   * @returns false si incohérence de prixs (l’appelant doit restaurer les ids précédents).
   */
  const syncMarchandiseFromCatalogue = (
    rowIndex: number,
    safeIds: number[],
    catalogueList: ProduitCatalogue[],
    options?: { showSuccessToast?: boolean },
  ): boolean => {
    setValue(`marchandise.${rowIndex}.produits_catalogue_ids`, safeIds)
    if (!safeIds.length) {
      return true
    }
    const selectedProds = catalogueList.filter((p) => safeIds.includes(p.id))
    if (!selectedProds.length) {
      return true
    }
    const prices = selectedProds.map((p) => {
      const pUnit = Number(p.prix_unitaire || 0)
      const pForfait = Number(p.prix_forfaitaire || 0)
      return pUnit > 0 ? pUnit : pForfait
    })
    const uniquePrices = [...new Set(prices)]
    if (uniquePrices.length > 1) {
      message.error(
        'Plusieurs produits sur cette ligne : le tarif de référence doit être identique pour le calcul ' +
          '(on compare le prix au kg s’il est défini sur la fiche, sinon le forfait). ' +
          'Retirez un produit ou alignez les fiches catalogue.',
      )
      return false
    }
    const sharedPrice = uniquePrices[0]
    const joinedNames = selectedProds.map((p) => p.nom).join(', ')
    setValue(`marchandise.${rowIndex}.nom_marchandise`, joinedNames)
    if (sharedPrice > 0) {
      setValue(`marchandise.${rowIndex}.prix_unit`, sharedPrice)
    }
    if (options?.showSuccessToast !== false) {
      message.success(
        `${selectedProds.length} produit(s) sélectionné(s) - Prix: ${sharedPrice} ${selectedProds[0]?.devise || 'FCFA'}`,
      )
    }
    return true
  }

  const submitQuickAddCatalogue = async () => {
    if (!quickAddRow) return
    const rowIdx = quickAddRow.rowIndex
    const nom = quickAddRow.nom.trim()
    if (!nom) {
      message.error('Le nom du produit est obligatoire')
      return
    }
    const pu = Number(quickAddPrix)
    const pf = Number(quickAddPrixForfait)
    if (
      quickAddPoidsMin != null &&
      quickAddPoidsMax != null &&
      quickAddPoidsMin > quickAddPoidsMax
    ) {
      message.error('Le poids min. ne peut pas dépasser le poids max.')
      return
    }
    const basePayload: Parameters<typeof produitsCatalogueService.create>[0] = {
      nom,
      categorie: quickAddCategorie,
      unite: quickAddUnite || undefined,
      actif: true,
    }
    if (quickAddMode === 'AU_KG') {
      if (!(pu > 0)) {
        message.error('Indiquez un prix au kg strictement supérieur à 0')
        return
      }
      basePayload.nature = 'PRIX_UNITAIRE'
      basePayload.prix_unitaire = pu
    } else if (quickAddMode === 'FORFAIT') {
      if (!(pf > 0)) {
        message.error('Indiquez un forfait strictement supérieur à 0')
        return
      }
      basePayload.nature = 'PRIX_FORFAITAIRE'
      basePayload.prix_forfaitaire = pf
      if (quickAddPoidsMin != null && quickAddPoidsMin >= 0) {
        basePayload.poids_min = quickAddPoidsMin
      }
      if (quickAddPoidsMax != null && quickAddPoidsMax >= 0) {
        basePayload.poids_max = quickAddPoidsMax
      }
    } else {
      if (!(pf > 0) || !(pu > 0)) {
        message.error('Forfait (tranche) et prix au kg au-delà sont obligatoires et doivent être > 0')
        return
      }
      basePayload.nature = 'PRIX_FORFAITAIRE'
      basePayload.prix_forfaitaire = pf
      basePayload.prix_unitaire = pu
      if (quickAddPoidsMin != null && quickAddPoidsMin >= 0) {
        basePayload.poids_min = quickAddPoidsMin
      }
      if (quickAddPoidsMax != null && quickAddPoidsMax >= 0) {
        basePayload.poids_max = quickAddPoidsMax
      }
    }

    setQuickAddSubmitting(true)
    try {
      const created = await produitsCatalogueService.create(basePayload)
      await queryClient.invalidateQueries({ queryKey: ['produits-catalogue'] })
      await queryClient.invalidateQueries({ queryKey: ['produits-catalogue-gestion'] })
      const prevIds = getValues(`marchandise.${rowIdx}.produits_catalogue_ids`) || []
      const newIds = [...new Set([...prevIds, created.id])]
      const mergedList = [...produitsCatalogue, created]
      const ok = syncMarchandiseFromCatalogue(rowIdx, newIds, mergedList, {
        showSuccessToast: false,
      })
      if (!ok) {
        setValue(`marchandise.${rowIdx}.produits_catalogue_ids`, prevIds)
        message.error(
          'Ce produit n’a pas le même tarif de référence que les autres sélectionnés sur cette ligne.',
        )
      } else {
        message.success('Produit enregistré dans le catalogue et sélectionné')
        resetQuickAddModal()
        setProduitSearchByRow((prev) => {
          const next = { ...prev }
          delete next[rowIdx]
          return next
        })
      }
    } catch (e: any) {
      message.error(e?.response?.data?.message || e?.message || 'Impossible d’enregistrer le produit')
    } finally {
      setQuickAddSubmitting(false)
    }
  }

  useEffect(() => {
    const fetchTarifs = async () => {
      try {
        const data = await tarifsService.getAll()
        setTarifs(data)
      } catch (error) {
        console.error('Erreur chargement tarifs:', error)
      }
    }
    fetchTarifs()
  }, [])

  useEffect(() => {
    console.log('[ColisForm] useEffect - marchandiseValues:', marchandiseValues)
    if (!marchandiseValues || !Array.isArray(marchandiseValues)) {
      console.log('[ColisForm] Pas de marchandises, total = 0')
      setTotalGeneral(0)
      return
    }

    const safeMarchandises = marchandiseValues.map((line) => ({
      prix_unit: line?.prix_unit || 0,
      poids_total: line?.poids_total || 0,
      prix_emballage: line?.prix_emballage || 0,
      prix_assurance: line?.prix_assurance || 0,
      prix_agence: line?.prix_agence || 0,
    }))

    console.log('[ColisForm] safeMarchandises:', safeMarchandises)
    const total = calculerTotalMarchandises(safeMarchandises)
    console.log('[ColisForm] Total calculé:', total)
    setTotalGeneral(total)
  }, [marchandiseValues])

  useEffect(() => {
    if (!initialData && user?.agency) {
      if (user.agency.code === 'LBP-PARIS') {
        setValue('trafic_envoi', 'Colis France -> CI')
      } else if (user.agency.code === 'LBP-DAKAR') {
        setValue('trafic_envoi', 'Colis Sénégal -> CI')
      }
    }
  }, [initialData, setValue, user])

  const addMarchandiseLine = () => {
    const newIndex = Math.max(...marchandiseLines) + 1
    setMarchandiseLines([...marchandiseLines, newIndex])

    const currentMarchandise = watch('marchandise') || []
    setValue('marchandise', [
      ...currentMarchandise,
      {
        nom_marchandise: '',
        nbre_colis: 0,
        nbre_articles: 0,
        poids_total: 0,
        prix_unit: 0,
        prix_emballage: 0,
        prix_assurance: 0,
        prix_agence: 0,
        type_emballage: [],
        produits_catalogue_ids: [],
        nbre_emballage: 1,
      },
    ])
  }

  const removeMarchandiseLine = (index: number) => {
    if (marchandiseLines.length > 1) {
      const newLines = marchandiseLines.filter((_, i) => i !== index)
      setMarchandiseLines(newLines)

      const currentMarchandise = watch('marchandise') || []
      const newMarchandise = currentMarchandise.filter((_, i) => i !== index)
      setValue('marchandise', newMarchandise)
    }
  }

  const onFormSubmit = async (data: ColisFormData) => {
    try {
      // 1) Résoudre / créer le client expéditeur => id_client (attendu par le backend)
      // Si le client a déjà été sélectionné ou créé via le bouton, on l'utilise directement
      let client = savedClient

      if (!client) {
        const tel = data.client_colis.tel_exp?.trim()
        const nom = data.client_colis.nom_exp?.trim()

        const searchTerm = tel || nom
        if (!searchTerm) {
          message.error("Téléphone ou nom de l'expéditeur requis pour créer le client")
          return
        }

        const found = await clientsService.searchClients(searchTerm)
        const existing =
          found.find((c) => (tel ? c.tel_exp === tel : false)) ||
          found.find((c) => c.nom_exp?.toLowerCase() === nom?.toLowerCase())

        client =
          existing ||
          (await clientsService.createClient({
            nom_exp: data.client_colis.nom_exp.trim(),
            type_piece_exp: data.client_colis.type_piece_exp?.trim() ?? '',
            num_piece_exp: data.client_colis.num_piece_exp?.trim() ?? '',
            tel_exp: data.client_colis.tel_exp.trim(),
            email_exp: data.client_colis.email_exp || undefined,
          }))
      }

      // 2) Auto-enregistrer les produits non présents dans le catalogue
      // Règle: si l'utilisateur a saisi un produit (nom + prix) sans le choisir dans le Select catalogue,
      // on le crée automatiquement pour qu'il soit sélectionnable la prochaine fois.
      const existingNames = new Set(
        (produitsCatalogue || [])
          .map((p) => (p?.nom ?? '').trim().toLowerCase())
          .filter((n) => n.length > 0),
      )

      for (const m of data.marchandise || []) {
        const ids = (m as any)?.produits_catalogue_ids as number[] | undefined
        const hasCatalogueSelection = Array.isArray(ids) && ids.length > 0
        if (hasCatalogueSelection) continue

        const nameRaw = (m?.nom_marchandise ?? '').trim()
        const price = Number(m?.prix_unit ?? 0)
        // Si plusieurs produits du catalogue ont été concaténés "A, B", on ne crée pas un produit "A, B".
        if (!nameRaw || nameRaw.includes(',')) continue
        if (!(price > 0)) continue

        const key = nameRaw.toLowerCase()
        if (existingNames.has(key)) continue

        try {
          await produitsCatalogueService.create({
            nom: nameRaw,
            categorie: 'DIVERS',
            nature: 'PRIX_UNITAIRE',
            prix_unitaire: price,
            actif: true,
          } as any)
          existingNames.add(key)
        } catch (e) {
          // On n'empêche pas l'enregistrement du colis si la création catalogue échoue (permissions, validation, etc.)
          console.warn('[ColisForm] impossible de créer le produit catalogue:', nameRaw, e)
        }
      }

      // 2) Mapper le modèle formulaire -> modèle backend
      const payload = {
        trafic_envoi: data.trafic_envoi,
        forme_envoi: formeEnvoi,
        mode_envoi: data.mode_envoi,
        date_envoi: data.date_envoi,

        ...(user?.agency_id != null && user.agency_id > 0
          ? { id_agence: user.agency_id }
          : {}),

        id_client: client.id,

        nom_dest: data.nom_destinataire,
        lieu_dest: data.lieu_dest,
        tel_dest: data.tel_dest,
        email_dest: data.email_dest || undefined,

        marchandises: (data.marchandise || []).map((m) => ({
          nom_marchandise: m.nom_marchandise,
          nbre_colis: m.nbre_colis,
          nbre_articles: m.nbre_articles,
          poids_total: m.poids_total,
          prix_unit: m.prix_unit,
          id_tarif: m.id_tarif,
          prix_emballage: m.prix_emballage || 0,
          prix_assurance: m.prix_assurance || 0,
          prix_agence: m.prix_agence || 0,
          // Toujours envoyer un tableau pour satisfaire le backend (@IsArray)
          type_emballage: Array.isArray((m as any).type_emballage)
            ? (m as any).type_emballage
            : typeof (m as any).type_emballage === 'string' && (m as any).type_emballage.trim() !== ''
              ? [(m as any).type_emballage]
              : [],
          nbre_emballage: m.nbre_emballage || 1,
        })),

        livraison: data.livraison ?? false,
        tracker_id: data.tracker_id || undefined,
        nom_recup: data.nom_recup || undefined,
        adresse_recup: data.adresse_recup || undefined,
        tel_recup: data.tel_recup || undefined,
        email_recup: data.email_recup || undefined,
      }

      onSubmit(payload as unknown as CreateColisDto)
    } catch (e: any) {
      console.error('[ColisForm] submit error:', e)
      message.error(e?.message || 'Erreur lors de la préparation des données du colis')
    }
  }


  const handleSearchClients = async (value: string) => {
    console.log('[ColisForm] Recherche clients avec:', value)
    if (!value || value.length < 2) {
      console.log('[ColisForm] Recherche annulée: valeur trop courte')
      setClients([])
      setDuplicateWarning(null)
      return
    }

    try {
      setSearching(true)
      const results = await clientsService.searchClients(value)
      setClients(results)

      // Détecte les doublons de nom exact (insensible à la casse)
      const normalized = value.trim().toLowerCase()
      const sameNameCount = results.filter(
        (c: any) => (c.nom_exp || '').trim().toLowerCase() === normalized
      ).length

      if (sameNameCount >= 2) {
        setDuplicateWarning(
          `⚠️ Attention — il existe ${sameNameCount} clients avec le nom "${value.trim()}". ` +
          `Veuillez sélectionner le bon client dans la liste en vérifiant son numéro de téléphone ou de pièce d'identité.`
        )
      } else {
        setDuplicateWarning(null)
      }
    } catch (error) {
      console.error('[ColisForm] Erreur recherche clients:', error)
    } finally {
      setSearching(false)
    }
  }


  const handleSelectClient = (value: string, option: any) => {
    const selectedClient = clients.find(c => c.id === option.key)
    if (selectedClient) {
      setValue('client_colis.nom_exp', selectedClient.nom_exp)
      setValue('client_colis.type_piece_exp', selectedClient.type_piece_exp)
      setValue('client_colis.num_piece_exp', selectedClient.num_piece_exp)
      setValue('client_colis.tel_exp', selectedClient.tel_exp)
      setValue('client_colis.email_exp', selectedClient.email_exp || '')
      setSavedClient(selectedClient)
      // Le bon client est sélectionné — on efface l'alerte
      setDuplicateWarning(null)
      setPhoneSearch('')
      setPhoneMatchMsg(null)
    }
  }

  // Désambiguation par numéro de téléphone quand plusieurs clients ont le même nom
  const handlePhoneSearch = (phone: string) => {
    setPhoneSearch(phone)
    if (!phone.trim()) {
      setPhoneMatchMsg(null)
      return
    }
    // On filtre parmi les clients déjà chargés en mémoire
    const matches = clients.filter((c: any) =>
      (c.tel_exp || '').replace(/\s/g, '').includes(phone.replace(/\s/g, ''))
    )
    if (matches.length === 1) {
      // Match unique : auto-sélection
      const c = matches[0]
      setValue('client_colis.nom_exp', c.nom_exp)
      setValue('client_colis.type_piece_exp', c.type_piece_exp)
      setValue('client_colis.num_piece_exp', c.num_piece_exp)
      setValue('client_colis.tel_exp', c.tel_exp)
      setValue('client_colis.email_exp', c.email_exp || '')
      setSavedClient(c)
      setDuplicateWarning(null)
      setPhoneSearch('')
      setPhoneMatchMsg(null)
    } else if (matches.length === 0) {
      setPhoneMatchMsg(`❌ Aucun client trouvé avec ce numéro parmi les résultats.`)
    } else {
      setPhoneMatchMsg(`🔍 ${matches.length} correspondance(s) — continuez à saisir pour affiner.`)
    }
  }

  const handleCreateClient = async () => {
    const nom = watch('client_colis.nom_exp')?.trim()
    const type_piece = watch('client_colis.type_piece_exp')
    const num_piece = watch('client_colis.num_piece_exp')?.trim()
    const tel = watch('client_colis.tel_exp')?.trim()
    const email = watch('client_colis.email_exp')

    if (!nom || !tel) {
      message.warning("Veuillez remplir au minimum le nom et le téléphone avant de créer le client")
      return
    }

    try {
      setCreatingClient(true)
      const newClient = await clientsService.createClient({
        nom_exp: nom,
        type_piece_exp: type_piece?.trim() || undefined,
        num_piece_exp: num_piece || undefined,
        tel_exp: tel,
        email_exp: email || undefined,
      })
      setSavedClient(newClient)
      message.success(`Client "${nom}" créé et enregistré avec succès !`)
    } catch (e: any) {
      message.error(e?.message || 'Erreur lors de la création du client')
    } finally {
      setCreatingClient(false)
    }
  }

  const isGroupage = formeEnvoi === 'groupage'

  return (
    <Form layout="vertical" onFinish={handleSubmit(onFormSubmit)}>
      {/* SECTION 1: TRAFIC ET DATE */}
      <Card title="Informations Générales" style={{ marginBottom: 16 }}>
        <Row gutter={16}>
          <Col xs={24} md={12}>
            <Controller
              name="trafic_envoi"
              control={control}
              render={({ field }) => (
                <Form.Item
                  label="Trafic d'envoi"
                  required
                  validateStatus={errors.trafic_envoi ? 'error' : ''}
                  help={errors.trafic_envoi?.message}
                >
                  <Select {...field} placeholder="Sélectionner le trafic d'envoi" size="large">
                    {APP_CONFIG.traficEnvoi
                      .filter((trafic) =>
                        isGroupage
                          ? trafic.includes('Groupage')
                          : trafic.includes('Colis Rapide')
                      )
                      .map((trafic) => (
                        <Option key={trafic} value={trafic}>
                          {trafic}
                        </Option>
                      ))}
                  </Select>
                </Form.Item>
              )}
            />
          </Col>

          <Col xs={24} md={12}>
            <Controller
              name="date_envoi"
              control={control}
              render={({ field }) => (
                <Form.Item
                  label="Date d'envoi"
                  required
                  validateStatus={errors.date_envoi ? 'error' : ''}
                  help={errors.date_envoi?.message}
                >
                  <DatePicker
                    value={dayjsFromApiForPicker(field.value)}
                    onChange={(date: Dayjs | null) =>
                      field.onChange(date ? date.format('YYYY-MM-DD') : '')
                    }
                    onBlur={field.onBlur}
                    format="DD/MM/YYYY"
                    style={{ width: '100%' }}
                    size="large"
                    {...(!allowPastShipmentDate
                      ? {
                          minDate: dayjs(),
                          disabledDate: (current: Dayjs) =>
                            !!current && current < dayjs().startOf('day'),
                        }
                      : {})}
                  />
                </Form.Item>
              )}
            />
          </Col>

          {!isGroupage && (
            <Col xs={24} md={12}>
              <Controller
                name="mode_envoi"
                control={control}
                render={({ field }) => (
                  <Form.Item
                    label="Mode d'envoi"
                    required
                    validateStatus={errors.mode_envoi ? 'error' : ''}
                    help={errors.mode_envoi?.message}
                  >
                    <Select {...field} placeholder="Sélectionner le mode d'envoi" size="large">
                      <Option value="DHL">DHL</Option>
                      <Option value="Colis Rapides Export">Colis Rapides Export</Option>
                      <Option value="Colis Rapides Import">Colis Rapides Import</Option>
                      <Option value="Autres">Autres</Option>
                    </Select>
                  </Form.Item>
                )}
              />
            </Col>
          )}

          {/* ID Traceur GPS — optionnel, pour le suivi temps réel sur la carte */}
          <Col xs={24} md={12}>
            <Controller
              name="tracker_id"
              control={control}
              render={({ field }) => (
                <Form.Item
                  label={
                    <span>
                      ID Traceur GPS&nbsp;
                      <span style={{ fontWeight: 400, color: '#888', fontSize: 12 }}>
                        (optionnel — ex: SPOT-001, TIVE-ABC)
                      </span>
                    </span>
                  }
                  tooltip="Entrez l'identifiant du traceur physique fixé sur ce colis pour activer le suivi en temps réel sur la cartographie."
                >
                  <Input
                    {...field}
                    placeholder="Ex: SPOT-001"
                    size="large"
                    prefix={<span style={{ fontSize: 16 }}>🛰️</span>}
                    style={{ fontFamily: 'monospace' }}
                  />
                </Form.Item>
              )}
            />
          </Col>
        </Row>
      </Card>

      {/* SECTION 2: CLIENT EXPÉDITEUR */}
      <Card
        title={
          <Space>
            Informations Expéditeur
            {savedClient && (
              <span style={{ fontSize: 13, color: '#52c41a', fontWeight: 'normal' }}>
                <CheckCircleOutlined /> {savedClient.id ? 'Client enregistré' : ''}
              </span>
            )}
          </Space>
        }
        style={{ marginBottom: 16 }}
      >
        <Row gutter={16}>
          <Col xs={24}>
            <Controller
              name="client_colis.nom_exp"
              control={control}
              render={({ field }) => (
                <Form.Item
                  label="Nom de l'expéditeur"
                  required
                  validateStatus={errors.client_colis?.nom_exp ? 'error' : ''}
                  help={errors.client_colis?.nom_exp?.message}
                >
                  <Space.Compact style={{ width: '100%' }}>
                    <AutoComplete
                      {...field}
                      style={{ flex: 1 }}
                      options={clients.map((client) => ({
                        value: client.nom_exp,
                        label: (
                          <div>
                            <strong>{client.nom_exp}</strong>
                            <br />
                            <small style={{ color: '#888' }}>
                              {client.tel_exp} • {client.type_piece_exp}
                            </small>
                          </div>
                        ),
                        key: client.id,
                      }))}
                      onSearch={(val: string) => {
                        // Si l'utilisateur retape, on réinitialise le client sauvegardé
                        if (savedClient && val !== savedClient.nom_exp) {
                          setSavedClient(null)
                        }
                        handleSearchClients(val)
                      }}
                      onSelect={(value: string, option: any) => {
                        field.onChange(value)
                        handleSelectClient(value, option)
                      }}
                      onChange={(val: string) => {
                        field.onChange(val)
                        // Si l'utilisateur vide le champ, on réinitialise
                        if (!val) {
                          setSavedClient(null)
                          setDuplicateWarning(null)
                          setPhoneSearch('')
                          setPhoneMatchMsg(null)
                        }
                      }}
                      placeholder="Rechercher ou saisir un client"
                      size="large"
                      allowClear
                      notFoundContent={searching ? 'Recherche...' : null}
                    />
                    {!savedClient && (
                      <WithPermission permission={PERMISSIONS.CLIENTS.CREATE}>
                        <Button
                          size="large"
                          type="primary"
                          icon={<UserAddOutlined />}
                          loading={creatingClient}
                          onClick={handleCreateClient}
                          title="Créer et enregistrer ce client"
                          style={{ whiteSpace: 'nowrap' }}
                        >
                          Créer et enregistrer
                        </Button>
                      </WithPermission>
                    )}
                    {savedClient && (
                      <Button
                        size="large"
                        icon={<CheckCircleOutlined />}
                        style={{ color: '#52c41a', borderColor: '#52c41a', whiteSpace: 'nowrap' }}
                        onClick={() => {
                          setSavedClient(null)
                          setValue('client_colis.nom_exp', '')
                          setValue('client_colis.type_piece_exp', '')
                          setValue('client_colis.num_piece_exp', '')
                          setValue('client_colis.tel_exp', '')
                          setValue('client_colis.email_exp', '')
                          setClients([])
                        }}
                      >
                        Changer de client
                      </Button>
                    )}
                  </Space.Compact>

                  {/* Bloc désambiguation doublons */}
                  {duplicateWarning && (
                    <div style={{ marginTop: 8 }}>
                      <Alert
                        type="warning"
                        showIcon
                        message={duplicateWarning}
                        style={{ marginBottom: 8 }}
                      />
                      {/* Saisie du numéro de téléphone pour trouver le bon client */}
                      <Input
                        prefix={<span style={{ color: '#B8900A', fontWeight: 700 }}>📞</span>}
                        placeholder="Saisir le numéro de téléphone du client pour l'identifier…"
                        value={phoneSearch}
                        onChange={({ currentTarget: { value } }: React.ChangeEvent<HTMLInputElement>) => handlePhoneSearch(value)}
                        size="large"
                        allowClear
                        style={{
                          borderColor: '#B8900A',
                          boxShadow: '0 0 0 2px rgba(184,144,10,0.15)',
                        }}
                      />
                      {phoneMatchMsg && (
                        <div style={{ marginTop: 4, fontSize: 12, color: phoneMatchMsg.startsWith('❌') ? '#c0392b' : '#1A2B5B' }}>
                          {phoneMatchMsg}
                        </div>
                      )}
                    </div>
                  )}
                </Form.Item>
              )}
            />
          </Col>

          <Col xs={24} md={6}>
            <Controller
              name="client_colis.type_piece_exp"
              control={control}
              render={({ field }) => (
                <Form.Item
                  label="Type de pièce d'identité"
                  validateStatus={errors.client_colis?.type_piece_exp ? 'error' : ''}
                  help={errors.client_colis?.type_piece_exp?.message}
                >
                  <Select {...field} placeholder="Type" size="large">
                    {APP_CONFIG.typesPieceIdentite.map((type) => (
                      <Option key={type} value={type}>
                        {type}
                      </Option>
                    ))}
                  </Select>
                </Form.Item>
              )}
            />
          </Col>

          <Col xs={24} md={6}>
            <Controller
              name="client_colis.num_piece_exp"
              control={control}
              render={({ field }) => (
                <Form.Item
                  label="N° Pièce d'identité"
                  validateStatus={errors.client_colis?.num_piece_exp ? 'error' : ''}
                  help={errors.client_colis?.num_piece_exp?.message}
                >
                  <Input {...field} placeholder="N° pièce" size="large" />
                </Form.Item>
              )}
            />
          </Col>

          <Col xs={24} md={12}>
            <Controller
              name="client_colis.tel_exp"
              control={control}
              render={({ field }) => (
                <Form.Item
                  label="Téléphone"
                  required
                  validateStatus={errors.client_colis?.tel_exp ? 'error' : ''}
                  help={errors.client_colis?.tel_exp?.message}
                >
                  <Input {...field} placeholder="+225 XX XX XX XX XX" size="large" />
                </Form.Item>
              )}
            />
          </Col>

          <Col xs={24} md={12}>
            <Controller
              name="client_colis.email_exp"
              control={control}
              render={({ field }) => (
                <Form.Item
                  label="Email (optionnel)"
                  validateStatus={errors.client_colis?.email_exp ? 'error' : ''}
                  help={errors.client_colis?.email_exp?.message}
                >
                  <Input {...field} type="email" placeholder="email@example.com" size="large" />
                </Form.Item>
              )}
            />
          </Col>
        </Row>
      </Card>

      {/* SECTION 3: MARCHANDISE (Dynamique) */}
      <Card
        title="Informations Marchandise"
        extra={
          <Button type="dashed" icon={<PlusOutlined />} onClick={addMarchandiseLine}>
            Ajouter une ligne
          </Button>
        }
        style={{ marginBottom: 16 }}
      >
        {marchandiseLines.map((lineIndex, index) => (
          <Card
            key={lineIndex}
            type="inner"
            title={`Colis N°${index + 1}`}
            extra={
              marchandiseLines.length > 1 && (
                <Button
                  type="text"
                  danger
                  icon={<DeleteOutlined />}
                  onClick={() => removeMarchandiseLine(index)}
                >
                  Supprimer
                </Button>
              )
            }
            style={{ marginBottom: 16 }}
          >
            <Row gutter={16}>
              {/* NOUVEAU: Sélecteur de produit du catalogue */}
              <Col xs={24} md={12}>
                <Form.Item
                  label="Produit du catalogue (optionnel)"
                  help="Tapez pour filtrer. S’il n’existe pas, utilisez « + Ajouter … au catalogue » en bas de la liste. Vous pouvez aussi saisir nom + prix ci-dessous : le produit sera créé à l’enregistrement du colis."
                >
                  <Select
                    mode="multiple"
                    placeholder="Rechercher ou sélectionner des produits..."
                    size="large"
                    allowClear
                    showSearch
                    optionFilterProp="label"
                    loading={loadingProduits}
                    getPopupContainer={selectPopupToBody}
                    value={watch(`marchandise.${index}.produits_catalogue_ids`) ?? []}
                    onSearch={(text: string) =>
                      setProduitSearchByRow((prev) => ({ ...prev, [index]: text }))
                    }
                    onDropdownVisibleChange={(open: boolean) => {
                      if (!open) {
                        setProduitSearchByRow((prev) => {
                          const next = { ...prev }
                          delete next[index]
                          return next
                        })
                      }
                    }}
                    filterOption={(input: string, option: any) =>
                      (option?.label ?? '').toString().toLowerCase().includes(input.toLowerCase())
                    }
                    onChange={(ids: number[]) => {
                      const prev =
                        getValues(`marchandise.${index}.produits_catalogue_ids`) || []
                      const safeIds = Array.isArray(ids) ? ids : []
                      const ok = syncMarchandiseFromCatalogue(
                        index,
                        safeIds,
                        produitsCatalogue,
                      )
                      if (!ok) {
                        setValue(`marchandise.${index}.produits_catalogue_ids`, prev)
                      }
                    }}
                    dropdownRender={(menu: React.ReactNode) => {
                      const q = (produitSearchByRow[index] ?? '').trim()
                      const qLower = q.toLowerCase()
                      const hasMatch =
                        !qLower ||
                        produitsCatalogue.some(
                          (p) => p.actif && p.nom.toLowerCase().includes(qLower),
                        )
                      return (
                        <>
                          {menu}
                          {q.length > 0 && !hasMatch && (
                            <div
                              style={{
                                padding: '8px 12px',
                                borderTop: '1px solid var(--ant-color-border-secondary, #f0f0f0)',
                              }}
                            >
                              <Button
                                type="link"
                                block
                                style={{ textAlign: 'left', height: 'auto', whiteSpace: 'normal' }}
                                onClick={() => {
                                  setQuickAddRow({ rowIndex: index, nom: q })
                                  setQuickAddPrix(null)
                                  setQuickAddPrixForfait(null)
                                  setQuickAddPoidsMin(null)
                                  setQuickAddPoidsMax(null)
                                  setQuickAddCategorie('DIVERS')
                                  setQuickAddMode('AU_KG')
                                  setQuickAddUnite('kg')
                                }}
                              >
                                + Ajouter « {q} » au catalogue
                              </Button>
                            </div>
                          )}
                        </>
                      )
                    }}
                    options={produitsCatalogue
                      .filter((p) => p.actif)
                      .map((p) => {
                        const prix =
                          Number(p.prix_unitaire || 0) || Number(p.prix_forfaitaire || 0)
                        const devise = p.devise || 'FCFA'
                        return {
                          value: p.id,
                          label: `${p.nom} (${prix} ${devise})`,
                        }
                      })}
                  />
                </Form.Item>
              </Col>

              <Col xs={24}>
                <Controller
                  name={`marchandise.${index}.nom_marchandise`}
                  control={control}
                  render={({ field }) => (
                    <Form.Item
                      label="Nom de la marchandise"
                      required
                      validateStatus={errors.marchandise?.[index]?.nom_marchandise ? 'error' : ''}
                      help={errors.marchandise?.[index]?.nom_marchandise?.message}
                    >
                      <Input {...field} placeholder="Description de la marchandise" size="large" />
                    </Form.Item>
                  )}
                />
              </Col>

              <Col xs={24} sm={6}>
                <Controller
                  name={`marchandise.${index}.nbre_colis`}
                  control={control}
                  render={({ field }) => (
                    <Form.Item
                      label="Nombre de colis"
                      required
                      validateStatus={errors.marchandise?.[index]?.nbre_colis ? 'error' : ''}
                      help={errors.marchandise?.[index]?.nbre_colis?.message}
                    >
                      <InputNumber
                        {...field}
                        value={field.value}
                        onChange={(value: number | null) => field.onChange(value || 0)}
                        min={1}
                        style={{ width: '100%' }}
                        size="large"
                      />
                    </Form.Item>
                  )}
                />
              </Col>

              {/* Type d'emballage */}
              <Col xs={24} sm={6}>
                <Controller
                  name={`marchandise.${index}.type_emballage`}
                  control={control}
                  render={({ field }) => (
                    <Form.Item label="Type d'emballage">
                      <Select
                        placeholder="Sélectionner un ou plusieurs types"
                        size="large"
                        allowClear
                        mode="multiple"
                        maxTagCount="responsive"
                        getPopupContainer={selectPopupToBody}
                        popupMatchSelectWidth={false}
                        onBlur={field.onBlur}
                        tagRender={(props: {
                          label: React.ReactNode
                          value: string | number
                          closable: boolean
                          onClose?: (e?: React.MouseEvent<HTMLElement, MouseEvent>) => void
                        }) => {
                          const { label, value, closable, onClose } = props
                          const v = String(value)
                          const text =
                            typeof label === 'string' && label.trim() !== ''
                              ? label
                              : EMBALLAGE_TYPE_LABELS[v] || v
                          return (
                            <Tag closable={closable} onClose={onClose} style={{ marginInlineEnd: 4 }}>
                              {text}
                            </Tag>
                          )
                        }}
                        value={(() => {
                          const v = field.value as unknown
                          if (Array.isArray(v)) return v as string[]
                          if (typeof v === 'string' && v.trim() !== '') return [v]
                          return []
                        })()}
                        onChange={(vals: string[]) =>
                          field.onChange(Array.isArray(vals) ? vals : [])
                        }
                      >
                        <Option value="petit_carton">📦 Petit Carton</Option>
                        <Option value="moyen_carton">📦 Moyen Carton</Option>
                        <Option value="grand_carton">📦 Grand Carton</Option>
                        <Option value="sac">👜 Sac</Option>
                        <Option value="valise">🧳 Valise</Option>
                        <Option value="bôrô">🪣 Bôrô</Option>
                      </Select>
                    </Form.Item>
                  )}
                />
              </Col>

              <Col xs={24} sm={4}>
                <Controller
                  name={`marchandise.${index}.nbre_emballage`}
                  control={control}
                  render={({ field }) => (
                    <Form.Item
                      label="Nbre d'emb."
                      required
                      validateStatus={errors.marchandise?.[index]?.nbre_emballage ? 'error' : ''}
                      help={errors.marchandise?.[index]?.nbre_emballage?.message}
                    >
                      <InputNumber
                        {...field}
                        value={field.value}
                        onChange={(value: number | null) => field.onChange(value || 1)}
                        min={1}
                        style={{ width: '100%' }}
                        size="large"
                      />
                    </Form.Item>
                  )}
                />
              </Col>

              <Col xs={24} sm={4}>
                <Controller
                  name={`marchandise.${index}.nbre_articles`}
                  control={control}
                  render={({ field }) => (
                    <Form.Item
                      label="Nombre d'articles"
                      required
                      validateStatus={errors.marchandise?.[index]?.nbre_articles ? 'error' : ''}
                      help={errors.marchandise?.[index]?.nbre_articles?.message}
                    >
                      <InputNumber
                        {...field}
                        value={field.value}
                        onChange={(value: number | null) => field.onChange(value || 0)}
                        min={1}
                        style={{ width: '100%' }}
                        size="large"
                      />
                    </Form.Item>
                  )}
                />
              </Col>



              <Col xs={24} sm={8}>
                <Controller
                  name={`marchandise.${index}.poids_total`}
                  control={control}
                  render={({ field }) => (
                    <Form.Item
                      label="Poids total (Kg)"
                      required
                      validateStatus={errors.marchandise?.[index]?.poids_total ? 'error' : ''}
                      help={errors.marchandise?.[index]?.poids_total?.message}
                    >
                      <InputNumber
                        {...field}
                        value={field.value}
                        onChange={(value: number | null) => field.onChange(value || 0)}
                        min={0.01}
                        step={0.01}
                        style={{ width: '100%' }}
                        size="large"
                      />
                    </Form.Item>
                  )}
                />
              </Col>

              <Col xs={24} sm={8}>
                <Controller
                  name={`marchandise.${index}.prix_unit`}
                  control={control}
                  render={({ field }) => (
                    <Form.Item
                      label={`Prix unitaire (${currency})`}
                      required
                      validateStatus={errors.marchandise?.[index]?.prix_unit ? 'error' : ''}
                      help={errors.marchandise?.[index]?.prix_unit?.message}
                    >
                      <InputNumber
                        {...field}
                        value={field.value}
                        onChange={(value: number | null) => field.onChange(value || 0)}
                        min={0}
                        style={{ width: '100%' }}
                        size="large"
                      />
                    </Form.Item>
                  )}
                />
              </Col>

              <Col xs={24} sm={8}>
                <Controller
                  name={`marchandise.${index}.prix_emballage`}
                  control={control}
                  render={({ field }) => (
                    <Form.Item label={`Prix emballage (${currency})`}>
                      <InputNumber
                        {...field}
                        value={field.value || 0}
                        onChange={(value: number | null) => field.onChange(value || 0)}
                        min={0}
                        style={{ width: '100%' }}
                        size="large"
                      />
                    </Form.Item>
                  )}
                />
              </Col>

              <Col xs={24} sm={8}>
                <Controller
                  name={`marchandise.${index}.prix_assurance`}
                  control={control}
                  render={({ field }) => (
                    <Form.Item label={`Prix assurance (${currency})`}>
                      <InputNumber
                        {...field}
                        value={field.value || 0}
                        onChange={(value: number | null) => field.onChange(value || 0)}
                        min={0}
                        style={{ width: '100%' }}
                        size="large"
                      />
                    </Form.Item>
                  )}
                />
              </Col>

              {!isGroupage && (
                <Col xs={24} sm={8}>
                  <Controller
                    name={`marchandise.${index}.prix_agence`}
                    control={control}
                    render={({ field }) => (
                      <Form.Item label="Frais Compagnie/Agence (optionnel)">
                        <InputNumber
                          {...field}
                          value={field.value || 0}
                          onChange={(value: number | null) => field.onChange(value || 0)}
                          min={0}
                          style={{ width: '100%' }}
                          size="large"
                        />
                      </Form.Item>
                    )}
                  />
                </Col>
              )}

              <Col xs={24}>
                <Text strong>
                  Total ligne:{' '}
                  {formatMontantWithDevise(
                    calculerTotalLigneMarchandise(
                      watch(`marchandise.${index}.prix_unit`) || 0,
                      watch(`marchandise.${index}.poids_total`) || 0,
                      watch(`marchandise.${index}.prix_emballage`) || 0,
                      watch(`marchandise.${index}.prix_assurance`) || 0,
                      watch(`marchandise.${index}.prix_agence`) || 0
                    ),
                    currency
                  )}
                </Text>
              </Col>
            </Row>
          </Card>
        ))}

        <Divider />
        <div style={{ textAlign: 'right', paddingTop: 16 }}>
          <Title level={4}>
            Total Général: {formatMontantWithDevise(
              marchandiseLines.reduce((total, _, index) => {
                return total + calculerTotalLigneMarchandise(
                  watch(`marchandise.${index}.prix_unit`) || 0,
                  watch(`marchandise.${index}.poids_total`) || 0,
                  watch(`marchandise.${index}.prix_emballage`) || 0,
                  watch(`marchandise.${index}.prix_assurance`) || 0,
                  watch(`marchandise.${index}.prix_agence`) || 0
                )
              }, 0),
              currency
            )}
          </Title>
        </div>
      </Card>

      {/* SECTION 4: DESTINATAIRE */}
      <Card title="Informations Destinataire" style={{ marginBottom: 16 }}>
        <Row gutter={16}>
          <Col xs={24} md={12}>
            <Controller
              name="nom_destinataire"
              control={control}
              render={({ field }) => (
                <Form.Item
                  label="Nom du destinataire"
                  required
                  validateStatus={errors.nom_destinataire ? 'error' : ''}
                  help={errors.nom_destinataire?.message}
                >
                  <Input {...field} placeholder="Nom complet" size="large" />
                </Form.Item>
              )}
            />
          </Col>

          <Col xs={24} md={12}>
            <Controller
              name="lieu_dest"
              control={control}
              render={({ field }) => (
                <Form.Item
                  label="Lieu de destination"
                  required
                  validateStatus={errors.lieu_dest ? 'error' : ''}
                  help={errors.lieu_dest?.message}
                >
                  <Input {...field} placeholder="Ville, Pays" size="large" />
                </Form.Item>
              )}
            />
          </Col>

          <Col xs={24} md={12}>
            <Controller
              name="tel_dest"
              control={control}
              render={({ field }) => (
                <Form.Item
                  label="Téléphone"
                  required
                  validateStatus={errors.tel_dest ? 'error' : ''}
                  help={errors.tel_dest?.message}
                >
                  <Input {...field} placeholder="+225 XX XX XX XX XX" size="large" />
                </Form.Item>
              )}
            />
          </Col>

          <Col xs={24} md={12}>
            <Controller
              name="email_dest"
              control={control}
              render={({ field }) => (
                <Form.Item
                  label="Email (optionnel)"
                  validateStatus={errors.email_dest ? 'error' : ''}
                  help={errors.email_dest?.message}
                >
                  <Input {...field} type="email" placeholder="email@example.com" size="large" />
                </Form.Item>
              )}
            />
          </Col>

          {/* Livraison à domicile */}
          <Col xs={24}>
            <Controller
              name="livraison"
              control={control}
              render={({ field }) => (
                <Form.Item>
                  <Checkbox
                    checked={field.value ?? false}
                    onChange={(e: { target: { checked: boolean } }) => field.onChange(e.target.checked)}
                  >
                    <span style={{ fontWeight: 600, fontSize: 14 }}>
                      🚚 Livraison à domicile demandée
                    </span>
                    <span style={{ color: '#888', marginLeft: 8, fontSize: 12 }}>
                      (si coché, "LIVRAISON : OUI" apparaîtra sur la facture)
                    </span>
                  </Checkbox>
                </Form.Item>
              )}
            />
          </Col>
        </Row>
      </Card>

      {/* SECTION 5: RÉCUPÉRATEUR (Optionnel) */}
      <Card title="Informations Récupérateur (Optionnel)" style={{ marginBottom: 16 }}>
        <Row gutter={16}>
          <Col xs={24} md={12}>
            <Controller
              name="nom_recup"
              control={control}
              render={({ field }) => (
                <Form.Item label="Nom du récupérateur">
                  <Input {...field} placeholder="Nom complet" size="large" />
                </Form.Item>
              )}
            />
          </Col>

          <Col xs={24} md={12}>
            <Controller
              name="adresse_recup"
              control={control}
              render={({ field }) => (
                <Form.Item label="Adresse">
                  <Input {...field} placeholder="Adresse complète" size="large" />
                </Form.Item>
              )}
            />
          </Col>

          <Col xs={24} md={12}>
            <Controller
              name="tel_recup"
              control={control}
              render={({ field }) => (
                <Form.Item label="Téléphone">
                  <Input {...field} placeholder="+225 XX XX XX XX XX" size="large" />
                </Form.Item>
              )}
            />
          </Col>

          <Col xs={24} md={12}>
            <Controller
              name="email_recup"
              control={control}
              render={({ field }) => (
                <Form.Item
                  label="Email"
                  validateStatus={errors.email_recup ? 'error' : ''}
                  help={errors.email_recup?.message}
                >
                  <Input {...field} type="email" placeholder="email@example.com" size="large" />
                </Form.Item>
              )}
            />
          </Col>
        </Row>
      </Card>

      {/* BOUTONS D'ACTION */}
      <Card>
        <Space>
          <Button type="primary" htmlType="submit" icon={<SaveOutlined />} size="large" loading={loading}>
            Enregistrer
          </Button>
          <Button size="large" onClick={onCancel}>Annuler</Button>
        </Space>
      </Card>

      <Modal
        title="Ajouter un produit au catalogue"
        open={quickAddRow !== null}
        onCancel={resetQuickAddModal}
        destroyOnClose
        okText="Enregistrer et sélectionner"
        cancelText="Annuler"
        confirmLoading={quickAddSubmitting}
        onOk={() => void submitQuickAddCatalogue()}
      >
        {quickAddRow ? (
          <Space direction="vertical" size="middle" style={{ width: '100%' }}>
            <div>
              <Text type="secondary">Nom</Text>
              <Input
                value={quickAddRow.nom}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                  setQuickAddRow((prev) =>
                    prev ? { ...prev, nom: e.target.value } : prev,
                  )
                }
                placeholder="Nom du produit"
                size="large"
              />
            </div>
            <div>
              <Text type="secondary">Catégorie</Text>
              <Select
                size="large"
                style={{ width: '100%' }}
                value={quickAddCategorie}
                onChange={(v: ProduitCatalogue['categorie']) => setQuickAddCategorie(v)}
                options={CATALOGUE_CATEGORY_OPTIONS}
              />
            </div>
            <div>
              <Text type="secondary">Type de tarif</Text>
              <Radio.Group
                value={quickAddMode}
                onChange={(e: RadioChangeEvent) =>
                  setQuickAddMode(e.target.value as QuickCatalogueTarifMode)
                }
                style={{ display: 'flex', flexDirection: 'column', gap: 8 }}
              >
                <Radio value="AU_KG">Prix au kg</Radio>
                <Radio value="FORFAIT">Forfait (optionnel : tranche de poids min. / max.)</Radio>
                <Radio value="FORFAIT_ET_KG">Forfait sur tranche + prix au kg au-delà</Radio>
              </Radio.Group>
            </div>
            {quickAddMode === 'AU_KG' && (
              <>
                <div>
                  <Text type="secondary">Prix au kg (FCFA)</Text>
                  <InputNumber
                    min={0}
                    style={{ width: '100%' }}
                    value={quickAddPrix ?? undefined}
                    onChange={(v: number | null) => setQuickAddPrix(typeof v === 'number' ? v : null)}
                    placeholder="Ex. 850"
                  />
                </div>
                <div>
                  <Text type="secondary">Unité</Text>
                  <Select
                    size="large"
                    style={{ width: '100%' }}
                    value={quickAddUnite}
                    onChange={(v: string) => setQuickAddUnite(v)}
                    options={UNITE_CATALOGUE_OPTIONS}
                  />
                </div>
              </>
            )}
            {quickAddMode === 'FORFAIT' && (
              <>
                <div>
                  <Text type="secondary">Montant forfaitaire (FCFA)</Text>
                  <InputNumber
                    min={0}
                    style={{ width: '100%' }}
                    value={quickAddPrixForfait ?? undefined}
                    onChange={(v: number | null) =>
                      setQuickAddPrixForfait(typeof v === 'number' ? v : null)
                    }
                  />
                </div>
                <Row gutter={12}>
                  <Col span={12}>
                    <Text type="secondary">Poids min. tranche (kg)</Text>
                    <InputNumber
                      min={0}
                      style={{ width: '100%' }}
                      value={quickAddPoidsMin ?? undefined}
                      onChange={(v: number | null) =>
                        setQuickAddPoidsMin(typeof v === 'number' ? v : null)
                      }
                    />
                  </Col>
                  <Col span={12}>
                    <Text type="secondary">Poids max. tranche (kg)</Text>
                    <InputNumber
                      min={0}
                      style={{ width: '100%' }}
                      value={quickAddPoidsMax ?? undefined}
                      onChange={(v: number | null) =>
                        setQuickAddPoidsMax(typeof v === 'number' ? v : null)
                      }
                    />
                  </Col>
                </Row>
                <div>
                  <Text type="secondary">Unité</Text>
                  <Select
                    size="large"
                    style={{ width: '100%' }}
                    value={quickAddUnite}
                    onChange={(v: string) => setQuickAddUnite(v)}
                    options={UNITE_CATALOGUE_OPTIONS}
                  />
                </div>
              </>
            )}
            {quickAddMode === 'FORFAIT_ET_KG' && (
              <>
                <div>
                  <Text type="secondary">Forfait sur la tranche (FCFA)</Text>
                  <InputNumber
                    min={0}
                    style={{ width: '100%' }}
                    value={quickAddPrixForfait ?? undefined}
                    onChange={(v: number | null) =>
                      setQuickAddPrixForfait(typeof v === 'number' ? v : null)
                    }
                  />
                </div>
                <Row gutter={12}>
                  <Col span={12}>
                    <Text type="secondary">Poids min. (kg)</Text>
                    <InputNumber
                      min={0}
                      style={{ width: '100%' }}
                      value={quickAddPoidsMin ?? undefined}
                      onChange={(v: number | null) =>
                        setQuickAddPoidsMin(typeof v === 'number' ? v : null)
                      }
                    />
                  </Col>
                  <Col span={12}>
                    <Text type="secondary">Poids max. (kg)</Text>
                    <InputNumber
                      min={0}
                      style={{ width: '100%' }}
                      value={quickAddPoidsMax ?? undefined}
                      onChange={(v: number | null) =>
                        setQuickAddPoidsMax(typeof v === 'number' ? v : null)
                      }
                    />
                  </Col>
                </Row>
                <div>
                  <Text type="secondary">Prix au kg au-delà de la tranche (FCFA)</Text>
                  <InputNumber
                    min={0}
                    style={{ width: '100%' }}
                    value={quickAddPrix ?? undefined}
                    onChange={(v: number | null) => setQuickAddPrix(typeof v === 'number' ? v : null)}
                  />
                </div>
                <div>
                  <Text type="secondary">Unité</Text>
                  <Select
                    size="large"
                    style={{ width: '100%' }}
                    value={quickAddUnite}
                    onChange={(v: string) => setQuickAddUnite(v)}
                    options={UNITE_CATALOGUE_OPTIONS}
                  />
                </div>
              </>
            )}
            <Alert
              type="info"
              showIcon
              message="Ligne avec plusieurs produits"
              description="Si vous cumulez plusieurs fiches sur une ligne, le calcul n’accepte qu’un seul tarif de référence (prix au kg prioritaire sur la fiche, sinon forfait)."
            />
          </Space>
        ) : null}
      </Modal>
    </Form>
  )
}
