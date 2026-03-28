import {
  IsEnum,
  IsString,
  IsOptional,
  IsBoolean,
  IsArray,
  ValidateNested,
} from 'class-validator';
import { Type } from 'class-transformer';
import { MessageType } from '../entities/litige-message.entity';

export class PieceJointeDto {
  @IsString()
  nom: string;

  @IsString()
  url: string;

  @IsString()
  type: string;

  @IsString()
  taille: number;
}

export class CreateMessageDto {
  @IsString()
  contenu: string;

  @IsOptional()
  @IsEnum(MessageType)
  type?: MessageType = MessageType.MESSAGE;

  @IsOptional()
  @IsBoolean()
  interne?: boolean = false;

  @IsOptional()
  @IsArray()
  @ValidateNested({ each: true })
  @Type(() => PieceJointeDto)
  pieces_jointes?: PieceJointeDto[];

  @IsOptional()
  metadata?: any;
}
