import { Type } from 'class-transformer';
import {
  ArrayMinSize,
  IsArray,
  IsInt,
  Min,
  ValidateNested,
} from 'class-validator';

export class LigneValidationDemandeDto {
  @IsInt()
  @Min(1)
  id_ligne: number;

  @IsInt()
  @Min(0)
  quantite_validee: number;
}

export class ValiderDemandeFournitureDto {
  @IsArray()
  @ArrayMinSize(1)
  @ValidateNested({ each: true })
  @Type(() => LigneValidationDemandeDto)
  lignes: LigneValidationDemandeDto[];
}
