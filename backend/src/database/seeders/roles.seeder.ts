import { DataSource } from 'typeorm';
import { Role } from '../../roles/entities/role.entity';

export async function seedRoles(dataSource: DataSource): Promise<void> {
  const roleRepository = dataSource.getRepository(Role);

  const roles = [
    {
      code: 'DIRECTEUR',
      nom: 'Directeur Général',
      description: 'Accès complet à tous les modules et fonctionnalités',
      niveau_hierarchique: 1,
      est_actif: true,
    },
    {
      code: 'MANAGER',
      nom: 'Manager/Superviseur',
      description: 'Gestion opérationnelle complète, validation des opérations',
      niveau_hierarchique: 2,
      est_actif: true,
    },
    {
      code: 'SUPERVISEUR_REGIONAL',
      nom: 'Superviseur Régional',
      description: 'Consultation multi-agences, rapports consolidés',
      niveau_hierarchique: 3,
      est_actif: true,
    },
    {
      code: 'AGENT_EXPLOITATION',
      nom: 'Agent Exploitation',
      description: 'Création et suivi des envois de colis',
      niveau_hierarchique: 4,
      est_actif: true,
    },
    {
      code: 'AGENT_GROUPAGE',
      nom: 'Agent Groupage',
      description: 'Gestion spécialisée du groupage de colis',
      niveau_hierarchique: 5,
      est_actif: true,
    },
    {
      code: 'CAISSIER',
      nom: 'Caissier',
      description: 'Gestion complète de la caisse',
      niveau_hierarchique: 6,
      est_actif: true,
    },
    {
      code: 'CAISSIER_GROUPAGE',
      nom: 'Caissier Groupage',
      description: 'Encaissement spécifique au groupage',
      niveau_hierarchique: 7,
      est_actif: true,
    },
    {
      code: 'AGENT_SUIVI',
      nom: 'Agent Suivi',
      description: 'Consultation et rapports uniquement',
      niveau_hierarchique: 8,
      est_actif: true,
    },
  ];

  for (const roleData of roles) {
    const existingRole = await roleRepository.findOne({
      where: { code: roleData.code },
    });

    if (!existingRole) {
      const role = roleRepository.create(roleData);
      await roleRepository.save(role);
      console.log(`✅ Rôle créé: ${roleData.nom}`);
    } else {
      console.log(`ℹ️  Rôle existe déjà: ${roleData.nom}`);
    }
  }
}
