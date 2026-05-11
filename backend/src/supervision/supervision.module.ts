import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { CaisseModule } from '../caisse/caisse.module';
import { NotificationModule } from '../notifications/notification.module';
import { RolesModule } from '../roles/roles.module';
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
import { SupervisionCronService } from './supervision-cron.service';
import { PdfSupervisionService } from './pdf-supervision.service';
import { SupervisionController } from './supervision.controller';
import { RhCongeRequest } from '../rh/entities/rh-conge-request.entity';
import { RhEvaluation } from '../rh/entities/rh-evaluation.entity';
import { RhEmploye } from '../rh/entities/rh-employe.entity';

@Module({
  imports: [
    CaisseModule,
    NotificationModule,
    RolesModule,
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
      RhCongeRequest,
      RhEvaluation,
      RhEmploye,
    ]),
  ],
  controllers: [SupervisionController],
  providers: [SupervisionService, SupervisionInsightsService, SupervisionCronService, PdfSupervisionService],
  exports: [SupervisionService, SupervisionInsightsService],
})
export class SupervisionModule {}
