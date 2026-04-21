import {
  Controller,
  Get,
  Post,
  Put,
  Delete,
  Body,
  Param,
  UseGuards,
  Request,
  Query,
  Patch,
} from '@nestjs/common';
import {
  ApiTags,
  ApiOperation,
  ApiResponse,
  ApiBearerAuth,
} from '@nestjs/swagger';
import { ColisService } from './colis.service';
import { CreateColisDto } from './dto/create-colis.dto';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import { AgencyRequiredGuard } from '../auth/guards/agency-required.guard';

const COLIS_READ = ['colis.groupage.read', 'colis.autres-envois.read'] as const;
const COLIS_CREATE = [
  'colis.groupage.create',
  'colis.autres-envois.create',
] as const;
const COLIS_UPDATE = [
  'colis.groupage.update',
  'colis.autres-envois.update',
] as const;
const COLIS_DELETE = [
  'colis.groupage.delete',
  'colis.autres-envois.delete',
] as const;
const COLIS_VALIDATE = [
  'colis.groupage.validate',
  'colis.autres-envois.validate',
] as const;

@ApiTags('colis')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard, AgencyRequiredGuard)
@Controller('colis')
export class ColisController {
  constructor(private readonly colisService: ColisService) {}

  @Post()
  @RequirePermission(...COLIS_CREATE)
  @ApiOperation({ summary: 'Créer un nouveau colis' })
  @ApiResponse({ status: 201, description: 'Colis créé avec succès' })
  async create(@Body() createColisDto: CreateColisDto, @Request() req) {
    const agenceId = await this.colisService.resolveAgenceIdForCreate(
      createColisDto.id_agence,
      req.user,
    );
    return this.colisService.create(
      createColisDto,
      req.user.username,
      agenceId,
    );
  }

  @Post('groupage')
  @RequirePermission(...COLIS_CREATE)
  @ApiOperation({ summary: 'Alias pour créer un colis groupage' })
  createGroupage(@Body() createColisDto: CreateColisDto, @Request() req) {
    return this.create(createColisDto, req);
  }

  @Post('autres-envois')
  @RequirePermission(...COLIS_CREATE)
  @ApiOperation({ summary: 'Alias pour créer un colis autres envois' })
  createAutresEnvois(@Body() createColisDto: CreateColisDto, @Request() req) {
    return this.create(createColisDto, req);
  }

  @Put(':id')
  @RequirePermission(...COLIS_UPDATE)
  @ApiOperation({ summary: 'Mettre à jour un colis existant' })
  @ApiResponse({ status: 200, description: 'Colis mis à jour avec succès' })
  async update(
    @Param('id') id: string,
    @Body() updateColisDto: CreateColisDto,
    @Request() req,
  ) {
    const agenceId = await this.colisService.resolveAgenceIdForCreate(
      updateColisDto.id_agence,
      req.user,
    );
    return this.colisService.update(
      +id,
      updateColisDto,
      req.user.username,
      agenceId,
    );
  }

  @Get()
  @RequirePermission(...COLIS_READ)
  @ApiOperation({ summary: 'Liste des colis' })
  findAll(@Query() query, @Request() req) {
    return this.colisService.findAll(query, req.user);
  }

  @Get('search')
  @RequirePermission(...COLIS_READ)
  @ApiOperation({ summary: 'Rechercher des colis' })
  search(
    @Query('search') searchTerm: string,
    @Query('forme_envoi') formeEnvoi: string,
    @Request() req,
  ) {
    return this.colisService.searchColis(searchTerm, formeEnvoi, req.user);
  }

  @Get('track/:ref')
  @RequirePermission(...COLIS_READ)
  @ApiOperation({ summary: "Suivi public d'un colis" })
  track(@Param('ref') ref: string) {
    return this.colisService.trackColis(ref);
  }

  @Get(':id')
  @RequirePermission(...COLIS_READ)
  @ApiOperation({ summary: "Détails d'un colis" })
  findOne(@Param('id') id: string) {
    return this.colisService.findOne(+id);
  }

  @Patch(':id/validate')
  @RequirePermission(...COLIS_VALIDATE)
  @ApiOperation({ summary: 'Valider un colis' })
  validate(@Param('id') id: number) {
    return this.colisService.validateColis(id);
  }

  @Patch(':id/receive-at-hub')
  @RequirePermission(...COLIS_UPDATE)
  @ApiOperation({ summary: 'Marquer le colis comme reçu au Hub (Bobigny)' })
  receiveAtHub(@Param('id') id: number) {
    return this.colisService.receiveAtHub(id);
  }

  @Delete(':id')
  @RequirePermission(...COLIS_DELETE)
  @ApiOperation({ summary: 'Supprimer un colis' })
  @ApiResponse({ status: 200, description: 'Colis supprimé avec succès' })
  @ApiResponse({ status: 403, description: 'Suppression non autorisée' })
  async remove(@Param('id') id: string, @Request() req) {
    return this.colisService.remove(+id, req.user);
  }
}
