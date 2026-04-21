import { DataSource } from 'typeorm';
import { Role } from '../../roles/entities/role.entity';
import { Permission } from '../../permissions/entities/permission.entity';
import { RolePermission } from '../../permissions/entities/role-permission.entity';

// Matrice des permissions par rôle basée sur l'analyse
const rolePermissionsMatrix = {
  ADMIN: '*',
  DIRECTEUR: '*', // Toutes les permissions
  /**
   * Assistant DG : périmètre DG (lecture & opérations), sans administration sensible.
   * Choix de restriction :
   * - pas de gestion utilisateurs / permissions
   * - pas de paramètres société (structures.parametres_application.*) ni gestion agences (create/update/delete)
   * - pas de suppressions critiques (delete sur colis/factures/paiements/caisse/fonds)
   * - pas d’annulation/validation workflow (factures/paiements/colis validation)
   */
  ASSISTANT_DG: [
    // Objectif: visibilité "complète" sur le système, mais STRICTEMENT en lecture seule.
    // Colis / exploitation (lecture)
    'exploitation.groupage_colis.read',
    'exploitation.autres_envois.read',
    'exploitation.rapports_envois.read',
    'exploitation.livraison.read',

    // Facturation (lecture)
    'facturation.cotation.read',
    'facturation.facturer.read',

    // Caisse (lecture)
    'operation_caisse.gestion_caisses.read',
    'operation_caisse.mouvements_caisses.read',
    'operation_caisse.journal.read',
    'operation_caisse.reglement_client.read',

    // Gestion fonds (lecture)
    'gestion_fonds.demandes_fonds.read',
    'gestion_fonds.recap_demandes.read',

    // Rapports (lecture)
    'rapports.suivi_envois.read',
    'rapports.statistiques.read',
    'rapports.ca_detaille.read',
    'rapports.business_analyst.read',

    // Structures (lecture)
    'structures.clients.read',
    'structures.zones_livraison.read',
    'structures.agences.read',
    'structures.parametres_application.read',

    // Litiges / call center (lecture)
    'litiges.view',
    'callcenter.inbox',

    // Pilotage exploitation (lecture)
    'exploitation.credits.read',
    'exploitation.points_journaliers.read',
    'exploitation.fournitures.read',
    // Prestataires: lecture + demande d’approbation (pas d’exécution sans accord)
    'exploitation.prestataires.read',
    'exploitation.prestataires_factures.read',
    'exploitation.prestataires_reglements.read',
    'exploitation.prestataires_retraits_hub.read',
    'exploitation.prestataires_retraits_hub.request_approval',
  ],
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
    'exploitation.credits.read',
    'exploitation.credits.submit_recap',
    'exploitation.points_journaliers.read',
    'exploitation.points_journaliers.create',
    'exploitation.points_journaliers.submit',
    'exploitation.fournitures.read',
    'exploitation.fournitures.request',
    // VALIDATIONS / ANNULATIONS (workflow)
    'colis.groupage.validate',
    'colis.autres-envois.validate',
    'factures.validate',
    'factures.cancel',
    'paiements.validate',
    'paiements.cancel',
  ],
  SUPERVISEUR_REGIONAL: [
    // Superviseur régional = mêmes capacités qu'AGENT_EXPLOITATION (opérations),
    // avec lecture multi-agences et rapports consolidés.
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
    'exploitation.credits.read',
    'exploitation.credits.manage',
    'exploitation.credits.export',
    'exploitation.points_journaliers.read',
    'exploitation.points_journaliers.validate',
    'exploitation.fournitures.read',
    'exploitation.fournitures.manage',
    // Prestataires (compagnies) + factures + règlements
    'exploitation.prestataires.create',
    'exploitation.prestataires.read',
    'exploitation.prestataires.update',
    'exploitation.prestataires_factures.create',
    'exploitation.prestataires_factures.read',
    'exploitation.prestataires_factures.update',
    'exploitation.prestataires_reglements.create',
    'exploitation.prestataires_reglements.read',
    // Retraits hub (lecture) : suivi interne des paiements espèces en agence
    'exploitation.prestataires_retraits_hub.read',
    // Rapports consolidés
    'rapports.suivi_envois.read',
    'rapports.statistiques.read',
    'rapports.ca_detaille.read',
  ],
  CHEF_AGENCE: [
    // Colis (opérationnel agence)
    'exploitation.groupage_colis.create',
    'exploitation.groupage_colis.read',
    'exploitation.groupage_colis.update',
    'exploitation.autres_envois.create',
    'exploitation.autres_envois.read',
    'exploitation.autres_envois.update',
    'exploitation.rapports_envois.read',
    // Facturation (émission / consultation)
    'facturation.facturer.create',
    'facturation.facturer.read',
    // Structures (lecture)
    'structures.clients.read',
    'structures.agences.read',
    // Caisse (lecture)
    'operation_caisse.gestion_caisses.read',
    // Workflow / validation
    'colis.groupage.validate',
    'colis.autres-envois.validate',
    'factures.validate',
    // Litiges / call center (utile terrain)
    'litiges.view',
    'litiges.create',
    'callcenter.inbox',
    // Exploitation (chef agence : soumission des points/recaps)
    'exploitation.points_journaliers.read',
    'exploitation.points_journaliers.create',
    'exploitation.points_journaliers.submit',
    'exploitation.credits.read',
    'exploitation.credits.submit_recap',
    // Fournitures (demandes agence)
    'exploitation.fournitures.read',
    'exploitation.fournitures.request',
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
    'exploitation.credits.read',
    'exploitation.credits.manage',
    'exploitation.credits.export',
    'exploitation.points_journaliers.read',
    'exploitation.points_journaliers.validate',
    'exploitation.fournitures.read',
    'exploitation.fournitures.manage',
  ],
  AGENT_GROUPAGE: [
    'exploitation.groupage_colis.create',
    'exploitation.groupage_colis.read',
    // Pas de modification/suppression après enregistrement (réservé chef/manager)
    'exploitation.autres_envois.create',
    'exploitation.autres_envois.read',
    // Pas de modification/suppression après enregistrement (réservé chef/manager)
    // Pas de facturation / encaissement : réservé caissiers d’agence et caissier principal
    // NB: Les points journaliers / récap crédits sont gérés par le CHEF_AGENCE (pas AGENT_GROUPAGE)
    // Création client depuis saisie colis (certains clients sans pièce)
    'structures.clients.create',
    'structures.clients.read',
    'structures.agences.read',
    'litiges.view',
    'litiges.create',
    'callcenter.inbox',
  ],
  CAISSIER: [
    'operation_caisse.gestion_caisses.create',
    // Pas de gestion_caisses.read : la liste multi-agences est réservée admin/directeur (API filtre par rôle).
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
    // Consolidation des points journaliers soumis par les agences
    'exploitation.points_journaliers.read',
    'exploitation.points_journaliers.validate',
    // Le caissier principal (Abobo-Dokui) fait aussi son PJ (sur son agence)
    'exploitation.points_journaliers.create',
    'exploitation.points_journaliers.submit',
    // Lecture des crédits / récaps remis par les agences
    'exploitation.credits.read',
    // Prestataires: retraits hub (caisse principale)
    'exploitation.prestataires_retraits_hub.read',
    'exploitation.prestataires_retraits_hub.update',
    // Liste / consultation factures clients (toutes agences visibles côté API pour ce rôle)
    'facturation.facturer.read',
  ],
  CAISSIER_AGENCE: [
    // Caisse agence : opérations et lecture sur sa caisse (API filtre par id_agence)
    'operation_caisse.gestion_caisses.read',
    'operation_caisse.mouvements_caisses.create',
    'operation_caisse.mouvements_caisses.read',
    'operation_caisse.mouvements_caisses.update',
    'operation_caisse.journal.read',
    // Encaissements clients
    'operation_caisse.reglement_client.create',
    'operation_caisse.reglement_client.read',
    'operation_caisse.reglement_client.update',
    // Consultation factures (facture auto-générée au colis)
    'facturation.facturer.read',
    // Structures (lecture)
    'structures.agences.read',
    'structures.clients.read',
    // Litiges / call center (utile terrain)
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
  CALL_CENTER: [
    'callcenter.inbox',
    'structures.clients.read',
    'structures.agences.read',
    // Lecture multi-agences (relation client)
    'exploitation.groupage_colis.read',
    'exploitation.autres_envois.read',
    // Factures / paiements : lecture seule pour répondre aux clients
    'facturation.facturer.read',
    'operation_caisse.reglement_client.read',
    'litiges.view',
    'litiges.create',
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
