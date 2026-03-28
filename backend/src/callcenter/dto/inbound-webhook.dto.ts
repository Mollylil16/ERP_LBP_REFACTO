import {
  IsIn,
  IsNotEmpty,
  IsOptional,
  IsString,
  MaxLength,
} from 'class-validator';

export class InboundWebhookDto {
  @IsString()
  @IsNotEmpty()
  @IsIn(['sms', 'whatsapp'])
  channel: 'sms' | 'whatsapp';

  @IsString()
  @IsNotEmpty()
  @MaxLength(40)
  from: string;

  @IsString()
  @IsNotEmpty()
  @MaxLength(40)
  to: string;

  @IsString()
  @IsNotEmpty()
  message: string;

  @IsOptional()
  @IsString()
  provider?: string;

  @IsOptional()
  @IsString()
  provider_message_id?: string;
}
