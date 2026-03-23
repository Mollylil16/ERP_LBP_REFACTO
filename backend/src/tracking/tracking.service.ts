import { Injectable, Logger, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { TrackerPosition } from './entities/tracker-position.entity';
import { TrackingGateway } from './tracking.gateway';

export interface UpdatePositionDto {
    tracker_id: string;     // ID du traceur physique
    ref_colis?: string;     // Référence colis (optionnel si mappé côté serveur)
    latitude: number;
    longitude: number;
    vitesse?: number;
    altitude?: number;
    batterie?: number;
    statut?: string;
    timestamp_gps?: string; // ISO 8601
    /** Clé secrète partagée avec les traceurs pour sécuriser l'endpoint */
    api_key?: string;
}

@Injectable()
export class TrackingService {
    private readonly logger = new Logger(TrackingService.name);

    /** Cache mémoire : dernière position connue par ref_colis */
    private readonly lastPositions = new Map<string, any>();

    constructor(
        @InjectRepository(TrackerPosition)
        private readonly positionRepo: Repository<TrackerPosition>,
        private readonly gateway: TrackingGateway,
    ) { }

    /**
     * Reçoit une position GPS d'un traceur et :
     * 1. L'enregistre en BDD (historique complet)
     * 2. Met à jour le cache mémoire
     * 3. Diffuse en temps réel via WebSocket
     */
    async receivePosition(dto: UpdatePositionDto): Promise<{ success: boolean; message: string }> {
        this.logger.log(`Position reçue → tracker=${dto.tracker_id} colis=${dto.ref_colis} [${dto.latitude}, ${dto.longitude}]`);

        // Enregistrement BDD
        const pos = this.positionRepo.create({
            tracker_id: dto.tracker_id,
            ref_colis: dto.ref_colis,
            latitude: dto.latitude,
            longitude: dto.longitude,
            vitesse: dto.vitesse,
            altitude: dto.altitude,
            batterie: dto.batterie,
            statut: dto.statut || 'EN_TRANSIT',
            timestamp_gps: dto.timestamp_gps ? new Date(dto.timestamp_gps) : new Date(),
        });
        await this.positionRepo.save(pos);

        // Mise à jour cache
        const key = dto.ref_colis || dto.tracker_id;
        this.lastPositions.set(key, {
            tracker_id: dto.tracker_id,
            ref_colis: dto.ref_colis,
            latitude: dto.latitude,
            longitude: dto.longitude,
            vitesse: dto.vitesse,
            batterie: dto.batterie,
            statut: dto.statut || 'EN_TRANSIT',
            timestamp_gps: pos.timestamp_gps?.toISOString(),
            updated_at: new Date().toISOString(),
        });

        // Diffusion WebSocket → tous les admins connectés voient le marqueur bouger
        this.gateway.broadcastPosition({
            tracker_id: dto.tracker_id,
            ref_colis: dto.ref_colis || dto.tracker_id,
            latitude: dto.latitude,
            longitude: dto.longitude,
            vitesse: dto.vitesse,
            batterie: dto.batterie,
            statut: dto.statut || 'EN_TRANSIT',
            timestamp_gps: pos.timestamp_gps?.toISOString(),
        });

        return { success: true, message: 'Position enregistrée et diffusée.' };
    }

    /** Dernière position connue pour un colis donné */
    async getLastPosition(ref_colis: string): Promise<any> {
        // D'abord le cache (plus rapide)
        if (this.lastPositions.has(ref_colis)) {
            return this.lastPositions.get(ref_colis);
        }
        // Sinon BDD
        const pos = await this.positionRepo.findOne({
            where: { ref_colis },
            order: { created_at: 'DESC' },
        });
        if (!pos) throw new NotFoundException(`Aucune position trouvée pour le colis ${ref_colis}`);
        return pos;
    }

    /** Toutes les positions d'un colis (historique de trajectoire) */
    async getHistory(ref_colis: string): Promise<TrackerPosition[]> {
        return this.positionRepo.find({
            where: { ref_colis },
            order: { created_at: 'ASC' },
        });
    }

    /** Toutes les dernières positions (snapshot pour la carte admin) */
    getAllLivePositions(): any[] {
        return Array.from(this.lastPositions.values());
    }
}
