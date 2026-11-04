# Finance

## Le module Finance
Le module Finance suit tout l'argent de LBP, du guichet jusqu'à la comptabilité.
Son menu est rangé en groupes : Pilotage (tableau de bord), Gestion des Fonds (demandes de fonds, prise en compte, imputation, historique, mouvements de caisse), Facturation & Caisse (factures clients, portefeuilles clients, points de caisse, appro caisse), Gestion des Coûts (dépenses prestataires, coûts d'approche), Trésorerie & Banque, Analyse & Comptabilité, et Aide.
Le guide du module se trouve dans Finance > Guide & Formation.
Certains écrans ne s'affichent que pour les rôles qui en ont besoin : c'est normal de ne pas tous les voir.

## Encaisser un règlement sur une facture
1. Ouvrir Finance > Factures Clients et retrouver la facture par son numéro ou le nom du client.
2. Cliquer « Consulter / Encaisser » pour ouvrir la fiche de la facture.
3. Descendre au formulaire d'encaissement, en bas de la fiche.
4. Saisir le montant reçu et choisir le mode : Espèces, Mobile Money (Wave, Orange, MTN) ou Carte bancaire.
5. Valider. Le reçu est alors éditable, et le solde de la facture se met à jour.
Si le client ne paie qu'une partie, on peut indiquer une nouvelle échéance pour le solde.

## Qui encaisse au guichet
Les rôles qui tiennent le guichet facturent le client et encaissent : caissière, caissière principale, chef d'agence, agent de saisie, agent d'enregistrement, gestionnaire de caisse, et le Directeur général.
Seule l'agence d'Abobo Dokui a une caissière. Dans les autres agences, c'est l'agent de saisie qui établit la facture, prend l'argent et soumet le point de caisse.
Les actes de contrôle restent au chef d'agence et à la direction : supprimer une facture, réinitialiser ses encaissements, consolider un point de caisse soumis par quelqu'un d'autre. Celui qui encaisse ne doit pas pouvoir effacer sa propre trace.

## Les statuts d'une facture
Une facture est « émise » tant que rien n'est encaissé, « partiellement payée » quand une partie est réglée, et « payée » quand le reste à payer est à zéro.
Un colis ne se remet au destinataire que si sa facture est « payée ».
Le numéro d'une facture commence par FA.

## Annuler un règlement saisi deux fois
Si un même règlement a été encaissé deux fois, ne corrigez pas la facture à la main.
Sur la fiche de la facture, section « Historique des Encaissements Physiques », cliquez sur « Annuler ce règlement » sur la ligne en trop.
Le motif est obligatoire (par exemple « saisi deux fois ») : c'est ce que lira la direction pour distinguer une erreur de saisie d'un encaissement escamoté. La confirmation rappelle le montant, pour ne pas supprimer la mauvaise ligne.
La facture se recalcule sur les règlements qui restent, et le point de caisse du jour est rouvert pour être recompté.
Peuvent annuler un règlement : caissière, caissière principale, chef d'agence, comptable et direction.

## Piège : corriger la facture ne corrige pas la caisse
La facture et les règlements sont deux choses différentes. Le rapport du jour et l'argent attendu dans le tiroir viennent des règlements enregistrés, pas du montant écrit sur la facture.
Si l'on corrige seulement la facture, les règlements en double restent : le rapport compte les deux, et la caissière paraît en manque de la différence.
Le bon geste est toujours « Annuler ce règlement » sur la ligne en trop.

## Annuler un règlement quand le point est consolidé
Si le point de caisse du jour du règlement est déjà consolidé, l'annulation est refusée avec le message « Cette correction doit passer par la direction ».
Dans ce cas, adressez-vous à la direction : la journée a déjà été vérifiée et centralisée.

## Réinitialiser une facture
« Réinitialiser » efface TOUS les règlements d'une facture et la remet à l'état « émise ».
C'est un acte de contrôle, réservé à la caissière, la caissière principale, le chef d'agence et la direction.
Pour un seul règlement en trop, n'utilisez pas « Réinitialiser » : utilisez « Annuler ce règlement ».

## Modifier une facture verrouillée
Une facture ne peut plus être modifiée par un agent après sa création.
Seuls le Directeur général et l'administrateur peuvent la modifier, depuis l'écran de recherche et d'audit des factures, et chaque champ modifié est enregistré (ancienne valeur et nouvelle valeur).
Pour un double encaissement, ce n'est pas la bonne voie : utilisez « Annuler ce règlement ».

## Le portefeuille client
Un client peut avoir un solde d'avance dans son portefeuille (Finance > Portefeuilles Clients).
Quand le client a un solde créditeur, la fiche de sa facture propose « Régler le solde via Portefeuille Client » : le montant est déduit du portefeuille et imputé sur la facture.
Ces règlements apparaissent dans le point de caisse sous la ligne « Portefeuille Client ».

## Relancer un client pour un solde impayé
Sur la fiche d'une facture non soldée, le bouton « Envoyer le Rappel de Solde » envoie un SMS de relance au client.
Le téléphone du client doit être juste pour que la relance arrive.

## Le point de caisse : à quoi il sert
Le point de caisse est le comptage du tiroir en fin de journée, comparé à ce que le logiciel attend.
Il y a un seul point par agence et par jour : c'est la caisse entière de l'agence qui se compte, pas celle d'un agent.
Il se fait dans Finance > Points de Caisse. L'écran montre en temps réel la position de caisse du jour, les encaissements par mode, les factures du jour et le journal des encaissements.
On l'appelle aussi la clôture, fermer la caisse ou boucler la journée.

## Soumettre un point de caisse, dans l'ordre
1. Ouvrir Finance > Points de Caisse et vérifier que la journée affichée est la bonne.
2. Compter physiquement l'argent du tiroir, billet par billet, dans la grille de comptage : le total se calcule tout seul.
3. Le montant compté est le comptage physique : ne recopiez pas le théorique, c'est lui qu'on vérifie.
4. Comparer avec le solde théorique affiché. Un écart s'explique, il ne se recopie pas : s'il y a un écart, l'explication est obligatoire.
5. Cliquer « Soumettre & Verrouiller la Caisse ». Le point passe à l'état « soumis » et la journée est verrouillée.
Attention : un appro validé entre en caisse au jour de la remise. Si un écart apparaît sans raison, regardez d'abord Finance > Appro Caisse.

## Qui soumet le point de caisse
Soumettent le point : la caissière, la caissière principale, le chef d'agence, l'agent de saisie et l'agent d'enregistrement, dans toutes les agences.
Le premier qui soumet verrouille la journée pour toute l'agence.
Ceux qui voient le cumul de l'agence voient toute la caisse. Le gestionnaire de caisse ne voit que ses propres opérations : il ne soumet pas, il s'adresse à la caissière ou à l'agent de saisie.

## Ce que chacun voit dans les points de caisse
La plupart des rôles du guichet et de la direction voient le cumul de l'agence : leurs opérations et celles de tous leurs collègues.
Un agent au périmètre restreint ne voit que ce qu'il a lui-même facturé et encaissé, pour aujourd'hui comme pour les journées passées. Un bandeau en haut de l'écran dit ce que les chiffres couvrent.
L'écart de caisse n'a de sens que pour la caisse entière : il n'est pas calculé pour un agent seul.

## Les états d'un point de caisse
« Temps réel (non soumis) » : la journée est en cours, le point est en brouillon.
« Soumis » : la caisse a été comptée et verrouillée par l'agence.
« Consolidé » : le point a été vérifié et centralisé par la caissière principale ou le Directeur général.
On ne consolide jamais un point que l'on a soi-même soumis : c'est le double contrôle.

## Caisse rouverte : un encaissement après la signature
Si un encaissement arrive après la soumission du point, la caisse est rouverte et le point repasse en brouillon.
Le message demande : « Recomptez votre caisse, puis fermez-la de nouveau avant de partir. »
Ce n'est pas une faute : c'est un comptage à refaire. Une agence qui signe à 15 h et encaisse jusqu'au soir a plus d'argent dans le tiroir que le chiffre signé.

## Alerte de clôture tardive
Après 15 h, si le point du jour n'est pas encore soumis, l'écran affiche une « Alerte clôture tardive » qui demande de faire le décompte des billets et de verrouiller la caisse.
Consigne de la direction : si l'agence ferme tôt, on peut laisser la caisse ouverte et faire le point le lendemain matin à l'arrivée.

## Caisse fermée : le point d'un jour précédent n'a pas été soumis
Depuis le 1er octobre 2026, une journée où de l'argent est entré doit être soumise. Tant qu'elle ne l'est pas, l'agence ne peut plus facturer ni encaisser le lendemain.
Le message dit : « Caisse fermée : le point du ... n'a pas été soumis », avec le montant encaissé ce jour-là qui n'a jamais été compté.
Pour rouvrir : comptez la caisse et soumettez ce point. La caisse se rouvre à la seconde. Si vous n'avez pas le droit de soumettre, demandez à la caissière ou à l'agent de saisie de votre agence.
Pourquoi : un argent jamais compté ne permet de voir aucun écart. Les journées d'avant le 1er octobre 2026 ne bloquent rien.

## Soumettre un point de caisse en retard
Le bloc de soumission rétroactive, dans Finance > Points de Caisse, permet de choisir une date passée sur le calendrier pour régulariser et soumettre un point oublié.
Le bouton devient alors « Soumettre le Point Rétroactif ».
Si le message « Le point de caisse de ce jour a déjà été soumis » s'affiche, la journée est déjà verrouillée.

## Imprimer les points de caisse
Finance > Points de Caisse propose le PDF de la journée de l'agence, un PDF de synthèse de toutes les agences pour ceux qui les voient toutes, et un rapport détaillé sur une plage de dates (« Générer le rapport détaillé »).
Le PDF de la journée peut s'imprimer à toute heure comme fiche intermédiaire.

## L'appro de caisse : ce que c'est
L'approvisionnement de caisse (l'appro) est l'argent que le siège remet à une agence.
Il se gère dans Finance > Appro Caisse. Une fois validé, il entre dans ce que le logiciel attend du tiroir de l'agence, à sa date de remise.
Tant qu'il n'est pas validé, il ne compte nulle part : un appro annoncé n'est pas un appro remis.
L'agence voit les appros qu'elle a reçus, pour comprendre son attendu du soir. Dans le point de caisse, une ligne « Appro de caisse (reçu du siège) » l'affiche, avec le calcul espèces + appro = attendu.

