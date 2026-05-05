import { IsDateString, IsEnum, IsInt, IsNumber, IsOptional, IsString } from 'class-validator';
import { TypeContrat } from '../entities/rh-employe.entity';

export class CreateContratDto {
  @IsInt() id_employe: number;
  @IsEnum(TypeContrat) type_contrat: TypeContrat;
  @IsDateString() date_debut: string;
  @IsOptional() @IsDateString() date_fin?: string;
  @IsOptional() @IsDateString() periode_essai_debut?: string;
  @IsOptional() @IsDateString() periode_essai_fin?: string;
  @IsOptional() @IsString() intitule_poste?: string;
  @IsOptional() @IsNumber() salaire_base?: number;
  @IsOptional() @IsString() notes?: string;
}
