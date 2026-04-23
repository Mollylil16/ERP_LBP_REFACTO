import { IsString, MinLength, MaxLength } from 'class-validator';

export class AnnotationDto {
  @IsString()
  @MinLength(1)
  @MaxLength(64)
  cibleId: string;

  @IsString()
  @MinLength(1)
  @MaxLength(40)
  cible: string;

  @IsString()
  @MinLength(3)
  contenu: string;
}
