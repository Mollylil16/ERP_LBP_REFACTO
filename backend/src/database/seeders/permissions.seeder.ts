import { DataSource } from 'typeorm';
import {
  Permission,
  PermissionModule,
  PermissionAction,
} from '../../permissions/entities/permission.entity';

export async function seedPermissions(dataSource: DataSource): Promise<void> {
  const permissionRepository = dataSource.getRepository(Permission);

  const permissions = [
    // EXPLOITATION - COLIS
    {
      module: PermissionModule.EXPLOITATION,
      fonctionnalite: 'groupage_colis',
      action: PermissionAction.CREATE,
      description: 'Créer un groupage de colis',
    },
    {
      module: PermissionModule.EXPLOITATION,
      fonctionnalite: 'groupage_colis',
      action: PermissionAction.READ,
      description: 'Consulter les groupages de colis',
    },
    {
      module: PermissionModule.EXPLOITATION,
      fonctionnalite: 'groupage_colis',
      action: PermissionAction.UPDATE,
      description: 'Modifier un groupage de colis',
    },
    {
      module: PermissionModule.EXPLOITATION,
      fonctionnalite: 'groupage_colis',
      action: PermissionAction.DELETE,
      description: 'Supprimer un groupage de colis',
    },

    {
      module: PermissionModule.EXPLOITATION,
      fonctionnalite: 'autres_envois',
      action: PermissionAction.CREATE,
      description: 'Créer un envoi de colis',
    },
    {
      module: PermissionModule.EXPLOITATION,
      fonctionnalite: 'autres_envois',
      action: PermissionAction.READ,
      description: 'Consulter les envois de colis',
    },
    {
      module: PermissionModule.EXPLOITATION,
      fonctionnalite: 'autres_envois',
      action: PermissionAction.UPDATE,
      description: 'Modifier un envoi de colis',
    },
    {
      module: PermissionModule.EXPLOITATION,
      fonctionnalite: 'autres_envois',
      action: PermissionAction.DELETE,
      description: 'Supprimer un envoi de colis',
    },

    {
      module: PermissionModule.EXPLOITATION,
      fonctionnalite: 'rapports_envois',
      action: PermissionAction.READ,
      description: "Consulter les rapports d'envois",
    },

    {
      module: PermissionModule.EXPLOITATION,
      fonctionnalite: 'livraison',
      action: PermissionAction.CREATE,
      description: 'Créer une livraison',
    },
    {
      module: PermissionModule.EXPLOITATION,
      fonctionnalite: 'livraison',
      action: PermissionAction.READ,
      description: 'Consulter les livraisons',
    },
    {
      module: PermissionModule.EXPLOITATION,
      fonctionnalite: 'livraison',
      action: PermissionAction.UPDATE,
      description: 'Modifier une livraison',
    },
    {
      module: PermissionModule.EXPLOITATION,
      fonctionnalite: 'livraison',
      action: PermissionAction.DELETE,
      description: 'Supprimer une livraison',
    },

    // FACTURATION
    {
      module: PermissionModule.FACTURATION,
      fonctionnalite: 'cotation',
      action: PermissionAction.CREATE,
      description: 'Créer une cotation/devis',
    },
    {
      module: PermissionModule.FACTURATION,
      fonctionnalite: 'cotation',
      action: PermissionAction.READ,
      description: 'Consulter les cotations',
    },
    {
      module: PermissionModule.FACTURATION,
      fonctionnalite: 'cotation',
      action: PermissionAction.UPDATE,
      description: 'Modifier une cotation',
    },
    {
      module: PermissionModule.FACTURATION,
      fonctionnalite: 'cotation',
      action: PermissionAction.DELETE,
      description: 'Supprimer une cotation',
    },

    {
      module: PermissionModule.FACTURATION,
      fonctionnalite: 'facturer',
      action: PermissionAction.CREATE,
      description: 'Créer une facture',
    },
    {
      module: PermissionModule.FACTURATION,
      fonctionnalite: 'facturer',
      action: PermissionAction.READ,
      description: 'Consulter les factures',
    },
    {
      module: PermissionModule.FACTURATION,
      fonctionnalite: 'facturer',
      action: PermissionAction.UPDATE,
      description: 'Modifier une facture',
    },
    {
      module: PermissionModule.FACTURATION,
      fonctionnalite: 'facturer',
      action: PermissionAction.DELETE,
      description: 'Supprimer une facture',
    },

    {
      module: PermissionModule.FACTURATION,
      fonctionnalite: 'parametres_facture',
      action: PermissionAction.CREATE,
      description: 'Créer des paramètres de facture',
    },
    {
      module: PermissionModule.FACTURATION,
      fonctionnalite: 'parametres_facture',
      action: PermissionAction.READ,
      description: 'Consulter les paramètres de facture',
    },
    {
      module: PermissionModule.FACTURATION,
      fonctionnalite: 'parametres_facture',
      action: PermissionAction.UPDATE,
      description: 'Modifier les paramètres de facture',
    },
    {
      module: PermissionModule.FACTURATION,
      fonctionnalite: 'parametres_facture',
      action: PermissionAction.DELETE,
      description: 'Supprimer des paramètres de facture',
    },

    // OPÉRATION CAISSE
    {
      module: PermissionModule.OPERATION_CAISSE,
      fonctionnalite: 'gestion_caisses',
      action: PermissionAction.CREATE,
      description: 'Créer une caisse',
    },
    {
      module: PermissionModule.OPERATION_CAISSE,
      fonctionnalite: 'gestion_caisses',
      action: PermissionAction.READ,
      description: 'Consulter les caisses',
    },
    {
      module: PermissionModule.OPERATION_CAISSE,
      fonctionnalite: 'gestion_caisses',
      action: PermissionAction.UPDATE,
      description: 'Modifier une caisse',
    },
    {
      module: PermissionModule.OPERATION_CAISSE,
      fonctionnalite: 'gestion_caisses',
      action: PermissionAction.DELETE,
      description: 'Supprimer une caisse',
    },

    {
      module: PermissionModule.OPERATION_CAISSE,
      fonctionnalite: 'mouvements_caisses',
      action: PermissionAction.CREATE,
      description: 'Créer un mouvement de caisse',
    },
    {
      module: PermissionModule.OPERATION_CAISSE,
      fonctionnalite: 'mouvements_caisses',
      action: PermissionAction.READ,
      description: 'Consulter les mouvements de caisse',
    },
    {
      module: PermissionModule.OPERATION_CAISSE,
      fonctionnalite: 'mouvements_caisses',
      action: PermissionAction.UPDATE,
      description: 'Modifier un mouvement de caisse',
    },
    {
      module: PermissionModule.OPERATION_CAISSE,
      fonctionnalite: 'mouvements_caisses',
      action: PermissionAction.DELETE,
      description: 'Supprimer un mouvement de caisse',
    },

    {
      module: PermissionModule.OPERATION_CAISSE,
      fonctionnalite: 'journal',
      action: PermissionAction.READ,
      description: 'Consulter le journal de caisse',
    },

    {
      module: PermissionModule.OPERATION_CAISSE,
      fonctionnalite: 'reglement_client',
      action: PermissionAction.CREATE,
      description: 'Créer un règlement client',
    },
    {
      module: PermissionModule.OPERATION_CAISSE,
      fonctionnalite: 'reglement_client',
      action: PermissionAction.READ,
      description: 'Consulter les règlements clients',
    },
    {
      module: PermissionModule.OPERATION_CAISSE,
      fonctionnalite: 'reglement_client',
      action: PermissionAction.UPDATE,
      description: 'Modifier un règlement client',
    },
    {
      module: PermissionModule.OPERATION_CAISSE,
      fonctionnalite: 'reglement_client',
      action: PermissionAction.DELETE,
      description: 'Supprimer un règlement client',
    },

    // GESTION DES FONDS
    {
      module: PermissionModule.GESTION_FONDS,
      fonctionnalite: 'demandes_fonds',
      action: PermissionAction.CREATE,
      description: 'Créer une demande de fonds',
    },
    {
      module: PermissionModule.GESTION_FONDS,
      fonctionnalite: 'demandes_fonds',
      action: PermissionAction.READ,
      description: 'Consulter les demandes de fonds',
    },
    {
      module: PermissionModule.GESTION_FONDS,
      fonctionnalite: 'demandes_fonds',
      action: PermissionAction.UPDATE,
      description: 'Modifier une demande de fonds',
    },
    {
      module: PermissionModule.GESTION_FONDS,
      fonctionnalite: 'demandes_fonds',
      action: PermissionAction.DELETE,
      description: 'Supprimer une demande de fonds',
    },

    {
      module: PermissionModule.GESTION_FONDS,
      fonctionnalite: 'paiement_demandes',
      action: PermissionAction.CREATE,
      description: 'Payer une demande de fonds',
    },
    {
      module: PermissionModule.GESTION_FONDS,
      fonctionnalite: 'paiement_demandes',
      action: PermissionAction.READ,
      description: 'Consulter les paiements de demandes',
    },
    {
      module: PermissionModule.GESTION_FONDS,
      fonctionnalite: 'paiement_demandes',
      action: PermissionAction.UPDATE,
      description: 'Modifier un paiement de demande',
    },
    {
      module: PermissionModule.GESTION_FONDS,
      fonctionnalite: 'paiement_demandes',
      action: PermissionAction.DELETE,
      description: 'Supprimer un paiement de demande',
    },

    {
      module: PermissionModule.GESTION_FONDS,
      fonctionnalite: 'recap_demandes',
      action: PermissionAction.READ,
      description: 'Consulter le récapitulatif des demandes',
    },

    // RAPPORTS/ÉTATS
    {
      module: PermissionModule.RAPPORTS,
      fonctionnalite: 'suivi_envois',
      action: PermissionAction.READ,
      description: 'Consulter le suivi des envois',
    },
    {
      module: PermissionModule.RAPPORTS,
      fonctionnalite: 'statistiques',
      action: PermissionAction.READ,
      description: 'Consulter les statistiques',
    },
    {
      module: PermissionModule.RAPPORTS,
      fonctionnalite: 'ca_detaille',
      action: PermissionAction.READ,
      description: 'Consulter le CA détaillé',
    },
    {
      module: PermissionModule.RAPPORTS,
      fonctionnalite: 'business_analyst',
      action: PermissionAction.READ,
      description: 'Accéder au Business Analyst',
    },

    // STRUCTURES
    {
      module: PermissionModule.STRUCTURES,
      fonctionnalite: 'clients',
      action: PermissionAction.CREATE,
      description: 'Créer un client',
    },
    {
      module: PermissionModule.STRUCTURES,
      fonctionnalite: 'clients',
      action: PermissionAction.READ,
      description: 'Consulter les clients',
    },
    {
      module: PermissionModule.STRUCTURES,
      fonctionnalite: 'clients',
      action: PermissionAction.UPDATE,
      description: 'Modifier un client',
    },
    {
      module: PermissionModule.STRUCTURES,
      fonctionnalite: 'clients',
      action: PermissionAction.DELETE,
      description: 'Supprimer un client',
    },

    {
      module: PermissionModule.STRUCTURES,
      fonctionnalite: 'zones_livraison',
      action: PermissionAction.CREATE,
      description: 'Créer une zone de livraison',
    },
    {
      module: PermissionModule.STRUCTURES,
      fonctionnalite: 'zones_livraison',
      action: PermissionAction.READ,
      description: 'Consulter les zones de livraison',
    },
    {
      module: PermissionModule.STRUCTURES,
      fonctionnalite: 'zones_livraison',
      action: PermissionAction.UPDATE,
      description: 'Modifier une zone de livraison',
    },
    {
      module: PermissionModule.STRUCTURES,
      fonctionnalite: 'zones_livraison',
      action: PermissionAction.DELETE,
      description: 'Supprimer une zone de livraison',
    },

    {
      module: PermissionModule.STRUCTURES,
      fonctionnalite: 'parametres_application',
      action: PermissionAction.READ,
      description: 'Consulter les paramètres généraux (société, branding)',
    },

    {
      module: PermissionModule.STRUCTURES,
      fonctionnalite: 'agences',
      action: PermissionAction.CREATE,
      description: 'Créer une agence',
    },
    {
      module: PermissionModule.STRUCTURES,
      fonctionnalite: 'agences',
      action: PermissionAction.READ,
      description: 'Consulter les agences',
    },
    {
      module: PermissionModule.STRUCTURES,
      fonctionnalite: 'agences',
      action: PermissionAction.UPDATE,
      description: 'Modifier une agence',
    },
    {
      module: PermissionModule.STRUCTURES,
      fonctionnalite: 'agences',
      action: PermissionAction.DELETE,
      description: 'Supprimer une agence',
    },

    {
      module: PermissionModule.STRUCTURES,
      fonctionnalite: 'utilisateurs',
      action: PermissionAction.CREATE,
      description: 'Créer un utilisateur',
    },
    {
      module: PermissionModule.STRUCTURES,
      fonctionnalite: 'utilisateurs',
      action: PermissionAction.READ,
      description: 'Consulter les utilisateurs',
    },
    {
      module: PermissionModule.STRUCTURES,
      fonctionnalite: 'utilisateurs',
      action: PermissionAction.UPDATE,
      description: 'Modifier un utilisateur',
    },
    {
      module: PermissionModule.STRUCTURES,
      fonctionnalite: 'utilisateurs',
      action: PermissionAction.DELETE,
      description: 'Supprimer un utilisateur',
    },
  ];

  for (const permData of permissions) {
    const code = `${permData.module.toLowerCase()}.${permData.fonctionnalite}.${permData.action.toLowerCase()}`;

    const existingPermission = await permissionRepository.findOne({
      where: { code },
    });

    if (!existingPermission) {
      const permission = permissionRepository.create({
        ...permData,
        code,
      });
      await permissionRepository.save(permission);
      console.log(`✅ Permission créée: ${code}`);
    } else {
      console.log(`ℹ️  Permission existe déjà: ${code}`);
    }
  }

  /** Codes déjà au format app (utilisés par le guard / le front), hors formule module.fonctionnalité.action */
  const explicitAppPermissions: Array<{
    code: string;
    module: PermissionModule;
    fonctionnalite: string;
    action: string | null;
    description: string;
  }> = [
    // Module = valeurs déjà présentes dans lbp_permissions_module_enum (PostgreSQL)
    {
      code: 'litiges.view',
      module: PermissionModule.EXPLOITATION,
      fonctionnalite: 'litiges',
      action: 'READ',
      description: 'Consulter les litiges',
    },
    {
      code: 'litiges.create',
      module: PermissionModule.EXPLOITATION,
      fonctionnalite: 'litiges',
      action: 'CREATE',
      description: 'Créer un litige ou un message',
    },
    {
      code: 'litiges.manage',
      module: PermissionModule.EXPLOITATION,
      fonctionnalite: 'litiges',
      action: 'UPDATE',
      description: 'Traiter / mettre à jour un litige',
    },
    {
      code: 'litiges.admin',
      module: PermissionModule.EXPLOITATION,
      fonctionnalite: 'litiges',
      action: 'DELETE',
      description: 'Supprimer ou restaurer un litige (admin)',
    },
    {
      code: 'callcenter.inbox',
      module: PermissionModule.STRUCTURES,
      fonctionnalite: 'callcenter_inbox',
      action: 'READ',
      description: 'Accéder à la boîte call center (conversations, envoi)',
    },
  ];

  for (const row of explicitAppPermissions) {
    const existing = await permissionRepository.findOne({
      where: { code: row.code },
    });
    if (!existing) {
      const permission = permissionRepository.create({
        module: row.module,
        fonctionnalite: row.fonctionnalite,
        action: row.action ?? undefined,
        code: row.code,
        description: row.description,
      });
      await permissionRepository.save(permission);
      console.log(`✅ Permission créée: ${row.code}`);
    } else {
      console.log(`ℹ️  Permission existe déjà: ${row.code}`);
    }
  }
}
