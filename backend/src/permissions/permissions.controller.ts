import {
  Controller,
  Get,
  Post,
  Body,
  Param,
  Delete,
  UseGuards,
} from '@nestjs/common';
import { PermissionsService } from './permissions.service';
import { CreatePermissionDto } from './dto/create-permission.dto';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import { PermissionModule } from './entities/permission.entity';

@Controller('permissions')
@UseGuards(JwtAuthGuard, PermissionsGuard)
export class PermissionsController {
  constructor(private readonly permissionsService: PermissionsService) {}

  @Post()
  @RequirePermission('config.system')
  create(@Body() createPermissionDto: CreatePermissionDto) {
    return this.permissionsService.create(createPermissionDto);
  }

  @Get()
  @RequirePermission('config.system')
  findAll() {
    return this.permissionsService.findAll();
  }

  @Get('actions-speciales')
  @RequirePermission('config.system')
  getAllActionsSpeciales() {
    return this.permissionsService.getAllActionsSpeciales();
  }

  @Get('module/:module')
  @RequirePermission('config.system')
  findByModule(@Param('module') module: PermissionModule) {
    return this.permissionsService.findByModule(module);
  }

  @Get(':id')
  @RequirePermission('config.system')
  findOne(@Param('id') id: string) {
    return this.permissionsService.findOne(+id);
  }

  @Delete(':id')
  @RequirePermission('config.system')
  remove(@Param('id') id: string) {
    return this.permissionsService.remove(+id);
  }
}
