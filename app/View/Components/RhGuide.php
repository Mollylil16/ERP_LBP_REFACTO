<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

/**
 * Le guide interactif du module RH.
 *
 * Un manuel se lit une fois puis se range. Ici on ne lit pas : on fait. Chaque
 * parcours est une suite de gestes a cocher, avec un bouton qui ouvre l'ecran
 * reel du logiciel. L'avancement est garde sur le poste de la personne, pour
 * qu'elle puisse s'arreter le soir et reprendre le lendemain ou elle en etait.
 *
 * Le contenu est ecrit ici plutot que range en base : ce sont les regles de
 * travail du module, elles changent avec le code qui les applique et doivent
 * etre relues en meme temps que lui. Chaque champ cite a ete releve dans les
 * formulaires eux-memes le 22/10/2026.
 *
 * L'ecran vit sous l'adresse /rh : le verrou de ModuleReserve s'y applique
 * seul, et il n'est donc visible que du DG, de la responsable RH et de
 * l'administrateur. Rien a ajouter ici pour cela.
 */
final class RhGuide
{
    /**
     * Les parcours : la vie reelle d'un collaborateur, geste apres geste.
     *
     * L'ordre des etapes n'est pas decoratif. Un dossier cree sans contrat
     * bloque la paie ; une sanction posee avant la demande d'explication est
     * indefendable. C'est cet ordre-la que le guide fait repeter.
     *
     * @var array<int, array<string, mixed>>
     */
    private const PARCOURS = [
        [
            'cle' => 'integrer',
            'titre' => 'Intégrer un collaborateur',
            'resume' => 'Du référentiel au contrat signé. Le parcours le plus fréquent, et celui où un oubli se paie le plus cher.',
            'etapes' => [
                [
                    'titre' => 'Vérifier que le référentiel est prêt',
                    'detail' => 'Avant de créer quoi que ce soit, s\'assurer que le service, la fonction, le statut et le site de la personne existent déjà dans les listes. Les créer maintenant évite de devoir interrompre la saisie.',
                    'url' => 'rh/parametrage',
                    'bouton' => 'Ouvrir le paramétrage',
                ],
                [
                    'titre' => 'Créer le dossier — identité',
                    'detail' => 'Le nom complet est le seul champ bloquant de tout le formulaire. Ne pas saisir le matricule : il se génère seul, d\'après le type de contrat et l\'ordre d\'intégration.',
                    'champs' => 'Nom complet, Sexe, Date de naissance, Situation matrimoniale, CNI, CNPS',
                    'url' => 'rh/personnel/nouveau',
                    'bouton' => 'Intégrer un collaborateur',
                ],
                [
                    'titre' => 'Renseigner l\'affectation et le contrat',
                    'detail' => 'La date de recrutement et la date de prise de service diffèrent souvent : c\'est la seconde qui compte pour le pointage. Laisser la durée vide pour un CDI.',
                    'champs' => 'Service, Fonction, Statut, Site, Date de recrutement, Date de prise de service, Durée en mois',
                ],
                [
                    'titre' => 'Compléter contacts et famille',
                    'detail' => 'Le contact d\'urgence se remplit systématiquement : c\'est le seul numéro utile le jour d\'un accident. Le nombre d\'enfants fait apparaître un emplacement d\'acte de naissance par enfant.',
                    'champs' => 'Téléphone, E-mail professionnel, Père, Mère, Contact d\'urgence, Nombre d\'enfants',
                ],
                [
                    'titre' => 'Déposer les pièces',
                    'detail' => 'Photo, acte de naissance, pièce d\'identité, diplôme, contrat. Rien n\'est obligatoire ici : mieux vaut créer le dossier tout de suite et compléter ensuite que d\'attendre un diplôme. Formats acceptés : PDF, DOC, DOCX, JPG, JPEG, PNG, WEBP.',
                ],
                [
                    'titre' => 'Créer son contrat',
                    'detail' => 'Étape la plus oubliée, et la plus lourde de conséquences : sans contrat enregistré ici, la paie ne pourra pas calculer le bulletin de cette personne. Renseigner la fin d\'essai déclenche l\'alerte de fin de période.',
                    'champs' => 'Collaborateur, Type, Référence, Début, Fin, Début essai, Fin essai',
                    'url' => 'rh/cycle-vie?section=contracts',
                    'bouton' => 'Ouvrir les contrats',
                ],
                [
                    'titre' => 'Contrôler le tableau de bord',
                    'detail' => 'L\'alerte « Contrats à paramétrer » doit avoir diminué d\'une unité. Si elle n\'a pas bougé, le contrat n\'a pas été enregistré.',
                    'url' => 'rh/dashboard',
                    'bouton' => 'Voir le tableau de bord',
                ],
            ],
        ],
        [
            'cle' => 'pointer',
            'titre' => 'Pointer une journée',
            'resume' => 'Le geste quotidien. Court, mais il ne se rattrape pas : une heure supplémentaire non pointée n\'est pas payée.',
            'etapes' => [
                [
                    'titre' => 'Ouvrir le pointage et choisir la date',
                    'detail' => 'Le logiciel affiche alors tous les collaborateurs, une ligne chacun, à remplir puis enregistrer en une seule fois.',
                    'url' => 'rh/pointage',
                    'bouton' => 'Ouvrir le pointage',
                ],
                [
                    'titre' => 'Donner un état à chacun',
                    'detail' => 'Six états possibles : Présent, Absent, Demi-journée, Mission, Congé, Repos. Aucune ligne ne doit rester sans état.',
                ],
                [
                    'titre' => 'Saisir les heures',
                    'detail' => 'Les heures supplémentaires alimentent directement la paie du mois. Une ligne laissée à zéro ne sera jamais rattrapée au moment du calcul.',
                    'champs' => 'Heure d\'arrivée, Heure de départ, Heures travaillées, Heures supplémentaires, Notes',
                ],
                [
                    'titre' => 'Enregistrer, et recommencer demain',
                    'detail' => 'Reconstituer un mois de présences de mémoire est à la fois faux et interminable. C\'est ce pointage qui justifiera une retenue en cas de contestation.',
                ],
            ],
        ],
        [
            'cle' => 'paie',
            'titre' => 'Faire une paie',
            'resume' => 'Quatre temps, toujours les mêmes. La dernière étape est définitive : tout vérifier avant.',
            'etapes' => [
                [
                    'titre' => 'Vider l\'alerte « Contrats à paramétrer »',
                    'detail' => 'Chaque salarié qui y figure sera absent de la paie. C\'est le premier endroit où regarder quand un bulletin manque.',
                    'url' => 'rh/dashboard',
                    'bouton' => 'Voir les alertes',
                ],
                [
                    'titre' => 'Ouvrir la période',
                    'detail' => 'Lui donner un code, une date de début et une date de fin. Adopter une écriture unique pour le code — par exemple 2026-10 — et s\'y tenir. La période passe à l\'état « Ouverte ».',
                    'champs' => 'Code période, Date de début, Date de fin',
                    'url' => 'rh/paie',
                    'bouton' => 'Ouvrir la paie',
                ],
                [
                    'titre' => 'Saisir les variables du mois',
                    'detail' => 'Pour chaque salarié, ce qui s\'ajoute et ce qui se retranche. Remplir les observations dès qu\'une retenue sort de l\'ordinaire : c\'est ce que lira le salarié qui conteste.',
                    'champs' => 'Salaire catégoriel, Sursalaire, Prime d\'ancienneté, Prime de congés payés, Gratification, Avantage en nature, Autres primes, CNPS salariale, Parts fiscales, Assurance maladie, Acompte, Autres retenues, Observations',
                ],
                [
                    'titre' => 'Calculer',
                    'detail' => 'Les bulletins sont produits et la période passe à « Calculée ». On peut encore revenir en arrière, corriger et recalculer autant de fois que nécessaire.',
                ],
                [
                    'titre' => 'Vérifier les bulletins',
                    'detail' => 'En cas de désaccord sur un montant, le moteur de paie donne le barème, les abattements avant impôt et les plafonds d\'exonération. C\'est une page de consultation : rien à y saisir.',
                    'url' => 'rh/paie/moteur',
                    'bouton' => 'Ouvrir le moteur de paie',
                ],
                [
                    'titre' => 'Clôturer',
                    'detail' => 'La période passe à « Clôturée ». Cette étape ne se défait pas. Ne la franchir qu\'après avoir relu les bulletins un par un.',
                ],
            ],
        ],
        [
            'cle' => 'discipline',
            'titre' => 'Mener une procédure disciplinaire',
            'resume' => 'L\'ordre des gestes est la procédure. C\'est l\'enchaînement horodaté dans le logiciel qui rend la décision défendable.',
            'etapes' => [
                [
                    'titre' => 'Rédiger la demande d\'explication',
                    'detail' => 'Elle précède toujours la sanction. Les faits constatés sont la partie décisive : être factuel et daté — « retard de 50 minutes le 12 octobre », jamais « manque de ponctualité ».',
                    'champs' => 'Objet, Faits constatés, Lieu ou cadre, Date ou période, Contexte, Explications attendues, Éléments complémentaires, Délai de réponse',
                    'url' => 'rh/explications',
                    'bouton' => 'Ouvrir les explications',
                ],
                [
                    'titre' => 'Laisser courir le délai',
                    'detail' => 'Le délai saisi court à partir de la demande. Au-delà, le dossier remonte en retard sur le tableau de bord.',
                ],
                [
                    'titre' => 'Enregistrer la réponse, ou relancer',
                    'detail' => 'Si le collaborateur répond, consigner sa réponse. S\'il ne répond pas, relancer — la relance est horodatée, et c\'est elle qui prouve qu\'il a eu l\'occasion de s\'expliquer.',
                ],
                [
                    'titre' => 'Clôturer la demande',
                    'detail' => 'Avec ou sans suite. Ne jamais clôturer un dossier resté sans réponse sans avoir relancé au moins une fois.',
                ],
                [
                    'titre' => 'Enregistrer la mesure, s\'il y a lieu',
                    'detail' => 'Avertissement, blâme, mise à pied ou autre. Le motif doit reprendre les faits, et la décision indiquer comment le collaborateur en a été informé.',
                    'champs' => 'Collaborateur, Mesure, Date, Motif, Décision / notification',
                    'url' => 'rh/cycle-vie?section=discipline',
                    'bouton' => 'Ouvrir le registre disciplinaire',
                ],
            ],
        ],
        [
            'cle' => 'depart',
            'titre' => 'Gérer un départ',
            'resume' => 'Et surtout : savoir quoi faire le jour où la personne revient.',
            'etapes' => [
                [
                    'titre' => 'Ouvrir la fiche du collaborateur',
                    'detail' => 'La sortie s\'enregistre depuis sa fiche, pas depuis un écran séparé.',
                    'url' => 'rh/personnel',
                    'bouton' => 'Ouvrir la liste du personnel',
                ],
                [
                    'titre' => 'Enregistrer la sortie',
                    'detail' => 'La date de sortie est le dernier jour travaillé. Le motif se choisit dans la liste tenue au paramétrage. Les observations consignent les circonstances.',
                    'champs' => 'Date de sortie, Motif, Observations',
                ],
                [
                    'titre' => 'Contrôler le registre des mouvements',
                    'detail' => 'Toutes les entrées et sorties y figurent avec leur date et leur motif. C\'est la pièce à produire lors d\'un contrôle ou d\'un point d\'effectif.',
                    'url' => 'rh/mouvements',
                    'bouton' => 'Voir les mouvements',
                ],
                [
                    'titre' => 'Si la personne revient : réintégrer, pas recréer',
                    'detail' => 'Le réflexe naturel est de créer un nouveau dossier. C\'est une erreur : on perd l\'ancienneté, l\'historique et les pièces. Retrouver l\'ancien dossier et utiliser la réintégration, qui ne demande qu\'une date.',
                ],
            ],
        ],
        [
            'cle' => 'annee',
            'titre' => 'Préparer l\'année',
            'resume' => 'À faire une fois, en janvier. Une heure de réglage qui évite douze mois de corrections.',
            'etapes' => [
                [
                    'titre' => 'Saisir les jours fériés des douze mois',
                    'detail' => 'Ces dates servent de référence au pointage et au calcul des congés. Un férié qui ne s\'applique pas à toutes les agences se désactive plutôt que de se supprimer.',
                    'url' => 'rh/feries',
                    'bouton' => 'Ouvrir les jours fériés',
                ],
                [
                    'titre' => 'Régler les règles de contrats',
                    'detail' => 'Pour chaque type — CDI, CDD, Stage, Prestataire — la durée de la période d\'essai, le nombre de renouvellements autorisés et le délai d\'alerte avant échéance. Avec un délai d\'alerte à zéro, aucune fin d\'essai ne sera signalée.',
                    'url' => 'rh/regles-contrats',
                    'bouton' => 'Ouvrir les règles de contrats',
                ],
                [
                    'titre' => 'Vérifier les signataires',
                    'detail' => 'Ces mentions figurent sur tous les documents RH imprimés. Une erreur ici se retrouve sur tous les contrats à la fois : à relire mot à mot avec la direction.',
                    'url' => 'rh/signataires',
                    'bouton' => 'Ouvrir les signataires',
                ],
                [
                    'titre' => 'Initialiser les soldes de congés',
                    'detail' => 'L\'alerte du tableau de bord liste les collaborateurs actifs dont le solde de départ n\'a jamais été renseigné.',
                    'url' => 'rh/dashboard',
                    'bouton' => 'Voir les alertes',
                ],
                [
                    'titre' => 'Planifier les évaluations',
                    'detail' => 'Annuelles, semestrielles, de fin d\'essai ou de fin de mission. Adopter une écriture unique pour la période — par exemple 2026 / S1 — sinon le registre devient illisible.',
                    'url' => 'rh/cycle-vie?section=evaluations',
                    'bouton' => 'Ouvrir les performances',
                ],
            ],
        ],
    ];

