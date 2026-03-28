import {
  Injectable,
  NotFoundException,
  ConflictException,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import {
  Permission,
  PermissionModule,
  PermissionAction,
} from './entities/permission.entity';
import { RolePermission } from './entities/role-permission.entity';
import { ActionSpeciale } from './entities/action-speciale.entity';
import { CreatePermissionDto } from './dto/create-permission.dto';

@Injectable()
export class PermissionsService {
  constructor(
    @InjectRepository(Permission)
    private permissionsRepository: Repository<Permission>,
    @InjectRepository(RolePermission)
    private rolePermissionsRepository: Repository<RolePermission>,
    @InjectRepository(ActionSpeciale)
    private actionsSpecialesRepository: Repository<ActionSpeciale>,
  ) {}

  async create(createPermissionDto: CreatePermissionDto): Promise<Permission> {
    const code = `${createPermissionDto.module.toLowerCase()}.${createPermissionDto.fonctionnalite}.${createPermissionDto.action.toLowerCase()}`;

    const existingPermission = await this.permissionsRepository.findOne({
      where: { code },
    });

    if (existingPermission) {
      throw new ConflictException(
        `La permission avec le code ${code} existe déjà`,
      );
    }

    const permission = this.permissionsRepository.create({
      ...createPermissionDto,
      code,
    });

    return await this.permissionsRepository.save(permission);
  }

  async findAll(): Promise<Permission[]> {
    return await this.permissionsRepository.find({
      order: { module: 'ASC', fonctionnalite: 'ASC', action: 'ASC' },
    });
  }

  async findByModule(module: PermissionModule): Promise<Permission[]> {
    return await this.permissionsRepository.find({
      where: { module },
      order: { fonctionnalite: 'ASC', action: 'ASC' },
    });
  }

  async findOne(id: number): Promise<Permission> {
    const permission = await this.permissionsRepository.findOne({
      where: { id },
    });

    if (!permission) {
      throw new NotFoundException(`Permission avec l'ID ${id} non trouvée`);
    }

    return permission;
  }

  async remove(id: number): Promise<void> {
    const permission = await this.findOne(id);
    await this.permissionsRepository.remove(permission);
  }

  async checkUserPermission(
    userId: number,
    permissionCode: string,
  ): Promise<boolean> {
    // Cette méthode sera implémentée pour vérifier si un utilisateur a une permission
    // Pour l'instant, retourne true (à implémenter avec la logique complète)
    return true;
  }

  async getAllActionsSpeciales(): Promise<ActionSpeciale[]> {
    return await this.actionsSpecialesRepository.find({
      order: { type: 'ASC', code: 'ASC' },
    });
  }
}
