import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { FacturesService } from './factures.service';
import { FacturesController } from './factures.controller';
import { FacturesPublicController } from './factures-public.controller';
import { Facture } from './entities/facture.entity';
import { Colis } from '../colis/entities/colis.entity';
import { LienPaiement } from '../paiements/entities/lien-paiement.entity';

@Module({
  imports: [TypeOrmModule.forFeature([Facture, Colis, LienPaiement])],
  providers: [FacturesService],
  controllers: [FacturesController, FacturesPublicController],
  exports: [FacturesService, TypeOrmModule],
})
export class FacturesModule { }
