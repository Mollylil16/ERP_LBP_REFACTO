import {
  Injectable,
  CanActivate,
  ExecutionContext,
  UnauthorizedException,
  ForbiddenException,
} from '@nestjs/common';
import { AuthGuard } from '@nestjs/passport';
import { DataSource } from 'typeorm';

@Injectable()
export class JwtAuthGuard extends AuthGuard('jwt') {
  constructor(private dataSource: DataSource) {
    super();
  }

  async canActivate(context: ExecutionContext): Promise<boolean> {
    // 1. Vérification standard du token JWT
    const result = await super.canActivate(context);
    if (!result) return false;

    // 2. Extraction de l'utilisateur connecté
    const request = context.switchToHttp().getRequest();
    const user = request.user;

    if (!user || !user.id) return true;

    // 3. VÉRIFICATION PONT RH : L'utilisateur est-il officiellement absent aujourd'hui ?
    // On ne bloque pas les administrateurs pour éviter tout blocage système catastrophique.
    const role = String(user.role || '').toUpperCase();
    if (['ADMIN', 'SUPER_ADMIN', 'DIRECTEUR'].includes(role)) {
      return true;
    }

    try {
      const today = new Date().toISOString().slice(0, 10);
      
      // Requête performante pour chercher un congé Approuvé couvrant "Aujourd'hui"
      const activeConge = await this.dataSource.query(
        `SELECT cr.id 
         FROM rh_conge_requests cr
         INNER JOIN rh_employes e ON e.id = cr.id_employe
         WHERE e.id_user = $1 
           AND cr.statut = 'APPROUVE_RH' 
           AND $2 BETWEEN cr.date_debut AND cr.date_fin
         LIMIT 1`,
        [user.id, today]
      );

      if (activeConge && activeConge.length > 0) {
        throw new ForbiddenException(
          "Accès suspendu : Vous êtes officiellement enregistré en congé ou absence aujourd'hui dans le système RH."
        );
      }
    } catch (e) {
      // Si c'est notre exception Forbidden, on la laisse remonter. 
      // Si c'est une erreur SQL, on laisse passer (principe de continuité de service).
      if (e instanceof ForbiddenException) throw e;
    }

    return true;
  }
}
