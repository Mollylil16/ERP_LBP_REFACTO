<?php

declare(strict_types=1);

namespace App\Services\Noel;

use App\Helpers\View;
use App\Security\NoelAcces;
use Throwable;

/**
 * La bouche de NOEL : ce qu'il repond quand on lui ecrit.
 *
 * Tout le reste etait le cerveau — la carte du logiciel, le dictionnaire, le
 * journal, la veille, le mot du matin. Cette classe les assemble en une
 * reponse, et elle seule decide de ce qui sort.
 *
 * TROIS REGLES QU'ELLE NE TRANSGRESSE JAMAIS.
 *
 * Elle ne nomme que les ecrans que la personne peut ouvrir. Si le concept est
 * compris mais qu'aucun ecran ne lui est accessible, NOEL ne dit pas « c'est
 * dans Finance, mais vous n'y avez pas droit » : il propose de transmettre.
 * Nommer une porte fermee, c'est apprendre a quelqu'un qu'il y a une porte.
 *
 * Elle prefere avouer plutot qu'inventer. Un assistant qui improvise une
 * procedure est pire qu'aucun assistant : on suit son conseil, et c'est faux.
 *
 * Elle ne transmet rien sans accord. Une question incomprise peut etre une
 * demande d'aide comme une confidence.
 */
final class NoelRepondeur
{
    private NoelCarte $carte;
    private ?NoelJournal $journal;
    private ?NoelMesure $mesure;

    public function __construct(
        ?NoelCarte $carte = null,
        ?NoelJournal $journal = null,
        ?NoelMesure $mesure = null
    ) {
        $this->carte = $carte ?? new NoelCarte();
        $this->journal = $journal;
        $this->mesure = $mesure;
    }

    /**
     * La reponse a un message.
     *
     * @return array{texte: string, liens: array<int, array{label: string, url: string}>, attente: ?string}
     */
    public function repondre(string $message, int $conversationId = 0): array
    {
        /*
         * D'abord : est-ce la reponse a ma derniere question ?
         *
         * NOEL pose des questions fermees — « voulez-vous que je
         * transmette ? », « qui suis-je ? ». Les lire comme des questions
         * neuves lui ferait repondre « je n'ai pas compris » a un « oui »
         * parfaitement clair, et c'est pire que de ne rien proposer.
         */
        $fil = NoelFil::dernier($conversationId);
        $suite = $this->suiteDuFil($message, $fil, $conversationId);

        if ($suite !== null) {
            return $suite;
        }

        $lu = NoelVocabulaire::comprendre($message);

        /*
         * « Et comment je fais ? » ne nomme rien : c'est le fil qui porte le
         * sujet. Sans cela, NOEL repondrait « je n'ai pas compris » a une
         * question limpide pour qui a lu les deux lignes precedentes.
         */
        if ($lu['concept'] === null && $lu['module'] === null && NoelVocabulaire::estUneRelance($message)) {
            $lu['concept'] = $fil['concept'];
            $lu['module'] = $fil['module'];

            if ($lu['concept'] !== null || $lu['module'] !== null) {
                $lu['intention'] = NoelVocabulaire::INTENTION_PROCEDURE;
            }
        }

        /*
         * « Comment encaisser un reglement » et « comment je fais pour
         * encaisser un reglement » sont la meme question.
         *
         * Seule la seconde portait un marqueur de procedure ; la premiere
         * tombait sur « comment » et recevait « C est ici. » suivi d un lien,
         * la ou l autre recevait les cinq etapes. Mesure le 04/11/2026 : sur
         * quatre facons de demander la meme chose, deux reponses.
         *
         * On ne devine pas pour autant : la question ne devient une procedure
         * que s il en existe une d ecrite. Sinon elle reste ce qu elle etait.
         */
        if ($lu['intention'] === NoelVocabulaire::INTENTION_COMMENT
            && NoelProcedure::pour($lu['concept'] ?? null, $lu['module'] ?? null) !== null) {
            $lu['intention'] = NoelVocabulaire::INTENTION_PROCEDURE;
        }

        /*
         * Un numero de suivi dans la question vaut mieux que tout le reste.
         *
         * « Ou est le colis LB-CI-0042 » recevait un lien vers la liste
         * entiere, alors que le numero etait dans la phrase. C est la question
         * la plus posee au guichet.
         *
         * Elle passe avant le classement par concept : qui donne un numero ne
         * demande pas ou se trouve l ecran des colis.
         */
        $fiche = $this->ficheDuColis($message);

        $this->mesurer($lu);

        $reponse = $fiche ?? match ($lu['intention']) {
            NoelVocabulaire::INTENTION_SALUTATION => $this->bonjour(),
            NoelVocabulaire::INTENTION_REMERCIEMENT => $this->deRien(),
            NoelVocabulaire::INTENTION_SIGNALEMENT => $this->signalement($message, $lu),
            NoelVocabulaire::INTENTION_PANNE => $this->panne($message, $lu),
            NoelVocabulaire::INTENTION_PRESENTATION => $this->presenter($lu),
            NoelVocabulaire::INTENTION_CHIFFRE => $this->chiffrer($message, $lu),
            NoelVocabulaire::INTENTION_PROCEDURE => $this->marcheASuivre($message, $lu),
            NoelVocabulaire::INTENTION_INCONNUE => $this->jeNeSaisPas($message, $lu),
            default => $this->orienter($lu),
        };

        NoelFil::retenir($conversationId, [
            'concept' => $lu['concept'] ?? $fil['concept'],
            'module' => $lu['module'] ?? $fil['module'],
            'attente' => $reponse['attente'],
            'question' => $reponse['attente'] === 'transmission' ? $message : null,
            'essais' => 0,
        ]);

        return $reponse;
    }

