import { DataSource } from 'typeorm';
import { Role } from '../../roles/entities/role.entity';
import { Permission } from '../../permissions/entities/permission.entity';
import { RolePermission } from '../../permissions/entities/role-permission.entity';

// Matrice des permissions par rôle basée sur l'analyse
const rolePermissionsMatrix = {
  ADMIN: '*',
  DIRECTEUR: '*', // Toutes les permissions
  MANAGER: [
    // EXPLOITATION
    'exploitation.groupage_colis.create',
    'exploitation.groupage_colis.read',
    'exploitation.groupage_colis.update',
    'exploitation.groupage_colis.delete',
    'exploitation.autres_envois.create',
    'exploitation.autres_envois.read',
    'exploitation.autres_envois.update',
    'exploitation.autres_envois.delete',
    'exploitation.rapports_envois.read',
    'exploitation.livraison.create',
    'exploitation.livraison.read',
    'exploitation.livraison.update',
    'exploitation.livraison.delete',
    // FACTURATION
    'facturation.cotation.create',
    'facturation.cotation.read',
    'facturation.cotation.update',
    'facturation.cotation.delete',
    'facturation.facturer.create',
    'facturation.facturer.read',
    'facturation.facturer.update',
    'facturation.facturer.delete',
    'facturation.parametres_facture.create',
    'facturation.parametres_facture.read',
    'facturation.parametres_facture.update',
    // OPÉRATION CAISSE
    'operation_caisse.gestion_caisses.create',
    'operation_caisse.gestion_caisses.read',
    'operation_caisse.gestion_caisses.update',
    'operation_caisse.gestion_caisses.delete',
    'operation_caisse.mouvements_caisses.create',
    'operation_caisse.mouvements_caisses.read',
    'operation_caisse.mouvements_caisses.update',
    'operation_caisse.mouvements_caisses.delete',
    'operation_caisse.journal.read',
    'operation_caisse.reglement_client.create',
    'operation_caisse.reglement_client.read',
    'operation_caisse.reglement_client.update',
    'operation_caisse.reglement_client.delete',
    // GESTION FONDS
    'gestion_fonds.demandes_fonds.create',
    'gestion_fonds.demandes_fonds.read',
    'gestion_fonds.demandes_fonds.update',
    'gestion_fonds.demandes_fonds.delete',
    'gestion_fonds.paiement_demandes.create',
    'gestion_fonds.paiement_demandes.read',
    'gestion_fonds.paiement_demandes.update',
    'gestion_fonds.paiement_demandes.delete',
    'gestion_fonds.recap_demandes.read',
    // RAPPORTS
    'rapports.suivi_envois.read',
    'rapports.statistiques.read',
    'rapports.ca_detaille.read',
    'rapports.business_analyst.read',
    // STRUCTURES
    'structures.clients.create',
    'structures.clients.read',
    'structures.clients.update',
    'structures.clients.delete',
    'structures.zones_livraison.create',
    'structures.zones_livraison.read',
    'structures.zones_livraison.update',
    'structures.zones_livraison.delete',
    'structures.parametres_application.read',
    'structures.agences.create',
    'structures.agences.read',
    'structures.agences.update',
    'litiges.view',
    'litiges.create',
    'litiges.manage',
    'litiges.admin',
    'callcenter.inbox',
    // VALIDATIONS / ANNULATIONS (workflow)
    'colis.groupage.validate',
    'colis.autres-envois.validate',
    'factures.validate',
    'factures.cancel',
    'paiements.validate',
    'paiements.cancel',
  ],
  SUPERVISEUR_REGIONAL: [
    'exploitation.groupage_colis.read',
    'exploitation.autres_envois.read',
    'exploitation.rapports_envois.read',
    'exploitation.livraison.read',
    'gestion_fonds.demandes_fonds.read',
    'gestion_fonds.recap_demandes.read',
    'rapports.suivi_envois.read',
    'rapports.statistiques.read',
    'rapports.ca_detaille.read',
    'structures.clients.read',
    'structures.zones_livraison.read',
    'structures.agences.read',
    'litiges.view',
    'litiges.manage',
    'callcenter.inbox',
  ],
  AGENT_EXPLOITATION: [
    'exploitation.groupage_colis.create',
    'exploitation.groupage_colis.read',
    'exploitation.groupage_colis.update',
    'exploitation.groupage_colis.delete',
    'exploitation.autres_envois.create',
    'exploitation.autres_envois.read',
    'exploitation.autres_envois.update',
    'exploitation.autres_envois.delete',
    'exploitation.rapports_envois.read',
    'exploitation.livraison.create',
    'exploitation.livraison.read',
    'exploitation.livraison.update',
    'exploitation.livraison.delete',
    'facturation.cotation.create',
    'facturation.cotation.read',
    'facturation.cotation.update',
    'facturation.cotation.delete',
    'facturation.facturer.create',
    'facturation.facturer.read',
    'facturation.facturer.update',
    'gestion_fonds.demandes_fonds.create',
    'gestion_fonds.demandes_fonds.read',
    'structures.clients.read',
    'structures.zones_livraison.read',
    'structures.agences.read',
    'litiges.view',
    'litiges.create',
    'litiges.manage',
    'callcenter.inbox',
  ],
  AGENT_GROUPAGE: [
    'exploitation.groupage_colis.create',
    'exploitation.groupage_colis.read',
    'exploitation.groupage_colis.update',
    'exploitation.groupage_colis.delete',
    'exploitation.autres_envois.create',
    'exploitation.autres_envois.read',
    'exploitation.autres_envois.update',
    'exploitation.autres_envois.delete',
    'exploitation.rapports_envois.read',
    'operation_caisse.gestion_caisses.read',
    'gestion_fonds.demandes_fonds.create',
    'gestion_fonds.demandes_fonds.read',
    'gestion_fonds.recap_demandes.read',
    'structures.clients.read',
    'structures.agences.read',
    'litiges.view',
    'litiges.create',
    'callcenter.inbox',
  ],
  CAISSIER: [
    'operation_caisse.gestion_caisses.create',
    'operation_caisse.gestion_caisses.read',
    'operation_caisse.gestion_caisses.update',
    'operation_caisse.gestion_caisses.delete',
    'operation_caisse.mouvements_caisses.create',
    'operation_caisse.mouvements_caisses.read',
    'operation_caisse.mouvements_caisses.update',
    'operation_caisse.mouvements_caisses.delete',
    'operation_caisse.journal.read',
    'operation_caisse.reglement_client.create',
    'operation_caisse.reglement_client.read',
    'operation_caisse.reglement_client.update',
    'operation_caisse.reglement_client.delete',
    'gestion_fonds.demandes_fonds.create',
    'gestion_fonds.demandes_fonds.read',
    'gestion_fonds.paiement_demandes.create',
    'gestion_fonds.paiement_demandes.read',
    'gestion_fonds.paiement_demandes.update',
    'gestion_fonds.paiement_demandes.delete',
    'structures.agences.read',
    'litiges.view',
    'callcenter.inbox',
  ],
  CAISSIER_GROUPAGE: [
    'exploitation.groupage_colis.read',
    'operation_caisse.gestion_caisses.create',
    'operation_caisse.gestion_caisses.read',
    'operation_caisse.gestion_caisses.update',
    'operation_caisse.gestion_caisses.delete',
    'operation_caisse.mouvements_caisses.create',
    'operation_caisse.mouvements_caisses.read',
    'operation_caisse.mouvements_caisses.update',
    'operation_caisse.mouvements_caisses.delete',
    'operation_caisse.journal.read',
    'operation_caisse.reglement_client.create',
    'operation_caisse.reglement_client.read',
    'operation_caisse.reglement_client.update',
    'operation_caisse.reglement_client.delete',
    'gestion_fonds.demandes_fonds.create',
    'gestion_fonds.demandes_fonds.read',
    'gestion_fonds.recap_demandes.read',
    'structures.agences.read',
    'litiges.view',
    'callcenter.inbox',
  ],
  AGENT_SUIVI: [
    'exploitation.groupage_colis.read',
    'exploitation.autres_envois.read',
    'exploitation.rapports_envois.read',
    'exploitation.livraison.read',
    'operation_caisse.gestion_caisses.read',
    'gestion_fonds.demandes_fonds.read',
    'gestion_fonds.recap_demandes.read',
    'rapports.suivi_envois.read',
    'rapports.business_analyst.read',
    'structures.agences.read',
    'litiges.view',
    'litiges.create',
    'callcenter.inbox',
  ],
};

