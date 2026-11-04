# Logistique

## Le module Logistique
Le module Logistique suit le magasin, les expéditions et les ressources internes.
Son menu est rangé en groupes : Pilotage (tableau de bord), Expéditions & Fret (Codes Non Payés, Groupage & Expéditions, Gestion des Colis), Magasin & Stockage (Suivi Colisage, Gestion des Rayons, Emballages LBP), Paramétrage & Gardiennage (Délais & Gardiennage), Suivi & Transport (Suivi GPS) et Ressources Internes (Fournitures bureau, Historique fournitures).
Plusieurs entrées ouvrent des écrans du module Facturation & Colisage, là où la donnée est réellement saisie.

## Le tableau de bord : l'état du magasin
Le tableau de bord de la Logistique répond aux deux questions du matin : ce qui dort en agence, agence par agence, et ce qui va manquer au comptoir.
Le bloc « Emballages à réapprovisionner » montre les emballages qui vont manquer si personne ne commande.
Rien n'y est ressaisi : tout est lu des colis et des stocks déjà enregistrés.

## Les Codes Non Payés (CNP)
Logistique > Codes Non Payés (CNP) liste les colis dont la facture n'est pas soldée (factures émises ou partielles), avec le montant à crédit, le montant encaissé, le reste dû à recouvrer et son équivalent en euros.
On filtre par agence et par période ; les colis export aérien et le cargo (maritime ou routier) sont présentés séparément.
On peut renseigner en ligne le groupe ou lot d'un colis. La liste s'exporte en PDF et en Excel.
Un chef d'agence ne voit que les créances de son agence ; un compte sans agence de rattachement ne voit rien.

## Le suivi colisage, sans montants
Logistique > Suivi Colisage montre les colis d'une agence à une date, tous types d'envoi confondus, volontairement sans aucun montant.
On filtre par agence, date et catégorie de code ; le titre de la liste dit sur quoi elle porte.
Une colonne mène d'un clic à la facture du colis ; « Non facturé » signale un colis qui n'a pas encore de facture.
Les exports PDF et Excel partent avec les mêmes filtres que l'écran.

## Gestion des rayons
Logistique > Gestion des Rayons permet de créer les rayons de l'agence et de voir leur capacité et leur remplissage.
Si le réglage d'affectation automatique est activé, un colis arrivé est placé tout seul dans un rayon disponible de l'agence.
Un colis peut être transféré d'un rayon à un autre ; le déplacement est enregistré.

## Délais et gardiennage
Logistique > Délais & Gardiennage règle, agence par agence, le délai gratuit de garde (en jours) et les frais de gardiennage par jour supplémentaire (en FCFA).
Le délai gratuit est le nombre de jours pendant lesquels le colis est stocké gratuitement après son arrivée ; ensuite la pénalité s'applique par jour de retard.
Les valeurs par défaut sont 7 jours gratuits puis 500 FCFA par colis et par jour. Le logiciel calcule seul le dépassement, à partir de la date d'entrée en rayon.

## Les emballages LBP
Logistique > Emballages LBP enregistre et suit le stock des cartons, sacs bôrô, valises et consommables, par agence.
On crée un emballage avec son code article, son libellé, sa catégorie (carton, sac tissé bôrô, valise ou malle fret, sac scellé sécurisé, consommable), son format, son prix de vente client, son prix d'achat et un seuil d'alerte de stock bas.
Un mouvement de stock se saisit avec son type : approvisionnement (entrée), sortie pour un client, transfert entre agences, ou démarque (pertes, endommagé), avec la quantité et un motif.

## Demander des fournitures de bureau
Logistique > Fournitures bureau est ouvert à toutes les agences pour demander des fournitures. Une agence ne voit que ce qu'elle a demandé.
Une demande ne part qu'après deux signatures : le superviseur régional approuve, puis le comptable confirme. Le Directeur général et son assistante peuvent aussi confirmer.
Personne ne confirme une demande qu'il a lui-même approuvée.

## L'historique des fournitures
Logistique > Historique fournitures est le journal des décisions prises sur les demandes de fournitures : qui a approuvé, qui a confirmé, quand et pourquoi.
Il se filtre par agence, par décideur et par période, et s'exporte en PDF ou en tableur quand une agence conteste.
Il est réservé à ceux qui approuvent et confirment.

## Groupage, gestion des colis et suivi GPS
Logistique > Groupage & Expéditions ouvre l'écran de groupage du module Facturation & Colisage : voyages, manifestes et affectation des colis.
Logistique > Gestion des Colis ouvre la liste de tous les colis.
Logistique > Suivi GPS ouvre le suivi des expéditions en transit et des derniers relevés GPS.
