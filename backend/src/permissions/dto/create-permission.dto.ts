import {
  IsEnum,
  IsString,
  IsNotEmpty,
  IsOptional,
  IsArray,
  IsInt,
} from 'class-validator';
import {
  PermissionModule,
  PermissionAction,
} from '../entities/permission.entity';

export class CreatePermissionDto {
  @IsEnum(PermissionModule)
  @IsNotEmpty()
  module: PermissionModule;

  @IsString()
  @IsNotEmpty()
  fonctionnalite: string;

  @IsEnum(PermissionAction)
  @IsNotEmpty()
  action: PermissionAction;

  @IsString()
  @IsOptional()
  description?: string;
}

export class AssignPermissionsToRoleDto {
  @IsInt()
  @IsNotEmpty()
  roleId: number;

  @IsArray()
  @IsInt({ each: true })
  permissionIds: number[];
}
