import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { ScheduleModule } from '@nestjs/schedule';
import { AlertService } from './alert.service';
import { Caisse } from '../caisse/entities/caisse.entity';
import { Facture } from '../factures/entities/facture.entity';
import { NotificationModule } from '../notifications/notification.module';
import { CaisseModule } from '../caisse/caisse.module';

@Module({
  imports: [
    ScheduleModule.forRoot(),
    TypeOrmModule.forFeature([Caisse, Facture]),
    NotificationModule,
    CaisseModule,
  ],
  providers: [AlertService],
  exports: [AlertService],
})
export class AlertModule {}
