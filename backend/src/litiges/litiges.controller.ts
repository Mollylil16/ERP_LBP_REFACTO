import {
  Controller,
  Get,
  Post,
  Body,
  Patch,
  Param,
  Delete,
  Query,
  UseGuards,
  Request,
  HttpStatus,
  ParseIntPipe,
} from '@nestjs/common';
import {
  ApiTags,
  ApiOperation,
  ApiResponse,
  ApiBearerAuth,
} from '@nestjs/swagger';
import { LitigesService } from './litiges.service';
import { CreateLitigeDto } from './dto/create-litige.dto';
import { UpdateLitigeDto } from './dto/update-litige.dto';
import { CreateMessageDto } from './dto/create-message.dto';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import { LitigeStatut } from './entities/litige.entity';

@ApiTags('litiges')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard)
@Controller('litiges')
export class LitigesController {
  constructor(private readonly litigesService: LitigesService) {}

  @Post()
  @RequirePermission('litiges.create')
  @ApiOperation({ summary: 'Créer un nouveau litige' })
  @ApiResponse({
    status: HttpStatus.CREATED,
    description: 'Litige créé avec succès',
  })
  async create(@Body() createLitigeDto: CreateLitigeDto, @Request() req) {
    return await this.litigesService.create(createLitigeDto, req.user.id);
  }

  @Get()
  @RequirePermission('litiges.view')
  @ApiOperation({ summary: 'Récupérer tous les litiges' })
  @ApiResponse({
    status: HttpStatus.OK,
    description: 'Liste des litiges récupérée',
  })
  async findAll(
    @Query('statut') statut?: LitigeStatut,
    @Query('type') type?: string,
    @Query('agence_id') agenceId?: number,
    @Query('assigne_id') assigneId?: number,
    @Query('page') page?: number,
    @Query('limit') limit?: number,
    @Query('with_deleted') withDeleted?: string,
    @Request() req?,
  ) {
    const canViewDeleted = this.canUseAdminFilters(req?.user);
    const includeDeleted =
      canViewDeleted && this.parseBooleanQuery(withDeleted);

    return await this.litigesService.findAll({
      statut,
      type,
      agence_id: agenceId,
      assigne_id: assigneId,
      page,
      limit,
      with_deleted: includeDeleted,
    });
  }

  @Get('statistics')
  @RequirePermission('litiges.view')
  @ApiOperation({ summary: 'Récupérer les statistiques des litiges' })
  @ApiResponse({
    status: HttpStatus.OK,
    description: 'Statistiques récupérées',
  })
  async getStatistics(@Query('agence_id') agenceId?: number) {
    return await this.litigesService.getStatistics(agenceId);
  }

  @Get(':id')
  @RequirePermission('litiges.view')
  @ApiOperation({ summary: 'Récupérer un litige par ID' })
  @ApiResponse({ status: HttpStatus.OK, description: 'Litige trouvé' })
  @ApiResponse({
    status: HttpStatus.NOT_FOUND,
    description: 'Litige non trouvé',
  })
  async findOne(@Param('id', ParseIntPipe) id: number) {
    return await this.litigesService.findOne(id);
  }

  @Patch(':id')
  @RequirePermission('litiges.manage')
  @ApiOperation({ summary: 'Mettre à jour un litige' })
  @ApiResponse({ status: HttpStatus.OK, description: 'Litige mis à jour' })
  @ApiResponse({
    status: HttpStatus.NOT_FOUND,
    description: 'Litige non trouvé',
  })
  async update(
    @Param('id', ParseIntPipe) id: number,
    @Body() updateLitigeDto: UpdateLitigeDto,
    @Request() req,
  ) {
    return await this.litigesService.update(id, updateLitigeDto, req.user.id);
  }

  @Delete(':id')
  @RequirePermission('litiges.admin')
  @ApiOperation({ summary: 'Supprimer un litige' })
  @ApiResponse({
    status: HttpStatus.NO_CONTENT,
    description: 'Litige supprimé',
  })
  @ApiResponse({
    status: HttpStatus.NOT_FOUND,
    description: 'Litige non trouvé',
  })
  async remove(@Param('id', ParseIntPipe) id: number) {
    await this.litigesService.remove(id);
  }

  @Post(':id/restore')
  @RequirePermission('litiges.admin')
  @ApiOperation({ summary: 'Restaurer un litige supprimé' })
  @ApiResponse({ status: HttpStatus.OK, description: 'Litige restauré' })
  @ApiResponse({
    status: HttpStatus.NOT_FOUND,
    description: 'Litige non trouvé',
  })
  async restore(@Param('id', ParseIntPipe) id: number) {
    return await this.litigesService.restore(id);
  }

  @Post(':id/messages')
  @RequirePermission('litiges.create')
  @ApiOperation({ summary: 'Ajouter un message à un litige' })
  @ApiResponse({ status: HttpStatus.CREATED, description: 'Message ajouté' })
  @ApiResponse({
    status: HttpStatus.NOT_FOUND,
    description: 'Litige non trouvé',
  })
  async addMessage(
    @Param('id', ParseIntPipe) id: number,
    @Body() createMessageDto: CreateMessageDto,
    @Request() req,
  ) {
    return await this.litigesService.addMessage(
      id,
      createMessageDto,
      req.user.id,
    );
  }

  @Get(':id/messages')
  @RequirePermission('litiges.view')
  @ApiOperation({ summary: 'Récupérer les messages d’un litige (paginé)' })
  @ApiResponse({ status: HttpStatus.OK, description: 'Messages récupérés' })
  @ApiResponse({
    status: HttpStatus.NOT_FOUND,
    description: 'Litige non trouvé',
  })
  async getMessages(
    @Param('id', ParseIntPipe) id: number,
    @Query('page') page?: string,
    @Query('limit') limit?: string,
    @Query('order') order?: 'ASC' | 'DESC',
  ) {
    const parsedPage = page ? Number(page) : 1;
    const parsedLimit = limit ? Number(limit) : 20;
    const normalizedOrder = order === 'DESC' ? 'DESC' : 'ASC';
    return await this.litigesService.getMessages(
      id,
      parsedPage,
      parsedLimit,
      normalizedOrder,
    );
  }

  private canUseAdminFilters(user: any): boolean {
    const role = user?.role?.code ?? user?.role;
    return ['ADMIN', 'DIRECTEUR', 'SUPER_ADMIN'].includes(role);
  }

  private parseBooleanQuery(value?: string): boolean {
    if (!value) return false;
    return ['true', '1', 'yes', 'on'].includes(value.toLowerCase());
  }
}
