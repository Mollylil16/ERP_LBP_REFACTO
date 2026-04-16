import { Injectable, Logger } from '@nestjs/common';
import { JwtService } from '@nestjs/jwt';
import * as bcrypt from 'bcrypt';
import { UsersService } from '../users/users.service';
import { RolesService } from '../roles/roles.service';
import { UserRole } from '../users/entities/user.entity';
import { effectiveRoleCode } from '../common/effective-role-code';
import { InjectRepository } from '@nestjs/typeorm';
import { RefreshToken } from './entities/refresh-token.entity';
import { Repository } from 'typeorm';
import * as crypto from 'crypto';
import { BusinessAuditService } from '../audit/business-audit.service';
import { ensureDashboardPermissions } from '../common/permission-code-map';

@Injectable()
export class AuthService {
  private readonly logger = new Logger(AuthService.name);

  constructor(
    private usersService: UsersService,
    private jwtService: JwtService,
    private rolesService: RolesService,
    @InjectRepository(RefreshToken)
    private readonly refreshTokenRepo: Repository<RefreshToken>,
    private readonly businessAudit: BusinessAuditService,
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

  auditLoginAttempt(payload: {
    ok: boolean;
    username: string;
    userId?: number;
    ip?: string | null;
    userAgent?: string | null;
  }) {
    this.businessAudit.logEvent({
      action: payload.ok ? 'auth.login.success' : 'auth.login.failed',
      entity: 'auth',
      userId: payload.userId,
      username: payload.username,
      details: {
        ip: payload.ip ?? null,
        userAgent: payload.userAgent ?? null,
      },
    });
  }

  /**
   * Même objet que dans la réponse login — obligatoire pour GET /auth/me
   * (sinon le front reçoit role en string brute et casse affichage + permissions).
   */
  private resolveRoleCode(user: any): string {
    const code = effectiveRoleCode(user);
    return code || UserRole.AGENT_EXPLOITATION;
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
      /** Pays d’agence (récap crédits France / Sénégal, etc.) */
      agency_pays: user.agence?.pays ?? null,
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
    const accessToken = this.jwtService.sign(payload);
    const refresh = await this.issueRefreshToken(user, undefined, undefined);

    return {
      token: accessToken,
      refresh_token: refresh.token,
      user: formattedUser,
      permissions: await this.getPermissionsForUser(user),
    };
  }

  /**
   * Refresh token opaque, stocké en base en hash + rotation à chaque refresh.
   * - `token` = valeur opaque envoyée au client
   * - `token_id` = identifiant (jti) stocké et indexé
   */
  async issueRefreshToken(
    user: any,
    ip?: string,
    userAgent?: string,
  ): Promise<{ token: string; token_id: string; expires_at: Date }> {
    const raw = crypto.randomBytes(48).toString('base64url');
    const tokenId = crypto.randomBytes(16).toString('hex');
    const hash = crypto.createHash('sha256').update(raw).digest('hex');

    const ttlDaysRaw = process.env.REFRESH_TOKEN_TTL_DAYS ?? '7';
    const ttlDays = Math.max(1, Math.min(30, Number(ttlDaysRaw) || 7));
    const expiresAt = new Date(Date.now() + ttlDays * 24 * 60 * 60 * 1000);

    const row = this.refreshTokenRepo.create({
      user: { id: user.id } as any,
      token_id: tokenId,
      token_hash: hash,
      expires_at: expiresAt,
      revoked_at: null,
      created_ip: ip ?? null,
      created_user_agent: userAgent ?? null,
    });
    await this.refreshTokenRepo.save(row);

    return { token: raw, token_id: tokenId, expires_at: expiresAt };
  }

  async refreshAccessToken(
    refreshTokenRaw: string,
    ip?: string,
    userAgent?: string,
  ): Promise<{ token: string; refresh_token: string }> {
    if (!refreshTokenRaw || refreshTokenRaw.trim() === '') {
      throw new Error('Refresh token manquant');
    }
    const hash = crypto
      .createHash('sha256')
      .update(refreshTokenRaw)
      .digest('hex');

    const existing = await this.refreshTokenRepo.findOne({
      where: { token_hash: hash },
      relations: ['user', 'user.agence', 'user.roleEntity'],
    });
    if (!existing) {
      this.businessAudit.logEvent({
        action: 'auth.refresh.failed',
        entity: 'auth',
        details: { reason: 'not_found' },
      });
      throw new Error('Refresh token invalide');
    }
    if (existing.revoked_at) {
      this.businessAudit.logEvent({
        action: 'auth.refresh.failed',
        entity: 'auth',
        userId: existing.user?.id,
        details: { reason: 'revoked' },
      });
      throw new Error('Refresh token révoqué');
    }
    if (new Date() > new Date(existing.expires_at)) {
      existing.revoked_at = new Date();
      await this.refreshTokenRepo.save(existing);
      this.businessAudit.logEvent({
        action: 'auth.refresh.failed',
        entity: 'auth',
        userId: existing.user?.id,
        details: { reason: 'expired' },
      });
      throw new Error('Refresh token expiré');
    }

    // Rotation: révoquer l'ancien et émettre un nouveau
    existing.revoked_at = new Date();
    existing.rotated_from_ip = ip ?? null;
    existing.rotated_from_user_agent = userAgent ?? null;
    await this.refreshTokenRepo.save(existing);

    const user = existing.user;
    const roleCode = this.resolveRoleCode(user);
    const payload = {
      username: user.username,
      sub: user.id,
      role: roleCode,
      code_acces: user.code_acces,
      id_agence: user.agence?.id ?? null,
    };
    const accessToken = this.jwtService.sign(payload);
    const nextRefresh = await this.issueRefreshToken(user, ip, userAgent);

    this.businessAudit.logEvent({
      action: 'auth.refresh.success',
      entity: 'auth',
      userId: user.id,
      username: user.username,
      details: { rotated: true },
    });

    return { token: accessToken, refresh_token: nextRefresh.token };
  }

  async revokeAllRefreshTokensForUser(userId: number, reason?: string) {
    await this.refreshTokenRepo
      .createQueryBuilder()
      .update(RefreshToken)
      .set({ revoked_at: new Date() })
      .where('user_id = :userId', { userId })
      .andWhere('revoked_at IS NULL')
      .execute();

    this.businessAudit.logEvent({
      action: 'auth.logout',
      entity: 'auth',
      userId,
      details: { reason: reason ?? 'logout' },
    });
  }

  private getRoleId(role: string): number {
    const roleMap: Record<string, number> = {
      DIRECTEUR: 1,
      ASSISTANT_DG: 11,
      MANAGER: 2,
      SUPERVISEUR_REGIONAL: 3,
      AGENT_EXPLOITATION: 4,
      CHEF_AGENCE: 10,
      AGENT_GROUPAGE: 5,
      CAISSIER: 6,
      AGENT_SUIVI: 8,
      CALL_CENTER: 9,
      ADMIN: 1,
    };
    return roleMap[role] || 4;
  }

  private getRoleName(role: string): string {
    const nameMap: Record<string, string> = {
      DIRECTEUR: 'Directeur Général',
      ASSISTANT_DG: 'Assistant DG',
      MANAGER: 'Manager / Superviseur',
      SUPERVISEUR_REGIONAL: 'Superviseur Régional',
      AGENT_EXPLOITATION: 'Agent Exploitation',
      CHEF_AGENCE: "Chef d'agence",
      AGENT_GROUPAGE: 'Agent Groupage',
      CAISSIER: 'Caissier Principal',
      AGENT_SUIVI: 'Agent Suivi',
      CALL_CENTER: 'Call center',
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
      return ensureDashboardPermissions([]);
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

    return ensureDashboardPermissions([]);
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
