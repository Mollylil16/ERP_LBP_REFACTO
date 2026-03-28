import {
  Controller,
  Post,
  Get,
  Param,
  Body,
  HttpCode,
  HttpStatus,
  Headers,
  UnauthorizedException,
  UseGuards,
} from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { TrackingService } from './tracking.service';
import type { UpdatePositionDto } from './tracking.service';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';

/** Même logique que la carte colis : lecture groupage ou autres envois */
const TRACKING_READ = [
  'colis.groupage.read',
  'colis.autres-envois.read',
] as const;

/**
 * Endpoint REST pour la réception des positions GPS des traceurs.
 *
 * Les traceurs physiques (SPOT, Tive, etc.) sont configurés pour appeler :
 *   POST /tracking/update
 * toutes les N secondes avec leur position.
 *
 * La carte admin reçoit les mises à jour en temps réel via WebSocket (/tracking).
 */
@ApiTags('tracking')
@Controller('tracking')
export class TrackingController {
  // Clé secrète simple pour que les traceurs ne soient pas accessibles publiquement
  // À mettre en variable d'environnement en production
  private readonly TRACKER_API_KEY =
    process.env.TRACKER_API_KEY || 'lbp-tracker-secret-2026';

  constructor(private readonly trackingService: TrackingService) {}

  /**
   * POST /tracking/update
   * Appelé par le traceur GPS physique ou un agent avec l'app mobile.
   *
   * Exemple de corps :
   * {
   *   "tracker_id": "SPOT-001",
   *   "ref_colis": "LBP-0226-007",
   *   "latitude": 48.8566,
   *   "longitude": 2.3522,
   *   "batterie": 85,
   *   "api_key": "lbp-tracker-secret-2026"
   * }
   */
  @Post('update')
  @HttpCode(HttpStatus.OK)
  async receivePosition(
    @Body() dto: UpdatePositionDto,
    @Headers('x-api-key') headerKey?: string,
  ) {
    const key = dto.api_key || headerKey;
    if (key !== this.TRACKER_API_KEY) {
      throw new UnauthorizedException('Clé API invalide');
    }
    return this.trackingService.receivePosition(dto);
  }

  /**
   * GET /tracking/live
   * Snapshot de toutes les positions en cours (utile au chargement initial de la carte)
   */
  @Get('live')
  @ApiBearerAuth()
  @UseGuards(JwtAuthGuard, PermissionsGuard)
  @RequirePermission(...TRACKING_READ)
  getLivePositions() {
    return this.trackingService.getAllLivePositions();
  }

  /**
   * GET /tracking/:ref_colis/last
   * Dernière position connue d'un colis
   */
  @Get(':ref_colis/last')
  @ApiBearerAuth()
  @UseGuards(JwtAuthGuard, PermissionsGuard)
  @RequirePermission(...TRACKING_READ)
  getLastPosition(@Param('ref_colis') ref: string) {
    return this.trackingService.getLastPosition(ref);
  }

  /**
   * GET /tracking/:ref_colis/history
   * Historique complet du trajet d'un colis (tracé de la trajectoire sur la carte)
   */
  @Get(':ref_colis/history')
  @ApiBearerAuth()
  @UseGuards(JwtAuthGuard, PermissionsGuard)
  @RequirePermission(...TRACKING_READ)
  getHistory(@Param('ref_colis') ref: string) {
    return this.trackingService.getHistory(ref);
  }
}