    /**
     * La partie reference : chaque ecran, ou il se trouve, ce qu'on y met.
     *
     * Les cinq ecrans marques « hors menu » ne figurent nulle part dans la
     * barre de gauche et ne s'atteignent que par les boutons du tableau de
     * bord. Sans cette mention, on les cherche en vain.
     *
     * @var array<int, array<string, mixed>>
     */
    private const ECRANS = [
        ['groupe' => 'Pilotage', 'nom' => 'Tableau de bord', 'url' => 'rh/dashboard',
         'role' => 'Trois lectures — Classique, Statistique, Analytique — cinq alertes et treize accès rapides. C\'est par ses boutons qu\'on atteint les écrans absents du menu.',
         'champs' => 'Alertes : Demandes employés, Contrats à paramétrer, Réclamations salariales, Explications à suivre, Soldes congés à initialiser'],

        ['groupe' => 'Collaborateurs', 'nom' => 'Dossiers du personnel', 'url' => 'rh/personnel',
         'role' => 'Le cœur du module. Tout le reste — contrats, paie, pointage, sanctions — s\'appuie sur ces dossiers. Création en quatre étapes.',
         'champs' => 'Nom complet (obligatoire), Matricule (auto), Sexe, Naissance, Situation matrimoniale, CNI, CNPS, Service, Fonction, Statut, Site, Dates, Durée, Contacts, Famille, Pièces'],

        ['groupe' => 'Collaborateurs', 'nom' => 'Organisation & carrière', 'url' => 'rh/cycle-vie?section=organization',
         'role' => 'Affiche la liste de tous les collaborateurs avec leur matricule. Écran de consultation : il ne permet pas de construire un organigramme hiérarchique.',
         'champs' => 'Aucune saisie'],

        ['groupe' => 'Collaborateurs', 'nom' => 'Promotions & mutations', 'url' => 'rh/mutations',
         'role' => 'Tout changement d\'affectation : service, poste, statut ou agence. Les champs laissés vides signifient « inchangé ».',
         'champs' => 'Titre, Motif, Nouveau service, Nouvelle fonction, Nouveau statut, Nouveau site, Nouvelle prise de service'],

        ['groupe' => 'Parcours & talents', 'nom' => 'Contrats & essais', 'url' => 'rh/cycle-vie?section=contracts',
         'role' => 'Le registre des contrats et la surveillance des périodes d\'essai. Un bandeau signale les essais à échéance avec un décompte en jours.',
         'champs' => 'Collaborateur, Type (CDI, CDD, Stage, Consultant), Référence, Début, Fin, Début essai, Fin essai'],

        ['groupe' => 'Parcours & talents', 'nom' => 'Missions & affectations', 'url' => 'rh/cycle-vie?section=assignments',
         'role' => 'Affectations internes : confier un projet ou un chantier sur une période donnée. À ne pas confondre avec les ordres de mission, qui concernent les déplacements.',
         'champs' => 'Collaborateur, Mission / projet, Code projet, Responsable, Site, Début, Fin, Instructions'],

        ['groupe' => 'Parcours & talents', 'nom' => 'Performances', 'url' => 'rh/cycle-vie?section=evaluations',
         'role' => 'Planification des entretiens d\'évaluation. C\'est un calendrier d\'échéances, pas un outil de notation. Seuls le DG et l\'administrateur peuvent supprimer une évaluation.',
         'champs' => 'Collaborateur, Évaluateur, Type (Annuelle, Semestrielle, Fin d\'essai, Fin de mission, Entretien professionnel), Période, Échéance'],

        ['groupe' => 'Parcours & talents', 'nom' => 'Formation', 'url' => 'rh/cycle-vie?section=trainings',
         'role' => 'Le catalogue des sessions. Une session se crée indépendamment des participants. Seuls le DG et l\'administrateur peuvent en supprimer une.',
         'champs' => 'Formation, Type (Interne, Externe, Obligatoire, Métier), Organisme, Début, Fin, Budget, Capacité'],

        ['groupe' => 'Entrées & sorties', 'nom' => 'Recrutement & onboarding', 'url' => 'rh/cycle-vie?section=recruitment',
         'role' => 'Page d\'orientation rappelant l\'ordre des opérations. Aucune saisie : ses boutons mènent à la création d\'un dossier et au registre des mouvements.',
         'champs' => 'Aucune saisie'],

        ['groupe' => 'Entrées & sorties', 'nom' => 'Départs & offboarding', 'url' => 'rh/mouvements',
         'role' => 'Le registre des entrées et des sorties. La sortie elle-même s\'enregistre depuis la fiche du collaborateur, tout comme la réintégration.',
         'champs' => 'Date de sortie, Motif, Observations — et, pour un retour, une simple date de réintégration'],

        ['groupe' => 'Administration RH', 'nom' => 'Temps & présence', 'url' => 'rh/pointage',
         'role' => 'Le pointage journalier. On choisit une date, le logiciel affiche tous les collaborateurs à remplir et à enregistrer en une fois.',
         'champs' => 'État (Présent, Absent, Demi-journée, Mission, Congé, Repos), Heure d\'arrivée, Heure de départ, Heures travaillées, Heures supplémentaires, Notes'],

        ['groupe' => 'Administration RH', 'nom' => 'Paie & variables', 'url' => 'rh/paie',
         'role' => 'Fonctionne par période mensuelle : ouvrir, saisir les variables, calculer, clôturer. Les états vont de « Ouverte » à « Calculée » puis « Clôturée », qui est définitif.',
         'champs' => 'Code période, Début, Fin, puis par salarié : primes, retenues, parts fiscales, acompte, observations'],

        ['groupe' => 'Administration RH', 'nom' => 'Discipline', 'url' => 'rh/cycle-vie?section=discipline',
         'role' => 'Le registre des mesures. Ce qui y est inscrit peut être produit devant l\'inspection du travail : être factuel et daté.',
         'champs' => 'Collaborateur, Mesure (Avertissement, Blâme, Mise à pied, Autre), Date, Motif, Décision / notification'],

        ['groupe' => 'Administration RH', 'nom' => 'Validations RH', 'url' => 'rh/cycle-vie?section=workflows',
         'role' => 'La corbeille de décisions, en deux tableaux : les demandes déposées par les collaborateurs, et le circuit Manager vers RH vers Direction. À vider chaque matin.',
         'champs' => 'Pour chaque ligne : approuver ou rejeter, en motivant. Un justificatif joint s\'ouvre dans un nouvel onglet'],

        ['groupe' => 'Administration RH', 'nom' => 'Planning congés', 'url' => 'rh/planning-conges',
         'role' => 'Le calendrier des jours fériés, mois par mois. Ces dates servent de référence au pointage et au calcul des congés.',
         'champs' => 'Nom du jour férié, Date — plus activer, désactiver ou supprimer'],

        ['groupe' => 'Configuration', 'nom' => 'Paramétrage', 'url' => 'rh/parametrage',
         'role' => 'Les six listes de référence qui alimentent toutes les listes déroulantes du module. Désactiver plutôt que supprimer : une entrée désactivée reste lisible sur les dossiers anciens.',
         'champs' => 'Services, Fonctions, Statuts, Motifs de sortie, Types de documents, Sites — chaque entrée ayant un libellé et un code généré si vide'],

        ['groupe' => 'Hors menu', 'nom' => 'Ordres de mission', 'url' => 'rh/missions',
         'role' => 'Les déplacements, avec un document formel. Un ordre reste modifiable tant qu\'il est en brouillon. États : Brouillon, Soumis, Approuvé, Rejeté, Annulé.',
         'champs' => 'Collaborateur, Objet / titre, Destination, Type de liaison (Chantier, Formation, Prospection, Autre), Début prévu, Fin prévue, But / contexte, Notes, Frais'],

        ['groupe' => 'Hors menu', 'nom' => 'Demandes d\'explication', 'url' => 'rh/explications',
         'role' => 'L\'étape qui précède toute sanction. Le formulaire est détaillé à dessein : il produit un document daté que le collaborateur devra signer.',
         'champs' => 'Collaborateur, Objet, Faits constatés, Lieu ou cadre, Date ou période, Contexte, Explications attendues, Éléments complémentaires, Délai de réponse'],

        ['groupe' => 'Hors menu', 'nom' => 'Règles de contrats', 'url' => 'rh/regles-contrats',
         'role' => 'Le comportement automatique du logiciel pour chaque type de contrat. À régler une fois, puis à oublier — mais il faut le régler.',
         'champs' => 'Durée de la période d\'essai (jours), Nombre maximal de renouvellements, Alerter avant échéance (jours)'],

        ['groupe' => 'Hors menu', 'nom' => 'Signataires RH', 'url' => 'rh/signataires',
         'role' => 'L\'en-tête et le pied de page de tous les documents RH imprimés : contrats, attestations, ordres de mission, demandes d\'explication.',
         'champs' => 'Employeur, Forme juridique, Capital, RCCM, Adresse, Ville de signature, Nom et titre du DG, Nom et titre RH, Texte de représentation, Pied de page lignes 1 et 2'],

        ['groupe' => 'Hors menu', 'nom' => 'Moteur de paie', 'url' => 'rh/paie/moteur',
         'role' => 'L\'écran de référence du calcul : barème, abattements avant impôt, plafonds d\'exonération. Page de consultation, utile pour justifier un montant contesté.',
         'champs' => 'Aucune saisie'],
    ];

