import { webcrypto } from 'node:crypto';
if (!globalThis.crypto) {
  (globalThis as any).crypto = webcrypto;
}

import { NestFactory } from '@nestjs/core';
import { ValidationPipe } from '@nestjs/common';
import { SwaggerModule, DocumentBuilder } from '@nestjs/swagger';
import { ConfigService } from '@nestjs/config';
import { AppModule } from './app.module';
import { GlobalExceptionFilter } from './common/filters/global-exception.filter';
import { initSentry } from './common/interceptors/sentry.interceptor';

async function bootstrap() {
  const app = await NestFactory.create(AppModule);
  const configService = app.get(ConfigService);
  // Support proxy TLS (NGINX/Traefik) for redirect + IP
  (app as any).set?.('trust proxy', 1);

  // ✅ Initialiser Sentry pour monitoring des erreurs
  const sentryDsn = configService.get<string>('SENTRY_DSN');
  if (sentryDsn) {
    initSentry(sentryDsn);
  }

  // Prefix application
  const apiPrefix = configService.get<string>('API_PREFIX') || 'api';
  app.setGlobalPrefix(apiPrefix);

  // ✅ Validation globale avec messages d'erreur détaillés
  app.useGlobalPipes(
    new ValidationPipe({
      whitelist: true,
      transform: true,
      forbidNonWhitelisted: true,
      transformOptions: {
        enableImplicitConversion: true,
      },
      errorHttpStatusCode: 400,
    }),
  );

  // ✅ Gestion globale des erreurs
  app.useGlobalFilters(new GlobalExceptionFilter());

  // ── CORS strict en production ──────────────────────────────────────────
  // Env: CORS_ORIGINS="https://app1.com,https://app2.com"
  const corsOriginsRaw = configService.get<string>('CORS_ORIGINS') ?? '';
  const corsOrigins = corsOriginsRaw
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean);

  const isProd = (configService.get<string>('NODE_ENV') ?? process.env.NODE_ENV) === 'production';
  app.enableCors({
    origin: (origin, cb) => {
      // Pas d'Origin = appels serveur-à-serveur / curl
      if (!origin) return cb(null, true);
      if (!isProd) return cb(null, true);
      if (corsOrigins.length === 0) return cb(new Error('CORS: origin not allowed'), false);
      return corsOrigins.includes(origin)
        ? cb(null, true)
        : cb(new Error('CORS: origin not allowed'), false);
    },
    credentials: true,
  });

  // ── HTTPS enforcement derrière proxy (NGINX/Traefik) ────────────────────
  // Activer si ton proxy termine TLS et envoie x-forwarded-proto=https
  const forceHttps = (configService.get<string>('FORCE_HTTPS') ?? '').toLowerCase() === 'true';
  if (isProd && forceHttps) {
    app.use((req: any, res: any, next: any) => {
      const proto = req.headers['x-forwarded-proto'];
      if (proto && String(proto).toLowerCase() !== 'https') {
        const host = req.headers.host;
        return res.redirect(301, `https://${host}${req.originalUrl}`);
      }
      return next();
    });
  }

  // Swagger
  const config = new DocumentBuilder()
    .setTitle('LBP API')
    .setDescription('The LBP (La Belle Porte) API description')
    .setVersion('1.0')
    .addBearerAuth()
    .build();
  const document = SwaggerModule.createDocument(app, config);
  SwaggerModule.setup(`${apiPrefix}/docs`, app, document);

  const portRaw = configService.get('PORT');
  const port =
    typeof portRaw === 'number'
      ? portRaw
      : typeof portRaw === 'string' && portRaw.trim() !== ''
        ? Number(portRaw)
        : 3001;

  await app.listen(port);
  console.log(
    `Application is running on: http://localhost:${port}/${apiPrefix}`,
  );
  console.log(
    `Swagger documentation: http://localhost:${port}/${apiPrefix}/docs`,
  );
}
bootstrap();
