<?php

declare(strict_types=1);

namespace App\Services\Noel;

/**
 * Le dictionnaire du metier, tel qu'on le parle vraiment a La Belle Porte.
 *
 * Personne ne tape « Je souhaiterais acceder a l'ecran de cloture de caisse ».
 * On tape « mon point passe pas », « je veux fermer ma caisse », « ya dra avec
 * mon point ». Un assistant qui n'entend que le vocabulaire du menu ne
 * comprendra jamais personne.
 *
 * Et c'est la bonne nouvelle : le vocabulaire d'un ERP est FINI. Les agents ne
 * parlent pas de mille choses — caisse, colis, facture, point, fonds,
 * pointage. Ecrire ce dictionnaire couvre donc la grande majorite des
 * questions reelles, sans aucune intelligence artificielle, et toujours avec
 * la bonne reponse plutot qu'une reponse inventee.
 *
 * Ce fichier n'attribue AUCUN droit : il traduit des mots en concepts. C'est
 * NoelCarte qui decide ensuite si la personne peut voir l'ecran correspondant.
 * Confondre les deux rendrait ce dictionnaire capable d'ouvrir des portes.
 */
final class NoelVocabulaire
{
    /**
     * Ce dont on parle, et ou ca se passe.
     *
     * Les adresses sont des candidates : NoelCarte les retient ou les ecarte
     * selon les droits de la personne. Un concept dont aucun ecran n'est
     * visible disparait simplement de la reponse.
     *
     * @var array<string, array{libelle: string, ecrans: array<int, string>, mots: array<int, string>}>
     */
    private const CONCEPTS = [
        'point_caisse' => [
            'libelle' => 'le point de caisse',
            'ecrans' => ['finance/clotures'],
            'mots' => [
                'point de caisse', 'point caisse', 'mon point', 'les points', 'pointa',
                'cloture', 'cloturer', 'cloture de caisse', 'fermer la caisse',
                'fermer ma caisse', 'fermeture caisse', 'soumettre', 'soumission',
                'envoyer mon point', 'declarer ma caisse', 'arreter la caisse',
                'comptage', 'compter la caisse', 'solde de caisse', 'ecart de caisse',
                'tiroir', 'brouillon', 'consolider', 'consolidation',
                'caisse', 'ma caisse', 'la caisse', 'sa caisse',
                'bordereau', 'bordereau de caisse', 'etat journalier', 'journee de caisse',
                'especes en caisse', 'le physique', 'physique declare', 'theorique',
                'rouvrir le point', 'reouverture', 'point du jour', 'point non soumis',
                'cloture du soir', 'faire ma journee', 'boucler la journee',
            ],
        ],
        'facture' => [
            'libelle' => 'les factures clients',
            'ecrans' => ['finance/factures'],
            'mots' => [
                'facture', 'factures', 'facturer', 'facturation', 'fature', 'factur',
                'encaisser', 'encaissement', 'encaissements', 'reglement', 'reglements',
                'regler', 'payer le client', 'paiement du client', 'paiement client',
                'acompte', 'solde client', 'reste a payer', 'restant du', 'impaye',
                'partiellement payee', 'recu', 'recu officiel', 'bon de caisse',
                'annuler un reglement', 'annuler le reglement', 'doublon', 'double encaissement',
                'encaisse deux fois', 'reinitialiser la facture', 'modifier la facture',
                'supprimer une facture', 'numero de facture', 'editer la facture',
                'imprimer la facture', 'qr code', 'mode de reglement', 'especes ou wave',
            ],
        ],
        'colis' => [
            'libelle' => 'les colis',
            'ecrans' => ['colisage/parcels', 'logistique/colisage', 'colisage/dashboard'],
            'mots' => [
                'colis', 'coli', 'kolis', 'colid', 'enregistrer un colis', 'saisir un colis',
                'saisie colis', 'saisie de colis', 'nouveau colis', 'creer un colis',
                'carton', 'cartons', 'bagage', 'bagages', 'valise', 'sac boro', 'boro',
                'marchandise', 'marchandises', 'contenu', 'nature du contenu',
                'poids', 'peser', 'pesee', 'poids total', 'kilo', 'kilos', 'kg',
                'tracking', 'numero de suivi', 'code colis', 'numero de tracking',
                'expediteur', 'destinataire', 'emballage', 'valeur declaree',
                'assurance', 'assurer le colis', 'fragile', 'declaration',
            ],
        ],
        'groupage' => [
            'libelle' => 'le groupage et les expeditions',
            'ecrans' => ['colisage/groupage', 'logistique/groupage-codes-non-payes'],
            'mots' => [
                'groupage', 'groupe', 'groupe code', 'expedition', 'expeditions',
                'depart', 'les departs', 'preparer un depart', 'dossier envoi',
                'dossier d envoi', 'vol', 'les vols', 'cargo', 'avion', 'bateau',
                'lta', 'numero de lta', 'manifeste', 'liste de colisage',
                'codes non payes', 'code non paye', 'impayes groupage', 'non paye',
                'creance', 'creances', 'client qui doit', 'dette client',
                'trajet', 'les trajets', 'categorie de code', 'lb ci', 'ca ci',
            ],
        ],
        'demande_fonds' => [
            'libelle' => 'les demandes de fonds',
            'ecrans' => ['finance/fonds'],
            'mots' => [
                'demande de fonds', 'demande fonds', 'demander de l argent',
                'besoin d argent', 'budget', 'depense a valider', 'valider une demande',
                'rejeter une demande', 'autorisation de depense', 'fonds',
                'demande de decaissement', 'faire une demande', 'ma demande de fonds',
                'validation de la demande', 'motif de la demande', 'imputation',
                'imputer', 'justificatif de depense', 'reliquat',
            ],
        ],
        'decaissement' => [
            'libelle' => 'le decaissement',
            'ecrans' => ['finance/fonds/prise-en-compte'],
            'mots' => [
                'decaisser', 'decaissement', 'prise en compte', 'sortir l argent',
                'remettre l argent', 'payer la demande', 'bon de sortie',
                'bon de sortie de caisse', 'donner l argent', 'sortie de caisse',
                'mode de decaissement', 'payer par cheque', 'payer par virement',
            ],
        ],
        'appro' => [
            'libelle' => 'l approvisionnement de caisse',
            'ecrans' => ['finance/appro-caisse'],
            'mots' => [
                'appro', 'approvisionnement', 'approvisionner', 'recharger la caisse',
                'argent du siege', 'fonds de caisse', 'remise d argent',
                'alimenter la caisse', 'appro caisse', 'date de remise',
                'valider l appro', 'provenance', 'recu du siege',
            ],
        ],
        'mouvements_caisse' => [
            'libelle' => 'les mouvements de caisse',
            'ecrans' => ['finance/mouvements-caisse'],
            'mots' => [
                'mouvement de caisse', 'mouvements', 'mouvements de caisse',
                'entrees et sorties', 'journal de caisse', 'ce qui est entre',
                'ce qui est sorti', 'historique de la caisse', 'les operations du jour',
            ],
        ],
        'pointage' => [
            'libelle' => 'le pointage des presences',
            'ecrans' => ['rh/pointage'],
            'mots' => [
                'pointage', 'pointer', 'presence', 'presences', 'absence', 'absences',
                'heure d arrivee', 'heure de depart', 'heures supplementaires',
                'heures sup', 'heure sup', 'retard', 'les retards', 'demi journee',
                'repos', 'jour de repos', 'liste de presence', 'feuille de presence',
                'pointer les agents', 'marquer absent', 'marquer present',
            ],
        ],
        'paie' => [
            'libelle' => 'la paie',
            'ecrans' => ['rh/paie', 'espace-employe/dashboard'],
            'mots' => [
                'paie', 'paye', 'salaire', 'salaires', 'bulletin', 'bulletin de paie',
                'bulletin de salaire', 'fiche de paie', 'net a payer', 'salaire net',
                'cnps', 'igr', 'impot', 'impot sur salaire', 'retenue', 'retenues',
                'prime', 'primes', 'gratification', 'sursalaire', 'salaire categoriel',
                'acompte sur salaire', 'avance sur salaire', 'parts fiscales',
                'periode de paie', 'cloturer la paie', 'calculer la paie',
                'prime d anciennete', 'avantage en nature', 'cotisation',
            ],
        ],
        'conge' => [
            'libelle' => 'les conges',
            'ecrans' => ['rh/planning-conges', 'espace-employe/demandes'],
            'mots' => [
                'conge', 'conges', 'vacances', 'permission', 'repos annuel',
                'solde de conge', 'mes conges', 'demander un conge', 'poser un conge',
                'jour ferie', 'feries', 'jours feries', 'absence autorisee',
                'planning des conges', 'reliquat de conge',
            ],
        ],
        'dossier_personnel' => [
            'libelle' => 'les dossiers du personnel',
            'ecrans' => ['rh/personnel'],
            'mots' => [
                'dossier du personnel', 'dossier employe', 'fiche employe',
                'nouvel employe', 'integrer quelqu un', 'embauche', 'embaucher',
                'recrutement', 'recruter', 'matricule', 'contrat de travail',
                'mutation', 'muter', 'depart', 'demission', 'licenciement',
                'sortie du personnel', 'reintegration', 'reintegrer', 'cni', 'piece d identite',
                'acte de naissance', 'diplome', 'periode d essai', 'fin d essai',
                'sanction', 'avertissement', 'blame', 'mise a pied', 'discipline',
                'demande d explication', 'explication',
            ],
        ],
        'espace_employe' => [
            'libelle' => 'votre espace personnel',
            'ecrans' => ['espace-employe/dashboard', 'espace-employe/demandes'],
            'mots' => [
                'mon dossier', 'mon espace', 'espace employe', 'mes documents',
                'mon bulletin', 'ma demande', 'faire une demande', 'mes conges',
                'mon profil', 'mes informations', 'mes papiers', 'mon contrat',
            ],
        ],
        'client' => [
            'libelle' => 'les clients',
            'ecrans' => ['crm/clients', 'finance/portefeuilles'],
            'mots' => [
                'client', 'clients', 'fiche client', 'nouveau client', 'creer un client',
                'contact', 'coordonnees du client', 'telephone du client',
                'portefeuille client', 'portefeuilles', 'avance client', 'credit client',
                'solde du client', 'compte client', 'crm', 'relance client',
                'reclamation', 'reclamation client', 'litige client', 'plainte',
            ],
        ],
        'entrepot' => [
            'libelle' => 'les entrepots et le stock',
            'ecrans' => ['entrepots/dashboard'],
            'mots' => [
                'entrepot', 'entrepots', 'magasin', 'stock', 'stocks', 'rayon',
                'emplacement', 'gardiennage', 'frais de gardiennage', 'stockage',
                'emballage', 'emballages', 'consommable', 'consommables',
                'inventaire', 'retrait', 'retirer un colis', 'enlevement',
            ],
        ],
        'depense' => [
            'libelle' => 'les depenses',
            'ecrans' => ['finance/depenses'],
            'mots' => [
                'depense', 'depenses', 'prestataire', 'prestataires', 'fournisseur',
                'facture fournisseur', 'note de frais', 'achat', 'achats',
                'cout d approche', 'couts d approche', 'charge', 'charges',
            ],
        ],
        'transit' => [
            'libelle' => 'le transit et la douane',
            'ecrans' => ['transit-douane/dashboard'],
            'mots' => [
                'transit', 'douane', 'douanier', 'dedouanement', 'dedouaner',
                'declaration en douane', 'droits de douane', 'taxe', 'taxes',
                'connaissement', 'conteneur', 'conteneurs', 'import', 'export',
                'importation', 'exportation', 'formalite', 'formalites',
            ],
        ],
        'suivi_colis' => [
            'libelle' => 'le suivi des colis',
            'ecrans' => ['tracking-colis/dashboard', 'logistique/colisage'],
            'mots' => [
                'suivi', 'suivi colisage', 'suivre un colis', 'ou est le colis',
                'localiser', 'localisation du colis', 'etat du colis', 'statut du colis',
                'colis en transit', 'colis arrive', 'colis livre', 'etape',
                'notifier le client', 'avis d arrivee',
            ],
        ],
        'flotte' => [
            'libelle' => 'la flotte et le transport',
            'ecrans' => ['flotte-transport/dashboard'],
            'mots' => [
                'vehicule', 'vehicules', 'camion', 'camions', 'voiture', 'moto',
                'chauffeur', 'chauffeurs', 'carburant', 'essence', 'gasoil',
                'entretien', 'reparation', 'assurance vehicule', 'visite technique',
                'flotte', 'course', 'livraison', 'tournee',
            ],
        ],
        'comptabilite' => [
            'libelle' => 'la comptabilite',
            'ecrans' => ['finance/comptabilite', 'finance/balance-comptes'],
            'mots' => [
                'comptabilite', 'compta', 'livre journal', 'grand livre', 'ecriture',
                'ecritures', 'ecriture comptable', 'plan comptable', 'compte comptable',
                'balance', 'balance des comptes', 'balance agee', 'lettrage', 'lettrer',
                'contre passer', 'contrepassation', 'bilan', 'rentabilite', 'resultat',
                'tresorerie', 'cashflow', 'rapprochement', 'rapprochement bancaire',
                'rapprochement mobile money',
            ],
        ],
        'compte_acces' => [
            'libelle' => 'votre compte et vos acces',
            'ecrans' => ['admin/utilisateurs'],
            'mots' => [
                'mot de passe', 'mdp', 'connexion', 'me connecter', 'identifiant',
                'compte bloque', 'compte desactive', 'acces refuse', 'droit', 'droits',
                'habilitation', 'habilitations', 'role', 'roles', 'permission',
                'permissions', 'je ne vois pas', 'pas autorise', 'page introuvable',
                'erreur 404', 'deconnecte', 'ca me deconnecte', 'session expiree',
                'jeton', 'creer un compte', 'nouvel utilisateur', 'changer mon mot de passe',
                'reinitialiser le mot de passe', 'je suis bloque',
            ],
        ],
        'impression' => [
            'libelle' => 'imprimer et exporter',
            'ecrans' => ['finance/factures', 'logistique/colisage'],
            'mots' => [
                'imprimer', 'impression', 'imprimante', 'pdf', 'editer en pdf',
                'tirer en pdf', 'tirer l etat', 'exporter', 'export', 'excel',
                'tableur', 'telecharger', 'sortir la liste', 'sortir le rapport',
                'etat en pdf', 'rapport pdf',
            ],
        ],
        'agence' => [
            'libelle' => 'les agences',
            'ecrans' => ['admin/agences', 'finance/dashboard'],
            'mots' => [
                'agence', 'agences', 'site', 'sites', 'dokui', 'abobo dokui',
                'adjame', 'aeroport', 'port bouet', 'bobigny', 'paris', 'senegal',
                'dakar', 'changer d agence', 'autre agence', 'mon agence',
            ],
        ],
    ];

