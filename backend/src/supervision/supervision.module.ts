import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { CaisseModule } from '../caisse/caisse.module';
import { NotificationModule } from '../notifications/notification.module';
import { Agence } from '../agences/entities/agence.entity';
import { Colis } from '../colis/entities/colis.entity';
import { Paiement } from '../paiements/entities/paiement.entity';
import { Facture } from '../factures/entities/facture.entity';
import { Caisse } from '../caisse/entities/caisse.entity';
import { MouvementCaisse } from '../caisse/entities/mouvement-caisse.entity';
import { User } from '../users/entities/user.entity';
import { Client } from '../clients/entities/client.entity';
import { SupervisionSignalement } from './entities/supervision-signalement.entity';
import { SupervisionDemandeJustification } from './entities/supervision-demande-justification.entity';
import { SupervisionAnnotation } from './entities/supervision-annotation.entity';
import { SupervisionRapport } from './entities/supervision-rapport.entity';
import { SupervisionService } from './supervision.service';
import { SupervisionInsightsService } from './supervision-insights.service';
import { SupervisionController } from './supervision.controller';

@Module({
  imports: [
    CaisseModule,
    NotificationModule,
    TypeOrmModule.forFeature([
      Agence,
      Colis,
      Paiement,
      Facture,
      Caisse,
      MouvementCaisse,
      User,
      Client,
      SupervisionSignalement,
      SupervisionDemandeJustification,
      SupervisionAnnotation,
      SupervisionRapport,
    ]),
  ],
  controllers: [SupervisionController],
  providers: [SupervisionService, SupervisionInsightsService],
  exports: [SupervisionService, SupervisionInsightsService],
})
export class SupervisionModule {}