    /**
     * Ce qu'il faut retenir si tout le reste est oublie.
     *
     * @var array<int, array<int, string>>
     */
    private const VIGILANCE = [
        ['Le module est invisible pour les autres', 'Seuls le DG, la responsable RH et l\'administrateur y entrent. Quelqu\'un d\'autre qui taperait l\'adresse reçoit une page introuvable, pas un refus. Ne pas élargir cette liste sans décision de la direction.'],
        ['Pas de contrat, pas de paie', 'L\'alerte « Contrats à paramétrer » est la première à traiter chaque mois. Tant qu\'un salarié y figure, son bulletin ne peut pas être calculé.'],
        ['Clôturer une période de paie est définitif', 'Relire les bulletins un par un avant de franchir cette étape.'],
        ['Le pointage se fait chaque jour', 'Les heures supplémentaires non pointées ne sont pas payées, et ne se rattrapent pas au moment du calcul.'],
        ['Un revenant se réintègre, il ne se recrée pas', 'Créer un second dossier fait perdre l\'ancienneté, l\'historique et les pièces.'],
        ['Explication d\'abord, sanction ensuite', 'C\'est cet enchaînement, horodaté dans le logiciel, qui rend la procédure défendable.'],
        ['Désactiver plutôt que supprimer', 'Une entrée de référentiel désactivée disparaît des listes mais reste lisible sur les dossiers anciens qui l\'utilisaient.'],
        ['Le matricule se génère tout seul', 'Ne pas le forcer à la création : il dépend du type de contrat et de l\'ordre d\'intégration.'],
    ];

