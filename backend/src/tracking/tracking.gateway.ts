import {
    WebSocketGateway,
    WebSocketServer,
    SubscribeMessage,
    MessageBody,
    OnGatewayConnection,
    OnGatewayDisconnect,
    ConnectedSocket,
} from '@nestjs/websockets';
import { Server, Socket } from 'socket.io';
import { Logger } from '@nestjs/common';

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
export class TrackingGateway implements OnGatewayConnection, OnGatewayDisconnect {
    @WebSocketServer()
    server: Server;

    private readonly logger = new Logger(TrackingGateway.name);

    handleConnection(client: Socket) {
        this.logger.log(`Client connecté : ${client.id}`);
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
        this.logger.log(`Position diffusée → ${positionData.ref_colis} [${positionData.latitude}, ${positionData.longitude}]`);
    }
}
