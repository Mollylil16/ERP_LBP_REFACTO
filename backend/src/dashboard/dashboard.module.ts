import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { DashboardService } from './dashboard.service';
import { DashboardController } from './dashboard.controller';
import { ColisModule } from '../colis/colis.module';
import { ClientsModule } from '../clients/clients.module';
import { FacturesModule } from '../factures/factures.module';
import { PaiementsModule } from '../paiements/paiements.module';
import { CaisseModule } from '../caisse/caisse.module';
import { AgencesModule } from '../agences/agences.module';
import { Colis } from '../colis/entities/colis.entity';
import { Client } from '../clients/entities/client.entity';
import { Facture } from '../factures/entities/facture.entity';
import { Paiement } from '../paiements/entities/paiement.entity';

@Module({
  imports: [
    TypeOrmModule.forFeature([Colis, Client, Facture, Paiement]),
    ColisModule,
    ClientsModule,
    FacturesModule,
    PaiementsModule,
    AgencesModule,
    CaisseModule,
  ],
  providers: [DashboardService],
  controllers: [DashboardController],
})
export class DashboardModule { }
