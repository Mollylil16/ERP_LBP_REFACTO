import {
  Controller,
  Get,
  Post,
  Put,
  Delete,
  Body,
  Param,
  Query,
  UseGuards,
  ParseIntPipe,
} from '@nestjs/common';
import { ProduitsCatalogueService } from './produits-catalogue.service';
import { CreateProduitCatalogueDto } from './dto/create-produit-catalogue.dto';
import { UpdateProduitCatalogueDto } from './dto/update-produit-catalogue.dto';
import { CategoriesProduit } from './entities/produit-catalogue.entity';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';

@Controller('produits-catalogue')
@UseGuards(JwtAuthGuard, PermissionsGuard)
export class ProduitsCatalogueController {
  constructor(private readonly produitsService: ProduitsCatalogueService) {}

  @Get()
  @RequirePermission('factures.read')
  findAll() {
    return this.produitsService.findAll();
  }

  @Get('categorie/:categorie')
  @RequirePermission('factures.read')
  findByCategorie(@Param('categorie') categorie: CategoriesProduit) {
    return this.produitsService.findByCategorie(categorie);
  }

  @Get('search')
  @RequirePermission('factures.read')
  search(@Query('q') query: string) {
    return this.produitsService.search(query);
  }

  @Get('historique')
  @RequirePermission('factures.read')
  getHistorique() {
    return this.produitsService.getHistoriqueUtilisation();
  }

  @Get(':id')
  @RequirePermission('factures.read')
  findOne(@Param('id', ParseIntPipe) id: number) {
    return this.produitsService.findOne(id);
  }

  @Post()
  @RequirePermission('factures.create')
  create(@Body() createDto: CreateProduitCatalogueDto) {
    return this.produitsService.create(createDto);
  }

  @Put(':id')
  @RequirePermission('factures.create')
  update(
    @Param('id', ParseIntPipe) id: number,
    @Body() updateDto: UpdateProduitCatalogueDto,
  ) {
    return this.produitsService.update(id, updateDto);
  }

  @Delete(':id')
  @RequirePermission('factures.delete')
  remove(@Param('id', ParseIntPipe) id: number) {
    return this.produitsService.remove(id);
  }
}
