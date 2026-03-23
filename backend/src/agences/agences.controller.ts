import {
    Controller, Get, Post, Body, Param, Put, Delete, Patch,
    UseGuards, Request, ForbiddenException, ParseIntPipe,
} from '@nestjs/common';
import { AgencesService } from './agences.service';
import type { CreateAgenceDto } from './agences.service';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
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
@UseGuards(JwtAuthGuard)
@Controller('agences')
export class AgencesController {
    constructor(private readonly agencesService: AgencesService) { }

    @Get()
    @ApiOperation({ summary: 'Toutes les agences actives (accessible à tous)' })
    findAll() {
        return this.agencesService.findAll();
    }

    @Get('stats')
    @ApiOperation({ summary: 'Stats agences : classement par activité (admin/DG)' })
    getStats(@Request() req: any) {
        // Accessible aussi au directeur pour le dashboard
        return this.agencesService.getStats();
    }

    @Get(':id')
    @ApiOperation({ summary: 'Détail d\'une agence' })
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