## Enregistrer un appro de caisse
1. Ouvrir Finance > Appro Caisse.
2. Choisir l'agence approvisionnée et saisir le montant remis, en FCFA ou en euros (Paris compte en euros).
3. Indiquer la date de remise : celle où l'argent a changé de main, pas celle de la saisie.
4. Choisir la provenance : Siège, Retrait bancaire, Autre agence (dire laquelle) ou Autre (dire quoi).
5. Écrire un motif clair : c'est ce que l'agence lira au moment du comptage.
Une agence ne peut pas s'approvisionner elle-même.

## Qui saisit et qui valide un appro
La caissière principale saisit l'appro, le comptable le valide. Deux mains, deux rôles : celui qui remet l'argent n'est pas celui qui l'inscrit aux comptes.
États : En attente de validation, Validé, Rejeté.
Un rejet doit dire pourquoi : la caissière principale corrigera d'après ce motif.
Un chef d'agence, une caissière ou un agent de saisie ne voit que les appros de son agence.

## Faire une demande de fonds
1. Ouvrir Finance > Demandes de Fonds et cliquer sur la création d'une demande (« Nouvelle demande de décaissement »).
2. Renseigner le cadre, fonctionnement ou traitement de dossier (transit), et le montant.
3. Expliquer le motif. C'est ce que lira la personne qui décidera : être précis évite un refus.
4. Enregistrer. La demande part en validation à la direction, puis en caisse pour paiement.
5. Suivre son état depuis la liste : à valider, validée, décaissée, puis imputée.

