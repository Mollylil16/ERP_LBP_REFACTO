import { Injectable, UnauthorizedException, BadRequestException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { User } from '../users/entities/user.entity';
import * as speakeasy from 'speakeasy';
import * as QRCode from 'qrcode';

const APP_NAME = 'LBP SIRH';
const MFA_REQUIRED_ROLES = ['ADMIN', 'RESPONSABLE_RH'];

@Injectable()
export class MfaService {
  constructor(
    @InjectRepository(User) private userRepo: Repository<User>,
  ) {}

  /** Génère un secret TOTP + QR code SVG pour l'utilisateur */
  async setupMfa(userId: number): Promise<{ qr_code: string; manual_key: string; otpauth_url: string }> {
    const user = await this.userRepo.findOne({ where: { id: userId } });
    if (!user) throw new BadRequestException('Utilisateur introuvable');

    const secret = speakeasy.generateSecret({
      name: `${APP_NAME} (${user.username})`,
      length: 20,
    });

    // Stocker le secret (non encore activé)
    await this.userRepo.update(userId, { mfa_secret: secret.base32 } as any);

    const otpauth = secret.otpauth_url!;
    const qr_code = await QRCode.toDataURL(otpauth);

    return { qr_code, manual_key: secret.base32, otpauth_url: otpauth };
  }

  /** Valide le token OTP et active le MFA */
  async enableMfa(userId: number, token: string): Promise<void> {
    const user = await this.userRepo.createQueryBuilder('u')
      .addSelect('u.mfa_secret')
      .where('u.id = :id', { id: userId })
      .getOne();

    if (!user?.mfa_secret) throw new BadRequestException('MFA non initialisé — appelez /auth/mfa/setup d\'abord');

    const ok = speakeasy.totp.verify({
      secret: user.mfa_secret,
      encoding: 'base32',
      token,
      window: 1,
    });
    if (!ok) throw new UnauthorizedException('Code OTP invalide');

    await this.userRepo.update(userId, { mfa_enabled: true } as any);
  }

  /** Désactive le MFA (admin uniquement ou l'utilisateur lui-même avec mot de passe) */
  async disableMfa(userId: number): Promise<void> {
    await this.userRepo.update(userId, { mfa_enabled: false, mfa_secret: null } as any);
  }

  /** Vérifie le token OTP lors du login — lève UnauthorizedException si invalide */
  async verifyToken(userId: number, token: string): Promise<void> {
    const user = await this.userRepo.createQueryBuilder('u')
      .addSelect('u.mfa_secret')
      .where('u.id = :id', { id: userId })
      .getOne();

    if (!user?.mfa_secret) throw new UnauthorizedException('MFA non configuré');

    const ok = speakeasy.totp.verify({
      secret: user.mfa_secret,
      encoding: 'base32',
      token,
      window: 1,
    });
    if (!ok) throw new UnauthorizedException('Code MFA invalide ou expiré');
  }

  /** Retourne true si ce rôle exige MFA */
  static roleRequiresMfa(roleCode: string): boolean {
    return MFA_REQUIRED_ROLES.includes(roleCode?.toUpperCase());
  }
}
