import { apiService } from './api.service'

export interface ProduitCatalogue {
    id: number
    code?: string
    nom: string
    categorie: 'DENREE' | 'HUILE_ET_KARITE' | 'DIVERS' | 'COLIS_RAPIDE_EXPORT'
    nature?: 'PRIX_UNITAIRE' | 'PRIX_FORFAITAIRE' | null
    prix_unitaire?: number
    prix_forfaitaire?: number
    poids_min?: number
    poids_max?: number
    devise?: string
    description?: string
    unite?: string
    actif: boolean
}

class ProduitsCatalogueService {
    /**
     * Récupérer tous les produits actifs du catalogue
     */
    async getAll(): Promise<ProduitCatalogue[]> {
        return apiService.get<ProduitCatalogue[]>('/produits-catalogue')
    }

    /** Actifs + inactifs (droits factures.create) */
    async getAllForManagement(): Promise<ProduitCatalogue[]> {
        return apiService.get<ProduitCatalogue[]>('/produits-catalogue/gestion')
    }

    /**
     * Récupérer les produits par catégorie
     */
    async getByCategorie(categorie: string): Promise<ProduitCatalogue[]> {
        return apiService.get<ProduitCatalogue[]>(`/produits-catalogue?categorie=${categorie}`)
    }

    /**
     * Récupérer un produit par ID
     */
    async getById(id: number): Promise<ProduitCatalogue> {
        return apiService.get<ProduitCatalogue>(`/produits-catalogue/${id}`)
    }

    /**
     * Rechercher des produits par terme
     */
    async search(term: string): Promise<ProduitCatalogue[]> {
        if (!term || term.length < 2) {
            return []
        }
        return apiService.get<ProduitCatalogue[]>(`/produits-catalogue/search?q=${encodeURIComponent(term)}`)
    }

    /**
     * Récupérer l'historique d'utilisation des produits
     */
    async getHistoriqueUtilisation(): Promise<any[]> {
        return apiService.get<any[]>('/produits-catalogue/historique')
    }

    /**
     * Créer un produit dans le catalogue
     */
    async create(data: Partial<ProduitCatalogue>): Promise<ProduitCatalogue> {
        return apiService.post<ProduitCatalogue>('/produits-catalogue', data)
    }

    async update(id: number, data: Partial<ProduitCatalogue>): Promise<ProduitCatalogue> {
        return apiService.put<ProduitCatalogue>(`/produits-catalogue/${id}`, data)
    }

    async remove(id: number): Promise<void> {
        return apiService.delete(`/produits-catalogue/${id}`)
    }
}

export const produitsCatalogueService = new ProduitsCatalogueService()
