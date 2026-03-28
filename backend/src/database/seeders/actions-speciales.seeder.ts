import { DataSource } from 'typeorm';
import {
  ActionSpeciale,
  ActionSpecialeType,
} from '../../permissions/entities/action-speciale.entity';

export async function seedActionsSpeciales(
  dataSource: DataSource,
): Promise<void> {
  const actionRepository = dataSource.getRepository(ActionSpeciale);

  const actions = [
    // RESTRICTIONS
    {
      code: 'uniquementGroupage',
      libelle: 'Uniquement Groupage',
      description:
        "L'utilisateur ne peut travailler que sur les groupages de colis",
      type: ActionSpecialeType.RESTRICTION,
    },
    {
      code: 'nePeutPasModifier',
      libelle: 'Ne Peut Pas Modifier',
      description: "L'utilisateur ne peut pas modifier les données",
      type: ActionSpecialeType.RESTRICTION,
    },
    {
      code: 'nePeutPasSupprimer',
      libelle: 'Ne Peut Pas Supprimer',
      description: "L'utilisateur ne peut pas supprimer les données",
      type: ActionSpecialeType.RESTRICTION,
    },
    {
      code: 'pageIndividuelle',
      libelle: 'Page Individuelle',
      description: "L'utilisateur ne voit que ses propres données",
      type: ActionSpecialeType.RESTRICTION,
    },
    {
      code: 'pageAgence',
      libelle: 'Page Agence',
      description: "L'utilisateur ne voit que les données de son agence",
      type: ActionSpecialeType.RESTRICTION,
    },

    // PRIVILÈGES
    {
      code: 'voirToutesAgences',
      libelle: 'Voir Toutes Agences',
      description: "L'utilisateur peut voir les données de toutes les agences",
      type: ActionSpecialeType.PRIVILEGE,
    },
    {
      code: 'validationDefinitive',
      libelle: 'Validation Définitive',
      description: "L'utilisateur peut valider définitivement les opérations",
      type: ActionSpecialeType.PRIVILEGE,
    },
    {
      code: 'validationMinute',
      libelle: 'Validation Minute',
      description: "L'utilisateur peut valider les minutes",
      type: ActionSpecialeType.PRIVILEGE,
    },
    {
      code: 'validationOuvertureDossier',
      libelle: 'Validation Ouverture Dossier',
      description: "L'utilisateur peut valider l'ouverture de dossiers",
      type: ActionSpecialeType.PRIVILEGE,
    },
    {
      code: 'validationProforma',
      libelle: 'Validation Proforma',
      description: "L'utilisateur peut valider les proformas",
      type: ActionSpecialeType.PRIVILEGE,
    },
    {
      code: 'encaisserMontantGroupage',
      libelle: 'Encaisser Montant Groupage',
      description: "L'utilisateur peut encaisser les montants de groupage",
      type: ActionSpecialeType.PRIVILEGE,
    },
    {
      code: 'annulerMontantEncaisseGroupage',
      libelle: 'Annuler Montant Encaissé Groupage',
      description:
        "L'utilisateur peut annuler les montants encaissés de groupage",
      type: ActionSpecialeType.PRIVILEGE,
    },
    {
      code: 'imputerCaisses',
      libelle: 'Imputer Les Caisses',
      description: "L'utilisateur peut imputer les caisses",
      type: ActionSpecialeType.PRIVILEGE,
    },
  ];

  for (const actionData of actions) {
    const existingAction = await actionRepository.findOne({
      where: { code: actionData.code },
    });

    if (!existingAction) {
      const action = actionRepository.create(actionData);
      await actionRepository.save(action);
      console.log(`✅ Action spéciale créée: ${actionData.libelle}`);
    } else {
      console.log(`ℹ️  Action spéciale existe déjà: ${actionData.libelle}`);
    }
  }
}
