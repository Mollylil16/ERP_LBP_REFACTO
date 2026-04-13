import { IsNotEmpty, IsString } from 'class-validator';

export class RefuserDemandeFournitureDto {
  @IsString()
  @IsNotEmpty()
  motif: string;
}
