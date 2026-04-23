import { IsInt, IsOptional, IsString, MinLength } from 'class-validator';
import { Type } from 'class-transformer';

export class DemanderJustificationDto {
  @IsInt()
  @Type(() => Number)
  agenceId: number;

  @IsString()
  @MinLength(3)
  motif: string;

  @IsOptional()
  @Type(() => Number)
  @IsInt()
  agentId?: number;

  @IsOptional()
  @Type(() => Number)
  @IsInt()
  chefAgenceId?: number;

  @IsOptional()
  @IsString()
  operationId?: string;
}