    public static function page(): string
    {
        $entete = Ui::pageHeader(
            'Guide interactif du module RH',
            'Six parcours à dérouler geste après geste, la fiche de chaque écran, et ce qu\'il faut retenir.',
            ['eyebrow' => 'Aide & formation', 'class' => 'rh-hero-white']
        );

        $onglets = [
            ['cle' => 'parcours', 'label' => '1. Parcours guidés', 'contenu' => self::parcours()],
            ['cle' => 'ecrans', 'label' => '2. Les écrans, un par un', 'contenu' => self::ecrans()],
            ['cle' => 'retenir', 'label' => '3. À retenir', 'contenu' => self::retenir()],
        ];

        return '<div class="finea-shell"><div class="finea-container">'
            . self::styles()
            . $entete
            . self::recherche()
            . Tabs::panneaux($onglets, 'rhguide')
            . self::script()
            . '</div></div>';
    }

    // ------------------------------------------------------------------
    // Onglet 1 : les parcours
    // ------------------------------------------------------------------

    private static function parcours(): string
    {
        $html = '<p class="rhg-intro">Chaque parcours suit un moment réel de la vie d\'un collaborateur. '
            . 'Cochez les étapes au fur et à mesure : votre avancement est conservé sur ce poste, '
            . 'vous pouvez vous arrêter et reprendre plus tard.</p>'
            . '<div class="rhg-parcours-liste">';

        foreach (self::PARCOURS as $parcours) {
            $html .= self::unParcours($parcours);
        }

        return $html . '</div>'
            . '<div class="rhg-reset-zone">'
            . '<button type="button" class="rh-filter-btn rh-filter-btn--reset" data-rhg-reset>'
            . self::icone('recommencer') . 'Effacer mon avancement</button>'
            . '</div>';
    }

