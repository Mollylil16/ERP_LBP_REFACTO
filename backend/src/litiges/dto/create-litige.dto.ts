import {
  IsEnum,
  IsString,
  IsOptional,
  IsNumber,
  IsEmail,
  MaxLength,
  MinLength,
} from 'class-validator';
import { LitigeType, LitigePriorite } from '../entities/litige.entity';

export class CreateLitigeDto {
  @IsEnum(LitigeType)
  type: LitigeType;

  @IsString()
  @MinLength(5)
  @MaxLength(200)
  objet: string;

  @IsString()
  @MinLength(10)
  description: string;

  @IsOptional()
  @IsEnum(LitigePriorite)
  priorite?: LitigePriorite = LitigePriorite.NORMALE;

  @IsOptional()
  @IsNumber()
  id_colis?: number;

  @IsOptional()
  @IsNumber()
  id_facture?: number;

  @IsNumber()
  id_client: number;

  @IsNumber()
  id_agence: number;

  // Informations de contact (optionnelles si différentes du client)
  @IsOptional()
  @IsString()
  contact_nom?: string;

  @IsOptional()
  @IsEmail()
  contact_email?: string;

  @IsOptional()
  @IsString()
  contact_telephone?: string;

  // Assignation optionnelle
  @IsOptional()
  @IsNumber()
  id_assigne?: number;

  @IsOptional()
  metadata?: any;
}
