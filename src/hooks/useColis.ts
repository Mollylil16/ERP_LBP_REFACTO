import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { colisService } from '@services/colis.service'
import type { CreateColisDto, UpdateColisDto } from '@types'
import { PaginationParams } from '@types'
import toast from 'react-hot-toast'

/**
 * Hook pour récupérer la liste des colis
 */
export function useColisList(
  formeEnvoi: 'groupage' | 'autres_envoi',
  params?: PaginationParams,
  options?: { enabled?: boolean }
) {
  return useQuery({
    queryKey: ['colis', formeEnvoi, params],
    queryFn: () => colisService.getColis(formeEnvoi, params),
    enabled: options?.enabled,
  })
}

/**
 * Hook pour récupérer un colis par ID
 */
export function useColis(id: number | null) {
  return useQuery({
    queryKey: ['colis', id],
    queryFn: () => colisService.getColisById(id!),
    enabled: !!id,
  })
}

/**
 * Hook pour créer un colis groupage
 */
export function useCreateGroupage() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (data: CreateColisDto) => colisService.createGroupage(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['colis'] })
      toast.success('Colis groupage créé avec succès')
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Erreur lors de la création du colis')
    },
  })
}

/**
 * Hook pour créer un colis autres envois
 */
export function useCreateAutresEnvois() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (data: CreateColisDto) => colisService.createAutresEnvois(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['colis'] })
      toast.success('Colis créé avec succès')
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Erreur lors de la création du colis')
    },
  })
}

/**
 * Hook pour mettre à jour un colis
 */
export function useUpdateColis() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: UpdateColisDto }) =>
      colisService.updateColis(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['colis'] })
      toast.success('Colis mis à jour avec succès')
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Erreur lors de la mise à jour')
    },
  })
}

/**
 * Hook pour supprimer un colis
 */
export function useDeleteColis() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => colisService.deleteColis(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['colis'] })
      toast.success('Colis supprimé avec succès')
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Erreur lors de la suppression')
    },
  })
}

/**
 * Hook pour valider un colis
 */
export function useValidateColis() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => colisService.validateColis(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['colis'] })
      toast.success('Colis validé avec succès')
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Erreur lors de la validation')
    },
  })
}

/**
 * Hook pour marquer un colis comme reçu au Hub (Bobigny)
 */
export function useReceiveAtHub() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => colisService.receiveAtHub(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['colis'] })
      toast.success('Colis marqué comme reçu à Bobigny')
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Erreur lors de la mise à jour du statut')
    },
  })
}