## Demandes de fonds : qui valide, qui décaisse
Depuis le 14 octobre 2026, celui qui valide n'est pas celui qui décaisse.
Valider ou rejeter : le Directeur général et l'assistante DG.
Décaisser : la caissière principale et le comptable (et le Directeur général).
Personne ne décaisse une demande qu'il a lui-même validée. Seul le Directeur général garde les deux gestes, pour qu'un jour d'absence ne bloque rien, mais jamais sur la même demande.
Un rejet se fait avec un motif.

## Décaisser une demande validée
1. Ouvrir Finance > Prise en Compte : seules les demandes déjà validées y figurent.
2. Cliquer « Décaisser » sur la ligne voulue : cela ouvre la fiche de la demande.
3. Descendre au panneau « Prise en compte caisse & décaissement », tout en bas.
4. Choisir le mode de paiement : espèces, mobile money, chèque ou virement.
5. Cliquer « Enregistrer le décaissement ». Le bon de sortie de caisse devient imprimable.
Le panneau n'apparaît pas si vous avez vous-même validé cette demande. Ce n'est pas une panne : personne ne décaisse ce qu'il a autorisé.

## Le bon de sortie de caisse
Le bon de sortie de caisse est le document officiel d'une sortie d'argent sur une demande de fonds.
Il devient disponible après le décaissement, avec le bouton « Imprimer le bon de sortie de caisse » sur la fiche de la demande.
On l'appelle aussi bon de caisse : il vient d'une demande de fonds, pas d'une facture.