    /**
     * Les facons de dire « ca ne marche pas ».
     *
     * Le francais d'ici et le nouchi d'Abidjan y sont, parce que c'est ce
     * qu'on tape quand on est presse et agace. Un agent bloque n'ecrit pas
     * « je rencontre une anomalie » : il ecrit « ya dra ».
     *
     * A relire par quelqu'un d'ici : cette liste a ete ecrite de bonne foi,
     * elle gagnera a etre corrigee et completee par ceux qui parlent la langue
     * tous les jours.
     *
     * @var array<int, string>
     */
    public const PANNE = [
        // Francais courant
        'marche pas', 'ne marche pas', 'fonctionne pas', 'ne fonctionne pas',
        'passe pas', 'ne passe pas', 'refuse', 'ca refuse', 'bloque', 'bloquee',
        'impossible', 'erreur', 'probleme', 'souci', 'panne', 'coince',
        'j arrive pas', 'je n arrive pas', 'arrive pas', 'ca veut pas',
        'ca charge pas', 'rien ne se passe', 'ecran blanc', 'page blanche',
        'sort pas', 'ne sort pas', 'veut pas sortir', 's affiche pas',
        'ne s affiche pas', 'apparait pas', 'n apparait pas', 'se lance pas',
        'enregistre pas', 'ne s enregistre pas', 'valide pas', 'ne valide pas',
        'introuvable', 'disparu', 'perdu', 'efface',
        // Francais d'ici et nouchi
        'ya dra', 'y a dra', 'ya drap', 'il y a dra',
        'ca gate', 'c est gate', 'ca a gate', 'gate',
        'c est chaud', 'ca chauffe', 'c est dur',
        'ca bloque dê', 'ca bloque de', 'ca passe pas de',
        'ya rien qui marche', 'ca fait quoi la',
        'tchei', 'tchai', 'ca fait comment', 'ca veut rien savoir',
        'ca me laisse pas', 'ca me refuse', 'ca dit non', 'ca repond rien',
        'y a un souci', 'il y a un souci', 'c est bloque', 'je suis bloque',
        'ca tourne', 'ca tourne en rond', 'ca charge toujours', 'ca rame',
        'ca plante', 'plante', 'ca saute', 'ca coupe', 'ca se ferme tout seul',
        'message d erreur', 'page d erreur', 'erreur 500', 'erreur 404',
        'je trouve pas', 'je ne trouve pas', 'introuvable', 'ca a disparu',
        'c est parti', 'ca s est efface', 'j ai perdu', 'ou est passe',
        'ca double', 'c est en double', 'deux fois', 'ca compte deux fois',
        'faux montant', 'mauvais montant', 'le chiffre est faux', 'ca colle pas',
        'ca correspond pas', 'y a un ecart', 'il manque', 'il manque de l argent',
        'bouge pas', 'ne bouge pas', 'est faux', 'c est faux', 'est fausse',
        'ne s ouvre pas', 'ouvre pas', 'ne se ferme pas', 'je peux pas',
        'je ne peux pas', 'pas possible', 'ca me laisse pas faire',
    ];

