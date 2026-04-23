import {
  CanActivate,
  ExecutionContext,
  ForbiddenException,
  Injectable,
} from '@nestjs/common';
import { effectiveRoleCode } from '../../common/effective-role-code';
import { GroupeursService } from '../services/groupeurs.service';

/**
 * Verrou “espace groupeur” :
 * - si rôle GROUPEUR_GROSSISTE → le compte doit être relié à un groupeur (groupeurId)
 * - pour les rôles admin (SUPERVISEUR_REGIONAL, ASSISTANT_DG, DIRECTEUR, ADMIN) : passe
 */
@Injectable()
export class GroupeurOwnerGuard implements CanActivate {
  constructor(private readonly groupeursService: GroupeursService) {}

  async canActivate(context: ExecutionContext): Promise<boolean> {
    const req = context.switchToHttp().getRequest();
    const user = req?.user;
    if (!user) return true;

    const roleCode = String(effectiveRoleCode(user) || '').toUpperCase();
    if (roleCode !== 'GROUPEUR_GROSSISTE') return true;

    // Injecter groupeurId dans la requête (utile aux controllers)
    if (!req.user.groupeurId) {
      const g = await this.groupeursService.findByUserId(user.sub ?? user.id);
      if (!g) {
        throw new ForbiddenException('Compte groupeur non configuré');
      }
      req.user.groupeurId = g.id;
    }
    return true;
  }
}
