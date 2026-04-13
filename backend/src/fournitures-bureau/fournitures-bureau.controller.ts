import {
  Body,
  Controller,
  Get,
  Param,
  Patch,
  Post,
  Query,
  Request,
  UseGuards,
} from '@nestjs/common';
import { ApiBearerAuth, ApiOperation, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import { FournituresBureauService } from './fournitures-bureau.service';
import { CreateFournitureArticleDto } from './dto/create-fourniture-article.dto';
import { AjustStockArticleDto } from './dto/ajust-stock-article.dto';
import { CreateDemandeFournitureDto } from './dto/create-demande-fourniture.dto';
import { ValiderDemandeFournitureDto } from './dto/valider-demande-fourniture.dto';
import { RefuserDemandeFournitureDto } from './dto/refuser-demande-fourniture.dto';

@ApiTags('fournitures-bureau')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard)
@Controller('fournitures-bureau')
export class FournituresBureauController {
  constructor(private readonly service: FournituresBureauService) {}

  @Get('articles')
  @RequirePermission(
    'exploitation.fournitures.read',
    'exploitation.fournitures.manage',
    'exploitation.fournitures.request',
  )
  @ApiOperation({ summary: 'Liste des articles (stock)' })
  listArticles(@Request() req: { user: any }) {
    return this.service.listArticles(req.user);
  }

  @Post('articles')
  @RequirePermission('exploitation.fournitures.manage')
  createArticle(
    @Request() req: { user: any },
    @Body() dto: CreateFournitureArticleDto,
  ) {
    return this.service.createArticle(req.user, dto);
  }

  @Patch('articles/:id/stock')
  @RequirePermission('exploitation.fournitures.manage')
  ajustStock(
    @Request() req: { user: any },
    @Param('id') id: string,
    @Body() dto: AjustStockArticleDto,
  ) {
    return this.service.ajustStock(req.user, +id, dto);
  }

  @Get('demandes')
  @RequirePermission(
    'exploitation.fournitures.read',
    'exploitation.fournitures.manage',
    'exploitation.fournitures.request',
  )
  listDemandes(
    @Request() req: { user: any },
    @Query('statut') statut?: string,
    @Query('agence_id') agence_id?: string,
  ) {
    return this.service.listDemandes(req.user, {
      statut,
      agence_id: agence_id ? +agence_id : undefined,
    });
  }

  @Post('demandes')
  @RequirePermission(
    'exploitation.fournitures.request',
    'exploitation.fournitures.manage',
  )
  createDemande(
    @Request() req: { user: any },
    @Body() dto: CreateDemandeFournitureDto,
  ) {
    return this.service.createDemande(req.user, dto);
  }

  @Patch('demandes/:id/soumettre')
  @RequirePermission(
    'exploitation.fournitures.request',
    'exploitation.fournitures.manage',
  )
  soumettre(@Request() req: { user: any }, @Param('id') id: string) {
    return this.service.soumettreDemande(req.user, +id);
  }

  @Patch('demandes/:id/valider')
  @RequirePermission('exploitation.fournitures.manage')
  valider(
    @Request() req: { user: any },
    @Param('id') id: string,
    @Body() dto: ValiderDemandeFournitureDto,
  ) {
    return this.service.validerDemande(req.user, +id, dto);
  }

  @Patch('demandes/:id/refuser')
  @RequirePermission('exploitation.fournitures.manage')
  refuser(
    @Request() req: { user: any },
    @Param('id') id: string,
    @Body() dto: RefuserDemandeFournitureDto,
  ) {
    return this.service.refuserDemande(req.user, +id, dto.motif);
  }

  @Patch('demandes/:id/livrer')
  @RequirePermission('exploitation.fournitures.manage')
  livrer(@Request() req: { user: any }, @Param('id') id: string) {
    return this.service.livrerDemande(req.user, +id);
  }
}
