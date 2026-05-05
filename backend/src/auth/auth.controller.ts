import {
  Controller,
  Post,
  Body,
  UseGuards,
  Request,
  Get,
  UnauthorizedException,
} from '@nestjs/common';
import {
  ApiTags,
  ApiOperation,
  ApiResponse,
  ApiBearerAuth,
} from '@nestjs/swagger';
import { AuthService } from './auth.service';
import { MfaService } from './mfa.service';
import { LoginDto } from './dto/login.dto';
import { JwtAuthGuard } from './guards/jwt-auth.guard';
import { Throttle } from '@nestjs/throttler';

@ApiTags('auth')
@Controller('auth')
export class AuthController {
  constructor(
    private authService: AuthService,
    private mfaService: MfaService,
  ) {}

  @Post('login')
  @Throttle({ default: { ttl: 60_000, limit: 10 } })
  @ApiOperation({ summary: 'Connexion utilisateur' })
  @ApiResponse({ status: 200, description: 'Connexion réussie' })
  @ApiResponse({ status: 401, description: 'Identifiants invalides' })
  async login(@Body() loginDto: LoginDto, @Request() req: any) {
    const user = await this.authService.validateUser(
      loginDto.username,
      loginDto.password,
    );
    if (!user) {
      // audit non bloquant (anti brute-force + investigations)
      this.authService.auditLoginAttempt?.({
        ok: false,
        username: loginDto.username,
        ip:
          req.headers['x-forwarded-for']?.split(',')[0]?.trim() ??
          req.ip ??
          null,
        userAgent: req.headers['user-agent'] ?? null,
      });
      throw new UnauthorizedException(
        "Nom d'utilisateur ou mot de passe incorrect",
      );
    }
    this.authService.auditLoginAttempt?.({
      ok: true,
      username: loginDto.username,
      userId: user.id,
      ip:
        req.headers['x-forwarded-for']?.split(',')[0]?.trim() ??
        req.ip ??
        null,
      userAgent: req.headers['user-agent'] ?? null,
    });
    return this.authService.login(user);
  }

  @Post('refresh')
  @ApiOperation({ summary: "Renouveler l'access token (rotation refresh)" })
  async refresh(@Body() body: { refresh_token: string }, @Request() req: any) {
    try {
      const ip =
        req.headers['x-forwarded-for']?.split(',')[0]?.trim() ??
        req.ip ??
        null;
      const ua = req.headers['user-agent'] ?? null;
      return await this.authService.refreshAccessToken(body.refresh_token, ip, ua);
    } catch (e: any) {
      throw new UnauthorizedException(e?.message || 'Refresh token invalide');
    }
  }

  @ApiBearerAuth()
  @UseGuards(JwtAuthGuard)
  @Post('logout')
  @ApiOperation({ summary: 'Déconnexion (révoque les refresh tokens)' })
  async logout(@Request() req: any) {
    await this.authService.revokeAllRefreshTokensForUser(req.user.id, 'logout');
    return { ok: true };
  }

  @ApiBearerAuth()
  @UseGuards(JwtAuthGuard)
  @Get('me')
  @ApiOperation({ summary: 'Récupérer le profil utilisateur actuel' })
  async getProfile(@Request() req) {
    return this.authService.toPublicUser(req.user);
  }

  @ApiBearerAuth()
  @UseGuards(JwtAuthGuard)
  @Get('permissions')
  @ApiOperation({ summary: "Récupérer les permissions de l'utilisateur" })
  async getPermissions(@Request() req) {
    return await this.authService.getPermissionsForUser(req.user);
  }

  // ── MFA endpoints ─────────────────────────────────────────────────────

  @ApiBearerAuth()
  @UseGuards(JwtAuthGuard)
  @Get('mfa/setup')
  @ApiOperation({ summary: 'Générer le QR code TOTP pour activer le MFA' })
  async mfaSetup(@Request() req) {
    return this.mfaService.setupMfa(req.user.id);
  }

  @ApiBearerAuth()
  @UseGuards(JwtAuthGuard)
  @Post('mfa/enable')
  @ApiOperation({ summary: 'Activer le MFA avec le premier code OTP valide' })
  async mfaEnable(@Request() req, @Body() body: { token: string }) {
    await this.mfaService.enableMfa(req.user.id, body.token);
    return { ok: true, message: 'MFA activé avec succès' };
  }

  @ApiBearerAuth()
  @UseGuards(JwtAuthGuard)
  @Post('mfa/disable')
  @ApiOperation({ summary: 'Désactiver le MFA' })
  async mfaDisable(@Request() req) {
    await this.mfaService.disableMfa(req.user.id);
    return { ok: true, message: 'MFA désactivé' };
  }

  @Post('mfa/challenge')
  @ApiOperation({ summary: 'Valider le code MFA lors du login (second facteur)' })
  async mfaChallenge(@Body() body: { mfa_session_token: string; token: string }) {
    return this.authService.completeMfaLogin(body.mfa_session_token, body.token);
  }

  @ApiBearerAuth()
  @UseGuards(JwtAuthGuard)
  @Get('mfa/status')
  @ApiOperation({ summary: 'Statut MFA de l\'utilisateur courant' })
  async mfaStatus(@Request() req) {
    return {
      mfa_enabled: Boolean(req.user.mfa_enabled),
      mfa_required: Boolean(req.user.mfa_required),
    };
  }
}
