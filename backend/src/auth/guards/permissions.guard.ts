import {
  Injectable,
  CanActivate,
  ExecutionContext,
  ForbiddenException,
} from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import { PERMISSIONS_KEY } from '../decorators/permissions.decorator';
import { RolesService } from '../../roles/roles.service';
import { effectiveRoleCode } from '../../common/effective-role-code';

/**
 * Vérifie les codes permission **app** (alignés login / frontend), via la matrice DB + permission-code-map.
 */
@Injectable()
export class PermissionsGuard implements CanActivate {
  constructor(
    private readonly reflector: Reflector,
    private readonly rolesService: RolesService,
  ) {}

  async canActivate(context: ExecutionContext): Promise<boolean> {
    const requiredPermissions = this.reflector.getAllAndOverride<string[]>(
      PERMISSIONS_KEY,
      [context.getHandler(), context.getClass()],
    );

    if (!requiredPermissions?.length) {
      return true;
    }

    const { user } = context.switchToHttp().getRequest();
    if (!user) {
      throw new ForbiddenException('Authentification requise');
    }

    if (this.hasFullAccess(user)) {
      return true;
    }

    const roleCode = effectiveRoleCode(user);
    if (!roleCode) {
      throw new ForbiddenException('Rôle utilisateur indéfini');
    }

    let appCodes: string[] = [];
    try {
      const fromDb =
        await this.rolesService.getAppPermissionCodesForRole(roleCode);
      if (fromDb?.length) {
        appCodes = fromDb;
      }
    } catch {
      throw new ForbiddenException('Impossible de vérifier les permissions');
    }

    if (appCodes.includes('*')) {
      return true;
    }

    const ok = requiredPermissions.some((p) => appCodes.includes(p));
    if (!ok) {
      throw new ForbiddenException({
        message: 'Permission insuffisante',
        required: requiredPermissions,
      });
    }
    return true;
  }

  private hasFullAccess(user: any): boolean {
    const r = (effectiveRoleCode(user) || '').toUpperCase();
    if (r === 'SUPER_ADMIN') {
      return true;
    }
    if (r === 'DIRECTEUR' || r === 'ADMIN' || r === 'ASSISTANT_DG') {
      return true;
    }
    if (user?.code_acces === 2) {
      return true;
    }
    return false;
  }
}
