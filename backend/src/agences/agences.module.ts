import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { Agence } from './entities/agence.entity';
import { AgencesService } from './agences.service';
import { AgencesController } from './agences.controller';
import { RolesModule } from '../roles/roles.module';

@Module({
  imports: [TypeOrmModule.forFeature([Agence]), RolesModule],
  providers: [AgencesService],
  controllers: [AgencesController],
  exports: [AgencesService, TypeOrmModule],
})
export class AgencesModule {}
