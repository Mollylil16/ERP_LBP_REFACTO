# Recherche & Audit

## Le module Recherche & Audit
Sur le portail, la tuile « Recherche & Audit » regroupe la recherche des factures et l'audit des factures verrouillées.
À ne pas confondre avec la tuile « Facturation », qui est le module du guichet (saisie des colis et facturation).
Son menu contient le tableau de bord, Factures Clients et Points de Caisse, qui ouvrent les mêmes écrans que dans le module Finance.

## Rechercher des factures par période, agence et trajet
L'écran Filtre & Recherche permet de retrouver les factures émises par période (mois et année de début et de fin), par agence et par trajet.
On peut choisir un code précis ou toute une catégorie : « Tout le Groupage Cargo (Tous codes) », « Tout le Colis Rapide (Tous codes) », ou DHL.
La case « Impayés uniquement (Reste > 0) » ne garde que les factures qui ne sont pas soldées.
Contrairement au suivi colisage de la Logistique, cet écran affiche les montants.

## Exporter le résultat de la recherche
En haut de l'écran Filtre & Recherche, deux boutons : « Export PDF Officiel » et « Export Excel (CSV UTF-8) ».
Les exports reprennent les mêmes filtres que l'écran : ce qui est affiché est ce qui est exporté.
Si aucune facture ne correspond, élargissez la période ou retirez un filtre.

## Une facture verrouillée
Après sa création, une facture est verrouillée : un agent ne peut plus la modifier. Son écran d'audit s'affiche alors en lecture seule avec le message « Cette facture est verrouillée : seul un Responsable peut la modifier ».
Seuls le Directeur général et l'administrateur peuvent la modifier. L'assistante DG la consulte sans pouvoir la modifier.
Chaque champ modifié est enregistré dans le journal d'audit, avec l'ancienne et la nouvelle valeur, l'auteur et la date (« Enregistrer la modification (tracée) »).

## Corriger un encaissement en double : pas par la modification de facture
Modifier une facture ne touche pas aux règlements déjà encaissés : le rapport du jour et l'argent attendu au tiroir viennent des règlements.
Pour un règlement saisi deux fois, la bonne voie est « Annuler ce règlement » sur la fiche de la facture, dans Finance > Factures Clients, section « Historique des Encaissements Physiques ».

## Accès aux factures d'une autre agence
Le message « Vous n'avez pas accès à cette facture (agence différente) » signifie que la facture appartient à une autre agence que la vôtre.
Chacun consulte les factures de son agence ; la direction et les rôles qui suivent tout le réseau voient toutes les agences.
