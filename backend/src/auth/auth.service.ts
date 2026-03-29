import { Injectable, Logger } from '@nestjs/common';
import { JwtService } from '@nestjs/jwt';
import * as bcrypt from 'bcrypt';
import { UsersService } from '../users/users.service';
import { RolesService } from '../roles/roles.service';
import { UserRole } from '../users/entities/user.entity';

@Injectable()
export class AuthService {
  private readonly logger = new Logger(AuthService.name);

  constructor(
    private usersService: UsersService,
    private jwtService: JwtService,
    private rolesService: RolesService,
  ) {}

  async validateUser(username: string, pass: string): Promise<any> {
    const user = await this.usersService.findByUsername(username);
    if (!user) {
      console.log(`[Auth] User not found: ${username}`);
      return null;
    }

    if (!user.actif) {
      console.log(`[Auth] User is inactive: ${username}`);
      return null;
    }

    const isPasswordValid = await bcrypt.compare(pass, user.password);
    if (!isPasswordValid) {
      console.log(`[Auth] Invalid password for user: ${username}`);
      return null;
    }

    const { password, ...result } = user;
    return result;
  }

  /**
   * Même objet que dans la réponse login — obligatoire pour GET /auth/me
   * (sinon le front reçoit role en string brute et casse affichage + permissions).
   */
  private resolveRoleCode(user: any): string {
    return typeof user.role === 'string'
      ? user.role
      : user.role?.code ??
          user.roleEntity?.code ??
          UserRole.AGENT_EXPLOITATION;
  }

  toPublicUser(user: any) {
    const hasGlobalAgencyAccess = this.hasGlobalAgencyAccess(user);
    const roleCode = this.resolveRoleCode(user);

    return {
      id: user.id,
      code_user: `USER${user.id.toString().padStart(3, '0')}`,
      username: user.username,
      nom_complet: user.nom_complet,
      full_name: user.nom_complet,
      email: user.email ?? null,
      phone: user.phone ?? null,
      role: {
        id: this.getRoleId(roleCode),
        code: roleCode,
        name: this.getRoleName(roleCode),
      },
      code_acces: user.code_acces,
      peut_voir_toutes_agences: Boolean(user.peut_voir_toutes_agences),
      agency_id: user.agence?.id ?? null,
      agency_name: user.agence?.nom ?? null,
      filter_mode: this.getFilterMode(user.code_acces),
      can_delete: user.code_acces === 2,
      can_modify: user.code_acces !== 2,
      status: user.actif ? 'active' : ('inactive' as 'active' | 'inactive'),
      must_change_password: user.must_change_password ?? false,
      agence_selected: hasGlobalAgencyAccess
        ? true
        : (user.agence_selected ?? user.agence != null),
      actif: user.actif,
      created_at: user.created_at
        ? new Date(user.created_at).toISOString()
        : new Date().toISOString(),
    };
  }

  async login(user: any) {
    const roleCode = this.resolveRoleCode(user);
    const payload = {
      username: user.username,
      sub: user.id,
      role: roleCode,
      code_acces: user.code_acces,
      id_agence: user.agence?.id ?? null,
    };

    const formattedUser = this.toPublicUser(user);

    return {
      token: this.jwtService.sign(payload),
      refresh_token: this.jwtService.sign(payload, { expiresIn: '7d' }),
      user: formattedUser,
      permissions: await this.getPermissionsForUser(user),
    };
  }

  private getRoleId(role: string): number {
    const roleMap: Record<string, number> = {
      DIRECTEUR: 1,
      MANAGER: 2,
      SUPERVISEUR_REGIONAL: 3,
      AGENT_EXPLOITATION: 4,
      AGENT_GROUPAGE: 5,
      CAISSIER: 6,
      CAISSIER_GROUPAGE: 7,
      AGENT_SUIVI: 8,
      ADMIN: 1,
    };
    return roleMap[role] || 4;
  }

  private getRoleName(role: string): string {
    const nameMap: Record<string, string> = {
      DIRECTEUR: 'Directeur Général',
      MANAGER: 'Manager / Superviseur',
      SUPERVISEUR_REGIONAL: 'Superviseur Régional',
      AGENT_EXPLOITATION: 'Agent Exploitation',
      AGENT_GROUPAGE: 'Agent Groupage',
      CAISSIER: 'Caissier Principal',
      CAISSIER_GROUPAGE: 'Caissier Groupage',
      AGENT_SUIVI: 'Agent Suivi',
      ADMIN: 'Administrateur',
    };
    return nameMap[role] || 'Agent Exploitation';
  }

  private getFilterMode(code_acces: number): 'individual' | 'agency' | 'all' {
    // CODEACCES 2 = Super Admin (all)
    // CODEACCES 1 = Admin/Manager (all)
    // CODEACCES 8 = Individual
    // CODEACCES 9 = Agency
    if (code_acces === 2 || code_acces === 1) {
      return 'all';
    }
    // Pour l'instant, on retourne 'all' par défaut
    // TODO: Implémenter la logique complète selon CODEACCES
    return 'all';
  }

  /**
   * Source unique : lbp_roles + lbp_role_permissions + lbp_permissions, avec mapping vers les codes app.
   * Si aucune permission en base pour le rôle : [] (exécuter `npm run seed` pour remplir la matrice).
   */
  async getPermissionsForUser(user: any): Promise<string[]> {
    const roleCode = this.resolveRoleCode(user);
    if (
      roleCode === 'DIRECTEUR' ||
      roleCode === 'ADMIN' ||
      user.code_acces === 2
    ) {
      return ['*'];
    }

    if (!roleCode) {
      this.logger.warn(
        `[LBP_PERMISSIONS] userId=${user?.id} login=${user?.username ?? '?'} : pas de code rôle — permissions=[]`,
      );
      return [];
    }

    try {
      const fromDb =
        await this.rolesService.getAppPermissionCodesForRole(roleCode);
      if (fromDb !== null && fromDb.length > 0) {
        return fromDb;
      }
      this.logger.warn(
        `[LBP_PERMISSIONS] Matrice vide ou non mappable pour le rôle "${roleCode}" (userId=${user?.id} login=${user?.username ?? '?'}). ` +
          `Vérifier lbp_role_permissions + lbp_permissions, ou exécuter depuis backend/: npm run seed`,
      );
    } catch (e) {
      this.logger.error(
        `[LBP_PERMISSIONS] Lecture DB échouée (userId=${user?.id} rôle=${roleCode})`,
        e instanceof Error ? e.stack : String(e),
      );
    }

    return [];
  }

  private hasGlobalAgencyAccess(user: any): boolean {
    const rc = this.resolveRoleCode(user);
    return Boolean(
      user?.peut_voir_toutes_agences ||
        user?.code_acces === 2 ||
        user?.code_acces === 1 ||
        rc === 'DIRECTEUR' ||
        rc === 'ADMIN',
    );
  }
}
