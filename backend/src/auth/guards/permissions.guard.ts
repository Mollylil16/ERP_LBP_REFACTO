import {
  Injectable,
  CanActivate,
  ExecutionContext,
  ForbiddenException,
} from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import { PERMISSIONS_KEY } from '../decorators/permissions.decorator';
import { RolesService } from '../../roles/roles.service';

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

    const roleCode =
      typeof user.role === 'string' ? user.role : user.role?.code;
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
    const role = user?.role;
    const roleStr = typeof role === 'string' ? role : role?.code;
    if (roleStr === 'SUPER_ADMIN') {
      return true;
    }
    if (roleStr === 'DIRECTEUR' || roleStr === 'ADMIN') {
      return true;
    }
    if (user?.code_acces === 2) {
      return true;
    }
    return false;
  }
}
