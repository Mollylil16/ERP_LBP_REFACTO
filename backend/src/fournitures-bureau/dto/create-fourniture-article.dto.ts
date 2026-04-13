import {
  IsBoolean,
  IsInt,
  IsNotEmpty,
  IsOptional,
  IsString,
  MaxLength,
  Min,
} from 'class-validator';

export class CreateFournitureArticleDto {
  @IsString()
  @IsNotEmpty()
  @MaxLength(50)
  code: string;

  @IsString()
  @IsNotEmpty()
  @MaxLength(200)
  nom: string;

  @IsOptional()
  @IsString()
  @MaxLength(30)
  unite?: string;

  @IsOptional()
  @IsInt()
  @Min(0)
  quantite_stock?: number;

  @IsOptional()
  @IsInt()
  @Min(0)
  seuil_alerte?: number;

  @IsOptional()
  @IsBoolean()
  actif?: boolean;
}
