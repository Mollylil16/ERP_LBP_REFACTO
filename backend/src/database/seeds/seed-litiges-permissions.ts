import { DataSource } from 'typeorm';
import { Permission } from '../../permissions/entities/permission.entity';
import { Role } from '../../roles/entities/role.entity';
import { RolePermission } from '../../permissions/entities/role-permission.entity';

export async function seedLitigesPermissions(dataSource: DataSource) {
  const permissionRepository = dataSource.getRepository(Permission);
  const roleRepository = dataSource.getRepository(Role);
  const rolePermissionRepository = dataSource.getRepository(RolePermission);

  console.log('🔐 Ajout des permissions Litiges...');

  // 1. Créer les permissions Litiges
  const litigesPermissions = [
    {
      code: 'litiges.view',
      name: 'Voir les litiges',
      module: 'litiges',
      action: 'view',
      description: 'Permet de consulter la liste et les détails des litiges',
    },
    {
      code: 'litiges.create',
      name: 'Créer un litige',
      module: 'litiges',
      action: 'create',
      description: 'Permet de créer un nouveau litige et ajouter des messages',
    },
    {
      code: 'litiges.manage',
      name: 'Gérer les litiges',
      module: 'litiges',
      action: 'manage',
      description: 'Permet de mettre à jour le statut et assigner des litiges',
    },
    {
      code: 'litiges.admin',
      name: 'Administration des litiges',
      module: 'litiges',
      action: 'admin',
      description:
        'Accès complet aux litiges (supprimer, statistiques avancées)',
    },
  ];

  const createdPermissions: Permission[] = [];
  for (const permData of litigesPermissions) {
    let permission = await permissionRepository.findOne({
      where: { code: permData.code },
    });

    if (!permission) {
      permission = permissionRepository.create(permData);
      permission = await permissionRepository.save(permission);
      console.log(`✅ Permission créée: ${permission.code}`);
    } else {
      console.log(`ℹ️  Permission existe déjà: ${permission.code}`);
    }

    createdPermissions.push(permission);
  }

  // 2. Assigner les permissions aux rôles
  const rolePermissionMappings = [
    // ADMIN - Toutes les permissions
    {
      roleCode: 'ADMIN',
      permissions: [
        'litiges.view',
        'litiges.create',
        'litiges.manage',
        'litiges.admin',
      ],
    },

    // DIRECTEUR - Toutes les permissions
    {
      roleCode: 'DIRECTEUR',
      permissions: [
        'litiges.view',
        'litiges.create',
        'litiges.manage',
        'litiges.admin',
      ],
    },

    // MANAGER - Gestion complète sauf suppression
    {
      roleCode: 'MANAGER',
      permissions: ['litiges.view', 'litiges.create', 'litiges.manage'],
    },

    // SUPERVISEUR_REGIONAL - Gestion complète sauf suppression
    {
      roleCode: 'SUPERVISEUR_REGIONAL',
      permissions: ['litiges.view', 'litiges.create', 'litiges.manage'],
    },

    // AGENT_EXPLOITATION - Voir et créer
    {
      roleCode: 'AGENT_EXPLOITATION',
      permissions: ['litiges.view', 'litiges.create'],
    },

    // AGENT_GROUPAGE - Voir et créer
    {
      roleCode: 'AGENT_GROUPAGE',
      permissions: ['litiges.view', 'litiges.create'],
    },

    // CAISSIER - Voir et créer (pour litiges de facturation)
    { roleCode: 'CAISSIER', permissions: ['litiges.view', 'litiges.create'] },

    // AGENT_SUIVI - Voir et créer (pour litiges de livraison)
    {
      roleCode: 'AGENT_SUIVI',
      permissions: ['litiges.view', 'litiges.create'],
    },
  ];

  for (const mapping of rolePermissionMappings) {
    const role = await roleRepository.findOne({
      where: { code: mapping.roleCode },
    });

    if (!role) {
      console.log(`⚠️  Rôle non trouvé: ${mapping.roleCode}`);
      continue;
    }

    for (const permissionCode of mapping.permissions) {
      const permission = createdPermissions.find(
        (p) => p.code === permissionCode,
      );

      if (permission) {
        // Vérifier si la relation existe déjà
        const existing = await rolePermissionRepository.findOne({
          where: {
            role: { id: role.id },
            permission: { id: permission.id },
          },
        });

        if (!existing) {
          const rolePermission = rolePermissionRepository.create({
            role,
            permission,
          });
          await rolePermissionRepository.save(rolePermission);
          console.log(`✅ Assigné: ${role.code} -> ${permission.code}`);
        } else {
          console.log(
            `ℹ️  Relation existe déjà: ${role.code} -> ${permission.code}`,
          );
        }
      }
    }
  }

  console.log('🎉 Permissions Litiges ajoutées avec succès !');
}
