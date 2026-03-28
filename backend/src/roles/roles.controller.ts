import {
  Controller,
  Get,
  Post,
  Body,
  Patch,
  Param,
  Delete,
  UseGuards,
} from '@nestjs/common';
import { RolesService } from './roles.service';
import { CreateRoleDto } from './dto/create-role.dto';
import { UpdateRoleDto } from './dto/update-role.dto';
import { AssignPermissionsToRoleDto } from '../permissions/dto/create-permission.dto';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';

@Controller('roles')
@UseGuards(JwtAuthGuard, PermissionsGuard)
export class RolesController {
  constructor(private readonly rolesService: RolesService) {}

  @Post()
  @RequirePermission('config.system')
  create(@Body() createRoleDto: CreateRoleDto) {
    return this.rolesService.create(createRoleDto);
  }

  @Get()
  @RequirePermission('config.system')
  findAll() {
    return this.rolesService.findAll();
  }

  /** Doit rester avant @Get(':id') */
  @Get('code/:code')
  @RequirePermission('config.system')
  findByCode(@Param('code') code: string) {
    return this.rolesService.findByCode(code);
  }

  @Get(':id/permissions')
  @RequirePermission('config.system')
  getPermissions(@Param('id') id: string) {
    return this.rolesService.getPermissions(+id);
  }

  @Get(':id')
  @RequirePermission('config.system')
  findOne(@Param('id') id: string) {
    return this.rolesService.findOne(+id);
  }

  @Patch(':id')
  @RequirePermission('config.system')
  update(@Param('id') id: string, @Body() updateRoleDto: UpdateRoleDto) {
    return this.rolesService.update(+id, updateRoleDto);
  }

  @Delete(':id')
  @RequirePermission('config.system')
  remove(@Param('id') id: string) {
    return this.rolesService.remove(+id);
  }

  @Post('assign-permissions')
  @RequirePermission('config.system')
  assignPermissions(@Body() assignPermissionsDto: AssignPermissionsToRoleDto) {
    return this.rolesService.assignPermissions(
      assignPermissionsDto.roleId,
      assignPermissionsDto.permissionIds,
    );
  }
}