    /** @param array<string, mixed> $parcours */
    private static function unParcours(array $parcours): string
    {
        $cle = (string) $parcours['cle'];
        $etapes = (array) $parcours['etapes'];
        $total = count($etapes);

        $lignes = '';
        foreach (array_values($etapes) as $index => $etape) {
            $lignes .= self::uneEtape($cle, $index, (array) $etape);
        }

        /*
         * Le parcours s'ouvre deja deplie : un accordeon ferme sur six lignes
         * donne l'impression d'un ecran vide, et le premier geste demande est
         * alors de chercher ou cliquer.
         */
        return '<details class="rhg-parcours" data-rhg-parcours="' . View::e($cle) . '" open'
            . ' data-rhg-cherche="' . View::e(self::motsCles($parcours)) . '">'
            . '<summary>'
            . '<span class="rhg-parcours-titre">' . View::e((string) $parcours['titre']) . '</span>'
            . '<span class="rhg-parcours-compteur" data-rhg-compteur>0 / ' . $total . '</span>'
            . '<span class="rhg-jauge"><span class="rhg-jauge-barre" data-rhg-jauge style="width:0%"></span></span>'
            . '</summary>'
            . '<p class="rhg-parcours-resume">' . View::e((string) $parcours['resume']) . '</p>'
            . '<ol class="rhg-etapes">' . $lignes . '</ol>'
            . '</details>';
    }