    /**
     * La reponse a la derniere question de NOEL, s'il en attendait une.
     *
     * @param array{concept: ?string, module: ?string, attente: ?string, question: ?string, essais: int} $fil
     *
     * @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string}|null
     */
    private function suiteDuFil(string $message, array $fil, int $conversationId): ?array
    {
        if ($fil['attente'] === 'transmission') {
            if (NoelVocabulaire::estOui($message)) {
                return $this->transmettre($fil, $conversationId);
            }

            if (NoelVocabulaire::estNon($message)) {
                NoelFil::oublierAttente($conversationId);

                return self::reponse("Très bien, je ne transmets rien. Dites-moi si vous changez d'avis.");
            }

            // Autre chose : la personne est passee a une nouvelle question.
            // On laisse tomber l'attente plutot que de la harceler.
            NoelFil::oublierAttente($conversationId);

            return null;
        }

        if ($fil['attente'] === 'enigme') {
            return $this->enigme($message, $fil, $conversationId);
        }

        return null;
    }

    /**
     * La personne accepte : la question part chez l'administrateur.
     *
     * @param array{question: ?string, concept: ?string} $fil
     *
     * @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string}
     */
    private function transmettre(array $fil, int $conversationId): array
    {
        NoelFil::oublierAttente($conversationId);

        $question = (string) ($fil['question'] ?? '');

        if (trim($question) === '') {
            return self::reponse("Je n'ai plus votre question sous la main. Reformulez-la et je la transmets.");
        }

        if (self::enTest()) {
            return self::reponse("C'est transmis a l'administrateur. Je vous previens des que c'est regle.");
        }

        $id = (new NoelApprentissage())->transmettre($question, $fil['concept'] ?? null);

        if ($id === null) {
            return self::reponse(
                "Je n'ai pas reussi a transmettre votre question. Dites-la de vive voix a l'administrateur, "
                    . "c'est plus sur que d'attendre."
            );
        }

        return self::reponse(
            "C'est transmis a l'administrateur. Je vous previens ici des que c'est regle, "
                . "et j'apprendrai au passage."
        );
    }

    /**
     * L'enigme du matin : trois essais, puis la solution et le pourquoi.
     *
     * @param array{essais: int} $fil
     *
     * @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string}|null
     */
    private function enigme(string $message, array $fil, int $conversationId): ?array
    {
        $enigme = NoelMotDuMatin::enigmeDuJour();

        if ($enigme === null) {
            NoelFil::oublierAttente($conversationId);

            return null;
        }

        /*
         * Une vraie question posee pendant l'enigme n'est pas une tentative.
         * On ne compte un essai que pour une phrase courte qui ne parle de
         * rien d'autre — sinon demander « ou est ma caisse » couterait une
         * chance a quelqu'un qui ne jouait pas.
         */
        $juste = NoelEnigme::correspond($message, $enigme);
        $lu = NoelVocabulaire::comprendre($message);
        $uneTentative = $juste
            || (mb_strlen(trim($message)) <= 40 && $lu['concept'] === null && $lu['module'] === null);

        if (!$uneTentative) {
            return null;
        }

        $verdict = NoelEnigme::juger($message, $enigme, (int) $fil['essais']);

        if ($verdict['verdict'] === NoelEnigme::ENCORE) {
            NoelFil::retenir($conversationId, array_merge($fil, [
                'attente' => 'enigme',
                'essais' => $verdict['essais'],
            ]));

            return self::reponse($verdict['message']);
        }

        NoelFil::oublierAttente($conversationId);

        $texte = $verdict['message'];

        if ($verdict['pourquoi'] !== null) {
            // Une ligne vide entre la reponse et son pourquoi : colles l un a
            // l autre, « La lettre R Dans le mot Paris » se lit de travers.
            $texte .= "

" . $verdict['pourquoi'];
        }

        return self::reponse($texte);
    }

    // ------------------------------------------------------------------
    // Les reponses
    // ------------------------------------------------------------------

