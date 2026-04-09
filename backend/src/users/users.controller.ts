import {
  Controller,
  Get,
  Post,
  Patch,
  Delete,
  Body,
  Param,
  ParseIntPipe,
  UseGuards,
  Request,
  ForbiddenException,
} from '@nestjs/common';
import { ApiTags, ApiOperation, ApiBearerAuth } from '@nestjs/swagger';
import { UsersService } from './users.service';
import type { CreateUserDto, UpdateUserDto } from './users.service';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import { UserRole } from './entities/user.entity';

/** Vérifie que l'appelant est DIRECTEUR ou ADMIN (cohérent avec l'ancien contrôle métier) */
function assertAdminOrDG(req: any) {
  const role = req.user?.role;
  if (role !== UserRole.DIRECTEUR && role !== UserRole.ADMIN) {
    throw new ForbiddenException('Accès réservé au Directeur ou au Superadmin');
  }
}

@ApiTags('users')
@Controller('users')
@UseGuards(JwtAuthGuard, PermissionsGuard)
@ApiBearerAuth()
export class UsersController {
  constructor(private readonly usersService: UsersService) {}

  // ── Liste & Détail ──────────────────────────────────────────────────────

  @Get()
  @RequirePermission('users.read')
  @ApiOperation({ summary: 'Liste tous les utilisateurs (admin/DG)' })
  async findAll() {
    return this.usersService.findAll();
  }

  @Get('stats')
  @RequirePermission('users.read')
  @ApiOperation({ summary: 'Statistiques des utilisateurs (admin/DG)' })
  async getStats() {
    return this.usersService.getUserStats();
  }

  /** Tout utilisateur connecté : e-mail / téléphone (sans users.update). */
  @Patch('me/profile')
  @ApiOperation({ summary: 'Mettre à jour son e-mail et téléphone' })
  async updateMyProfile(
    @Request() req: any,
    @Body() body: { email?: string | null; phone?: string | null },
  ) {
    const dto: UpdateUserDto = {};
    if (body.email !== undefined) dto.email = body.email === null ? null : body.email;
    if (body.phone !== undefined) dto.phone = body.phone === null ? null : body.phone;
    return this.usersService.updateUser(req.user.id, dto);
  }

  @Get(':id')
  @ApiOperation({ summary: "Détail d'un utilisateur" })
  async findOne(@Param('id', ParseIntPipe) id: number, @Request() req: any) {
    // L'utilisateur peut voir son propre profil, admin/DG peut tout voir
    if (req.user.id !== id) assertAdminOrDG(req);
    return this.usersService.findOne(id);
  }

  // ── Création & Modification ─────────────────────────────────────────────

  @Post()
  @RequirePermission('users.create')
  @ApiOperation({ summary: 'Créer un utilisateur (admin/DG)' })
  async create(@Body() dto: CreateUserDto) {
    const user = await this.usersService.createUser(dto);
    const sendResult = await this.usersService.sendTemporaryPassword(user.id);
    return {
      ...user,
      temp_password_sent: sendResult.sent,
      temp_password_message: sendResult.message,
    };
  }

  @Patch(':id')
  @RequirePermission('users.update')
  @ApiOperation({ summary: 'Modifier un utilisateur (admin/DG)' })
  async update(
    @Param('id', ParseIntPipe) id: number,
    @Body() dto: UpdateUserDto,
  ) {
    return this.usersService.updateUser(id, dto);
  }

  @Patch(':id/toggle-active')
  @RequirePermission('users.update')
  @ApiOperation({ summary: 'Activer / Désactiver un utilisateur (admin/DG)' })
  async toggleActive(@Param('id', ParseIntPipe) id: number) {
    return this.usersService.toggleActive(id);
  }

  @Delete(':id')
  @RequirePermission('users.delete', 'users.update')
  @ApiOperation({ summary: 'Supprimer (désactiver) un utilisateur (admin/DG)' })
  async delete(@Param('id', ParseIntPipe) id: number) {
    await this.usersService.deleteUser(id);
    return { message: 'Utilisateur désactivé' };
  }

  // ── Gestion Mot de Passe ────────────────────────────────────────────────

  @Post(':id/reset-password')
  @RequirePermission('users.update')
  @ApiOperation({ summary: 'Reset mdp temporaire (admin/DG)' })
  async resetPassword(
    @Param('id', ParseIntPipe) id: number,
    @Body('newPassword') newPassword: string,
  ) {
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
      throw new ForbiddenException(
        'Vous ne pouvez changer que votre propre mot de passe',
      );
    }
    await this.usersService.changePassword(id, oldPassword, newPassword);
    return { message: 'Mot de passe changé avec succès' };
  }

  @Get(':id/password')
  @RequirePermission('users.update')
  @ApiOperation({
    summary: 'Voir le mdp temporaire en clair (admin/DG uniquement)',
  })
  async getPassword(@Param('id', ParseIntPipe) id: number) {
    return this.usersService.getPasswordPlain(id);
  }

  @Post(':id/send-temp-password')
  @RequirePermission('users.update')
  @ApiOperation({
    summary: 'Envoyer le mot de passe temporaire par WhatsApp/SMS (admin/DG)',
  })
  async sendTemporaryPassword(@Param('id', ParseIntPipe) id: number) {
    return this.usersService.sendTemporaryPassword(id);
  }

  // ── Sélection d'Agence (1ère connexion) ────────────────────────────────

  @Post(':id/select-agence')
  @ApiOperation({
    summary: 'Sélectionner son agence lors de la 1ère connexion',
  })
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
