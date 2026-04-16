import { User, PaginationParams } from '@types'
import { apiService } from './api.service'

// Type ClientColis (expéditeur)
export interface ClientColis {
  id: number
  nom_exp: string
  type_piece_exp?: string
  num_piece_exp?: string
  tel_exp: string
  email_exp?: string
  date_enrg: string
  created_by?: string
}

class ClientsService {
  /**
   * Récupérer la liste des clients expéditeurs
   * Le backend renvoie actuellement un simple tableau de clients (sans pagination serveur).
   */
  async getClients(params?: PaginationParams): Promise<ClientColis[]> {
    const queryParams = new URLSearchParams()
    if (params?.search) queryParams.append('search', params.search)

    const queryString = queryParams.toString()
    const url = queryString ? `/clients/search?${queryString}` : '/clients'

    return apiService.get<any>(url)
  }

  /**
   * Récupérer un client par son ID
   */
  async getClientById(id: number): Promise<ClientColis> {
    return apiService.get<ClientColis>(`/clients/${id}`)
  }

  /**
   * Rechercher des clients
   */
  async searchClients(searchTerm: string): Promise<ClientColis[]> {
    return apiService.get<ClientColis[]>(`/clients/search?search=${searchTerm}`)
  }

  /**
   * Créer un nouveau client expéditeur
   */
  async createClient(data: CreateClientDto): Promise<ClientColis> {
    return apiService.post<ClientColis>('/clients', data)
  }

  /**
   * Mettre à jour un client
   */
  async updateClient(id: number, data: UpdateClientDto): Promise<ClientColis> {
    return apiService.put<ClientColis>(`/clients/${id}`, data)
  }

  /**
   * Supprimer un client
   */
  async deleteClient(id: number): Promise<void> {
    return apiService.delete<void>(`/clients/${id}`)
  }

  /**
   * Récupérer l'historique des colis d'un client
   */
  async getClientHistory(id: number): Promise<any[]> {
    return apiService.get<any[]>(`/clients/${id}/history`)
  }
}

export interface CreateClientDto {
  nom_exp: string
  /** Optionnel: certains clients n'ont pas de pièce */
  type_piece_exp?: string
  /** Optionnel: certains clients n'ont pas de pièce */
  num_piece_exp?: string
  tel_exp: string
  email_exp?: string
}

export interface UpdateClientDto extends Partial<CreateClientDto> { }

export const clientsService = new ClientsService()
