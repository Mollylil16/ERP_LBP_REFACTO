import { IsDateString, IsInt, IsOptional, IsString, Min } from 'class-validator';

export class CreateCongeRequestDto {
  @IsInt() id_employe: number;
  @IsInt() id_conge_type: number;
  @IsDateString() date_debut: string;
  @IsDateString() date_fin: string;
  @IsInt() @Min(1) nb_jours: number;
  @IsOptional() @IsString() motif?: string;
}

export class ValiderCongeDto {
  @IsOptional() @IsString() commentaire?: string;
}
