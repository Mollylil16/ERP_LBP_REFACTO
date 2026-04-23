import { IsInt, IsOptional, IsString, IsDateString, MinLength } from 'class-validator';
import { Type } from 'class-transformer';

export class SoumettreRapportDto {
  @IsString()
  @MinLength(2)
  type: string;

  @IsString()
  periode: string;

  @IsOptional()
  @Type(() => Number)
  @IsInt()
  agenceId?: number;

  @IsDateString()
  dateDebut: string;

  @IsDateString()
  dateFin: string;

  @IsOptional()
  @IsString()
  commentaire?: string;
}
