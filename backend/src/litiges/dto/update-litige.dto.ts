import { PartialType } from '@nestjs/mapped-types';
import {
  IsEnum,
  IsString,
  IsOptional,
  IsNumber,
  IsDecimal,
} from 'class-validator';
import { CreateLitigeDto } from './create-litige.dto';
import { LitigeStatut, LitigePriorite } from '../entities/litige.entity';

export class UpdateLitigeDto extends PartialType(CreateLitigeDto) {
  @IsOptional()
  @IsEnum(LitigeStatut)
  statut?: LitigeStatut;

  @IsOptional()
  @IsEnum(LitigePriorite)
  priorite?: LitigePriorite;

  @IsOptional()
  @IsNumber()
  id_assigne?: number;

  @IsOptional()
  @IsString()
  resolution?: string;

  @IsOptional()
  @IsDecimal()
  montant_compensation?: number;

  @IsOptional()
  escalade?: boolean;
}
