import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { ScheduleModule } from '@nestjs/schedule';
import { NotificationService } from './notification.service';
import { NotificationController } from './notification.controller';
import { UnpaidInvoicesNotificationService } from './unpaid-invoices-notification.service';
import { MessagingGatewayService } from './messaging-gateway.service';
import { Notification } from './entities/notification.entity';
import { User } from '../users/entities/user.entity';
import { PaiementsModule } from '../paiements/paiements.module';
import { RolesModule } from '../roles/roles.module';

@Module({
  imports: [
    TypeOrmModule.forFeature([Notification, User]),
    ScheduleModule.forRoot(),
    PaiementsModule,
    RolesModule,
  ],
  providers: [
    NotificationService,
    UnpaidInvoicesNotificationService,
    MessagingGatewayService,
  ],
  controllers: [NotificationController],
  exports: [
    NotificationService,
    UnpaidInvoicesNotificationService,
    MessagingGatewayService,
  ],
})
export class NotificationModule {}
