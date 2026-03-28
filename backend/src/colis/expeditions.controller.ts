import {
  Controller,
  Get,
  Post,
  Body,
  Patch,
  Param,
  Delete,
  UseGuards,
  Request,
} from '@nestjs/common';
import { ExpeditionsService } from './expeditions.service';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import { ExpeditionStatut } from './entities/expedition.entity';

const COLIS_READ = ['colis.groupage.read', 'colis.autres-envois.read'] as const;
const COLIS_WRITE = [
  'colis.groupage.update',
  'colis.autres-envois.update',
] as const;

@Controller('expeditions')
@UseGuards(JwtAuthGuard, PermissionsGuard)
export class ExpeditionsController {
  constructor(private readonly expeditionsService: ExpeditionsService) {}

  @Post()
  @RequirePermission(...COLIS_WRITE)
  create(@Body() createExpeditionDto: any, @Request() req) {
    return this.expeditionsService.create(createExpeditionDto, req.user);
  }

  @Get()
  @RequirePermission(...COLIS_READ)
  findAll(@Request() req) {
    return this.expeditionsService.findAll(req.user);
  }

  @Get(':id')
  @RequirePermission(...COLIS_READ)
  findOne(@Param('id') id: string) {
    return this.expeditionsService.findOne(+id);
  }

  @Post(':id/colis')
  @RequirePermission(...COLIS_WRITE)
  addColis(@Param('id') id: string, @Body('colisIds') colisIds: number[]) {
    return this.expeditionsService.addColis(+id, colisIds);
  }

  @Delete(':id/colis/:colisId')
  @RequirePermission(...COLIS_WRITE)
  removeColis(@Param('id') id: string, @Param('colisId') colisId: string) {
    return this.expeditionsService.removeColis(+id, +colisId);
  }

  @Patch(':id/status')
  @RequirePermission(...COLIS_WRITE)
  updateStatus(
    @Param('id') id: string,
    @Body('statut') statut: ExpeditionStatut,
  ) {
    return this.expeditionsService.updateStatus(+id, statut);
  }
}