    /** @param array<string, mixed> $etape */
    private static function uneEtape(string $cle, int $index, array $etape): string
    {
        $id = 'rhg-' . $cle . '-' . $index;

        $champs = isset($etape['champs'])
            ? '<p class="rhg-champs"><span>Champs concernés</span>' . View::e((string) $etape['champs']) . '</p>'
            : '';

        $bouton = '';
        if (isset($etape['url'])) {
            $bouton = '<a class="rhg-ouvrir" href="' . View::e(View::url((string) $etape['url'])) . '">'
                . self::icone('ouvrir') . View::e((string) ($etape['bouton'] ?? 'Ouvrir l\'écran')) . '</a>';
        }

        return '<li class="rhg-etape">'
            . '<input type="checkbox" class="rhg-coche" id="' . View::e($id) . '"'
            . ' data-rhg-etape="' . View::e($cle) . '">'
            . '<label for="' . View::e($id) . '" class="rhg-etape-corps">'
            . '<strong>' . View::e((string) $etape['titre']) . '</strong>'
            . '<span class="rhg-etape-detail">' . View::e((string) $etape['detail']) . '</span>'
            . '</label>'
            . $champs
            . $bouton
            . '</li>';
    }

    // ------------------------------------------------------------------
    // Onglet 2 : la fiche de chaque ecran
    // ------------------------------------------------------------------

    private static function ecrans(): string
    {
        $html = '<p class="rhg-intro">Les seize écrans du menu, plus les cinq qui n\'y figurent pas '
            . 'et ne s\'atteignent que depuis le tableau de bord. Dépliez celui qui vous intéresse.</p>';

        $groupeCourant = '';
        foreach (self::ECRANS as $ecran) {
            $groupe = (string) $ecran['groupe'];

            if ($groupe !== $groupeCourant) {
                $groupeCourant = $groupe;
                $html .= '<h3 class="rhg-groupe" data-rhg-groupe>' . View::e($groupe) . '</h3>';
            }

            $html .= '<details class="rhg-ecran" data-rhg-cherche="' . View::e(self::motsCles($ecran)) . '">'
                . '<summary><span class="rhg-ecran-nom">' . View::e((string) $ecran['nom']) . '</span>'
                . ($groupe === 'Hors menu'
                    ? '<span class="rhg-etiquette">Hors menu</span>'
                    : '')
                . '</summary>'
                . '<p class="rhg-ecran-role">' . View::e((string) $ecran['role']) . '</p>'
                . '<p class="rhg-champs"><span>Ce qu\'on y saisit</span>' . View::e((string) $ecran['champs']) . '</p>'
                . '<a class="rhg-ouvrir" href="' . View::e(View::url((string) $ecran['url'])) . '">'
                . self::icone('ouvrir') . 'Ouvrir cet écran</a>'
                . '</details>';
        }

        return $html;
    }

    // ------------------------------------------------------------------
    // Onglet 3 : a retenir
    // ------------------------------------------------------------------

    private static function retenir(): string
    {
        $html = '<p class="rhg-intro">Les huit règles qui évitent les erreurs coûteuses.</p>'
            . '<ol class="rhg-vigilance">';

        foreach (self::VIGILANCE as $point) {
            $html .= '<li data-rhg-cherche="' . View::e(mb_strtolower($point[0] . ' ' . $point[1])) . '">'
                . '<strong>' . View::e($point[0]) . '</strong>'
                . '<span>' . View::e($point[1]) . '</span></li>';
        }

        $html .= '</ol>';

        return $html . '<aside class="rhg-encadre rhg-encadre--attention"'
            . ' data-rhg-cherche="limites organigramme recrutement onboarding evolution">'
            . '<h4>Ce que le module ne fait pas encore</h4>'
            . '<p>L\'écran « Organisation &amp; carrière » n\'est qu\'une liste de collaborateurs : il ne permet pas '
            . 'de construire un organigramme hiérarchique. L\'écran « Recrutement &amp; onboarding » est une page '
            . 'd\'orientation, sans aucune saisie. Si ces fonctions sont attendues, ce sont des évolutions à demander '
            . 'plutôt que des réglages à chercher.</p></aside>';
    }

    // ------------------------------------------------------------------
    // Recherche
    // ------------------------------------------------------------------

    private static function recherche(): string
    {
        return '<div class="rhg-recherche">'
            . self::icone('loupe')
            . '<input type="search" id="rhg-recherche" placeholder="Chercher un champ, un écran, un geste — par exemple CNPS, acompte, mutation"'
            . ' autocomplete="off" data-rhg-recherche>'
            . '<span class="rhg-resultats" data-rhg-resultats hidden></span>'
            . '</div>';
    }

    // ------------------------------------------------------------------
    // Briques
    // ------------------------------------------------------------------

    /**
     * Les mots sur lesquels un bloc se laisse trouver.
     *
     * On y verse titre, resume, detail et noms de champs : la personne cherche
     * « CNPS » ou « acompte », c'est-a-dire le mot qu'elle a sous les yeux dans
     * un formulaire, pas le nom de l'ecran qui le porte.
     *
     * @param array<string, mixed> $bloc
     */
    private static function motsCles(array $bloc): string
    {
        $morceaux = [];

        foreach (['titre', 'nom', 'groupe', 'resume', 'role', 'champs'] as $champ) {
            if (isset($bloc[$champ]) && is_string($bloc[$champ])) {
                $morceaux[] = $bloc[$champ];
            }
        }

        foreach ((array) ($bloc['etapes'] ?? []) as $etape) {
            $etape = (array) $etape;
            $morceaux[] = (string) ($etape['titre'] ?? '');
            $morceaux[] = (string) ($etape['detail'] ?? '');
            $morceaux[] = (string) ($etape['champs'] ?? '');
        }

        return mb_strtolower(implode(' ', $morceaux));
    }

    /** Icones en SVG : un caractere emoji se rend differemment sur chaque poste. */
    private static function icone(string $nom): string
    {
        $traces = [
            'ouvrir' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>'
                . '<polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line>',
            'loupe' => '<circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>',
            'recommencer' => '<path d="M3 2v6h6"></path><path d="M3.51 15a9 9 0 1 0 2.13-9.36L3 8"></path>',
        ];

        return '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor"'
            . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . ($traces[$nom] ?? '') . '</svg>';
    }

