import {
  IsDateString,
  IsInt,
  IsNumber,
  IsOptional,
  IsString,
  MaxLength,
  Min,
} from 'class-validator';

export class CreateFacturePrestataireDto {
  @IsInt()
  @Min(1)
  id_agence: number;

  @IsOptional()
  @IsString()
  @MaxLength(100)
  pays?: string;

  @IsInt()
  @Min(1)
  prestataire_id: number;

  @IsDateString()
  date_reception: string;

  @IsOptional()
  @IsString()
  @MaxLength(100)
  numero_lta?: string;

  @IsOptional()
  @IsString()
  @MaxLength(100)
  numero_envoi?: string;

  @IsString()
  @MaxLength(120)
  numero_facture: string;

  @IsNumber()
  @Min(0)
  montant_total: number;

  @IsOptional()
  @IsString()
  @MaxLength(10)
  devise?: string;

  /** Si date_echeance n'est pas fournie, on calcule date_reception + delai_reglement_jours */
  @IsOptional()
  @IsInt()
  @Min(0)
  delai_reglement_jours?: number;

  @IsOptional()
  @IsDateString()
  date_echeance?: string;

  @IsOptional()
  @IsString()
  note?: string;
}

