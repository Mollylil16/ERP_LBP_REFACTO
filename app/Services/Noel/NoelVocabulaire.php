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
                'tiroir', 'brouillon', 'consolider',
                // « ma caisse » tout court : pour un agent de guichet, c'est
                // toujours de son point qu'il parle, jamais du meuble.
                'caisse', 'ma caisse', 'la caisse', 'sa caisse',
            ],
        ],
        'facture' => [
            'libelle' => 'les factures clients',
            'ecrans' => ['finance/factures'],
            'mots' => [
                'facture', 'factures', 'facturer', 'facturation', 'fature', 'factur',
                'encaisser', 'encaissement', 'reglement', 'regler', 'payer le client',
                'acompte', 'solde client', 'reste a payer', 'impaye', 'partiellement payee',
                'recu', 'bon de caisse', 'annuler un reglement', 'doublon',
            ],
        ],
        'colis' => [
            'libelle' => 'les colis',
            'ecrans' => ['colisage/parcels', 'logistique/colisage', 'colisage/dashboard'],
            'mots' => [
                'colis', 'coli', 'kolis', 'enregistrer un colis', 'saisir un colis',
                'saisie colis', 'nouveau colis', 'carton', 'cartons', 'bagage',
                'marchandise', 'marchandises', 'poids', 'peser', 'pesee', 'tracking',
                'numero de suivi', 'code colis', 'expediteur', 'destinataire',
            ],
        ],
        'groupage' => [
            'libelle' => 'le groupage et les expeditions',
            'ecrans' => ['colisage/groupage', 'logistique/groupage-codes-non-payes'],
            'mots' => [
                'groupage', 'groupe', 'expedition', 'depart', 'vol', 'cargo',
                'lta', 'manifeste', 'codes non payes', 'code non paye', 'impayes groupage',
                'preparer un depart', 'dossier envoi',
            ],
        ],
        'demande_fonds' => [
            'libelle' => 'les demandes de fonds',
            'ecrans' => ['finance/fonds'],
            'mots' => [
                'demande de fonds', 'demande fonds', 'demander de l argent',
                'besoin d argent', 'budget', 'depense a valider', 'valider une demande',
                'rejeter une demande', 'autorisation de depense',
            ],
        ],
        'decaissement' => [
            'libelle' => 'le decaissement',
            'ecrans' => ['finance/fonds/prise-en-compte'],
            'mots' => [
                'decaisser', 'decaissement', 'prise en compte', 'sortir l argent',
                'remettre l argent', 'payer la demande', 'bon de sortie',
            ],
        ],
        'appro' => [
            'libelle' => 'l approvisionnement de caisse',
            'ecrans' => ['finance/appro-caisse'],
            'mots' => [
                'appro', 'approvisionnement', 'approvisionner', 'recharger la caisse',
                'argent du siege', 'fonds de caisse', 'remise d argent',
            ],
        ],
        'mouvements_caisse' => [
            'libelle' => 'les mouvements de caisse',
            'ecrans' => ['finance/mouvements-caisse'],
            'mots' => [
                'mouvement de caisse', 'mouvements', 'entrees et sorties',
                'journal de caisse', 'ce qui est entre', 'ce qui est sorti',
            ],
        ],
        'pointage' => [
            'libelle' => 'le pointage des presences',
            'ecrans' => ['rh/pointage'],
            'mots' => [
                'pointage', 'pointer', 'presence', 'presences', 'absence', 'absences',
                'heure d arrivee', 'heures supplementaires', 'heures sup', 'retard',
                'demi journee', 'repos',
            ],
        ],
        'paie' => [
            'libelle' => 'la paie',
            /*
             * L'ecran RH d'abord, l'espace employe en repli. Un agent
             * ordinaire ne peut pas ouvrir la paie : NoelCarte ecartera la
             * premiere adresse et gardera la seconde, ou chacun lit son propre
             * bulletin. Sans ce repli, NOEL n'aurait rien a lui repondre.
             */
            'ecrans' => ['rh/paie', 'espace-employe/dashboard'],
            'mots' => [
                'paie', 'paye', 'salaire', 'salaires', 'bulletin', 'bulletin de paie',
                'fiche de paie', 'net a payer', 'cnps', 'igr', 'impot', 'retenue',
                'prime', 'gratification', 'sursalaire', 'acompte sur salaire',
            ],
        ],
        'conge' => [
            'libelle' => 'les conges',
            'ecrans' => ['rh/planning-conges', 'espace-employe/demandes'],
            'mots' => [
                'conge', 'conges', 'vacances', 'permission', 'repos annuel',
                'solde de conge', 'jour ferie', 'feries', 'absence autorisee',
            ],
        ],
        'dossier_personnel' => [
            'libelle' => 'les dossiers du personnel',
            'ecrans' => ['rh/personnel'],
            'mots' => [
                'dossier du personnel', 'dossier employe', 'fiche employe',
                'nouvel employe', 'integrer quelqu un', 'embauche', 'recrutement',
                'matricule', 'contrat de travail', 'mutation', 'depart', 'demission',
            ],
        ],
        'espace_employe' => [
            'libelle' => 'votre espace personnel',
            'ecrans' => ['espace-employe/dashboard', 'espace-employe/demandes'],
            'mots' => [
                'mon dossier', 'mon espace', 'mes documents', 'mon bulletin',
                'ma demande', 'faire une demande', 'mes conges', 'mon profil',
            ],
        ],
        'client' => [
            'libelle' => 'les clients',
            'ecrans' => ['crm/clients', 'finance/portefeuilles'],
            'mots' => [
                'client', 'clients', 'fiche client', 'nouveau client', 'contact',
                'portefeuille client', 'avance client', 'credit client', 'telephone du client',
            ],
        ],
        'entrepot' => [
            'libelle' => 'les entrepots et le stock',
            'ecrans' => ['entrepots/dashboard'],
            'mots' => [
                'entrepot', 'magasin', 'stock', 'rayon', 'emplacement', 'gardiennage',
                'stockage', 'emballage', 'emballages',
            ],
        ],
        'depense' => [
            'libelle' => 'les depenses',
            'ecrans' => ['finance/depenses'],
            'mots' => [
                'depense', 'depenses', 'prestataire', 'fournisseur', 'facture fournisseur',
                'note de frais', 'achat',
            ],
        ],
        'compte_acces' => [
            'libelle' => 'votre compte et vos acces',
            'ecrans' => ['admin/utilisateurs'],
            'mots' => [
                'mot de passe', 'mdp', 'connexion', 'me connecter', 'identifiant',
                'compte bloque', 'acces refuse', 'droit', 'droits', 'habilitation',
                'role', 'permission', 'je ne vois pas', 'pas autorise',
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
        'tchei', 'tchai',
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
    ];

    /** @var array<int, string> */
    public const REMERCIEMENT = [
        'merci', 'mercii', 'merci beaucoup', 'c est bon', 'ok merci', 'parfait',
        'nickel', 'super', 'ya fohi', 'y a fohi', 'ya foi', 'c est gbe', 'gbe',
        'bien recu', 'd accord merci',
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
        'ce serait bien si', 'il faudrait', 'ca serait mieux',
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
