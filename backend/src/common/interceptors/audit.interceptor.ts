import {
  Injectable,
  NestInterceptor,
  ExecutionContext,
  CallHandler,
  Logger,
} from '@nestjs/common';
import { Observable } from 'rxjs';
import { tap } from 'rxjs/operators';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { AuditLog } from '../../database/entities/audit-log.entity';

/**
 * Intercepteur pour logger toutes les actions dans audit_logs
 */
@Injectable()
export class AuditInterceptor implements NestInterceptor {
  private readonly logger = new Logger(AuditInterceptor.name);

  constructor(
    @InjectRepository(AuditLog)
    private auditLogRepository: Repository<AuditLog>,
  ) {}

  intercept(context: ExecutionContext, next: CallHandler): Observable<any> {
    const request = context.switchToHttp().getRequest();
    const { method, url, user, body, params } = request;

    // Ignorer certaines routes (health checks, etc.)
    if (this.shouldSkipAudit(url)) {
      return next.handle();
    }

    const startTime = Date.now();

    return next.handle().pipe(
      tap({
        next: async (response) => {
          const duration = Date.now() - startTime;

          try {
            await this.createAuditLog({
              userId: user?.id || 'anonymous',
              action: this.getActionFromMethod(method),
              entity: this.getEntityFromUrl(url),
              entityId: params?.id || null,
              details: this.sanitizeDetails({ body, params, response }),
              ipAddress: request.ip,
              userAgent: request.headers['user-agent'],
              duration,
              status: 'success',
            });
          } catch (error) {
            this.logger.error('Erreur création audit log:', error);
          }
        },
        error: async (error) => {
          const duration = Date.now() - startTime;

          try {
            await this.createAuditLog({
              userId: user?.id || 'anonymous',
              action: this.getActionFromMethod(method),
              entity: this.getEntityFromUrl(url),
              entityId: params?.id || null,
              details: this.sanitizeDetails({
                body,
                params,
                error: error.message,
              }),
              ipAddress: request.ip,
              userAgent: request.headers['user-agent'],
              duration,
              status: 'error',
            });
          } catch (auditError) {
            this.logger.error(
              'Erreur création audit log (erreur):',
              auditError,
            );
          }
        },
      }),
    );
  }

  /**
   * Créer un log d'audit
   */
  private async createAuditLog(data: Partial<AuditLog>) {
    const auditLog = this.auditLogRepository.create(data);
    await this.auditLogRepository.save(auditLog);
  }

  /**
   * Déterminer l'action depuis la méthode HTTP
   */
  private getActionFromMethod(method: string): string {
    const actions: Record<string, string> = {
      GET: 'READ',
      POST: 'CREATE',
      PUT: 'UPDATE',
      PATCH: 'UPDATE',
      DELETE: 'DELETE',
    };
    return actions[method] || 'UNKNOWN';
  }

  /**
   * Extraire l'entité depuis l'URL
   */
  private getEntityFromUrl(url: string): string {
    const match = url.match(/\/api\/([^\/\?]+)/);
    return match ? match[1] : 'unknown';
  }

  /**
   * Nettoyer les détails sensibles (mots de passe, tokens, etc.)
   */
  private sanitizeDetails(details: any): any {
    const sanitized = { ...details };
    const sensitiveFields = ['password', 'token', 'secret', 'apiKey'];

    const removeSensitive = (obj: any) => {
      if (typeof obj !== 'object' || obj === null) return obj;

      for (const key in obj) {
        if (
          sensitiveFields.some((field) => key.toLowerCase().includes(field))
        ) {
          obj[key] = '[REDACTED]';
        } else if (typeof obj[key] === 'object') {
          removeSensitive(obj[key]);
        }
      }
      return obj;
    };

    return removeSensitive(sanitized);
  }

  /**
   * Vérifier si on doit ignorer l'audit pour cette URL
   */
  private shouldSkipAudit(url: string): boolean {
    const skipPatterns = ['/health', '/metrics', '/api/docs', '/favicon.ico'];
    return skipPatterns.some((pattern) => url.includes(pattern));
  }
}
