import {
  IsString,
  IsEnum,
  IsOptional,
  IsNumber,
  IsBoolean,
  Min,
  Max,
} from 'class-validator';
import {
  CategoriesProduit,
  NaturePrix,
} from '../entities/produit-catalogue.entity';

export class CreateProduitCatalogueDto {
  @IsString()
  nom: string;

  @IsEnum(CategoriesProduit)
  categorie: CategoriesProduit;

  @IsOptional()
  @IsEnum(NaturePrix)
  nature?: NaturePrix;

  @IsOptional()
  @IsNumber()
  @Min(0)
  prix_unitaire?: number;

  @IsOptional()
  @IsNumber()
  @Min(0)
  prix_forfaitaire?: number;

  @IsOptional()
  @IsNumber()
  @Min(0)
  poids_min?: number;

  @IsOptional()
  @IsNumber()
  @Min(0)
  poids_max?: number;

  @IsOptional()
  @IsString()
  description?: string;

  @IsOptional()
  @IsString()
  unite?: string;

  @IsOptional()
  @IsBoolean()
  actif?: boolean;
}