## L'imputation d'une demande de fonds
Après le décaissement, la dépense doit être justifiée : c'est l'imputation, dans Finance > Imputation.
On y saisit le montant réel dépensé, le reliquat restitué en caisse s'il en reste, les références des pièces justificatives (quittances de douane, reçus, factures) et des observations comptables.
Une fois l'imputation validée, la demande est clôturée (« Dossier imputé et clôturé »). On n'impute qu'une demande décaissée, et une seule fois.

## L'historique des demandes de fonds
Finance > Historique montre chaque décision prise sur une demande de fonds : qui a validé, qui a rejeté, quand et pourquoi.
On filtre par dates, par statut ou par recherche (numéro de demande, motif, commentaire).
Une demande qui n'a jamais été décidée n'y apparaît pas.

## Les mouvements de caisse
Finance > Mouvements de Caisse rassemble ce qui entre et ce qui sort d'un tiroir dans la journée : solde de la veille, mouvement du jour, et ce qui doit rester ce soir.
Les règlements de factures, les appros validés et les décaissements de demandes de fonds y entrent tout seuls : ils se corrigent à leur source, pas ici.
Les entrées sont rangées en : règlements de factures Dossier, règlements de factures Colis, approvisionnement de caisse, autres versements. Les sorties en : traitement de dossier, traitement de colis, fonctionnement.
Les francs et les euros ne s'additionnent jamais : chaque caisse se lit dans sa monnaie.

## Saisir un versement ou un retrait dans les mouvements de caisse
La saisie manuelle ne sert qu'à ce que le logiciel ne connaît pas déjà : « Effectuer un versement (encaissement) » ou « Effectuer un retrait (décaissement) ».
Le mouvement est daté, rattaché à une caisse et à un cadre ; on note le bénéficiaire ou le déposant, la pièce (reçu, chèque) et, pour une sortie, à quoi elle sert : la ligne sera relue au comptage du soir.
Un appro ne se saisit jamais ici : il a son propre écran et entre tout seul.
Saisissent : caissières, caissière principale, gestionnaire de caisse, comptable, agent de saisie et agent d'enregistrement, chacun dans les caisses qu'il tient. Le journal de caisse s'imprime en PDF.

## Les dépenses prestataires
Finance > Dépenses Prestataires reçoit les demandes de règlement des prestataires régionaux.
Les superviseurs régionaux et généraux (et le Directeur général) soumettent la demande ; la caissière principale ou le Directeur général la paie ou la rejette.
On ne peut pas approuver une demande dont on est l'auteur.

## La comptabilité : livre journal et écritures
Finance > Livre Journal présente les écritures comptables, filtrables par journal (ventes, caisses, achats, banque, opérations diverses), par compte et par période.
Le comptable peut saisir une écriture manuelle (opération diverse) : date, journal, compte à débiter, compte à créditer, montant et libellé.
Une écriture erronée ne se supprime pas : on la contre-passe. Le bouton « Contre-passer » crée l'écriture inverse exacte, et l'originale reste visible.
Les écritures se lettrent avec un code, et s'exportent au format SYSCOHADA en CSV.

## Les autres écrans d'analyse
Finance > Plan Comptable : le plan comptable SYSCOHADA.
Finance > Balance des Comptes et Finance > Balance Âgée : les soldes des comptes et l'ancienneté des créances clients.
Finance > Rentabilité (P&L) : les résultats.
Finance > Trésorerie & Cashflow : les encaissements et décaissements prévus.
Finance > Rapprochement Mobile : le rapprochement des paiements Mobile Money.
Finance > Coûts d'Approche : les coûts d'acheminement saisis par lot.

## Francs CFA et euros
LBP travaille en francs CFA (XOF) et en euros (EUR), notamment pour les envois de et vers la France.
La parité est fixe : 1 euro = 655,957 FCFA.
La caisse de Paris se compte en euros ; un appro pour Paris se saisit donc en euros.
