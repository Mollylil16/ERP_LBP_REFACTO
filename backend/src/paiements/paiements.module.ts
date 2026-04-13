import { Module, forwardRef } from '@nestjs/common';
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
import { RolesModule } from '../roles/roles.module';
import { ExploitationModule } from '../exploitation/exploitation.module';

@Module({
  imports: [
    TypeOrmModule.forFeature([Paiement, Facture, LienPaiement, Colis]),
    CaisseModule,
    RolesModule,
    forwardRef(() => ExploitationModule),
  ],
  providers: [PaiementsService, PaiementLienService],
  controllers: [
    PaiementsController,
    PaiementsLienController,
    PaiementsHistoryController,
  ], // ✅ AJOUT
  exports: [PaiementsService, PaiementLienService, TypeOrmModule],
})
export class PaiementsModule {}
