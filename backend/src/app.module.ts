import { Module } from '@nestjs/common';
import { ConfigModule, ConfigService } from '@nestjs/config';
import { TypeOrmModule } from '@nestjs/typeorm';
import * as pg from 'pg';
import { AppController } from './app.controller';
import { AppService } from './app.service';
import { UsersModule } from './users/users.module';
import { ClientsModule } from './clients/clients.module';
import { ColisModule } from './colis/colis.module';
import { FacturesModule } from './factures/factures.module';
import { PaiementsModule } from './paiements/paiements.module';
import { AuthModule } from './auth/auth.module';
import { CaisseModule } from './caisse/caisse.module';
import { DashboardModule } from './dashboard/dashboard.module';
import { RapportsModule } from './rapports/rapports.module';
import { AlertModule } from './alerts/alert.module';
import { NotificationModule } from './notifications/notification.module';
import { AnalyticsModule } from './analytics/analytics.module';
import { TarifsModule } from './tarifs/tarifs.module';
import { AgencesModule } from './agences/agences.module';
import { RolesModule } from './roles/roles.module';
import { PermissionsModule } from './permissions/permissions.module';
import { ProduitsCatalogueModule } from './produits-catalogue/produits-catalogue.module';
import { TrackingModule } from './tracking/tracking.module';
import { LitigesModule } from './litiges/litiges.module';
import { CallCenterModule } from './callcenter/callcenter.module';
import { ExploitationModule } from './exploitation/exploitation.module';
import { FournituresBureauModule } from './fournitures-bureau/fournitures-bureau.module';
import { ThrottlerModule } from '@nestjs/throttler';

@Module({
  imports: [
    ConfigModule.forRoot({
      isGlobal: true,
    }),
    TypeOrmModule.forRootAsync({
      imports: [ConfigModule],
      useFactory: (configService: ConfigService) => {
        const parseNumber = (value: unknown, fallback?: number) => {
          if (typeof value === 'number' && Number.isFinite(value)) return value;
          if (typeof value === 'string' && value.trim() !== '') {
            const n = Number(value);
            if (Number.isFinite(n)) return n;
          }
          return fallback;
        };

        const parseBoolean = (value: unknown, fallback?: boolean) => {
          if (typeof value === 'boolean') return value;
          if (typeof value === 'string') {
            const v = value.trim().toLowerCase();
            if (['true', '1', 'yes', 'y', 'on'].includes(v)) return true;
            if (['false', '0', 'no', 'n', 'off'].includes(v)) return false;
          }
          return fallback;
        };

        const typeormLoggingRaw = configService.get('TYPEORM_LOGGING');
        const typeormLogging =
          typeormLoggingRaw !== undefined &&
          typeormLoggingRaw !== null &&
          !(
            typeof typeormLoggingRaw === 'string' &&
            typeormLoggingRaw.trim() === ''
          )
            ? parseBoolean(typeormLoggingRaw, false)
            : process.env.NODE_ENV !== 'test';

        return {
          type: 'postgres' as const,
          driver: pg,
          host: configService.get<string>('DB_HOST'),
          port: parseNumber(configService.get('DB_PORT'), 5432),
          username: configService.get<string>('DB_USERNAME'),
          password: configService.get<string>('DB_PASSWORD'),
          database: configService.get<string>('DB_DATABASE'),
          entities: [__dirname + '/**/*.entity{.ts,.js}'],
          synchronize: parseBoolean(configService.get('DB_SYNCHRONIZE'), false),
          logging: typeormLogging,
        };
      },
      inject: [ConfigService],
    }),
    ThrottlerModule.forRoot([
      {
        // Anti brute-force global (valeurs réglables via env plus tard si besoin)
        ttl: 60_000,
        limit: 120,
      },
    ]),
    UsersModule,
    ClientsModule,
    ColisModule,
    FacturesModule,
    PaiementsModule,
    NotificationModule,
    AnalyticsModule,
    AuthModule,
    CaisseModule,
    DashboardModule,
    RapportsModule,
    AlertModule, // ✅ Module d'alertes automatiques
    TarifsModule,
    AgencesModule,
    RolesModule,
    PermissionsModule,
    ProduitsCatalogueModule,
    TrackingModule,
    LitigesModule,
    CallCenterModule,
    ExploitationModule,
    FournituresBureauModule,
  ],
  controllers: [AppController],
  providers: [AppService],
})
export class AppModule {}
