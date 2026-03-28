import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { RapportsService } from './rapports.service';
import { RapportsController } from './rapports.controller';
import { Colis } from '../colis/entities/colis.entity';
import { RolesModule } from '../roles/roles.module';

@Module({
  imports: [TypeOrmModule.forFeature([Colis]), RolesModule],
  providers: [RapportsService],
  controllers: [RapportsController],
})
export class RapportsModule {}