    private static function styles(): string
    {
        return '<style>'
            . '.rhg-intro{color:#475569;max-width:70ch;line-height:1.65;margin:0 0 1.5rem;}'
            . '.rhg-recherche{position:relative;display:flex;align-items:center;gap:.6rem;background:#fff;'
            . 'border:1px solid #cbd5e1;border-radius:10px;padding:.7rem 1rem;margin:0 0 1.5rem;}'
            . '.rhg-recherche svg{color:#64748b;flex-shrink:0;}'
            . '.rhg-recherche input{flex:1;border:0;outline:0;font-size:.95rem;background:transparent;min-width:0;}'
            . '.rhg-resultats{font-size:.82rem;color:#0f766e;font-weight:700;white-space:nowrap;}'

            . '.rhg-parcours-liste{display:grid;gap:1rem;}'
            . '.rhg-parcours{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:1rem 1.25rem;}'
            . '.rhg-parcours>summary{cursor:pointer;display:grid;grid-template-columns:1fr auto;'
            . 'align-items:center;gap:.5rem 1rem;list-style:none;}'
            . '.rhg-parcours>summary::-webkit-details-marker{display:none;}'
            . '.rhg-parcours-titre{font-weight:800;font-size:1.05rem;color:#0f172a;}'
            . '.rhg-parcours-compteur{font-size:.82rem;color:#64748b;font-variant-numeric:tabular-nums;}'
            . '.rhg-jauge{grid-column:1 / -1;height:5px;background:#e2e8f0;border-radius:999px;overflow:hidden;}'
            . '.rhg-jauge-barre{display:block;height:100%;background:#0f766e;border-radius:999px;transition:width .25s;}'
            . '.rhg-parcours.est-fini .rhg-jauge-barre{background:#16a34a;}'
            . '.rhg-parcours.est-fini .rhg-parcours-compteur{color:#16a34a;font-weight:700;}'
            . '.rhg-parcours-resume{color:#475569;font-size:.92rem;margin:.75rem 0 0;line-height:1.6;}'

            . '.rhg-etapes{counter-reset:rhg;list-style:none;padding:0;margin:1rem 0 0;display:grid;gap:.6rem;}'
            . '.rhg-etape{counter-increment:rhg;display:grid;grid-template-columns:auto 1fr;gap:.25rem .75rem;'
            . 'background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:.85rem 1rem;}'
            . '.rhg-coche{width:18px;height:18px;margin:.2rem 0 0;cursor:pointer;accent-color:#0f766e;flex-shrink:0;}'
            . '.rhg-etape-corps{cursor:pointer;display:block;}'
            . '.rhg-etape-corps strong{display:block;color:#0f172a;font-size:.95rem;}'
            . '.rhg-etape-corps strong::before{content:counter(rhg) ". ";color:#0f766e;font-variant-numeric:tabular-nums;}'
            . '.rhg-etape-detail{display:block;color:#475569;font-size:.88rem;line-height:1.6;margin-top:.25rem;}'
            . '.rhg-etape.est-faite{background:#f0fdf4;border-color:#bbf7d0;}'
            . '.rhg-etape.est-faite .rhg-etape-corps strong{color:#15803d;text-decoration:line-through;}'
            . '.rhg-champs{grid-column:2;margin:.5rem 0 0;font-size:.83rem;color:#334155;background:#fff;'
            . 'border:1px dashed #cbd5e1;border-radius:6px;padding:.5rem .7rem;line-height:1.55;}'
            . '.rhg-champs span{display:block;font-size:.72rem;text-transform:uppercase;letter-spacing:.1em;'
            . 'color:#64748b;font-weight:700;margin-bottom:.2rem;}'
            . '.rhg-ouvrir{grid-column:2;justify-self:start;display:inline-flex;align-items:center;gap:.4rem;'
            . 'margin-top:.6rem;padding:.4rem .75rem;border:1px solid #0f766e;border-radius:6px;color:#0f766e;'
            . 'font-size:.83rem;font-weight:600;text-decoration:none;background:#fff;}'
            . '.rhg-ouvrir:hover{background:#0f766e;color:#fff;}'

            . '.rhg-reset-zone{margin-top:1.5rem;}'
            . '.rhg-groupe{font-size:.75rem;text-transform:uppercase;letter-spacing:.12em;color:#64748b;'
            . 'margin:1.75rem 0 .6rem;font-weight:800;}'
            . '.rhg-ecran{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:.85rem 1.1rem;'
            . 'margin-bottom:.55rem;}'
            . '.rhg-ecran>summary{cursor:pointer;display:flex;align-items:center;gap:.6rem;list-style:none;}'
            . '.rhg-ecran>summary::-webkit-details-marker{display:none;}'
            . '.rhg-ecran-nom{font-weight:700;color:#0f172a;}'
            . '.rhg-etiquette{font-size:.68rem;text-transform:uppercase;letter-spacing:.08em;background:#fef3c7;'
            . 'color:#92400e;border-radius:999px;padding:.12rem .5rem;font-weight:700;}'
            . '.rhg-ecran-role{color:#475569;font-size:.9rem;line-height:1.6;margin:.7rem 0 0;}'
            . '.rhg-ecran .rhg-champs,.rhg-ecran .rhg-ouvrir{grid-column:auto;}'

            . '.rhg-vigilance{counter-reset:vig;list-style:none;padding:0;margin:0;display:grid;gap:.6rem;}'
            . '.rhg-vigilance li{counter-increment:vig;background:#fff;border:1px solid #e2e8f0;'
            . 'border-left:4px solid #0f766e;border-radius:0 8px 8px 0;padding:.85rem 1.1rem;}'
            . '.rhg-vigilance strong{display:block;color:#0f172a;}'
            . '.rhg-vigilance strong::before{content:counter(vig) ". ";color:#0f766e;}'
            . '.rhg-vigilance span{display:block;color:#475569;font-size:.88rem;line-height:1.6;margin-top:.2rem;}'

            . '.rhg-encadre{border-left:4px solid;padding:1rem 1.15rem;border-radius:0 8px 8px 0;margin-top:1.5rem;}'
            . '.rhg-encadre h4{margin:0 0 .4rem;font-size:.95rem;}'
            . '.rhg-encadre p{margin:0;line-height:1.65;font-size:.9rem;}'
            . '.rhg-encadre--attention{background:#fffbeb;border-color:#d97706;}'
            . '.rhg-encadre--attention h4{color:#b45309;}'

            . '.rhg-masque{display:none !important;}'
            . '@media (max-width:640px){.rhg-etape{grid-template-columns:1fr;}'
            . '.rhg-coche{margin-bottom:.4rem;}'
            . '.rhg-champs,.rhg-ouvrir{grid-column:1;}}'
            . '</style>';
    }

