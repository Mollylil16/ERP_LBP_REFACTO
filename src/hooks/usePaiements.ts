import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { paiementsService, CreatePaiementDto, UpdatePaiementDto } from '@services/paiements.service'
import { PaginationParams } from '@types'
import toast from 'react-hot-toast'

/**
 * Hook pour récupérer la liste des paiements
 */
export function usePaiementsList(params?: PaginationParams) {
  return useQuery({
    queryKey: ['paiements', params],
    queryFn: () => paiementsService.getPaiements(params),
  })
}

/**
 * Hook pour récupérer un paiement par ID
 */
export function usePaiement(id: number | null) {
  return useQuery({
    queryKey: ['paiements', id],
    queryFn: () => paiementsService.getPaiementById(id!),
    enabled: !!id,
  })
}

/**
 * Hook pour récupérer les paiements d'un colis
 */
export function usePaiementsByColis(refColis: string | null) {
  return useQuery({
    queryKey: ['paiements', 'colis', refColis],
    queryFn: () => paiementsService.getPaiementsByColis(refColis!),
    enabled: !!refColis,
  })
}

/**
 * Hook pour créer un paiement
 */
export function useCreatePaiement() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (data: CreatePaiementDto) => paiementsService.createPaiement(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['paiements'] })
      queryClient.invalidateQueries({ queryKey: ['colis'] })
      queryClient.invalidateQueries({ queryKey: ['factures'] })
      toast.success('Paiement enregistré avec succès')
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Erreur lors de l\'enregistrement du paiement')
    },
  })
}

/**
 * Hook pour annuler un paiement
 */
export function useCancelPaiement() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => paiementsService.cancelPaiement(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['paiements'] })
      queryClient.invalidateQueries({ queryKey: ['colis'] })
      toast.success('Paiement annulé avec succès')
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Erreur lors de l\'annulation')
    },
  })
}
