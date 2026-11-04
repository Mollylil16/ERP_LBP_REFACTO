# Facturation & Colisage

## Le module Facturation & Colisage
C'est le module du guichet : on y enregistre les colis, on les facture, on prépare les départs et on réceptionne les colis à l'arrivée.
Sur le portail, sa tuile s'appelle « Facturation ».
Son menu est rangé en groupes : Pilotage (tableau de bord), Opération (saisie des colis par trajet), Activité (recherche, groupage, autres envois, départs, réception, documents), Exploitation, Analyse (reporting, rapports journaliers), Configuration (paramétrage) et Aide (guide d'utilisation).
Le Guide d'Utilisation du module, dans Facturation & Colisage > Guide d'Utilisation, reprend la saisie, les trajets, les tarifs, le retrait et les règles anti-fraude.

## Enregistrer un colis, étape par étape
1. Renseigner l'expéditeur : le chercher par son nom ou son téléphone, ou le créer.
2. Renseigner le destinataire, avec un téléphone au format international : il porte les notifications d'arrivée.
3. Peser la marchandise et reporter le poids brut exact, puis la valeur déclarée.
4. Ajouter les lignes de marchandises : produit, quantité, poids unitaire, emballage.
5. Enregistrer. Le logiciel génère alors le numéro de suivi.
6. Sur la fiche du colis, cliquer « Facturer (1-Clic) » pour finaliser.
Conseil : passez par le menu Opération et saisissez le colis directement sur son trajet, pour qu'il ne puisse plus être changé par inadvertance.

## Saisir par le menu Opération pour verrouiller le trajet
Le menu Opération range la saisie par trajet : Groupage Cargo (LB-CI, LB-FR, S-FR, S-CI, LB-CA, F-SN), Colis Rapide (CA-CI, CA-FR, CA-SN, CA-IS, CA-IC, CA-CC) et DHL.
Exemple : Facturation & Colisage > Opération > Groupage Cargo > LB-CI : Abidjan vers France.
Le formulaire est le même que la saisie générale, mais le trajet y est fixé d'avance et ne peut plus être changé par erreur.
Pourquoi : un colis saisi sur le mauvais trajet est mal tarifé, part dans le mauvais départ et fausse les rapports.

## L'expéditeur et le destinataire
Chaque colis est lié à deux contacts distincts et joignables.
Recherche rapide : tapez les premières lettres du nom ou le téléphone pour charger un client déjà connu, avant d'en créer un nouveau.
Création rapide : si le client n'existe pas, remplissez l'encadré (nom complet, téléphone, e-mail facultatif, adresse) : il est enregistré automatiquement.
Le téléphone doit être au format international : c'est lui qui reçoit les SMS et WhatsApp d'arrivée en agence. Un numéro faux, c'est un client jamais prévenu.
L'adresse indique la ville et le quartier de livraison ou de ramassage.

## Poids, valeur déclarée, devise et agences
Poids total (kg) : pesez la marchandise sur la balance de l'agence et reportez le poids brut exact.
Valeur déclarée : la valeur marchande déclarée par le client. Elle sert de base à l'indemnisation, au calcul de l'assurance et aux frais de douane. Une valeur minorée se retourne contre le client en cas de litige.
Devise : franc CFA (XOF) par défaut, sinon euro (EUR) ou dollar (USD).
Agence de départ : l'agence qui prend le colis en charge. Agence d'arrivée : le point de retrait où le destinataire viendra chercher son colis.

## Les lignes de marchandises et les emballages
Le tableau des marchandises détaille le contenu : produit, nombre de colis, poids unitaire, emballage et quantité d'emballage.
Le choix du produit pré-remplit son prix au kilo. Le bouton d'ajout de ligne crée une nouvelle ligne quand le colis contient des produits de natures différentes.
Une même ligne ne peut réunir que des produits au même tarif au kilo : sinon la validation est bloquée. Créez une ligne par tarif.
Plusieurs produits d'une même ligne se séparent au clavier par une barre oblique ; sur la facture, ils s'affichent séparés par une espace.
Nommez toujours le produit dès qu'une ligne porte de la marchandise : c'est ce que le client lit dans la colonne Description de sa facture.

## Comment le prix d'un colis est calculé
Total d'une ligne = (nombre de colis x poids unitaire x prix au kilo) + (quantité d'emballage x prix de l'emballage).
Montant de l'assurance = valeur déclarée x 2 %, calculé seulement si la case d'assurance est cochée.
Grand total = somme des lignes + montant de l'assurance. Il est affiché en francs CFA et en euros.
L'aérien se facture au kilo et convient aux marchandises légères ou de valeur ; le maritime accepte de plus grands volumes, avec des délais plus longs. Vérifiez le trajet avant de valider.

## L'assurance colis
Sur le formulaire de saisie, la case « Souscrire à l'Assurance Colis » ajoute 2 % de la valeur déclarée au total, pour une couverture jusqu'à la valeur déclarée.
Elle est facultative et se coche à la demande du client. Pour l'aérien urgent (ordinateur, téléphones), le guide conseille de la cocher et de renseigner la marque et la valeur avec précision.
Attention : la fiche d'engagement imprimée au verso de la facture parle, elle, d'une prime de garantie de 10 %. Les deux taux ne sont pas les mêmes ; si un client pose la question, demandez à la direction avant de lui répondre.

## Le numéro de suivi d'un colis
Le numéro de suivi est créé automatiquement à l'enregistrement du colis.
Il a la forme « code du trajet - numéro à trois chiffres », par exemple LB-FR-001 ou CA-CI-045.
C'est lui qu'on cherche à la réception, qu'on scanne à la douchette et que le client utilise pour suivre son colis sur le site internet (le QR code imprimé sur la facture y mène).

## Facturer un colis
Sur la fiche du colis, cliquez sur « Facturer (1-Clic) » : la facture est créée à partir du colis, avec un numéro qui commence par FA.
C'est celui qui enregistre le colis qui établit la facture, et elle reste à son nom.
Si la caisse de l'agence est fermée parce que le point de caisse d'un jour précédent n'a pas été soumis, la facturation est refusée avec le message « Caisse fermée » : il faut d'abord soumettre ce point.
L'encaissement se fait ensuite dans Finance > Factures Clients.

## Imprimer la facture et l'étiquette
Sur la fiche du colis, le bouton « Facture » ouvre le document à imprimer : la facture au recto, la fiche d'engagement client au verso.
Imprimez en recto-verso : la facture devant, la fiche derrière, sur la même feuille. Le bouton « Imprimer la facture » lance l'impression.
Le bouton « Étiquette Thermique » ouvre l'étiquette à coller sur le colis.

## Ce qui est écrit sur la facture remise au client
La facture porte l'en-tête « IMPRIMÉ SPÉCIFIQUE - Facture & Colisage », l'agence, le numéro de suivi et le nombre total de colis.
Un QR code « Suivi colis » permet au client de suivre son envoi depuis son téléphone.
Un bandeau prévient le client : il dispose de 3 jours pour récupérer son colis à compter de la date de notification ; passé ce délai, LBP décline toute responsabilité.
Le numéro du service client est imprimé sur la facture.

## La fiche d'engagement client : ce que c'est et où elle s'imprime
La fiche d'engagement client, appelée « Fiche d'engagement client - Garantie colis », est la déclaration de valeur du client et sa preuve d'adhésion à la garantie.
Elle s'imprime automatiquement au verso de chaque facture : on l'obtient en cliquant sur « Facture » depuis la fiche du colis, puis en imprimant en recto-verso.
Elle est pré-remplie avec ce que le logiciel connaît déjà : numéro de fiche (le numéro de facture), numéro de colis, date, agent LBP, expéditeur, destinataire, trafic, contenu, nombre de colis, poids, valeur déclarée et montants.
Le reste se remplit au stylo au comptoir : pièce d'identité, état du colis au dépôt, bénéficiaire, mode de paiement, signatures.

## La fiche d'engagement client : son contenu
La fiche compte dix parties :
1. Identification de l'expéditeur / client.
2. Identification du destinataire.
3. Description du colis et déclaration de valeur.
4. Garantie souscrite et paiement.
5. Engagement du client (les sept engagements).
6. Risques garantis et exclusions.
7. Déclaration de sinistre.
8. Bénéficiaire de l'indemnisation.
9. Déclaration et signatures (client, et pour LBP-CI avec cachet).
10. Reçu client.
Elle se termine par un avis important : la fiche doit être conservée avec la preuve de dépôt et les documents contractuels.

## La fiche d'engagement client : les sept engagements
En signant, le client :
1. Certifie l'exactitude des informations fournies dans la fiche.
2. Certifie que le contenu déclaré correspond réellement au contenu du colis.
3. Reconnaît avoir déclaré la valeur réelle des biens confiés à LBP-CI.
4. Reconnaît qu'une fausse déclaration, dissimulation ou sous-évaluation volontaire peut entraîner les conséquences prévues par les conditions contractuelles.
5. Certifie que le colis ne contient aucun produit interdit ou soumis à restriction non déclaré.
6. Reconnaît avoir reçu ou eu accès aux conditions générales de la garantie.
7. Accepte les conditions de prise en charge du colis et d'indemnisation en cas de sinistre.

## La fiche d'engagement client : pourquoi la faire signer
La fiche protège à la fois le client et LBP. Elle prouve ce que le client a déclaré : le contenu, la valeur et son adhésion à la garantie.
En cas de perte, de vol ou de dommage, c'est elle qui sert de base à l'étude de la demande d'indemnisation.
Sans signature, LBP ne peut pas prouver que le client a déclaré la valeur réelle ni qu'il connaissait les exclusions.
Le client signe en écrivant « Lu et approuvé » ; l'agent signe pour LBP-CI avec le cachet. Faites-la signer à chaque dépôt, avant que le client ne reparte.

## La fiche d'engagement : garantie, exclusions et sinistre
La fiche précise que le paiement de la prime de garantie ne veut pas dire que l'indemnisation sera égale à ce pourcentage de la valeur : l'indemnisation dépend des conditions contractuelles et des justificatifs.
Risques garantis, sous réserve des conditions générales : perte, vol, destruction totale, dommages matériels.
Exclusions principales : espèces et valeurs, bijoux et objets de très grande valeur sauf déclaration acceptée, armes, stupéfiants, produits dangereux ou illicites, marchandises interdites, marchandises mal emballées, sous-déclaration volontaire, fausse déclaration.
En cas de sinistre, le client prévient LBP-CI au plus vite et fournit les pièces demandées : la fiche, la preuve de dépôt, sa pièce d'identité, une preuve de valeur, des photos du colis ou des dommages.

## Modifier ou supprimer un colis
La modification d'un colis depuis sa fiche est réservée au Directeur général et à l'administrateur.
La suppression est possible pour le Directeur général, l'administrateur, le chef d'agence, la caissière principale et le superviseur général. Elle est définitive.
Un colis auquel d'autres enregistrements sont liés, par exemple une facture, ne peut pas être supprimé : le logiciel l'indique.
Modifier un colis ou une facture déjà payée déclenche une alerte : obtenez l'accord écrit de la direction avant.

## Le retrait du colis par le destinataire
Règle d'or avant de remettre un colis : la facture liée doit être au statut « payée ». Si elle est « émise » ou « partiellement payée », refusez la remise et envoyez le client à la caisse.
Sur la fiche du colis, section « Signaler le retrait du colis », renseignez le nom du récupérateur, le numéro de sa CNI ou de son passeport, et son téléphone.
Cliquez ensuite sur « Confirmer la livraison (Signature juridique) ». Le colis passe au statut RETIRÉ.

## Les frais de gardiennage au retrait
Après l'arrivée du colis en agence, le destinataire a un délai gratuit de garde ; ensuite des frais de gardiennage courent par colis et par jour.
Les valeurs par défaut sont 7 jours de franchise puis 500 FCFA par colis et par jour, mais chaque agence peut les régler autrement dans Logistique > Délais & Gardiennage.
Le logiciel calcule seul le dépassement à partir de la date d'entrée en rayon. Les pénalités sont dues au moment du retrait.

## Les statuts d'un colis
EN_PRÉPARATION : le colis est enregistré à l'agence de départ.
EN_TRANSIT : il est parti.
RÉCEPTIONNÉ : il est arrivé à l'agence d'arrivée.
LIVRÉ ou RETIRÉ : il a été remis au destinataire.
Le statut s'affiche en haut de la fiche du colis, à côté de son QR code.

## Retrouver un colis
Facturation & Colisage > Vue d'ensemble (tous les colis) liste tous les colis, avec des filtres et une recherche.
Facturation & Colisage > Filtre & Recherche ouvre la recherche des factures par période, agence et trajet, avec export PDF et Excel.
Depuis la liste, on ouvre la fiche d'un colis pour voir ses marchandises, sa facture, son statut et ses contacts.

## Autres envois : l'express (DHL et Colis Rapide)
Facturation & Colisage > Autres envois sert au suivi, à la saisie et à l'édition des factures des envois express, DHL et Colis Rapide.
Le bouton « Nouvel envoi express » ouvre le formulaire.
Pour un envoi DHL (documents, enveloppe), appliquez le forfait DHL, utilisez l'emballage carton officiel et gardez le numéro de bordereau DHL.

## La saisie DHL et sa rentabilité
Facturation & Colisage > Opération > DHL > DHL Express (Saisie) ouvre la saisie d'un envoi DHL.
On y renseigne le numéro de bordereau AWB DHL, puis 1. le montant encaissé auprès du client, 2. le coût d'achat DHL facturé à LBP ; le bénéfice net de LBP (3.) se calcule tout seul.
L'écran Facturation & Colisage > Opération > DHL > Rentabilité & Suivi DHL suit ces envois et leur marge.

## Le groupage et les manifestes
Facturation & Colisage > Groupage / Fret sert à planifier les voyages de groupage et à affecter les colis aux conteneurs ou palettes.
« Planifier un voyage » crée un manifeste : type de transport (aérien, maritime ou terrestre), agence de départ, agence de destination, date et heure de départ prévues.
Sur la fiche du voyage, on affecte les colis disponibles avec « Affecter au groupage », ou on les scanne pour les charger dans le manifeste.

## Préparer un départ : qui et pourquoi
Facturation & Colisage > Préparer un départ est réservé à l'agent export et au Directeur général ; les autres comptes ne voient pas ce menu.
L'agent export y saisit ce que dit le document de la compagnie (LTA en aérien, connaissement en maritime, lettre de voiture en routier, AWB en express DHL), tel qu'il est écrit.
On ne coche pas de colis : ce sont les chiffres du document de la compagnie qui comptent. Le Directeur général contrôle ensuite le départ avant de le valider.

## Préparer un départ : les dix colonnes
L'agent export choisit le mode (aérien, maritime, routier, express DHL), puis remplit les dix colonnes demandées par LBP :
1. Date de départ. 2. Compagnie. 3. LTA (ou le numéro du document de la compagnie). 4. Nombre de colis. 5. Poids total.
6. Transitaire au départ et son montant. 7. Transitaire à destination et son montant. 8. Livraison au départ et son coût. 9. Livraison à l'arrivée et son coût. 10. Emballages (carton, bôrô, valise, sac, palette, fût, autre).
Le prestataire se tape librement : s'il correspond à un prestataire connu, le départ s'y rattache.

## Préparer un départ : enregistrer, les colis partent d'office
En enregistrant le départ, tous les colis enregistrés pour cette destination et pas encore partis partent avec lui.
Le logiciel le rappelle avant d'enregistrer. Ces colis deviennent alors attendus à l'agence d'arrivée, qui pourra les pointer à la Réception des colis.
Si le formulaire est refusé, le logiciel affiche la liste complète des corrections à faire, sans perdre ce qui a été saisi.

## Préparer un départ : pièces jointes et soumission au Directeur général
On joint le document de la compagnie et les factures reçues (PDF, photo ou Excel, 10 Mo au maximum).
Le montant facturé de chaque poste se saisit avec la facture du prestataire correspondante.
Quand tout est complet, « Soumettre au Directeur général » envoie le départ en contrôle. Après soumission, l'agent ne peut plus le modifier, sauf s'il lui est renvoyé.
Les états d'un départ : En cours, Soumis au DG, À corriger, Validé, Repris. Un départ « À corriger » se reprend et se soumet de nouveau.

## Historique des envois et prestataires
Facturation & Colisage > Historique des envois liste les départs avec leurs dix colonnes. On filtre par agent, par période ou par recherche, puis on exporte en PDF ou Excel : l'export reprend exactement les départs affichés.
Facturation & Colisage > Transporteurs et prestataires tient la liste des compagnies, transitaires et livreurs proposés dans les départs.
Pour une compagnie aérienne, le préfixe LTA se saisit en 3 chiffres (par exemple 057 pour Air France) : il sert à contrôler ses numéros de LTA.

## Réception des colis : les trois choix
Facturation & Colisage > Réception des colis montre les départs attendus par votre agence.
Pour chaque colis, on choisit : Reçu, Reçu endommagé ou Non reçu. Un colis jamais pointé reste « En attente ».
Chaque choix est enregistré tout de suite, sans recharger la page : un agent interrompu au milieu de mille colis ne perd rien.
La liste se filtre (en attente, reçus, endommagés, non reçus) et se cherche par numéro sans quitter la page. L'écran est pensé pour le téléphone.
Tout utilisateur de l'agence peut pointer et corriger ; chaque geste est enregistré avec son auteur et son heure.

## Réception : le colis reçu endommagé
« Reçu endommagé » exige un commentaire qui décrit le dommage, par exemple « carton ouvert » ou « liquide renversé ».
Pourquoi : sans ce mot, l'agence d'envoi ne peut ni prévenir le client, ni se retourner contre le transporteur.
Le commentaire est facultatif pour les autres choix, et limité à 500 caractères.

## Réception : le colis non reçu et la règle des 24 heures
« Non reçu », dit par l'agent, rend le colis manquant tout de suite : l'agence d'envoi le voit sans attendre le lendemain et peut le chercher.
L'arrivée d'un départ, c'est son premier colis pointé. 24 heures après, tout colis encore jamais pointé est compté manquant.
Un départ qui a des colis non reçus reste dans la liste de réception, pour pouvoir corriger si le colis réapparaît.

## Réception : tout cocher et la douchette
« Tout cocher reçu » marque « Reçu » d'un coup tous les colis encore en attente d'un départ. Les colis déjà dits endommagés ou non reçus ne changent pas.
La douchette (le scanner) marque « Reçu » le colis scanné, même s'il avait été dit non reçu : le scanner, c'est l'avoir en main.
Un colis connu mais qui n'était pas attendu par l'agence est enregistré « reçu hors liste ».
Un code inconnu est refusé : on ne peut pas pointer un colis qui n'a jamais été saisi.

## Réception : corriger une erreur de pointage
Toute erreur se corrige : on repasse un colis de « Non reçu » à « Reçu », ou on le remet « En attente ».
Remettre en attente efface son commentaire. Chaque changement garde l'état d'avant, l'état d'après, le commentaire, l'auteur et l'heure.
Si le message « Rien à changer : le colis était déjà dans cet état » s'affiche, le pointage était déjà bon.

## Prévenir les clients de l'arrivée
Pointer un colis n'envoie aucun SMS : une erreur de pointage ne doit pas prévenir un client à tort.
Le bouton « Prévenir les clients », sur le départ, est un geste séparé : il envoie les SMS d'arrivée aux destinataires des colis pointés comme reçus.
Faites-le une fois le pointage vérifié.

## Suivi des départs
Facturation & Colisage > Suivi des départs montre, sur une période, les départs envoyés et reçus : colis envoyés, reçus, dont endommagés, non reçus, en attente, et le taux de réception, avec un tableau par trajet.
Le détail d'un départ montre chaque colis et l'historique des pointages ; il est visible des deux agences concernées.
Les heures sont celles de l'agence où le geste a eu lieu, avec l'heure de l'autre agence entre parenthèses quand elles diffèrent (Paris et Abidjan).
Si aucun départ n'apparaît, élargissez la période ou vérifiez que les départs ont bien été enregistrés.

## Rapport de réception
Facturation & Colisage > Rapport de réception donne, pour chaque départ : colis attendus, reçus, reçus endommagés, non reçus, en attente, manquants, et le taux de réception.
On choisit la période (un jour, la semaine du lundi au dimanche, ou du ... au ...), l'agence d'envoi, l'agence d'arrivée ou un départ précis. Le rapport s'exporte en PDF et en Excel.
Les chiffres sont ceux du moment où l'on tire le rapport. Le taux est arrondi vers le bas : 999 colis sur 1 000 font 99,9 %, jamais 100 %.
Une agence ne voit que les départs qu'elle a envoyés ou reçus ; la direction voit tout.

## Le résumé de réception de 18 heures
Chaque soir à 18 h, heure d'Abidjan, NOEL publie dans le groupe de messagerie de l'agence d'envoi un résumé par départ : combien de colis l'agence d'arrivée a reçus sur le total, combien en bon état, endommagés, non reçus et encore en attente, avec un lien vers le rapport.
Un résumé part pour chaque départ qui a eu des pointages dans la journée, ou dont le délai des 24 heures vient d'expirer.
La journée du résumé va de 18 h la veille à 18 h : un pointage fait à 18 h 30 entre dans le résumé du lendemain.
Un départ n'a qu'un résumé par jour.

## Rapports journaliers et reporting
Facturation & Colisage > Rapports Journaliers donne l'activité par agence, au jour et au mois : colis facturés en francs CFA et en euros, colis retirés, colis au-delà de la date limite de retrait, crédits entre agences.
Facturation & Colisage > Reporting donne le tonnage, le chiffre d'affaires et les délais sur une période.
Les Rapports Journaliers ne sont visibles qu'avec le droit correspondant.

## Les règles anti-fraude de la saisie
Chaque action est analysée automatiquement. Quatre règles évitent les signalements sur votre profil :
- Sous-déclaration : ne minorez pas la valeur déclarée des colis lourds ; le logiciel compare le rapport valeur/poids à l'historique de l'agence.
- Cumul des tâches : créer, encaisser et valider soi-même la même facture est signalé.
- Actions hors horaires : créer ou modifier un colis ou une facture la nuit ou le week-end sans autorisation est enregistré comme suspect.
- Modification après validation : modifier un colis ou une facture déjà clôturée ou payée déclenche une alerte ; obtenez d'abord l'accord écrit de la direction.

## Paramétrage du module
Facturation & Colisage > Paramétrage contient les réglages du module, dont le taux de change de l'euro et des autres devises utilisé pour afficher les totaux.
Le franc CFA a une parité fixe avec l'euro : 1 euro = 655,957 FCFA.
