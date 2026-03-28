import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { CallCenterController } from './callcenter.controller';
import { CallCenterService } from './callcenter.service';
import { CallCenterConversation } from './entities/callcenter-conversation.entity';
import { CallCenterMessage } from './entities/callcenter-message.entity';
import { Client } from '../clients/entities/client.entity';
import { Facture } from '../factures/entities/facture.entity';
import { Litige } from '../litiges/entities/litige.entity';
import { NotificationModule } from '../notifications/notification.module';
import { RolesModule } from '../roles/roles.module';

@Module({
  imports: [
    TypeOrmModule.forFeature([
      CallCenterConversation,
      CallCenterMessage,
      Client,
      Facture,
      Litige,
    ]),
    NotificationModule,
    RolesModule,
  ],
  controllers: [CallCenterController],
  providers: [CallCenterService],
  exports: [CallCenterService],
})
export class CallCenterModule {}
