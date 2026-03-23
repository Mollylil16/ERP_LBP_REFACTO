import { useQuery } from '@tanstack/react-query'
import { produitsCatalogueService, ProduitCatalogue } from '@services/produits-catalogue.service'

/**
 * Hook pour récupérer tous les produits du catalogue
 */
export const useProduitsCatalogue = () => {
    return useQuery({
        queryKey: ['produits-catalogue'],
        queryFn: () => produitsCatalogueService.getAll(),
        staleTime: 1000 * 5, // 5 secondes (pour éviter cache obsolète en dev)
    })
}

/**
 * Hook pour récupérer les produits par catégorie
 */
export const useProduitsParCategorie = (categorie?: string) => {
    return useQuery({
        queryKey: ['produits-catalogue', 'categorie', categorie],
        queryFn: () => produitsCatalogueService.getByCategorie(categorie!),
        enabled: !!categorie,
        staleTime: 1000 * 60 * 30,
    })
}

/**
 * Hook pour récupérer un produit par ID
 */
export const useProduitCatalogue = (id?: number) => {
    return useQuery({
        queryKey: ['produits-catalogue', id],
        queryFn: () => produitsCatalogueService.getById(id!),
        enabled: !!id,
        staleTime: 1000 * 60 * 30,
    })
}

/**
 * Hook pour l'historique d'utilisation des produits
 */
export const useHistoriqueProduitsUtilisation = () => {
    return useQuery({
        queryKey: ['produits-catalogue', 'historique'],
        queryFn: () => produitsCatalogueService.getHistoriqueUtilisation(),
        staleTime: 1000 * 60 * 5, // 5 minutes
    })
}
