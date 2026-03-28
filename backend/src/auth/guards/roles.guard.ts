import { Injectable, CanActivate, ExecutionContext } from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import { ROLES_KEY } from '../decorators/roles.decorator';

@Injectable()
export class RolesGuard implements CanActivate {
  constructor(private reflector: Reflector) {}

  canActivate(context: ExecutionContext): boolean {
    const requiredRoles = this.reflector.getAllAndOverride<string[]>(
      ROLES_KEY,
      [context.getHandler(), context.getClass()],
    );

    if (!requiredRoles) {
      return true;
    }

    const { user } = context.switchToHttp().getRequest();

    if (!user) {
      return false;
    }

    // Vérifier si l'utilisateur a un des rôles requis (ancien système)
    if (requiredRoles.includes(user.role)) {
      return true;
    }

    // Vérifier si l'utilisateur a un des rôles requis (nouveau système)
    if (user.roleEntity && requiredRoles.includes(user.roleEntity.code)) {
      return true;
    }

    return false;
  }
}
