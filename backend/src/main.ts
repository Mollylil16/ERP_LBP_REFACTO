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

  // ✅ Initialiser Sentry pour monitoring des erreurs
  const sentryDsn = configService.get<string>('SENTRY_DSN');
  if (sentryDsn) {
    initSentry(sentryDsn);
  }

  // Prefix application
  const apiPrefix = configService.get<string>('API_PREFIX') || 'api';
  app.setGlobalPrefix(apiPrefix);

  // ✅ Validation globale avec messages d'erreur détaillés
  app.useGlobalPipes(new ValidationPipe({
    whitelist: true,
    transform: true,
    forbidNonWhitelisted: true,
    transformOptions: {
      enableImplicitConversion: true,
    },
    errorHttpStatusCode: 400,
  }));

  // ✅ Gestion globale des erreurs
  app.useGlobalFilters(new GlobalExceptionFilter());

  // CORS — accepter toutes les origines en développement (indispensable pour accès depuis téléphone via IP réseau)
  app.enableCors({
    origin: true, // Accepte toutes les origines (à restreindre en production)
    credentials: true,
  });

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
  console.log(`Application is running on: http://localhost:${port}/${apiPrefix}`);
  console.log(`Swagger documentation: http://localhost:${port}/${apiPrefix}/docs`);
}
bootstrap();
