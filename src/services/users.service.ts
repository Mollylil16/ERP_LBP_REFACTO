import { User } from '@types'
import { apiService } from './api.service'

class UsersService {
  /** Créer un utilisateur (admin/DG) */
  async create(data: {
    username: string
    nom_complet: string
    role: string
    password?: string
    agence_id?: number
    phone?: string
    email?: string
    actif?: boolean
  }): Promise<User> {
    return apiService.post<User>('/users', data)
  }

  /** Mettre à jour un utilisateur */
  async update(id: number, data: any): Promise<User> {
    return apiService.patch<User>(`/users/${id}`, data)
  }

  /** Désactiver un utilisateur (soft delete) */
  async delete(id: number): Promise<void> {
    return apiService.delete<void>(`/users/${id}`)
  }

  /** Activer/Désactiver toggle */
  async toggleActive(id: number): Promise<User> {
    return apiService.patch<User>(`/users/${id}/toggle-active`, {})
  }

  /** Liste tous les utilisateurs */
  async getAll(): Promise<User[]> {
    const users = await apiService.get<any[]>('/users')
    return users.map(u => this.mapUser(u))
  }

  /** Détail (ex. son propre profil si id = utilisateur courant) */
  async getById(id: number): Promise<any> {
    return apiService.get(`/users/${id}`)
  }

  /** Mise à jour e-mail / téléphone par l’utilisateur connecté */
  async updateMyProfile(body: { email?: string | null; phone?: string | null }): Promise<any> {
    return apiService.patch('/users/me/profile', body)
  }

  /** Voir le mot de passe temporaire en clair */
  async getPasswordPlain(id: number): Promise<{ password_plain: string | null; changed: boolean }> {
    return apiService.get(`/users/${id}/password`)
  }

  /** Réinitialiser le mdp avec un nouveau mdp temporaire */
  async resetPassword(id: number, newPassword: string): Promise<void> {
    return apiService.post(`/users/${id}/reset-password`, { newPassword })
  }

  /** Envoyer le mot de passe temporaire par WhatsApp/SMS */
  async sendTemporaryPassword(id: number): Promise<{ sent: boolean; message: string }> {
    return apiService.post(`/users/${id}/send-temp-password`)
  }

  /** Changer son propre mot de passe (1ère connexion ou profil) */
  async changePassword(id: number, oldPassword: string, newPassword: string): Promise<void> {
    return apiService.post(`/users/${id}/change-password`, { oldPassword, newPassword })
  }

  /** Sélectionner son agence (1ère connexion) */
  async selectAgence(userId: number, agenceId: number): Promise<User> {
    return apiService.post(`/users/${userId}/select-agence`, { agence_id: agenceId })
  }

  /** Stats utilisateurs pour dashboard */
  async getStats(): Promise<any> {
    return apiService.get('/users/stats')
  }

  private mapUser(user: any): User {
    return {
      id: user.id,
      code_user: user.code_user || `USER${user.id.toString().padStart(3, '0')}`,
      username: user.username,
      nom_complet: user.nom_complet,
      email: user.email,
      phone: user.phone,
      role: {
        id: this.getRoleId(user.role),
        code: user.role,
        name: this.getRoleName(user.role),
      },
      agency: user.agence || null,
      agency_id: user.agence?.id || null,
      actif: user.actif,
      must_change_password: user.must_change_password,
      agence_selected: user.agence_selected,
      password_plain: user.password_plain,
      created_at: user.created_at,
    } as User
  }

  private getRoleId(role: string): number {
    const roleMap: Record<string, number> = {
      'DIRECTEUR': 1, 'MANAGER': 2, 'SUPERVISEUR_REGIONAL': 3,
      'AGENT_EXPLOITATION': 4, 'AGENT_GROUPAGE': 5, 'CAISSIER': 6,
      'CAISSIER_GROUPAGE': 7, 'AGENT_SUIVI': 8, 'CALL_CENTER': 9, 'CHEF_AGENCE': 10, 'ADMIN': 1,
    }
    return roleMap[role] || 4
  }

  private getRoleName(role: string): string {
    const nameMap: Record<string, string> = {
      'DIRECTEUR': 'Directeur Général', 'MANAGER': 'Manager / Superviseur',
      'SUPERVISEUR_REGIONAL': 'Superviseur Régional', 'AGENT_EXPLOITATION': 'Agent Exploitation',
      'AGENT_GROUPAGE': 'Agent Groupage', 'CAISSIER': 'Caissier Principal',
      'CAISSIER_GROUPAGE': 'Caissier Groupage',       'AGENT_SUIVI': 'Agent Suivi', 'CHEF_AGENCE': "Chef d'agence",
      'CALL_CENTER': 'Call center',
      'ADMIN': 'Administrateur',
    }
    return nameMap[role] || 'Agent Exploitation'
  }
}

export const usersService = new UsersService()
