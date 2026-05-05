import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { ScheduleModule } from '@nestjs/schedule';
import { AlertService } from './alert.service';
import { Caisse } from '../caisse/entities/caisse.entity';
import { Facture } from '../factures/entities/facture.entity';
import { Litige } from '../litiges/entities/litige.entity';
import { PointJournalier } from '../exploitation/entities/point-journalier.entity';
import { MouvementCaisse } from '../caisse/entities/mouvement-caisse.entity';
import { Agence } from '../agences/entities/agence.entity';
import { Notification } from '../notifications/entities/notification.entity';
import { NotificationModule } from '../notifications/notification.module';
import { CaisseModule } from '../caisse/caisse.module';

@Module({
  imports: [
    ScheduleModule.forRoot(),
    TypeOrmModule.forFeature([
      Caisse,
      Facture,
      Litige,
      PointJournalier,
      MouvementCaisse,
      Agence,
      Notification,
    ]),
    NotificationModule,
    CaisseModule,
  ],
  providers: [AlertService],
  exports: [AlertService],
})
export class AlertModule {}
