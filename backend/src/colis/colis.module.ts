import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { ColisService } from './colis.service';
import { ColisController } from './colis.controller';
import { Colis, Marchandise } from './entities/colis.entity';
import { Expedition } from './entities/expedition.entity';
import { Client } from '../clients/entities/client.entity';
import { Agence } from '../agences/entities/agence.entity';
import { ExpeditionsService } from './expeditions.service';
import { ExpeditionsController } from './expeditions.controller';
import { FacturesModule } from '../factures/factures.module';
import { TarifsModule } from '../tarifs/tarifs.module';
import { WhatsappService } from '../notifications/whatsapp.service';
import { NotificationModule } from '../notifications/notification.module';
import { RolesModule } from '../roles/roles.module';

@Module({
  imports: [
    TypeOrmModule.forFeature([Colis, Marchandise, Client, Expedition, Agence]),
    FacturesModule,
    TarifsModule,
    NotificationModule,
    RolesModule,
  ],
  providers: [ColisService, ExpeditionsService],
  controllers: [ColisController, ExpeditionsController],
  exports: [ColisService, TypeOrmModule],
})
export class ColisModule {}