    /** @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string} */
    private function bonjour(): array
    {
        $mot = NoelMotDuMatin::duJour(null, NoelAcces::prenom());

        $texte = $mot['salutation'] . '. ' . $mot['texte'];

        if ($mot['categorie'] === NoelMotDuMatin::ENIGME) {
            // On ne donne pas la reponse : chercher trente secondes est tout
            // l'interet du matin. Trois essais, puis la solution et le pourquoi.
            return self::reponse($texte, [], 'enigme');
        }

        return self::reponse($texte);
    }

    /** @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string} */
    private function deRien(): array
    {
        return self::reponse("Avec plaisir. Je suis la si ca bloque encore.");
    }

    /**
     * La personne demande qu'on intervienne : acces, correction, proposition.
     *
     * @param array<string, mixed> $lu
     *
     * @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string}
     */
    private function signalement(string $message, array $lu): array
    {
        // « au sujet de les factures » ne se dit pas : les libelles portent
        // deja leur article.
        $sujet = $lu['libelle'] !== null ? ', concernant ' . $lu['libelle'] : '';

        return self::reponse(
            "C'est note" . $sujet . ". Je transmets votre demande a l'administrateur, "
                . "et je vous previens des que c'est regle. Confirmez-moi simplement l'envoi.",
            $this->liens($lu),
            'transmission'
        );
    }

    /**
     * Quelque chose ne marche pas. On cherche la cause avant de citer la regle.
     *
     * @param array<string, mixed> $lu
     *
     * @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string}
     */
    private function panne(string $message, array $lu): array
    {
        $cause = $this->causeConnue($lu);

        if ($cause !== null) {
            return self::reponse($cause, $this->liens($lu));
        }

        $liens = $this->liens($lu);

        if ($liens === []) {
            return $this->jeNeSaisPas($message, $lu);
        }

        return self::reponse(
            "Je ne vois pas de cause evidente de mon cote. L'écran concerne est ci-dessous. "
                . "Si ca bloque toujours, dites-le-moi : je transmets a l'administrateur.",
            $liens,
            'transmission'
        );
    }

    /**
     * Ce que le journal sait, et qui explique souvent le blocage.
     *
     * C'est ici que NOEL cesse de reciter le manuel. « Le decaissement se fait
     * sur la fiche » ne sert a rien a quelqu'un pour qui le panneau n'existera
     * jamais ; « vous avez valide cette demande ce matin » lui dit tout.
     *
     * @param array<string, mixed> $lu
     */
    private function causeConnue(array $lu): ?string
    {
        $journal = $this->journal();

        if ($journal === null) {
            return null;
        }

        try {
            if ($lu['concept'] === 'point_caisse') {
                $heure = $journal->pointDeCaisseDuJour();

                if ($heure !== null) {
                    return "Votre point de caisse du jour est déjà soumis, a " . $heure
                        . ". C'est sans doute pour cela que l'écran ne vous propose plus de le faire. "
                        . "Si un montant est faux, il faut le rouvrir plutot que le ressaisir.";
                }
            }

            if ($lu['concept'] === 'facture') {
                $doublons = $journal->doublonsDeReglementDuJour();

                if ($doublons !== []) {
                    return NoelJournal::phraseDuDoublon($doublons[0]);
                }
            }

            if ($lu['concept'] === 'decaissement') {
                return "Si l'écran ne vous propose pas le panneau de décaissement, c'est "
                    . "probablement que vous avez vous-même valide cette demande : depuis le "
                    . "14 octobre, celui qui autorise la dépense n'est pas celui qui sort l'argent. "
                    . "Le décaissement revient alors a quelqu'un d'autre.";
            }
        } catch (Throwable $e) {
            error_log('[NOEL repondeur] cause illisible : ' . $e->getMessage());
        }

        return null;
    }

    /**
     * La personne cherche un ecran.
     *
     * @param array<string, mixed> $lu
     *
     * @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string}
     */
    private function orienter(array $lu): array
    {
        $liens = $this->liens($lu);

        /*
         * Ce que NOEL sait deja ne doit pas attendre qu on se plaigne.
         *
         * « Mon point de caisse est-il soumis ? » recevait « C est ici. »
         * et un lien, alors que la reponse — oui, a 15h20 — etait a une
         * requete de distance. Elle n etait dite que dans la branche des
         * pannes, c est-a-dire a qui disait « ca ne marche pas ».
         */
        $etat = $this->etatConnu($lu);

        if ($liens === []) {
            /*
             * Le concept est compris, mais aucun ecran n'est ouvert a cette
             * personne. On ne dit pas « c'est dans Finance mais vous n'y avez
             * pas droit » : nommer une porte fermee, c'est apprendre qu'il y a
             * une porte.
             */
            return self::reponse(
                "Je ne trouve pas d'écran que vous puissiez ouvrir pour cela. "
                    . "Si vous pensez qu'il vous en faut l'accès, dites-le-moi : je transmets.",
                [],
                'transmission'
            );
        }

        $texte = count($liens) === 1
            ? "C'est ici."
            : "Cela se trouve a l'un de ces endroits.";

        if ($etat !== null) {
            $texte = $etat . "

" . $texte;
        }

        return self::reponse($texte, $liens);
    }

