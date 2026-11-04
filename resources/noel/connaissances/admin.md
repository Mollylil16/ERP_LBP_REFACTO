# Administration

## Le module Administration
L'Administration gère les comptes du personnel, leurs rôles et leurs permissions, et garde la trace de chaque changement.
Son menu : Tableau de bord ; Accès & sécurité (Utilisateurs, Permissions, Rôles et anomalies, Journal des comptes) ; Qualité (Santé & tests, Espace de la messagerie).
La tuile Admin n'apparaît qu'aux administrateurs.

## Créer un compte utilisateur
Administration > Utilisateurs, puis créer un compte : nom et prénoms, adresse e-mail, téléphone, agence de rattachement, rôles.
On peut relier le compte à un dossier RH existant (« Dossier RH associé ») ; sans ce lien, l'Espace employé de la personne lui dira de contacter les RH.
Le choix « Sans agence spécifique (Toutes agences / Siège) » est réservé aux comptes qui suivent tout le réseau.
L'agence de rattachement décide de la caisse que la personne voit, des colis qu'elle réceptionne et de son groupe de messagerie d'agence : ne l'oubliez pas.

## Réinitialiser un mot de passe
Sur la fiche du compte, « Réinitialiser » donne un mot de passe provisoire tiré au hasard, propre à ce compte.
Il s'affiche une seule fois : notez-le et transmettez-le en privé à la personne.
À sa prochaine connexion, la personne devra choisir son propre mot de passe : tant qu'elle ne l'a pas fait, seul l'écran de changement de mot de passe s'ouvre.

## Désactiver ou réactiver un compte
Désactiver un compte coupe ses accès tout de suite. On le fait par exemple au départ d'un agent.
On peut désactiver ou activer plusieurs comptes d'un coup en les sélectionnant dans la liste.
Un compte désactivé sort automatiquement des groupes de la messagerie ; ses anciens messages restent visibles des autres.

## Attribuer des rôles
Les rôles d'un compte décident des modules et des écrans qu'il voit. Un agent qui ne peut plus encaisser a souvent un rôle manquant.
Tous les rôles portés par le personnel figurent au catalogue de l'écran, y compris Agent de Saisie, Gestionnaire de Caisse, Agent Call Center, Responsable Groupage.
Enregistrer la fiche d'un compte conserve les rôles qu'il porte déjà, même ceux que le formulaire n'affiche pas.

## L'écran Rôles et anomalies
Administration > Rôles et anomalies montre qui porte quel rôle, sans ouvrir les fiches une à une.
Il signale ce qui cloche : comptes actifs sans aucun rôle (ils peuvent se connecter mais ne rien faire), rôles portés mais absents du catalogue, comptes sans connexion depuis 60 jours ou jamais, comptes qui contournent les validations à deux mains, rôles que personne ne porte.
À regarder dès qu'un agent dit « je n'ai plus accès ».

## Le journal des comptes
Administration > Journal des comptes dit qui a touché à quel compte, et quoi exactement, avec l'avant et l'après côte à côte.
Exemple : savoir qu'un rôle est passé de « caissière, agent de saisie » à « agent de saisie » permet de le rétablir.
Le journal se filtre par compte, par auteur et par période, et s'exporte en PDF et en Excel.

## Les permissions
Administration > Permissions règle les droits fins par compte et par écran, en plus des rôles.
La matrice des permissions donne une vue d'ensemble des droits de tous les comptes.

## Diagnostiquer « je n'ai pas accès »
Regardez d'abord si la personne s'est connectée récemment : un compte qui se connecte n'a pas un problème de mot de passe, mais peut-être de rôle ou d'agence.
Vérifiez ensuite ses rôles et son agence de rattachement dans sa fiche, puis l'écran Rôles et anomalies.
Le module RH, lui, est volontairement réservé à trois fonctions : y refuser quelqu'un n'est pas une panne.

## L'espace de la messagerie et les groupes automatiques
Administration > Espace de la messagerie montre la place qu'occupent les fichiers de la messagerie sur l'hébergement, par mois et par catégorie. Au-delà du seuil, la jauge passe au rouge.
Il montre aussi les groupes automatiques (un par agence, « Tout LBP », « Annonces Direction ») avec leurs membres et leurs administrateurs.
Ces groupes se mettent à jour chaque nuit ; le bouton de l'écran les resynchronise tout de suite, par exemple après un changement d'agence.

## Mettre un module en maintenance
Depuis Administration > Santé & tests, un module peut être mis en maintenance avec un motif.
Sa tuile affiche alors « En maintenance » sur le portail et ne s'ouvre plus, jusqu'à ce qu'on la rouvre.
L'écran Santé & tests lance aussi les contrôles du logiciel module par module.
