import { Type } from 'class-transformer';
import {
  ArrayMinSize,
  IsArray,
  IsInt,
  IsOptional,
  IsString,
  Min,
  ValidateNested,
} from 'class-validator';

export class LigneDemandeFournitureDto {
  @IsInt()
  @Min(1)
  id_article: number;

  @IsInt()
  @Min(1)
  quantite: number;
}

export class CreateDemandeFournitureDto {
  @IsInt()
  @Min(1)
  id_agence: number;

  @IsOptional()
  @IsString()
  observations?: string;

  @IsArray()
  @ArrayMinSize(1)
  @ValidateNested({ each: true })
  @Type(() => LigneDemandeFournitureDto)
  lignes: LigneDemandeFournitureDto[];
}
