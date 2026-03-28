import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { AnalyticsService } from './analytics.service';
import { AnalyticsController } from './analytics.controller';
import { Colis } from '../colis/entities/colis.entity';
import { Client } from '../clients/entities/client.entity';
import { Facture } from '../factures/entities/facture.entity';
import { Paiement } from '../paiements/entities/paiement.entity';
import { MouvementCaisse } from '../caisse/entities/mouvement-caisse.entity';
import { CaisseModule } from '../caisse/caisse.module';
import { RolesModule } from '../roles/roles.module';

@Module({
  imports: [
    TypeOrmModule.forFeature([
      Colis,
      Client,
      Facture,
      Paiement,
      MouvementCaisse,
    ]),
    CaisseModule,
    RolesModule,
  ],
  controllers: [AnalyticsController],
  providers: [AnalyticsService],
  exports: [AnalyticsService],
})
export class AnalyticsModule {}
