import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { RapportsService } from './rapports.service';
import { RapportsController } from './rapports.controller';
import { Colis } from '../colis/entities/colis.entity';
import { Facture } from '../factures/entities/facture.entity';
import { Paiement } from '../paiements/entities/paiement.entity';
import { MouvementCaisse } from '../caisse/entities/mouvement-caisse.entity';
import { Caisse } from '../caisse/entities/caisse.entity';
import { Agence } from '../agences/entities/agence.entity';
import { RolesModule } from '../roles/roles.module';

@Module({
  imports: [
    TypeOrmModule.forFeature([Colis, Facture, Paiement, MouvementCaisse, Caisse, Agence]),
    RolesModule,
  ],
  providers: [RapportsService],
  controllers: [RapportsController],
})
export class RapportsModule {}
