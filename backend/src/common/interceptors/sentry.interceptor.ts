import * as Sentry from '@sentry/node';
import {
  Injectable,
  NestInterceptor,
  ExecutionContext,
  CallHandler,
} from '@nestjs/common';
import { Observable, throwError } from 'rxjs';
import { catchError, tap } from 'rxjs/operators';

/**
 * Intercepteur pour capturer les erreurs et les envoyer à Sentry
 */
@Injectable()
export class SentryInterceptor implements NestInterceptor {
  intercept(context: ExecutionContext, next: CallHandler): Observable<any> {
    const request = context.switchToHttp().getRequest();
    const { method, url, user } = request;

    // Ajouter contexte à Sentry
    Sentry.setContext('request', {
      method,
      url,
      userId: user?.id,
      userEmail: user?.email,
    });

    return next.handle().pipe(
      tap(() => {
        // Succès - on peut logger des métriques ici
      }),
      catchError((error) => {
        // Capturer l'erreur dans Sentry
        Sentry.captureException(error, {
          tags: {
            endpoint: `${method} ${url}`,
            userId: user?.id,
          },
          extra: {
            body: request.body,
            params: request.params,
            query: request.query,
          },
        });

        return throwError(() => error);
      }),
    );
  }
}

/**
 * Initialiser Sentry
 */
export function initSentry(dsn?: string) {
  if (!dsn) {
    console.warn('⚠️ Sentry DSN non configuré - Monitoring désactivé');
    return;
  }

  Sentry.init({
    dsn,
    environment: process.env.NODE_ENV || 'development',
    tracesSampleRate: 1.0, // 100% des transactions en dev, réduire en prod
    integrations: [
      // Intégrations automatiques
      Sentry.httpIntegration(),
    ],
    beforeSend(event, hint) {
      // Filtrer les erreurs sensibles
      if (event.exception) {
        const error = hint.originalException;
        // Ne pas envoyer les erreurs de validation
        if (error instanceof Error && error.name === 'ValidationError') {
          return null;
        }
      }
      return event;
    },
  });

  console.log('✓ Sentry initialisé');
}
