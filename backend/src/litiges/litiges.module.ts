import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { LitigesService } from './litiges.service';
import { LitigesController } from './litiges.controller';
import { Litige } from './entities/litige.entity';
import { LitigeMessage } from './entities/litige-message.entity';
import { RolesModule } from '../roles/roles.module';
import { AuditModule } from '../audit/audit.module';

@Module({
  imports: [
    TypeOrmModule.forFeature([Litige, LitigeMessage]),
    RolesModule,
    AuditModule,
  ],
  controllers: [LitigesController],
  providers: [LitigesService],
  exports: [LitigesService], // Exporter pour utilisation dans d'autres modules
})
export class LitigesModule {}
