/**
 * Hook React Query pour la gestion de la caisse
 */

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  getCaisses,
  getCaisseById,
  getSoldeCaisse,
  getMouvementsCaisse,
  createAppro,
  createDecaissement,
  createEntreeCaisse,
  deleteMouvementCaisse,
  getRapportGrandesLignes,
  getJourneeConsolidee,
} from '@services/caisse.service'
import type { Caisse, MouvementCaisse, RapportGrandesLignes } from '@types'
import { message } from 'antd'

const QUERY_KEYS = {
  caisses: ['caisses'] as const,
  caisse: (id: number) => ['caisses', id] as const,
  soldeCaisse: (idCaisse?: number) => ['caisses', 'solde', idCaisse] as const,
  mouvements: (params?: any) => ['caisses', 'mouvements', params] as const,
  rapport: (params?: any) => ['caisses', 'rapport', params] as const,
  journeeConsolidee: (date?: string) => ['caisses', 'journee-consolidee', date] as const,
}

/**
 * Hook pour récupérer la liste des caisses
 */
export const useCaisses = () => {
  return useQuery<Caisse[], Error>({
    queryKey: QUERY_KEYS.caisses,
    queryFn: () => getCaisses(),
    staleTime: 60000, // 1 minute
  })
}

/**
 * Hook pour récupérer une caisse par ID
 */
export const useCaisse = (id: number) => {
  return useQuery<Caisse, Error>({
    queryKey: QUERY_KEYS.caisse(id),
    queryFn: () => getCaisseById(id),
    enabled: !!id,
    staleTime: 60000,
  })
}

/**
 * Hook pour récupérer le solde d'une caisse
 */
export const useSoldeCaisse = (idCaisse?: number) => {
  return useQuery<number, Error>({
    queryKey: QUERY_KEYS.soldeCaisse(idCaisse),
    queryFn: () => getSoldeCaisse(idCaisse),
    staleTime: 30000, // 30 secondes
    refetchInterval: 30000, // Actualisation automatique toutes les 30 secondes
  })
}

/**
 * Hook pour récupérer les mouvements de caisse
 */
export const useMouvementsCaisse = (params?: {
  type?: string
  date_debut?: string
  date_fin?: string
  id_caisse?: number
}) => {
  return useQuery<MouvementCaisse[], Error>({
    queryKey: QUERY_KEYS.mouvements(params),
    queryFn: () => getMouvementsCaisse(params),
    staleTime: 30000,
  })
}

/** Journée consolidée (toutes caisses pour caissier principal / direction, sinon agence seule). */
export const useJourneeConsolidee = (date?: string) => {
  return useQuery({
    queryKey: QUERY_KEYS.journeeConsolidee(date),
    queryFn: () => getJourneeConsolidee(date),
    staleTime: 30_000,
  })
}

/**
 * Hook pour créer un approvisionnement
 */
export const useCreateAppro = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (data: Partial<MouvementCaisse>) => createAppro(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: QUERY_KEYS.mouvements() })
      queryClient.invalidateQueries({ queryKey: QUERY_KEYS.soldeCaisse() })
      queryClient.invalidateQueries({ queryKey: QUERY_KEYS.caisses })
      message.success('Approvisionnement créé avec succès')
    },
    onError: (error: any) => {
      message.error(error?.response?.data?.message || 'Erreur lors de la création de l\'approvisionnement')
    },
  })
}

/**
 * Hook pour créer un décaissement
 */
export const useCreateDecaissement = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (data: Partial<MouvementCaisse>) => createDecaissement(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: QUERY_KEYS.mouvements() })
      queryClient.invalidateQueries({ queryKey: QUERY_KEYS.soldeCaisse() })
      queryClient.invalidateQueries({ queryKey: QUERY_KEYS.caisses })
      message.success('Décaissement créé avec succès')
    },
    onError: (error: any) => {
      message.error(error?.response?.data?.message || 'Erreur lors de la création du décaissement')
    },
  })
}

/**
 * Hook pour créer une entrée de caisse
 */
export const useCreateEntreeCaisse = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (data: Partial<MouvementCaisse>) => createEntreeCaisse(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: QUERY_KEYS.mouvements() })
      queryClient.invalidateQueries({ queryKey: QUERY_KEYS.soldeCaisse() })
      queryClient.invalidateQueries({ queryKey: QUERY_KEYS.caisses })
      message.success('Entrée de caisse créée avec succès')
    },
    onError: (error: any) => {
      message.error(error?.response?.data?.message || 'Erreur lors de la création de l\'entrée de caisse')
    },
  })
}

/**
 * Hook pour supprimer un mouvement de caisse
 */
export const useDeleteMouvementCaisse = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => deleteMouvementCaisse(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: QUERY_KEYS.mouvements() })
      queryClient.invalidateQueries({ queryKey: QUERY_KEYS.soldeCaisse() })
      queryClient.invalidateQueries({ queryKey: QUERY_KEYS.caisses })
      message.success('Mouvement supprimé avec succès')
    },
    onError: (error: any) => {
      message.error(error?.response?.data?.message || 'Erreur lors de la suppression du mouvement')
    },
  })
}

/**
 * Hook pour récupérer le rapport "Grandes Lignes"
 */
export const useRapportGrandesLignes = (params: {
  date_debut: string
  date_fin: string
  id_caisse?: number
}) => {
  return useQuery<RapportGrandesLignes, Error>({
    queryKey: QUERY_KEYS.rapport(params),
    queryFn: () => getRapportGrandesLignes(params),
    enabled: !!params.date_debut && !!params.date_fin,
    staleTime: 60000,
  })
}