    /**
     * Les salutations, pour que NOEL reponde avant de travailler.
     *
     * « On dit quoi ? » et « C'est comment ? » sont les deux bonjours les plus
     * frequents a Abidjan. Les ignorer ferait repondre NOEL a cote de la
     * plaque des la premiere phrase, ce qui est la pire des entrees en matiere.
     *
     * @var array<int, string>
     */
    public const SALUTATION = [
        'bonjour', 'bonsoir', 'salut', 'bjr', 'bsr', 'slt', 'coucou', 'hello',
        'on dit quoi', 'c est comment', 'cest comment', 'comment ca va', 'ca va',
        'ca dit quoi', 'quoi de neuf', 'bonne arrivee', 'ya quoi', 'y a quoi',
        'bonne journee', 'bon matin', 'comment tu vas', 'comment allez vous',
        'ca va noel', 'noel', 'salut noel', 'bonjour noel', 'tu es la',
        'es tu la', 'tu m entends', 'on est ensemble', 'c est comment ce matin',
        'comment le travail', 'comment ca se passe', 'yako',
    ];

    /** @var array<int, string> */
    public const REMERCIEMENT = [
        'merci', 'mercii', 'merci beaucoup', 'c est bon', 'ok merci', 'parfait',
        'nickel', 'super', 'ya fohi', 'y a fohi', 'ya foi', 'c est gbe', 'gbe',
        'bien recu', 'd accord merci', 'merci noel', 'tres bien', 'ca marche',
        'c est clair', 'j ai compris', 'compris', 'tu es fort', 'bravo',
        'je te remercie', 'grand merci', 'ok c est bon', 'parfait merci',
    ];