    /**
     * Ce que NOEL sait deja de l etat des choses, et qui repond a la question.
     *
     * Court, factuel, et toujours vrai au moment ou il le dit. Rien
     * d interprete : une date de soumission est une date de soumission.
     *
     * @param array<string, mixed> $lu
     */
    private function etatConnu(array $lu): ?string
    {
        if (($lu['concept'] ?? null) !== 'point_caisse') {
            return null;
        }

        $journal = $this->journal();

        if ($journal === null) {
            return null;
        }

        try {
            $heure = $journal->pointDeCaisseDuJour();
        } catch (Throwable $e) {
            error_log('[NOEL] état du point de caisse illisible : ' . $e->getMessage());

            return null;
        }

        return $heure === null
            ? null
            : 'Votre point de caisse du jour est déjà soumis, a ' . $heure . '.';
    }

    /**
     * Les concepts sur lesquels NOEL sait avancer un chiffre.
     *
     * La liste est courte et le restera : chaque entree suppose une requete
     * ecrite a la main, relue, et bornee aux droits de la personne. Un chiffre
     * approximatif serait pire que pas de chiffre du tout.
     *
     * @var array<int, string>
     */
    private const CHIFFRE_COLIS = ['colis', 'suivi_colis', 'groupage'];

    /** @var array<int, string> */
    private const CHIFFRE_CAISSE = ['point_caisse', 'mouvements_caisse', 'appro'];

    /**
     * Repondre par un chiffre, quand on en a un.
     *
     * Trois choses peuvent manquer : le journal (en test, il n y a pas de
     * base), le droit (la portee de la personne ne couvre aucune caisse), ou
     * simplement le concept (NOEL ne sait pas compter les conges). Dans les
     * trois cas on retombe sur l orientation habituelle — un lien vaut mieux
     * qu un chiffre invente.
     *
     * @param array<string, mixed> $lu
     *
     * @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string}
     */
    private function chiffrer(string $message, array $lu): array
    {
        $concept = (string) ($lu['concept'] ?? '');
        $journal = $this->journal;

        if ($journal === null) {
            return $this->orienter($lu);
        }

        /*
         * Deux questions tres differentes portent le meme concept :
         * « combien j ai encaisse » et « combien de factures impayees ». Seul
         * le mot de la question les separe, pas le concept.
         */
        if ($concept === 'facture') {
            $plat = NoelCarte::sansAccent($message);

            if (str_contains($plat, 'impay') || str_contains($plat, 'reste a payer')) {
                $impayees = $journal->facturesImpayees();

                if ($impayees !== null) {
                    return self::reponse(self::phraseDesImpayees($impayees), $this->liens($lu));
                }
            } else {
                $encaisse = $journal->encaisseDuJour();

                if ($encaisse !== null) {
                    return self::reponse(self::phraseDeMonEncaisse($encaisse), $this->liens($lu));
                }
            }
        }

        if (in_array($concept, self::CHIFFRE_COLIS, true)) {
            $compte = $journal->colisDuJour();

            if ($compte !== null) {
                return self::reponse(self::phraseDesColis($compte), $this->liens($lu));
            }
        }

        if (in_array($concept, self::CHIFFRE_CAISSE, true)) {
            $solde = $journal->soldeDeCaisse();

            if ($solde !== null) {
                return self::reponse(self::phraseDeLaCaisse($solde), $this->liens($lu));
            }
        }

        return $this->orienter($lu);
    }

