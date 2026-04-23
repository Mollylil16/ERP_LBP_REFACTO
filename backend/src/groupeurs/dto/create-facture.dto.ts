import {
  IsArray,
  IsIn,
  IsNumber,
  IsOptional,
  IsString,
  MaxLength,
} from 'class-validator';

export class CreateFactureDto {
  @IsString()
  @MaxLength(30)
  numero_facture: string;

  @IsOptional()
  expedition_id?: string;

  @IsString()
  @MaxLength(150)
  client_nom: string;

  @IsOptional()
  @IsString()
  @MaxLength(120)
  client_contact?: string;

  @IsOptional()
  @IsString()
  date_emission?: string;

  @IsOptional()
  @IsString()
  date_echeance?: string;

  @IsArray()
  lignes: unknown[];

  @IsNumber()
  sous_total: number;

  @IsOptional()
  @IsNumber()
  tva_pct?: number;

  @IsOptional()
  @IsNumber()
  tva_montant?: number;

  @IsNumber()
  total_ttc: number;

  @IsOptional()
  @IsString()
  @MaxLength(5)
  devise?: string;

  @IsOptional()
  @IsIn(['en_attente', 'partiel', 'paye', 'en_retard', 'annule'])
  statut_paiement?: string;

  @IsOptional()
  @IsNumber()
  montant_recu?: number;

  @IsOptional()
  @IsString()
  date_paiement?: string;

  @IsOptional()
  @IsString()
  @MaxLength(30)
  mode_paiement?: string;

  @IsOptional()
  @IsString()
  notes?: string;
}