    /**
     * Le comportement : cases cochees, jauges, recherche.
     *
     * L'avancement vit dans localStorage, donc sur le poste et non en base.
     * C'est un aide-memoire personnel, pas une donnee de l'entreprise : le
     * stocker en base reviendrait a dire a la direction qui en est ou dans sa
     * formation. Toute lecture et toute ecriture sont protegees — en navigation
     * privee l'acces leve une exception, et le guide doit rester utilisable.
     */
    private static function script(): string
    {
        return '<script>' . <<<'JS'
(function () {
    var CLE = 'lbp.rh.guide.v1';

    function lire() {
        try { return JSON.parse(window.localStorage.getItem(CLE) || '{}') || {}; }
        catch (e) { return {}; }
    }

    function ecrire(etat) {
        try { window.localStorage.setItem(CLE, JSON.stringify(etat)); } catch (e) {}
    }

    var etat = lire();

    function majParcours(parcours) {
        var cases = parcours.querySelectorAll('.rhg-coche');
        var faites = 0;
        for (var i = 0; i < cases.length; i++) {
            if (cases[i].checked) { faites++; }
            cases[i].closest('.rhg-etape').classList.toggle('est-faite', cases[i].checked);
        }
        var total = cases.length;
        var compteur = parcours.querySelector('[data-rhg-compteur]');
        var jauge = parcours.querySelector('[data-rhg-jauge]');
        if (compteur) { compteur.textContent = faites + ' / ' + total; }
        if (jauge) { jauge.style.width = (total ? Math.round(faites * 100 / total) : 0) + '%'; }
        parcours.classList.toggle('est-fini', total > 0 && faites === total);
    }

    var parcours = document.querySelectorAll('[data-rhg-parcours]');

    for (var p = 0; p < parcours.length; p++) {
        var cases = parcours[p].querySelectorAll('.rhg-coche');
        for (var c = 0; c < cases.length; c++) {
            cases[c].checked = etat[cases[c].id] === true;
            cases[c].addEventListener('change', function (ev) {
                etat[ev.target.id] = ev.target.checked;
                ecrire(etat);
                majParcours(ev.target.closest('[data-rhg-parcours]'));
            });
        }
        majParcours(parcours[p]);
    }

    var reset = document.querySelector('[data-rhg-reset]');
    if (reset) {
        reset.addEventListener('click', function () {
            etat = {};
            ecrire(etat);
            for (var i = 0; i < parcours.length; i++) {
                var cs = parcours[i].querySelectorAll('.rhg-coche');
                for (var j = 0; j < cs.length; j++) { cs[j].checked = false; }
                majParcours(parcours[i]);
            }
        });
    }

    /* La recherche traverse les trois onglets. Filtrer uniquement l'onglet
       ouvert donnerait « aucun resultat » sur un mot pourtant present deux
       onglets plus loin, et la recherche passerait pour cassee. */
    var champ = document.querySelector('[data-rhg-recherche]');
    var sortie = document.querySelector('[data-rhg-resultats]');
    var blocs = document.querySelectorAll('[data-rhg-cherche]');
    var panneaux = document.querySelectorAll('.finea-inline-pane');
    var groupes = document.querySelectorAll('[data-rhg-groupe]');

    function sansAccent(texte) {
        return texte.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    function filtrer() {
        var terme = sansAccent(champ.value.trim());
        var actif = terme.length >= 2;
        var trouves = 0;

        for (var i = 0; i < blocs.length; i++) {
            var ok = !actif || sansAccent(blocs[i].getAttribute('data-rhg-cherche') || '').indexOf(terme) !== -1;
            blocs[i].classList.toggle('rhg-masque', !ok);
            if (ok && actif) {
                trouves++;
                if (blocs[i].tagName === 'DETAILS') { blocs[i].open = true; }
            }
        }

        /* Les titres de groupe n'ont rien a dire pendant une recherche : les
           garder laisserait des intitules suivis de vide. */
        for (var g = 0; g < groupes.length; g++) {
            groupes[g].classList.toggle('rhg-masque', actif);
        }

        for (var n = 0; n < panneaux.length; n++) {
            if (actif) {
                panneaux[n].removeAttribute('hidden');
                panneaux[n].classList.add('is-active');
            } else if (!panneaux[n].hasAttribute('data-rhg-initial')) {
                panneaux[n].setAttribute('hidden', '');
                panneaux[n].classList.remove('is-active');
            }
        }

        if (sortie) {
            sortie.hidden = !actif;
            sortie.textContent = trouves + (trouves > 1 ? ' resultats' : ' resultat');
        }
    }

    if (champ) {
        for (var n = 0; n < panneaux.length; n++) {
            if (panneaux[n].classList.contains('is-active')) {
                panneaux[n].setAttribute('data-rhg-initial', '');
            }
        }
        champ.addEventListener('input', filtrer);
    }
})();
JS
            . '</script>';
    }
}
