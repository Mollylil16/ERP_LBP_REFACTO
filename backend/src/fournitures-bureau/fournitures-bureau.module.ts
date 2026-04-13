import { Module, forwardRef } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { FournitureArticle } from './entities/fourniture-article.entity';
import { FournitureDemande } from './entities/fourniture-demande.entity';
import { FournitureDemandeLigne } from './entities/fourniture-demande-ligne.entity';
import { Agence } from '../agences/entities/agence.entity';
import { FournituresBureauService } from './fournitures-bureau.service';
import { FournituresBureauController } from './fournitures-bureau.controller';
import { NotificationModule } from '../notifications/notification.module';
import { RolesModule } from '../roles/roles.module';

@Module({
  imports: [
    TypeOrmModule.forFeature([
      FournitureArticle,
      FournitureDemande,
      FournitureDemandeLigne,
      Agence,
    ]),
    forwardRef(() => NotificationModule),
    RolesModule,
  ],
  controllers: [FournituresBureauController],
  providers: [FournituresBureauService],
  exports: [FournituresBureauService],
})
export class FournituresBureauModule {}
