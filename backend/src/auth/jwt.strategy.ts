import { ExtractJwt, Strategy } from 'passport-jwt';
import { PassportStrategy } from '@nestjs/passport';
import { Injectable, UnauthorizedException } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { UsersService } from '../users/users.service';

@Injectable()
export class JwtStrategy extends PassportStrategy(Strategy) {
    constructor(
        private configService: ConfigService,
        private usersService: UsersService,
    ) {
        super({
            jwtFromRequest: ExtractJwt.fromAuthHeaderAsBearerToken(),
            ignoreExpiration: false,
            secretOrKey: configService.get<string>('JWT_SECRET'),
        });
    }

    async validate(payload: any) {
        // On récupère l'utilisateur complet en base à chaque fois pour avoir les derniers flags
        // (must_change_password, agence_selected, etc.)
        const user = await this.usersService.findById(payload.sub);
        if (!user || !user.actif) {
            throw new UnauthorizedException('Utilisateur inactif ou introuvable');
        }
        return user;
    }
}
