import { apiService } from './api.service'

export type FournitureArticle = {
  id: number
  code: string
  nom: string
  unite: string
  quantite_stock: number
  seuil_alerte: number
  actif: boolean
}

export type FournitureDemandeLigne = {
  id: number
  quantite: number
  quantite_validee: number | null
  quantite_livree: number | null
  article: FournitureArticle
}

export type FournitureDemande = {
  id: number
  statut: string
  observations: string | null
  motif_refus: string | null
  date_validation: string | null
  date_livraison: string | null
  created_at: string
  agence: { id: number; nom: string }
  demandeur?: { id: number; nom_complet?: string; email?: string }
  valideur?: { id: number; nom_complet?: string } | null
  livreur?: { id: number; nom_complet?: string } | null
  lignes: FournitureDemandeLigne[]
}

export const fournituresBureauService = {
  listArticles: () => apiService.get<FournitureArticle[]>('/fournitures-bureau/articles'),

  createArticle: (body: {
    code: string
    nom: string
    unite?: string
    quantite_stock?: number
    seuil_alerte?: number
    actif?: boolean
  }) => apiService.post<FournitureArticle>('/fournitures-bureau/articles', body),

  ajustStock: (id: number, quantite_stock: number) =>
    apiService.patch<FournitureArticle>(`/fournitures-bureau/articles/${id}/stock`, {
      quantite_stock,
    }),

  listDemandes: (params?: { statut?: string; agence_id?: number }) =>
    apiService.get<FournitureDemande[]>('/fournitures-bureau/demandes', { params }),

  createDemande: (body: {
    id_agence: number
    observations?: string
    lignes: { id_article: number; quantite: number }[]
  }) => apiService.post<FournitureDemande>('/fournitures-bureau/demandes', body),

  soumettreDemande: (id: number) =>
    apiService.patch<FournitureDemande>(`/fournitures-bureau/demandes/${id}/soumettre`, {}),

  validerDemande: (
    id: number,
    lignes: { id_ligne: number; quantite_validee: number }[],
  ) =>
    apiService.patch<FournitureDemande>(`/fournitures-bureau/demandes/${id}/valider`, {
      lignes,
    }),

  refuserDemande: (id: number, motif: string) =>
    apiService.patch<FournitureDemande>(`/fournitures-bureau/demandes/${id}/refuser`, {
      motif,
    }),

  livrerDemande: (id: number) =>
    apiService.patch<FournitureDemande>(`/fournitures-bureau/demandes/${id}/livrer`, {}),
}