export async function seedRolePermissions(
  dataSource: DataSource,
): Promise<void> {
  const roleRepository = dataSource.getRepository(Role);
  const permissionRepository = dataSource.getRepository(Permission);
  const rolePermissionRepository = dataSource.getRepository(RolePermission);

  for (const [roleCode, permissionCodes] of Object.entries(
    rolePermissionsMatrix,
  )) {
    const role = await roleRepository.findOne({ where: { code: roleCode } });

    if (!role) {
      console.log(`⚠️  Rôle ${roleCode} non trouvé`);
      continue;
    }

    // Supprimer les associations existantes
    await rolePermissionRepository.delete({ role: { id: role.id } });

    if (permissionCodes === '*') {
      // Directeur a toutes les permissions
      const allPermissions = await permissionRepository.find();

      for (const permission of allPermissions) {
        const rolePermission = rolePermissionRepository.create({
          role,
          permission,
        });
        await rolePermissionRepository.save(rolePermission);
      }

      console.log(`✅ ${roleCode}: Toutes les permissions assignées`);
    } else {
      // Assigner les permissions spécifiques
      for (const permCode of permissionCodes as string[]) {
        const permission = await permissionRepository.findOne({
          where: { code: permCode },
        });

        if (!permission) {
          console.log(`⚠️  Permission ${permCode} non trouvée`);
          continue;
        }

        const rolePermission = rolePermissionRepository.create({
          role,
          permission,
        });
        await rolePermissionRepository.save(rolePermission);
      }

      console.log(
        `✅ ${roleCode}: ${(permissionCodes as string[]).length} permissions assignées`,
      );
    }
  }
}