    /**
     * Les demandes d'aide humaine : la personne veut qu'on intervienne.
     *
     * @var array<int, string>
     */
    public const SIGNALEMENT = [
        'signaler', 'prevenir', 'dis a l admin', 'dire a l administrateur',
        'remonter', 'faire remonter', 'j ai besoin d aide', 'aidez moi', 'aide moi',
        'donne moi le droit', 'donnez moi acces', 'il me faut l acces',
        'je veux proposer', 'suggestion', 'ameliorer', 'changer', 'modifier',
        'ce serait bien si', 'il faudrait', 'ca serait mieux', 'on devrait',
        'je propose', 'proposition', 'une idee', 'j ai une idee',
        'previens l administrateur', 'dis le a brunell', 'informe la direction',
        'fais remonter', 'transmets', 'signale ca', 'je signale',
        'j ai un probleme', 'il me faut', 'je voudrais pouvoir',
        'je n ai pas le droit', 'je n ai pas acces', 'donne moi l acces',
        'ouvre moi', 'autorise moi', 'debloque moi', 'il faut corriger',
        'c est a corriger', 'il y a une erreur dans', 'ca devrait etre',
    ];

    /**
     * Ce que la question cherche a obtenir.
     *
     * On ne repond pas pareil a « ou est l'ecran » et a « ca ne marche pas » :
     * la premiere attend un chemin, la seconde attend qu'on s'occupe d'elle.
     */
    public const INTENTION_OU = 'ou';
    public const INTENTION_COMMENT = 'comment';
    public const INTENTION_PANNE = 'panne';
    public const INTENTION_SIGNALEMENT = 'signalement';
    public const INTENTION_SALUTATION = 'salutation';
    public const INTENTION_REMERCIEMENT = 'remerciement';
    public const INTENTION_INCONNUE = 'inconnue';