    /**
     * « 39 colis enregistres aujourd hui a Abobo Dokui, en 12 saisies. »
     *
     * Publique et statique parce qu elle est pure : un tableau entre, une
     * phrase sort. C est la seule facon de l eprouver sans base, et ce qu on
     * veut eprouver est justement la phrase — l accord des pluriels, le
     * silence sur un nombre de saisies qui n apprend rien.
     *
     * L ecart entre les deux nombres se dit : un colis saisi peut en porter
     * plusieurs, et une journee de 39 colis en 12 saisies ne ressemble pas a
     * une journee de 39 colis en 39 saisies.
     *
     * @param array{colis: int, poids: float, saisies: int, portee: string, reseau: bool} $compte
     */
    public static function phraseDesColis(array $compte): string
    {
        if ($compte['colis'] === 0) {
            $texte = "Aucun colis enregistré aujourd'hui " . $compte['portee'] . '.';

            /*
             * La piste de l autre agence ne vaut que pour qui ne voit que la
             * sienne. La servir a quelqu un qui voit tout le reseau serait lui
             * proposer de chercher la ou il regarde deja.
             */
            if (!$compte['reseau']) {
                $texte .= " Si vous en attendiez, ils ont peut-être été saisis sur une autre agence.";
            }

            return $texte;
        }

        $texte = self::nombre($compte['colis']) . ' colis enregistré'
            . ($compte['colis'] > 1 ? 's' : '') . " aujourd'hui " . $compte['portee'];

        if ($compte['saisies'] !== $compte['colis']) {
            $texte .= ', en ' . self::nombre($compte['saisies']) . ' saisie'
                . ($compte['saisies'] > 1 ? 's' : '');
        }

        /*
         * Le poids vient avec, parce qu il est dans la meme requete et qu on
         * le demande dans la foulee : « combien de colis » et « quel poids »
         * sont deux moities de la meme question au guichet.
         */
        if (($compte['poids'] ?? 0.0) > 0.0) {
            $texte .= ', pour ' . number_format((float) $compte['poids'], 1, ',', ' ') . ' kg';
        }

        return $texte . '.';
    }

    /**
     * « Il doit rester 1 430 000 F dans le tiroir ce soir. »
     *
     * Le detail suit, parce qu un solde sans ses termes ne se verifie pas :
     * ce qu il y avait hier, ce qui est entre, ce qui est sorti.
     *
     * @param array{ouverture: float, mouvement: float, cloture: float, entrees: float, sorties: float} $solde
     */
    public static function phraseDeLaCaisse(array $solde): string
    {
        return 'Il doit rester ' . self::franc($solde['cloture']) . ' dans le tiroir ce soir.'
            . "

Le détail : " . self::franc($solde['ouverture']) . ' hier au soir, '
            . self::franc($solde['entrees']) . " d'entrées et "
            . self::franc($solde['sorties']) . " de sorties aujourd'hui."
            . "

Ce chiffre est celui de l'écran Mouvements de Caisse, et il s'arrête à ce que"
            . ' vous avez le droit de voir.';
    }

    /**
     * La fiche d un colis, quand la question porte son numero.
     *
     * Le format est celui du logiciel : un code d agence, puis des segments
     * separes par des tirets — LB-CI-0042, ABJ-DKI-001. On ne cherche que si
     * la phrase contient un tiret entoure de caracteres : sans cela, chaque
     * question partirait en requete pour rien.
     *
     * Rend null des que le moindre doute existe — pas de journal, aucun
     * numero reconnaissable, ou un colis hors du perimetre de la personne.
     * NOEL repond alors comme avant.
     *
     * @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string}|null
     */
    private function ficheDuColis(string $message): ?array
    {
        if (preg_match('/\b([A-Z0-9]{2,}(?:-[A-Z0-9]+){1,4})\b/i', $message, $trouve) !== 1) {
            return null;
        }

        $journal = $this->journal();

        if ($journal === null) {
            return null;
        }

        try {
            $colis = $journal->colisParNumero(strtoupper($trouve[1]));
        } catch (Throwable $e) {
            error_log('[NOEL] recherche de colis impossible : ' . $e->getMessage());

            return null;
        }

        if ($colis === null) {
            return null;
        }

        return self::reponse(
            self::phraseDuColis($colis),
            $this->liens(['ecrans' => ['colisage/parcels', 'tracking-colis/dashboard']])
        );
    }

    /**
     * « Vous avez encaisse 1 250 000 F aujourd hui, en 9 reglements. »
     *
     * @param array{xof: float, eur: float, nombre: int} $encaisse
     */
    public static function phraseDeMonEncaisse(array $encaisse): string
    {
        if ($encaisse['nombre'] === 0) {
            return "Vous n'avez encaissé aucun règlement aujourd'hui.";
        }

        $montants = [self::franc($encaisse['xof'])];

        // Les deux monnaies restent separees : Paris compte en euros.
        if (abs($encaisse['eur']) >= 0.005) {
            $montants[] = number_format($encaisse['eur'], 2, ',', ' ') . ' EUR';
        }

        return 'Vous avez encaissé ' . implode(' et ', $montants) . " aujourd'hui, en "
            . self::nombre($encaisse['nombre']) . ' règlement'
            . ($encaisse['nombre'] > 1 ? 's' : '') . '.';
    }

    /**
     * « 23 factures attendent encore 4 820 000 F à Abobo Dokui. »
     *
     * @param array{nombre: int, restant: float, portee: string, reseau: bool} $impayees
     */
    public static function phraseDesImpayees(array $impayees): string
    {
        if ($impayees['nombre'] === 0) {
            return 'Aucune facture impayée ' . $impayees['portee'] . '. Tout est réglé.';
        }

        return self::nombre($impayees['nombre']) . ' facture'
            . ($impayees['nombre'] > 1 ? 's attendent' : ' attend') . ' encore '
            . self::franc($impayees['restant']) . ' ' . $impayees['portee'] . '.';
    }

