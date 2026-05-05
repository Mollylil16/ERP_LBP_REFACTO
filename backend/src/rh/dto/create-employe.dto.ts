import { IsDateString, IsEmail, IsEnum, IsInt, IsOptional, IsString, MaxLength, Min } from 'class-validator';
import { Sexe, SituationFamiliale, StatutEmploye, TypeContrat } from '../entities/rh-employe.entity';

export class CreateEmployeDto {
  @IsString() @MaxLength(100) nom: string;
  @IsString() @MaxLength(200) prenoms: string;
  @IsOptional() @IsDateString() date_naissance?: string;
  @IsOptional() @IsString() lieu_naissance?: string;
  @IsOptional() @IsString() nationalite?: string;
  @IsOptional() @IsEnum(Sexe) sexe?: Sexe;
  @IsOptional() @IsEnum(SituationFamiliale) situation_familiale?: SituationFamiliale;
  @IsOptional() @IsInt() @Min(0) nb_enfants?: number;
  @IsOptional() @IsString() numero_cni?: string;
  @IsOptional() @IsString() numero_cnps?: string;
  @IsOptional() @IsString() adresse?: string;
  @IsOptional() @IsString() telephone?: string;
  @IsOptional() @IsEmail() email_pro?: string;
  @IsOptional() @IsEmail() email_perso?: string;
  @IsDateString() date_embauche: string;
  @IsOptional() @IsString() intitule_poste?: string;
  @IsOptional() @IsString() categorie?: string;
  @IsOptional() @IsString() grade?: string;
  @IsOptional() @IsString() departement?: string;
  @IsOptional() @IsString() service?: string;
  @IsOptional() @IsEnum(TypeContrat) type_contrat_actuel?: TypeContrat;
  @IsOptional() @IsEnum(StatutEmploye) statut?: StatutEmploye;
  @IsOptional() @IsInt() id_agence?: number;
  @IsOptional() @IsInt() id_responsable?: number;
  @IsOptional() @IsInt() id_user?: number;
}
