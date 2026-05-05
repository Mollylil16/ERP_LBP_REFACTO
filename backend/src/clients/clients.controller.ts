import {
  Controller,
  Get,
  Post,
  Body,
  Param,
  Put,
  UseGuards,
  Query,
  Request,
} from '@nestjs/common';
import { ApiTags, ApiOperation, ApiBearerAuth } from '@nestjs/swagger';
import { ClientsService } from './clients.service';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import { Client } from './entities/client.entity';

@ApiTags('clients')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard)
@Controller('clients')
export class ClientsController {
  constructor(private readonly clientsService: ClientsService) {}

  @Post()
  @RequirePermission('clients.create')
  @ApiOperation({ summary: 'Créer un nouveau client' })
  create(@Body() clientData: Partial<Client>) {
    return this.clientsService.create(clientData);
  }

  @Get()
  @RequirePermission('clients.read')
  @ApiOperation({ summary: 'Liste des clients' })
  findAll(@Request() req: any) {
    return this.clientsService.findAll(req.user);
  }

  @Get('search')
  @RequirePermission('clients.read')
  @ApiOperation({ summary: 'Rechercher des clients' })
  search(@Query('search') searchTerm: string, @Request() req: any) {
    return this.clientsService.search(searchTerm, req.user);
  }

  @Get(':id')
  @RequirePermission('clients.read')
  @ApiOperation({ summary: "Détails d'un client" })
  findOne(@Param('id') id: string) {
    return this.clientsService.findOne(+id);
  }

  @Get(':id/history')
  @RequirePermission('clients.read')
  @ApiOperation({ summary: "Historique des colis d'un client" })
  getHistory(@Param('id') id: string) {
    return this.clientsService.getClientHistory(+id);
  }
}