    /**
     * « LB-CI-0042 : 3 colis, 18,5 kg, enregistré le 02/11/2026 à Adjamé. »
     *
     * @param array{numero: string, statut: string, agence: string, poids: float, colis: int, quand: string} $colis
     */
    public static function phraseDuColis(array $colis): string
    {
        $etats = [
            'enregistre' => 'enregistré, pas encore facturé',
            'facture' => 'facturé',
            'en_transit' => 'en transit',
            'arrive' => 'arrivé à destination',
            'livre' => 'livré',
            'retire' => 'retiré par son destinataire',
            'annule' => 'annulé',
        ];

        return $colis['numero'] . ' : ' . ($etats[$colis['statut']] ?? $colis['statut']) . '.'
            . "

" . self::nombre($colis['colis']) . ' colis, '
            . number_format($colis['poids'], 1, ',', ' ') . ' kg, saisi le '
            . $colis['quand'] . ' à ' . $colis['agence'] . '.';
    }

    /** Un nombre, avec ses milliers separes. */
    private static function nombre(int $valeur): string
    {
        return number_format($valeur, 0, ',', ' ');
    }

    /** Un montant en francs. */
    private static function franc(float $valeur): string
    {
        return number_format($valeur, 0, ',', ' ') . ' F';
    }

    /**
     * Expliquer une chose precise, et montrer ou elle se fait.
     *
     * NOEL ne sait pas DEFINIR « un point de caisse » : personne ne lui a
     * ecrit de definition, et en inventer une lui ferait dire des choses
     * qu aucun humain n a relues. Il dit donc ce qu il sait vraiment — le nom
     * de la chose, le module dont elle releve, et les ecrans ou elle se fait.
     * C est moins qu une definition, et beaucoup plus qu un cours sur Finance.
     *
     * Rend null quand aucun ecran n est ouvert a cette personne : on retombe
     * alors sur le module, qui sait refuser sans nommer de porte fermee.
     *
     * @param array<string, mixed> $lu
     *
     * @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string}|null
     */
    private function presenterLaChose(array $lu): ?array
    {
        $liens = $this->liens($lu);

        if ($liens === []) {
            return null;
        }

        $libelle = trim((string) ($lu['libelle'] ?? ''));

        if ($libelle === '') {
            return null;
        }

        $slug = (string) ($lu['module'] ?? '');
        $modules = $this->carte->modulesVisibles();

        $texte = ucfirst($libelle) . '.';

        if (isset($modules[$slug])) {
            $texte .= ' Cela releve du module ' . $modules[$slug]['label'] . '.';
        }

        $texte .= "

" . (count($liens) === 1
            ? "Voici l'écran où cela se passe."
            : 'Voici les écrans où cela se passe.');

        if (isset($modules[$slug])) {
            $texte .= "

Dites-moi ce que vous voulez y faire — par exemple « comment je fais pour "
                . self::exempleDeGeste($slug) . " » — et je vous donne les étapes.";
        }

