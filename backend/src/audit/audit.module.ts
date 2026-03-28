import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { AuditLog } from '../database/entities/audit-log.entity';
import { BusinessAuditService } from './business-audit.service';

@Module({
  imports: [TypeOrmModule.forFeature([AuditLog])],
  providers: [BusinessAuditService],
  exports: [BusinessAuditService],
})
export class AuditModule {}