    /**
     * Lire une phrase : de quoi parle-t-elle, et qu'attend-elle ?
     *
     * @return array{concept: ?string, libelle: ?string, ecrans: array<int, string>, intention: string, score: int}
     */
    public static function comprendre(string $phrase): array
    {
        $texte = ' ' . NoelCarte::sansAccent($phrase) . ' ';
        $texte = preg_replace('/[^a-z0-9 ]+/', ' ', $texte) ?? $texte;
        $texte = (string) preg_replace('/\s+/', ' ', $texte);

        $concept = self::meilleurConcept($texte);

        return [
            'concept' => $concept['cle'],
            'libelle' => $concept['cle'] !== null ? self::CONCEPTS[$concept['cle']]['libelle'] : null,
            'ecrans' => $concept['cle'] !== null ? self::CONCEPTS[$concept['cle']]['ecrans'] : [],
            'intention' => self::intention($texte, $concept['cle'] !== null),
            'score' => $concept['score'],
        ];
    }

    /**
     * Le concept dont le vocabulaire colle le mieux a la phrase.
     *
     * Une expression de plusieurs mots vaut plus qu'un mot seul : « point de
     * caisse » doit l'emporter sur « caisse » croise par hasard ailleurs.
     *
     * @return array{cle: ?string, score: int}
     */
    private static function meilleurConcept(string $texte): array
    {
        $meilleur = ['cle' => null, 'score' => 0];

        foreach (self::CONCEPTS as $cle => $concept) {
            $score = 0;

            foreach ($concept['mots'] as $mot) {
                $mot = NoelCarte::sansAccent($mot);

                if (str_contains($texte, ' ' . $mot . ' ')) {
                    $score += 1 + substr_count($mot, ' ') * 2;
                    continue;
                }

                // Les fautes de frappe : « fature », « kolis », « pointa ».
                // On ne tolere l'approximation que sur les mots assez longs,
                // sinon « paie » et « page » deviendraient le meme mot.
                if (mb_strlen($mot) >= 6 && !str_contains($mot, ' ')) {
                    foreach (explode(' ', trim($texte)) as $tape) {
                        if (mb_strlen($tape) >= 5 && levenshtein($tape, $mot) <= 1) {
                            $score += 1;
                            break;
                        }
                    }
                }
            }

            if ($score > $meilleur['score']) {
                $meilleur = ['cle' => $cle, 'score' => $score];
            }
        }

        return $meilleur;
    }

