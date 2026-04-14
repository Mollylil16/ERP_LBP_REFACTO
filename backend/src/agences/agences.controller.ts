import {
  Controller,
  Get,
  Post,
  Body,
  Param,
  Put,
  Delete,
  Patch,
  UseGuards,
  Request,
  ForbiddenException,
  ParseIntPipe,
} from '@nestjs/common';
import { AgencesService } from './agences.service';
import type { CreateAgenceDto } from './agences.service';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import { ApiTags, ApiOperation, ApiBearerAuth } from '@nestjs/swagger';
import { UserRole } from '../users/entities/user.entity';

function assertAdminOrDG(req: any) {
  const role = req.user?.role;
  if (role !== UserRole.DIRECTEUR && role !== UserRole.ADMIN) {
    throw new ForbiddenException('Accès réservé au Directeur ou au Superadmin');
  }
}

@ApiTags('Agences')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard)
@Controller('agences')
export class AgencesController {
  constructor(private readonly agencesService: AgencesService) {}

  @Get()
  @RequirePermission('agences.read', 'config.view')
  @ApiOperation({ summary: 'Liste des agences actives (lecture métier ou admin config)' })
  findAll(@Request() req: any) {
    return this.agencesService.findAll(req.user);
  }

  @Get('stats')
  @RequirePermission('config.view', 'dashboard.admin')
  @ApiOperation({
    summary: 'Stats agences : classement par activité (admin/DG)',
  })
  getStats(@Request() req: any) {
    return this.agencesService.getStats();
  }

  @Get(':id')
  @RequirePermission('agences.read', 'config.view')
  @ApiOperation({ summary: "Détail d'une agence" })
  findOne(@Param('id', ParseIntPipe) id: number) {
    return this.agencesService.findOne(id);
  }

  @Post()
  @ApiOperation({ summary: 'Créer une agence (admin/DG)' })
  create(@Body() dto: CreateAgenceDto, @Request() req: any) {
    assertAdminOrDG(req);
    return this.agencesService.create(dto);
  }

  @Put(':id')
  @Patch(':id')
  @ApiOperation({ summary: 'Modifier une agence (admin/DG)' })
  update(
    @Param('id', ParseIntPipe) id: number,
    @Body() dto: Partial<CreateAgenceDto>,
    @Request() req: any,
  ) {
    assertAdminOrDG(req);
    return this.agencesService.update(id, dto);
  }

  @Delete(':id')
  @ApiOperation({ summary: 'Désactiver une agence (admin/DG)' })
  remove(@Param('id', ParseIntPipe) id: number, @Request() req: any) {
    assertAdminOrDG(req);
    return this.agencesService.remove(id);
  }
}
