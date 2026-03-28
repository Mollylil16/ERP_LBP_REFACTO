import {
  Injectable,
  CanActivate,
  ExecutionContext,
  UnauthorizedException,
} from '@nestjs/common';
import { AuthGuard } from '@nestjs/passport';

@Injectable()
export class JwtAuthGuard extends AuthGuard('jwt') {
  async canActivate(context: ExecutionContext): Promise<boolean> {
    // Default JWT authentication
    try {
      const result = await super.canActivate(context);
      return result as boolean;
    } catch (e) {
      throw e;
    }
  }
}