    private static function intention(string $texte, bool $conceptTrouve): string
    {
        // L'ordre compte. Une phrase peut contenir « merci » et une panne ; ce
        // qui attend une action passe devant ce qui n'en attend pas.
        if (self::contient($texte, self::SIGNALEMENT)) {
            return self::INTENTION_SIGNALEMENT;
        }

        if (self::contient($texte, self::PANNE)) {
            return self::INTENTION_PANNE;
        }

        /*
         * Le bonjour passe avant la question SEULEMENT si la phrase ne parle
         * de rien d'autre. « C'est comment ? » est un bonjour a Abidjan, mais
         * « bonjour, c'est comment pour la caisse ? » est une vraie question —
         * et y repondre « bonjour a vous aussi » serait insupportable.
         */
        if (!$conceptTrouve && self::contient($texte, self::SALUTATION)) {
            return self::INTENTION_SALUTATION;
        }

        if (str_contains($texte, ' comment ') || str_contains($texte, ' pourquoi ')) {
            return self::INTENTION_COMMENT;
        }

        if (str_contains($texte, ' ou ') || str_contains($texte, ' trouve ') || str_contains($texte, ' cherche ')) {
            return self::INTENTION_OU;
        }

        if (self::contient($texte, self::REMERCIEMENT)) {
            return self::INTENTION_REMERCIEMENT;
        }

        if (self::contient($texte, self::SALUTATION)) {
            return self::INTENTION_SALUTATION;
        }

        // Un concept reconnu sans verbe : « le point de caisse ». La personne
        // veut y aller — c'est la lecture la plus utile, et la moins risquee.
        return $conceptTrouve ? self::INTENTION_OU : self::INTENTION_INCONNUE;
    }

    /** @param array<int, string> $expressions */
    private static function contient(string $texte, array $expressions): bool
    {
        foreach ($expressions as $expression) {
            if (str_contains($texte, ' ' . NoelCarte::sansAccent($expression) . ' ')) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> */
    public static function concepts(): array
    {
        return array_keys(self::CONCEPTS);
    }

    /** Le nombre total d'expressions comprises : sert a mesurer la couverture. */
    public static function tailleDuDictionnaire(): int
    {
        $total = count(self::PANNE) + count(self::SALUTATION)
            + count(self::REMERCIEMENT) + count(self::SIGNALEMENT);

        foreach (self::CONCEPTS as $concept) {
            $total += count($concept['mots']);
        }

        return $total;
    }
}
