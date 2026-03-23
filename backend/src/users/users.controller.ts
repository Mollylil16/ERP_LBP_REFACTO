import {
    Controller, Get, Post, Patch, Delete, Body, Param, ParseIntPipe,
    UseGuards, Request, ForbiddenException,
} from '@nestjs/common';
import { ApiTags, ApiOperation, ApiBearerAuth } from '@nestjs/swagger';
import { UsersService } from './users.service';
import type { CreateUserDto, UpdateUserDto } from './users.service';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { UserRole } from './entities/user.entity';

/** Vérifie que l'appelant est DIRECTEUR ou ADMIN (superadmin) */
function assertAdminOrDG(req: any) {
    const role = req.user?.role;
    if (role !== UserRole.DIRECTEUR && role !== UserRole.ADMIN) {
        throw new ForbiddenException('Accès réservé au Directeur ou au Superadmin');
    }
}

@ApiTags('users')
@Controller('users')
@UseGuards(JwtAuthGuard)
@ApiBearerAuth()
export class UsersController {
    constructor(private readonly usersService: UsersService) { }

    // ── Liste & Détail ──────────────────────────────────────────────────────

    @Get()
    @ApiOperation({ summary: 'Liste tous les utilisateurs (admin/DG)' })
    async findAll(@Request() req: any) {
        assertAdminOrDG(req);
        return this.usersService.findAll();
    }

    @Get('stats')
    @ApiOperation({ summary: 'Statistiques des utilisateurs (admin/DG)' })
    async getStats(@Request() req: any) {
        assertAdminOrDG(req);
        return this.usersService.getUserStats();
    }

    @Get(':id')
    @ApiOperation({ summary: 'Détail d\'un utilisateur' })
    async findOne(@Param('id', ParseIntPipe) id: number, @Request() req: any) {
        // L'utilisateur peut voir son propre profil, admin/DG peut tout voir
        if (req.user.id !== id) assertAdminOrDG(req);
        return this.usersService.findOne(id);
    }

    // ── Création & Modification ─────────────────────────────────────────────

    @Post()
    @ApiOperation({ summary: 'Créer un utilisateur (admin/DG)' })
    async create(@Body() dto: CreateUserDto, @Request() req: any) {
        assertAdminOrDG(req);
        return this.usersService.createUser(dto);
    }

    @Patch(':id')
    @ApiOperation({ summary: 'Modifier un utilisateur (admin/DG)' })
    async update(
        @Param('id', ParseIntPipe) id: number,
        @Body() dto: UpdateUserDto,
        @Request() req: any,
    ) {
        assertAdminOrDG(req);
        return this.usersService.updateUser(id, dto);
    }

    @Patch(':id/toggle-active')
    @ApiOperation({ summary: 'Activer / Désactiver un utilisateur (admin/DG)' })
    async toggleActive(@Param('id', ParseIntPipe) id: number, @Request() req: any) {
        assertAdminOrDG(req);
        return this.usersService.toggleActive(id);
    }

    @Delete(':id')
    @ApiOperation({ summary: 'Supprimer (désactiver) un utilisateur (admin/DG)' })
    async delete(@Param('id', ParseIntPipe) id: number, @Request() req: any) {
        assertAdminOrDG(req);
        await this.usersService.deleteUser(id);
        return { message: 'Utilisateur désactivé' };
    }

    // ── Gestion Mot de Passe ────────────────────────────────────────────────

    @Post(':id/reset-password')
    @ApiOperation({ summary: 'Reset mdp temporaire (admin/DG)' })
    async resetPassword(
        @Param('id', ParseIntPipe) id: number,
        @Body('newPassword') newPassword: string,
        @Request() req: any,
    ) {
        assertAdminOrDG(req);
        await this.usersService.resetPassword(id, newPassword);
        return { message: 'Mot de passe réinitialisé' };
    }

    @Post(':id/change-password')
    @ApiOperation({ summary: 'Changer son propre mdp (utilisateur)' })
    async changePassword(
        @Param('id', ParseIntPipe) id: number,
        @Body('oldPassword') oldPassword: string,
        @Body('newPassword') newPassword: string,
        @Request() req: any,
    ) {
        // Seul l'utilisateur lui-même peut changer son mdp via cette route
        if (req.user.id !== id) {
            throw new ForbiddenException('Vous ne pouvez changer que votre propre mot de passe');
        }
        await this.usersService.changePassword(id, oldPassword, newPassword);
        return { message: 'Mot de passe changé avec succès' };
    }

    @Get(':id/password')
    @ApiOperation({ summary: 'Voir le mdp temporaire en clair (admin/DG uniquement)' })
    async getPassword(@Param('id', ParseIntPipe) id: number, @Request() req: any) {
        assertAdminOrDG(req);
        return this.usersService.getPasswordPlain(id);
    }

    // ── Sélection d'Agence (1ère connexion) ────────────────────────────────

    @Post(':id/select-agence')
    @ApiOperation({ summary: 'Sélectionner son agence lors de la 1ère connexion' })
    async selectAgence(
        @Param('id', ParseIntPipe) id: number,
        @Body('agence_id') agenceId: number,
        @Request() req: any,
    ) {
        if (req.user.id !== id) {
            throw new ForbiddenException('Action non autorisée');
        }
        return this.usersService.setAgence(id, agenceId);
    }
}
