import { Controller, Get, Post, Put, Delete, Body, Param, UseGuards, Request, Query, Patch } from '@nestjs/common';
import { ApiTags, ApiOperation, ApiResponse, ApiBearerAuth } from '@nestjs/swagger';
import { ColisService } from './colis.service';
import { CreateColisDto } from './dto/create-colis.dto';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';

@ApiTags('colis')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('colis')
export class ColisController {
    constructor(private readonly colisService: ColisService) { }

    @Post()
    @ApiOperation({ summary: 'Créer un nouveau colis' })
    @ApiResponse({ status: 201, description: 'Colis créé avec succès' })
    create(@Body() createColisDto: CreateColisDto, @Request() req) {
        return this.colisService.create(createColisDto, req.user.username, req.user.id_agence);
    }

    @Post('groupage')
    @ApiOperation({ summary: 'Alias pour créer un colis groupage' })
    createGroupage(@Body() createColisDto: CreateColisDto, @Request() req) {
        return this.create(createColisDto, req);
    }

    @Post('autres-envois')
    @ApiOperation({ summary: 'Alias pour créer un colis autres envois' })
    createAutresEnvois(@Body() createColisDto: CreateColisDto, @Request() req) {
        return this.create(createColisDto, req);
    }

    @Put(':id')
    @ApiOperation({ summary: 'Mettre à jour un colis existant' })
    @ApiResponse({ status: 200, description: 'Colis mis à jour avec succès' })
    update(
        @Param('id') id: string,
        @Body() updateColisDto: CreateColisDto,
        @Request() req,
    ) {
        return this.colisService.update(+id, updateColisDto, req.user.username, req.user.id_agence);
    }

    @Get()
    @ApiOperation({ summary: 'Liste des colis' })
    findAll(@Query() query, @Request() req) {
        return this.colisService.findAll(query, req.user);
    }

    @Get('search')
    @ApiOperation({ summary: 'Rechercher des colis' })
    search(@Query('search') searchTerm: string, @Query('forme_envoi') formeEnvoi: string, @Request() req) {
        return this.colisService.searchColis(searchTerm, formeEnvoi, req.user);
    }

    @Get('track/:ref')
    @ApiOperation({ summary: 'Suivi public d\'un colis' })
    track(@Param('ref') ref: string) {
        return this.colisService.trackColis(ref);
    }

    @Get(':id')
    @ApiOperation({ summary: 'Détails d\'un colis' })
    findOne(@Param('id') id: string) {
        return this.colisService.findOne(+id);
    }

    @Patch(':id/validate')
    @ApiOperation({ summary: 'Valider un colis' })
    validate(@Param('id') id: number) {
        return this.colisService.validateColis(id);
    }

    @Patch(':id/receive-at-hub')
    @ApiOperation({ summary: 'Marquer le colis comme reçu au Hub (Bobigny)' })
    receiveAtHub(@Param('id') id: number) {
        return this.colisService.receiveAtHub(id);
    }

    @Delete(':id')
    @ApiOperation({ summary: 'Supprimer un colis' })
    @ApiResponse({ status: 200, description: 'Colis supprimé avec succès' })
    @ApiResponse({ status: 403, description: 'Suppression non autorisée' })
    async remove(@Param('id') id: string, @Request() req) {
        return this.colisService.remove(+id, req.user);
    }
}