        return self::reponse($texte, $liens);
    }

    /**
     * Presenter un module : a quoi il sert, et ce qu'on y trouve.
     *
     * « Parle-moi du module finance » n'attend pas un lien mais une
     * explication. NOEL a deja toute la matiere — le catalogue porte la
     * description de chaque module et la liste de ses ecrans, groupe par
     * groupe. Il ne lui manquait que de s'en servir pour raconter.
     *
     * Et il ne raconte que ce que la personne peut ouvrir : presenter en
     * detail un module auquel elle n'a pas acces serait lui decrire une piece
     * fermee a clef.
     *
     * @param array<string, mixed> $lu
     *
     * @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string}
     */
    private function presenter(array $lu): array
    {
        /*
         * Une question pointue appelle une reponse pointue.
         *
         * « C est quoi un point de caisse ? » nomme une CHOSE, pas un module.
         * Repondre par la presentation entiere de Finance — vingt-deux ecrans,
         * sept groupes — noie la question, et les liens offerts deviennent les
         * portes d entree du module au lieu de l ecran demande. Mesure le
         * 03/11/2026 : les quatre liens rendus ne contenaient pas celui des
         * points de caisse.
         *
         * Le module ne se raconte donc en entier que lorsqu on l a nomme.
         */
        if (empty($lu['module_nomme']) && ($lu['concept'] ?? null) !== null) {
            $precis = $this->presenterLaChose($lu);

            if ($precis !== null) {
                return $precis;
            }
        }

        $slug = (string) ($lu['module'] ?? '');
        $modules = $this->carte->modulesVisibles();

        if ($slug === '' || !isset($modules[$slug])) {
            /*
             * Le module existe peut-etre, mais pas pour cette personne. On ne
             * le nomme pas : apprendre a quelqu'un qu'il y a une porte est
             * exactement ce qu'on evite partout ailleurs.
             */
            return self::reponse(
                "Je ne vois pas de module de ce nom parmi ceux que vous pouvez ouvrir. "
                    . "Dites-moi plutot ce que vous cherchez a faire, je vous montrerai ou c'est.",
                [],
                'transmission'
            );
        }

        $module = $modules[$slug];
        $ecrans = $module['ecrans'];

        $texte = $module['label'] . ".";

        if (trim((string) $module['description']) !== '') {
            $texte .= ' ' . rtrim((string) $module['description'], '.') . '.';
        }

        $texte .= "

Vous y avez accès a " . count($ecrans) . ' écran'
            . (count($ecrans) > 1 ? 's' : '') . self::parGroupes($ecrans) . '.';

        $texte .= "

Dites-moi ce que vous voulez y faire — par exemple « comment je fais pour "
            . self::exempleDeGeste($slug) . " » — et je vous donne les étapes.";

        return self::reponse($texte, self::premiersEcrans($ecrans));
    }

    /**
     * « rangés en 6 groupes : Pilotage, Gestion des fonds, … »
     *
     * @param array<int, array<string, string>> $ecrans
     */
    private static function parGroupes(array $ecrans): string
    {
        $groupes = [];

        foreach ($ecrans as $ecran) {
            $groupe = trim((string) ($ecran['groupe'] ?? ''));

            if ($groupe !== '') {
                $groupes[$groupe] = $groupe;
            }
        }

        if ($groupes === []) {
            return '';
        }

        return ', ranges en ' . count($groupes) . ' groupe' . (count($groupes) > 1 ? 's' : '')
            . ' : ' . implode(', ', array_values($groupes));
    }

    /**
     * Les premiers ecrans, pour entrer quelque part plutot que de lire une liste.
     *
     * @param array<int, array<string, string>> $ecrans
     *
     * @return array<int, array{label: string, url: string}>
     */
    private static function premiersEcrans(array $ecrans): array
    {
        $liens = [];

        foreach (array_slice($ecrans, 0, 4) as $ecran) {
            $liens[] = [
                'label' => (string) $ecran['label'],
                'url' => View::url((string) $ecran['url']),
            ];
        }

        return $liens;
    }

    /**
     * Un exemple de geste propre au module, pour amorcer la question suivante.
     *
     * Terminer une presentation par « dites-moi ce que vous voulez faire »
     * laisse la personne devant une page blanche. Un exemple concret lui donne
     * le ton et le vocabulaire.
     */
    private static function exempleDeGeste(string $slug): string
    {
        return match ($slug) {
            'finance' => 'soumettre mon point de caisse',
            'colisage' => 'enregistrer un colis',
            'logistique' => 'preparer un depart',
            'rh' => 'integrer un collaborateur',
            'crm' => 'créer une fiche client',
            'entrepots' => 'retirer un colis du magasin',
            'transit-douane' => 'suivre un dossier de dedouanement',
            'flotte-transport' => 'suivre un vehicule',
            'employee' => 'consulter mon bulletin',
            default => 'faire une recherche',
        };
    }

    /**
     * La marche a suivre : les gestes dans l'ordre.
     *
     * @param array<string, mixed> $lu
     *
     * @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string}
     */
    private function marcheASuivre(string $message, array $lu): array
    {
        $etapes = NoelProcedure::pour($lu['concept'] ?? null, $lu['module'] ?? null);

        if ($etapes === null) {
            // Mieux vaut orienter que d'inventer une procedure : quelqu'un la
            // suivrait, et elle serait fausse.
            $liens = $this->liens($lu);

            if ($liens !== []) {
                return self::reponse(
                    "Je n'ai pas de marche a suivre ecrite pour cela, mais l'écran est ici. "
                        . "Si vous voulez que je l'apprenne, dites-le-moi.",
                    $liens,
                    'transmission'
                );
            }

            return $this->jeNeSaisPas($message, $lu);
        }

        /*
         * Une marche a suivre se refuse comme un ecran se refuse.
         *
         * Raconter comment integrer un collaborateur a quelqu'un qui n'a pas
         * acces aux Ressources humaines reviendrait a lui decrire une piece
         * fermee a clef — et a lui apprendre que cette piece existe, ce que le
         * module RH interdit precisement. Les etapes suivent les droits des
         * ecrans qu'elles decrivent.
         */
        $liens = $this->liens(['ecrans' => $etapes['ecrans']]);

        if ($liens === []) {
            return self::reponse(
                "Cette opération se fait dans une partie du logiciel que vous ne pouvez pas ouvrir. "
                    . "Dites-moi ce que vous cherchez a faire et je vous orienterai autrement, "
                    . "ou je transmets votre demande a l'administrateur.",
                [],
                'transmission'
            );
        }

        $texte = $etapes['titre'] . "
";

        foreach ($etapes['etapes'] as $rang => $etape) {
            $texte .= "
" . ($rang + 1) . '. ' . $etape;
        }

        if ($etapes['attention'] !== null) {
            $texte .= "

A retenir : " . $etapes['attention'];
        }

        return self::reponse($texte, $liens);
    }

    /**
     * NOEL n'a pas compris, et il le dit.
     *
     * @param array<string, mixed> $lu
     *
     * @return array{texte: string, liens: array<int, array<string, string>>, attente: ?string}
     */
    private function jeNeSaisPas(string $message, array $lu): array
    {
        if (!NoelAcces::saitConverser()) {
            return self::reponse(
                NoelApprentissage::proposition()
                    . "\n\nJe sais surtout vous dire ou se trouvent les écrans du logiciel "
                    . "et ce qu'on y saisit. Pour le reste, je ne sais pas encore tenir une vraie conversation.",
                [],
                'transmission'
            );
        }

        // L'IA propre a LBP (NoelIA) : seulement quand les regles n'ont pas
        // compris, et jamais pendant la suite de tests.
        if (!self::enTest()) {
            $texte = NoelIA::depuisConfiguration()->repondre($message, $this->carte, NoelAcces::prenom());
            if ($texte !== null) {
                return self::reponse($texte);
            }
        }

        return self::reponse(NoelApprentissage::proposition(), [], 'transmission');
    }

    // ------------------------------------------------------------------
    // Briques
    // ------------------------------------------------------------------

    /**
     * Les ecrans proposables, c'est-a-dire ceux que la personne peut ouvrir.
     *
     * @param array<string, mixed> $lu
     *
     * @return array<int, array{label: string, url: string}>
     */
    private function liens(array $lu): array
    {
        $candidats = (array) ($lu['ecrans'] ?? []);
        $visibles = [];

        /*
         * Le PREMIER module qui porte une adresse gagne, pas le dernier.
         *
         * Plusieurs modules citent le meme ecran : « finance/clotures » figure
         * au menu de Finance et a celui de Recherche & Audit. En ecrasant au
         * fur et a mesure, NOEL annoncait « Recherche & Audit > Points de
         * Caisse » a une caissiere qui cherchait simplement sa cloture. Le
         * catalogue est range du plus central au plus specialise : le premier
         * est le bon.
         */
        foreach ($this->carte->modulesVisibles() as $module) {
            foreach ($module['ecrans'] as $ecran) {
                if (!isset($visibles[$ecran['url']])) {
                    $visibles[$ecran['url']] = $module['label'] . ' > ' . $ecran['label'];
                }
            }
        }

        $liens = [];
        foreach ($candidats as $url) {
            $url = ltrim((string) $url, '/');

            if (isset($visibles[$url])) {
                $liens[] = ['label' => $visibles[$url], 'url' => View::url($url)];
            }
        }

        return $liens;
    }

    /**
     * Sommes-nous dans la suite de tests ?
     *
     * Database::getConnection() n'echoue pas quand la base manque : il arrete
     * le processus. Un try/catch ne protege donc de rien, et le repondeur
     * ferait tomber toute la suite des la premiere question posee. Meme parade
     * que AuditLogService et NoelCarte.
     */
    private static function enTest(): bool
    {
        return ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? '') === 'testing';
    }

    /** @param array<string, mixed> $lu */
    private function mesurer(array $lu): void
    {
        if ($this->mesure === null && self::enTest()) {
            return;
        }

        try {
            ($this->mesure ?? new NoelMesure())->noterQuestion([
                'concept' => $lu['concept'] ?? null,
                'intention' => (string) ($lu['intention'] ?? ''),
            ]);
        } catch (Throwable $e) {
            // Compter est utile ; repondre l'est davantage.
            error_log('[NOEL repondeur] mesure impossible : ' . $e->getMessage());
        }
    }

    private function journal(): ?NoelJournal
    {
        if ($this->journal !== null) {
            return $this->journal;
        }

        if (self::enTest()) {
            return null;
        }

        try {
            return $this->journal = new NoelJournal();
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<int, array{label: string, url: string}> $liens
     *
     * @return array{texte: string, liens: array<int, array{label: string, url: string}>, attente: ?string}
     */
    private static function reponse(string $texte, array $liens = [], ?string $attente = null): array
    {
        return ['texte' => $texte, 'liens' => $liens, 'attente' => $attente];
    }

    /**
     * La reponse mise en texte, pour un fil de discussion qui ne connait que
     * des messages.
     *
     * @param array{texte: string, liens: array<int, array{label: string, url: string}>} $reponse
     */
    public static function enMessage(array $reponse): string
    {
        $texte = $reponse['texte'];

        foreach ($reponse['liens'] as $lien) {
            $texte .= "\n" . $lien['label'] . ' : ' . $lien['url'];
        }

        return $texte;
    }
}
