import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { RolesService } from './roles.service';
import { RolesController } from './roles.controller';
import { Role } from './entities/role.entity';
import { Permission } from '../permissions/entities/permission.entity';
import { RolePermission } from '../permissions/entities/role-permission.entity';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { AuditModule } from '../audit/audit.module';

@Module({
  imports: [
    TypeOrmModule.forFeature([Role, Permission, RolePermission]),
    AuditModule,
  ],
  controllers: [RolesController],
  providers: [RolesService, PermissionsGuard],
  exports: [RolesService, PermissionsGuard],
})
export class RolesModule {}
