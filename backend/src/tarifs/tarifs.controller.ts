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
import { TarifsService } from './tarifs.service';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';

@Controller('tarifs')
@UseGuards(JwtAuthGuard, PermissionsGuard)
export class TarifsController {
  constructor(private readonly tarifsService: TarifsService) {}

  @Post()
  @RequirePermission('config.update')
  create(@Body() createTarifDto: any) {
    return this.tarifsService.create(createTarifDto);
  }

  @Get()
  @RequirePermission('config.view')
  findAll() {
    return this.tarifsService.findAll();
  }

  @Get(':id')
  @RequirePermission('config.view')
  findOne(@Param('id') id: string) {
    return this.tarifsService.findOne(+id);
  }

  @Patch(':id')
  @RequirePermission('config.update')
  update(@Param('id') id: string, @Body() updateTarifDto: any) {
    return this.tarifsService.update(+id, +updateTarifDto);
  }

  @Delete(':id')
  @RequirePermission('config.update')
  remove(@Param('id') id: string) {
    return this.tarifsService.remove(+id);
  }
}
