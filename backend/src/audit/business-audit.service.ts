import { Injectable, Logger } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { AuditLog } from '../database/entities/audit-log.entity';

export interface BusinessAuditPayload {
  /** ex. litige.updated, facture.validated */
  action: string;
  /** ex. litige, facture */
  entity: string;
  entityId?: string;
  userId?: number | string;
  username?: string;
  details?: Record<string, unknown>;
}

/**
 * Écriture dans `audit_logs` pour des actions métier (complète l’intercepteur HTTP).
 * Ne bloque jamais la transaction métier : persistance asynchrone après le commit.
 */
@Injectable()
export class BusinessAuditService {
  private readonly logger = new Logger(BusinessAuditService.name);

  constructor(
    @InjectRepository(AuditLog)
    private readonly auditLogRepository: Repository<AuditLog>,
  ) {}

  logEvent(payload: BusinessAuditPayload): void {
    setImmediate(() => {
      void this.persist(payload);
    });
  }

  private async persist(p: BusinessAuditPayload): Promise<void> {
    try {
      const row = this.auditLogRepository.create({
        userId: p.userId != null ? String(p.userId) : undefined,
        action: p.action,
        entity: p.entity,
        entityId: p.entityId ?? undefined,
        details: {
          ...p.details,
          ...(p.username ? { username: p.username } : {}),
        },
        status: 'success',
      });
      await this.auditLogRepository.save(row);
    } catch (err) {
      this.logger.warn(
        `Écriture audit métier ignorée (${p.action} / ${p.entity})`,
        err,
      );
    }
  }
}
