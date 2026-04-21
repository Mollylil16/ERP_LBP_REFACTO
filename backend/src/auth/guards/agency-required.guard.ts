import { CanActivate, ExecutionContext, Injectable } from '@nestjs/common';

/**
 * Verrou serveur : pour les rôles "terrain" rattachés à une agence,
 * on refuse les endpoints sensibles si `id_agence` n'est pas défini.
 *
 * Objectif : éviter qu’un utilisateur entre dans les modules (factures/paiements/caisse/colis)
 * à cause d’un cache front, d’une version front ancienne, ou d’un appel API direct.
 */
@Injectable()
export class AgencyRequiredGuard implements CanActivate {
  private resolveRoleCode(user: any): string {
    const r = user?.role;
    if (typeof r === 'string') return String(r).trim().toUpperCase();
    if (r && typeof r === 'object' && typeof r.code === 'string') {
      return String(r.code).trim().toUpperCase();
    }
    if (user?.roleEntity && typeof user.roleEntity.code === 'string') {
      return String(user.roleEntity.code).trim().toUpperCase();
    }
    return '';
  }

  private hasGlobalAgencyAccess(user: any): boolean {
    const rc = this.resolveRoleCode(user);
    if (
      ['ADMIN', 'DIRECTEUR', 'ASSISTANT_DG', 'SUPERVISEUR_REGIONAL'].includes(rc)
    ) {
      return true;
    }
    if (user?.peut_voir_toutes_agences === true) return true;
    if (Number(user?.code_acces) === 2) return true;
    if (user?.filter_mode === 'all') return true;
    return false;
  }

  canActivate(context: ExecutionContext): boolean {
    const req = context.switchToHttp().getRequest();
    const user = req?.user;
    if (!user) return true; // JwtAuthGuard gère l’authentification

    // Profils sièges / multi-agences : pas de blocage
    if (this.hasGlobalAgencyAccess(user)) return true;

    // Profils terrain : agence obligatoire
    const idAgence = user?.id_agence ?? user?.agence?.id ?? null;
    return idAgence != null;
  }
}

