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
import { LitigesModule } from '../litiges/litiges.module';
import { AlertModule } from '../alerts/alert.module';
import { Colis } from '../colis/entities/colis.entity';
import { Client } from '../clients/entities/client.entity';
import { Facture } from '../factures/entities/facture.entity';
import { Paiement } from '../paiements/entities/paiement.entity';
import { Caisse } from '../caisse/entities/caisse.entity';
import { Litige } from '../litiges/entities/litige.entity';
import { PointJournalier } from '../exploitation/entities/point-journalier.entity';
import { FournitureDemande } from '../fournitures-bureau/entities/fourniture-demande.entity';
import { RolesModule } from '../roles/roles.module';

@Module({
  imports: [
    TypeOrmModule.forFeature([Colis, Client, Facture, Paiement, Caisse, Litige, PointJournalier, FournitureDemande]),
    ColisModule,
    ClientsModule,
    FacturesModule,
    PaiementsModule,
    AgencesModule,
    CaisseModule,
    LitigesModule,
    RolesModule,
    AlertModule,
  ],
  providers: [DashboardService],
  controllers: [DashboardController],
})
export class DashboardModule {}
