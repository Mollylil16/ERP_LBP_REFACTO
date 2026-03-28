import {
  ExceptionFilter,
  Catch,
  ArgumentsHost,
  HttpException,
  HttpStatus,
  Logger,
} from '@nestjs/common';
import * as Sentry from '@sentry/node';
import { ForbiddenException } from '@nestjs/common';
import { Request, Response } from 'express';

/**
 * Filtre global pour gérer toutes les exceptions de l'application
 * Fournit des réponses d'erreur cohérentes et loggées
 */
@Catch()
export class GlobalExceptionFilter implements ExceptionFilter {
  private readonly logger = new Logger(GlobalExceptionFilter.name);

  catch(exception: unknown, host: ArgumentsHost) {
    const ctx = host.switchToHttp();
    const response = ctx.getResponse<Response>();
    const request = ctx.getRequest<Request>();

    let status = HttpStatus.INTERNAL_SERVER_ERROR;
    let message = 'Une erreur interne est survenue';
    let errors: any = null;

    // Gestion des HttpException (erreurs NestJS)
    if (exception instanceof HttpException) {
      status = exception.getStatus();
      const exceptionResponse = exception.getResponse();

      if (typeof exceptionResponse === 'object') {
        message = (exceptionResponse as any).message || exception.message;
        errors = (exceptionResponse as any).errors || null;
      } else {
        message = exceptionResponse;
      }
    }
    // Gestion des erreurs de validation class-validator
    else if (
      exception instanceof Error &&
      exception.name === 'ValidationError'
    ) {
      status = HttpStatus.BAD_REQUEST;
      message = 'Erreur de validation';
      errors = exception.message;
    }
    // Gestion des erreurs TypeORM
    else if (
      exception instanceof Error &&
      exception.name === 'QueryFailedError'
    ) {
      status = HttpStatus.BAD_REQUEST;
      message = 'Erreur de base de données';

      // Extraire le message d'erreur PostgreSQL
      const error = exception as any;
      if (error.code === '23505') {
        message = 'Cette entrée existe déjà (violation de contrainte unique)';
      } else if (error.code === '23503') {
        message = 'Référence invalide (violation de clé étrangère)';
      } else if (error.code === '23502') {
        message = 'Champ obligatoire manquant';
      }
    }
    // Autres erreurs
    else if (exception instanceof Error) {
      message = exception.message;
    }

    // Construire la réponse d'erreur
    const errorResponse = {
      statusCode: status,
      timestamp: new Date().toISOString(),
      path: request.url,
      method: request.method,
      message,
      ...(errors && { errors }),
    };

    // Audit structuré des refus d’accès (403) — grep `LBP_ACCESS_DENIED` / exploitable par log/métriques
    if (status === HttpStatus.FORBIDDEN) {
      const user = (request as any).user;
      let required: string[] | undefined;
      if (exception instanceof HttpException) {
        const body = exception.getResponse();
        if (typeof body === 'object' && body !== null && 'required' in body) {
          required = (body as { required?: string[] }).required;
        }
      }
      const audit = {
        event: 'LBP_ACCESS_DENIED',
        path: request.url,
        method: request.method,
        userId: user?.id ?? null,
        username: user?.username ?? null,
        role:
          typeof user?.role === 'string'
            ? user.role
            : (user?.role?.code ?? null),
        required: required ?? null,
        reason: typeof message === 'string' ? message : JSON.stringify(message),
      };
      this.logger.warn(JSON.stringify(audit));
      try {
        Sentry.captureMessage('LBP_ACCESS_DENIED', {
          level: 'warning',
          tags: { event: 'LBP_ACCESS_DENIED' },
          extra: audit,
        });
      } catch {
        /* Sentry indisponible */
      }
    }

    // Logger l'erreur
    if (status >= 500) {
      this.logger.error(
        `${request.method} ${request.url} - ${status}`,
        exception instanceof Error ? exception.stack : exception,
      );
    } else if (status !== HttpStatus.FORBIDDEN) {
      this.logger.warn(
        `${request.method} ${request.url} - ${status}: ${message}`,
      );
    }

    // Envoyer la réponse
    response.status(status).json(errorResponse);
  }
}
