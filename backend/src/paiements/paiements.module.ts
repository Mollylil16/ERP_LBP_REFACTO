import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { PaiementsService } from './paiements.service';
import { PaiementsController } from './paiements.controller';
import { PaiementsHistoryController } from './paiements-history.controller';
import { Paiement } from './entities/paiement.entity';
import { Facture } from '../factures/entities/facture.entity';
import { Colis } from '../colis/entities/colis.entity';
import { LienPaiement } from './entities/lien-paiement.entity';
import { PaiementLienService } from './paiements-lien.service';
import { PaiementsLienController } from './paiements-lien.controller';
import { CaisseModule } from '../caisse/caisse.module';

@Module({
  imports: [
    TypeOrmModule.forFeature([Paiement, Facture, LienPaiement, Colis]),
    CaisseModule,
  ],
  providers: [PaiementsService, PaiementLienService],
  controllers: [PaiementsController, PaiementsLienController, PaiementsHistoryController], // ✅ AJOUT
  exports: [PaiementsService, PaiementLienService, TypeOrmModule],
})
export class PaiementsModule { }
