import {
  WebSocketGateway,
  WebSocketServer,
  OnGatewayConnection,
  OnGatewayDisconnect,
} from '@nestjs/websockets';
import { Server, Socket } from 'socket.io';
import { Logger } from '@nestjs/common';
import { JwtService } from '@nestjs/jwt';
import { ConfigService } from '@nestjs/config';

/**
 * WebSocket Gateway — Tracking GPS Temps Réel
 *
 * Deux canaux :
 *   - "tracker:update"  → reçu du traceur/agent, diffusé à tous les admins
 *   - "tracking:live"   → émis à tous les clients connectés quand une position arrive
 */
@WebSocketGateway({
  cors: { origin: '*' },
  namespace: '/tracking',
})
export class TrackingGateway
  implements OnGatewayConnection, OnGatewayDisconnect
{
  @WebSocketServer()
  server: Server;

  private readonly logger = new Logger(TrackingGateway.name);

  constructor(
    private readonly jwtService: JwtService,
    private readonly configService: ConfigService,
  ) {}

  handleConnection(client: Socket) {
    const token =
      (client.handshake.auth?.token as string | undefined)?.trim() ||
      (
        client.handshake.headers?.authorization as string | undefined
      )?.replace(/^Bearer\s+/i, '')?.trim();
    if (!token) {
      this.logger.warn(`Connexion refusée (pas de JWT) : ${client.id}`);
      client.disconnect(true);
      return;
    }
    try {
      this.jwtService.verify(token, {
        secret: this.configService.get<string>('JWT_SECRET'),
      });
      this.logger.log(`Client connecté : ${client.id}`);
    } catch {
      this.logger.warn(`Connexion refusée (JWT invalide) : ${client.id}`);
      client.disconnect(true);
    }
  }

  handleDisconnect(client: Socket) {
    this.logger.log(`Client déconnecté : ${client.id}`);
  }

  /**
   * Diffuse une mise à jour de position à tous les clients admin connectés.
   * Appelé par TrackingService après enregistrement en BDD.
   */
  broadcastPosition(positionData: {
    tracker_id: string;
    ref_colis: string;
    latitude: number;
    longitude: number;
    vitesse?: number;
    batterie?: number;
    statut?: string;
    timestamp_gps?: string;
  }) {
    this.server.emit('tracking:live', positionData);
    this.logger.log(
      `Position diffusée → ${positionData.ref_colis} [${positionData.latitude}, ${positionData.longitude}]`,
    );
  }
}
