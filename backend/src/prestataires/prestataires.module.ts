import { Module, forwardRef } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { Prestataire } from './entities/prestataire.entity';
import { FacturePrestataire } from './entities/facture-prestataire.entity';
import { ReglementPrestataire } from './entities/reglement-prestataire.entity';
import { PrestatairesService } from './prestataires.service';
import { PrestatairesController } from './prestataires.controller';
import { NotificationModule } from '../notifications/notification.module';
import { Agence } from '../agences/entities/agence.entity';
import { User } from '../users/entities/user.entity';
import { PrestatairesAlertsService } from './prestataires-alerts.service';
import { RolesModule } from '../roles/roles.module';

@Module({
  imports: [
    TypeOrmModule.forFeature([
      Prestataire,
      FacturePrestataire,
      ReglementPrestataire,
      Agence,
      User,
    ]),
    forwardRef(() => NotificationModule),
    RolesModule,
  ],
  controllers: [PrestatairesController],
  providers: [PrestatairesService, PrestatairesAlertsService],
  exports: [PrestatairesService],
})
export class PrestatairesModule {}

