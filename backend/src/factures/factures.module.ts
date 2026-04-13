import { Module, forwardRef } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { FacturesService } from './factures.service';
import { FacturesController } from './factures.controller';
import { FacturesPublicController } from './factures-public.controller';
import { Facture } from './entities/facture.entity';
import { Colis } from '../colis/entities/colis.entity';
import { LienPaiement } from '../paiements/entities/lien-paiement.entity';
import { RolesModule } from '../roles/roles.module';
import { AuditModule } from '../audit/audit.module';
import { ExploitationModule } from '../exploitation/exploitation.module';

@Module({
  imports: [
    TypeOrmModule.forFeature([Facture, Colis, LienPaiement]),
    RolesModule,
    AuditModule,
    forwardRef(() => ExploitationModule),
  ],
  providers: [FacturesService],
  controllers: [FacturesController, FacturesPublicController],
  exports: [FacturesService, TypeOrmModule],
})
export class FacturesModule {}
