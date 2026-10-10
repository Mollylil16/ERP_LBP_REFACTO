<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Repositories\Finance\MouvementsCaisseRepository;
use App\Security\MouvementsCaisseAcces;
use App\Services\Finance\MouvementsCaisseService;
use PDO;
use Tests\TestCase;

/**
 * Mouvements de caisse : les entrées et les sorties du jour.
 *
 * L'écran que la direction avait dans son ancien logiciel. Il ne ressaisit
 * rien : les règlements de factures, les approvisionnements validés et les
 * décaissements de demandes de fonds sont déjà dans LBP. Trois choses se
 * jouent ici et sont épinglées par ces tests :
 *
 * - une variation nulle quand la veille est à zéro, plutôt qu'un « -100 % »
 *   depuis rien ou une division impossible ;
 * - le refus de retirer une ligne reprise de LBP, avec l'écran où aller ;
 * - l'impossibilité qu'un même règlement soit compté deux fois.
 */
final class MouvementsCaisseTest extends TestCase
{
    // ------------------------------------------------------------------
    // Doublures
    // ------------------------------------------------------------------

    /**
     * Un dépôt qui rend les lignes qu'on lui donne, sans base.
     *
     * @param array<string, array<int, array<string, mixed>>> $sources
     */
    private function depot(array $sources = [], ?string $referenceConnue = null): MouvementsCaisseRepository
    {
        return new class ($this->createStub(PDO::class), $sources, $referenceConnue) extends MouvementsCaisseRepository {
            /**
             * @param array<string, array<int, array<string, mixed>>> $sources
             */
            public function __construct(
                PDO $pdo,
                private array $sources,
                private ?string $referenceConnue
            ) {
                parent::__construct($pdo);
            }

            /** @return array<int, array<string, mixed>> */
            private function source(string $cle, string $jour): array
            {
                return $this->sources[$cle . '@' . $jour] ?? [];
            }

            public function caisses(int $agenceId = 0): array
            {
                return $this->sources['caisses'] ?? [];
            }

            /**
             * Le cumul depuis l'origine : toutes les journées du jeu d'essai.
             *
             * La doublure somme ce qu'elle porte, sans borne de date — c'est
             * précisément ce que la vraie requête fait en base. Les tests qui
             * vérifiaient les cumuls comparaient auparavant les chiffres du
             * jour, parce que le service les y recopiait.
             *
             * @return array<string, float>
             */
            public function cumuls(array $portee): array
            {
                $totaux = ['versements' => 0.0, 'retraits' => 0.0, 'versements_eur' => 0.0, 'retraits_eur' => 0.0];

                foreach ($this->sources as $cle => $lignes) {
                    if (!is_array($lignes) || !str_contains((string) $cle, '@')) {
                        continue;
                    }

                    $sortie = str_starts_with((string) $cle, 'demandes') || str_starts_with((string) $cle, 'sorties');

                    foreach ($lignes as $ligne) {
                        $montant = (float) ($ligne['montant'] ?? $ligne['amount'] ?? 0);
                        $eur = strtoupper((string) ($ligne['devise'] ?? 'XOF')) === 'EUR';
                        $champ = ($sortie ? 'retraits' : 'versements') . ($eur ? '_eur' : '');
                        $totaux[$champ] += $montant;
                    }
                }

                return $totaux;
            }

            public function caisse(int $id): ?array
            {
                foreach ($this->caisses() as $caisse) {
                    if ((int) $caisse['id'] === $id) {
                        return $caisse + ['actif' => 1];
                    }
                }

                return null;
            }

            public function agences(): array
            {
                return $this->sources['agences'] ?? [];
            }

            public function paiements(string $jour, array $portee): array
            {
                return $this->source('paiements', $jour);
            }

            public function approsValides(string $jour, array $portee): array
            {
                return $this->source('appros', $jour);
            }

            public function demandesDecaissees(string $jour, array $portee, array $statuts): array
            {
                return $this->source('demandes', $jour);
            }

            public function mouvementsSaisis(string $jour, string $type, array $portee): array
            {
                return $this->source('saisis_' . $type, $jour);
            }

            public function mouvementSaisi(int $id): ?array
            {
                foreach ($this->sources['saisis'] ?? [] as $mouvement) {
                    if ((int) $mouvement['id'] === $id) {
                        return $mouvement;
                    }
                }

                return null;
            }

            public function referenceDejaConnue(string $reference): ?string
            {
                return $this->referenceConnue;
            }

            /** @var array<string, mixed> Ce que le service a voulu écrire. */
            public array $ecrit = [];

            public function enregistrerMouvement(array $valeurs): int
            {
                $this->ecrit = $valeurs;

                return 1;
            }

            public function annulerMouvement(int $id, int $userId): bool
            {
                return true;
            }
        };
    }

    /** Une caissière de Dokui : elle saisit, et ne voit que son agence. */
    private function caissiere(): MouvementsCaisseAcces
    {
        return new MouvementsCaisseAcces(['caissiere'], false, 42, 3400);
    }

    /** Le comptable : il saisit et voit tout le réseau. */
    private function comptable(): MouvementsCaisseAcces
    {
        return new MouvementsCaisseAcces(['comptable'], false, 7, null);
    }

    /**
     * Un chef d'agence : il lit et contrôle la journée de son agence, il ne la
     * saisit pas. Celui qui tient le tiroir enregistre, celui qui le surveille
     * ne s'ajoute pas de lignes.
     */
    private function lecteur(): MouvementsCaisseAcces
    {
        return new MouvementsCaisseAcces(['chef_agence'], false, 51, 3400);
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $sources
     */
    private function service(
        array $sources = [],
        ?MouvementsCaisseAcces $acces = null,
        ?string $referenceConnue = null
    ): MouvementsCaisseService {
        return new MouvementsCaisseService(
            $this->depot($sources, $referenceConnue),
            $acces ?? $this->comptable()
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function caisses(): array
    {
        return [
            ['id' => 11, 'nom' => 'Caisse guichet Dokui', 'code' => 'DOK-1', 'type' => 'EXPLOITATION',
             'solde' => 450000.0, 'agence_id' => 3400, 'agence' => 'Abobo Dokui'],
            ['id' => 12, 'nom' => 'Caisse fonctionnement Dokui', 'code' => 'DOK-2', 'type' => 'FONCTIONNEMENT',
             'solde' => 80000.0, 'agence_id' => 3400, 'agence' => 'Abobo Dokui'],
        ];
    }

    // ------------------------------------------------------------------
    // L'agence est l'unité, la caisse nommée est facultative
    // ------------------------------------------------------------------

    /**
     * On enregistre sans choisir de caisse : l'agence suffit.
     *
     * La table lbp_caisses est vide chez LBP — vérifié en production le
     * 10/10/2026, cinq agences actives, zéro caisse. Exiger une caisse rendait
     * la seule fonction neuve de l'écran inutilisable partout, et le message
     * de refus ne disait pas pourquoi.
     */
    public function test_un_mouvement_s_enregistre_sur_la_seule_agence(): void
    {
        $service = $this->service(['caisses' => []], $this->caissiere());

        [$message, $erreurs] = $service->enregistrerVersement([
            'agence_id' => 3400,
            'montant' => '125000',
            'libelle' => 'Complément de fonds remis par le siège',
            'date_mouvement' => '2026-10-08',
            'cadre' => 'AUTRE',
        ], 42);

        self::assertSame([], $erreurs);
        self::assertNotSame('', $message);
    }

    /** Sans agence non plus, on ne compte rien nulle part. */
    public function test_un_mouvement_sans_agence_est_refuse(): void
    {
        $service = $this->service(['caisses' => []], $this->caissiere());

        [, $erreurs] = $service->enregistrerVersement([
            'montant' => '125000',
            'libelle' => 'Versement sans destination',
            'date_mouvement' => '2026-10-08',
        ], 42);

        self::assertContains(
            "Choisissez l'agence concernée : un mouvement sans agence ne se compte nulle part.",
            $erreurs
        );
    }

    // ------------------------------------------------------------------
    // La fuite du 10/10/2026
    // ------------------------------------------------------------------

    /**
     * Un compte sans agence ne lit pas la caisse d'une autre en changeant l'URL.
     *
     * La portée était décidée APRÈS que la caisse demandée avait réécrit
     * l'agence : il suffisait d'ajouter ?caisse_id=11 pour que « bloquée »
     * devienne « l'agence de cette caisse ». La journée d'Adjamé sortait alors
     * en entier — nom du client et montant — sur l'écran et sur les quatre
     * exports. Démontré par exécution avant correction.
     *
     * Neuf comptes de production sont dans ce cas, dont le responsable
     * groupage général, et sept rôles du guichet peuvent s'y retrouver.
     */
    public function test_un_compte_sans_agence_ne_lit_pas_la_caisse_d_une_autre(): void
    {
        // Une caissière sans agence : ni portée réseau, ni agence de
        // rattachement. C'est le cas exact relevé en production.
        $sansAgence = new MouvementsCaisseAcces(['caissiere'], false, 7, null);

        $tableau = $this->service([
            'caisses' => $this->caisses(),
            'paiements@2026-10-08' => [[
                'id' => 501, 'montant' => 777000.0, 'devise' => 'XOF', 'mode' => 'especes',
                'agence_id' => 3400, 'agence' => 'Abobo Dokui', 'numero_facture' => 'F-DOK-001',
                'client' => 'Client à ne pas divulguer', 'caissier' => 'Awa Koné',
                'date' => '2026-10-08 10:00',
            ]],
        ], $sansAgence)->tableauDuJour(['date' => '2026-10-08', 'caisse_id' => 11]);

        self::assertSame([], $tableau['versements'], "La caisse demandée par l'URL ne doit rien livrer.");
        self::assertSame(0.0, $tableau['kpis']['entrees']['total']);
        self::assertSame([], $tableau['caisses'], 'La liste ne doit pas nommer les caisses des autres agences.');
        self::assertStringNotContainsString('Client à ne pas divulguer', json_encode($tableau, JSON_UNESCAPED_UNICODE) ?: '');
    }

    /** Une portée réseau, elle, lit bien — la correction ne ferme pas la porte à tous. */
    public function test_une_portee_reseau_lit_toujours_sans_agence(): void
    {
        /*
         * Le responsable groupage général suit les trois agences de Côte
         * d'Ivoire sans être rattaché à aucune : sa portée est nommée dans
         * Auth::ROLES_PORTEE_RESEAU, elle ne doit rien à l'absence d'agence.
         */
        $reseau = new MouvementsCaisseAcces(['responsable_groupage'], false, 7, null);

        $tableau = $this->service([
            'caisses' => $this->caisses(),
            'paiements@2026-10-08' => [[
                'id' => 501, 'montant' => 777000.0, 'devise' => 'XOF', 'mode' => 'especes',
                'agence_id' => 3400, 'agence' => 'Abobo Dokui', 'numero_facture' => 'F-DOK-001',
                'client' => 'SOCIMEX', 'caissier' => 'Awa Koné', 'date' => '2026-10-08 10:00',
            ]],
        ], $reseau)->tableauDuJour(['date' => '2026-10-08']);

        self::assertCount(1, $tableau['versements']);
        self::assertSame(777000.0, $tableau['kpis']['entrees']['total']);
    }

    // ------------------------------------------------------------------
    // La variation, et le zéro de la veille
    // ------------------------------------------------------------------

    /**
     * « -100 % » depuis rien ne veut rien dire, et la division est impossible :
     * l'écart vaut null, charge à l'écran de ne rien afficher.
     */
    public function test_la_variation_est_nulle_quand_la_veille_est_a_zero(): void
    {
        self::assertNull(MouvementsCaisseService::variation(125000.0, 0.0));
        self::assertNull(MouvementsCaisseService::variation(0.0, 0.0));
    }

    /** Une veille non nulle donne un écart en pourcentage, arrondi au dixième. */
    public function test_la_variation_se_compte_sur_la_veille(): void
    {
        self::assertSame(100.0, MouvementsCaisseService::variation(200000.0, 100000.0));
        self::assertSame(-50.0, MouvementsCaisseService::variation(50000.0, 100000.0));
        self::assertSame(33.3, MouvementsCaisseService::variation(400000.0, 300000.0));
    }

    /**
     * Une veille négative — plus de sorties que d'entrées — ne doit pas
     * inverser le sens de l'écart : remonter de -100 000 à -50 000 est une
     * amélioration, pas une chute.
     */
    public function test_une_veille_negative_n_inverse_pas_le_sens_de_l_ecart(): void
    {
        self::assertSame(50.0, MouvementsCaisseService::variation(-50000.0, -100000.0));
    }

    /**
     * Le tableau d'une première journée : rien la veille, donc aucun écart
     * affiché, mais des totaux bien là.
     */
    public function test_une_premiere_journee_ne_se_compare_a_rien(): void
    {
        $tableau = $this->service([
            'caisses' => $this->caisses(),
            'paiements@2026-10-08' => [[
                'id' => 501, 'montant' => 125000.0, 'devise' => 'XOF', 'mode' => 'especes',
                'date_paiement' => '2026-10-08 09:40:00', 'numero_facture' => 'FA-01-2026-000012',
                'agence_id' => 3400, 'agence' => 'Abobo Dokui', 'tiers' => 'KONE Awa',
                'caissier' => 'OUEDRAOGO KADIDIATOU', 'numero_tracking' => 'LBP-0012', 'dossier' => null,
            ]],
        ])->tableauDuJour(['date' => '2026-10-08']);

        self::assertSame('2026-10-08', $tableau['date']);
        self::assertSame(125000.0, $tableau['kpis']['entrees']['total']);
        self::assertSame(0.0, $tableau['kpis']['entrees']['veille']);
        self::assertNull($tableau['kpis']['entrees']['variation']);
        self::assertNull($tableau['kpis']['sorties']['variation']);
        self::assertNull($tableau['kpis']['solde']['variation']);
    }

    // ------------------------------------------------------------------
    // Le contrat de données
    // ------------------------------------------------------------------

    /**
     * L'écran est construit en parallèle contre ce contrat : une clé qui change
     * de nom casse une colonne sans rien faire échouer côté service.
     */
    public function test_le_tableau_rend_exactement_les_cles_attendues(): void
    {
        $tableau = $this->service(['caisses' => $this->caisses()])->tableauDuJour([]);

        self::assertSame(
            ['date', 'filtres', 'caisses', 'agences', 'kpis', 'cumuls', 'versements', 'retraits', 'peutSaisir'],
            array_keys($tableau)
        );
        self::assertSame(['date', 'caisse_id', 'agence_id', 'q'], array_keys($tableau['filtres']));
        self::assertSame(['entrees', 'sorties', 'solde'], array_keys($tableau['kpis']));
        /*
         * Les clés en euros doublent chaque montant depuis le 08/10/2026 : les
         * deux monnaies restent séparées de bout en bout, sans conversion ni
         * taux. Sans elles, une agence de Paris lisait des historiques pleins
         * et des compteurs à zéro.
         */
        self::assertSame(
            ['total', 'dossiers', 'colis', 'autres', 'veille', 'variation',
             'total_eur', 'dossiers_eur', 'colis_eur', 'autres_eur', 'veille_eur', 'variation_eur'],
            array_keys($tableau['kpis']['entrees'])
        );
        self::assertSame(
            ['total', 'veille', 'variation', 'total_eur', 'veille_eur', 'variation_eur'],
            array_keys($tableau['kpis']['solde'])
        );
        self::assertSame(
            ['versements', 'retraits', 'solde', 'versements_eur', 'retraits_eur', 'solde_eur'],
            array_keys($tableau['cumuls'])
        );
        self::assertSame(
            ['id', 'nom', 'agence', 'type', 'solde'],
            array_keys($tableau['caisses'][0])
        );
    }

    /** Chaque ligne d'historique porte les douze colonnes que l'écran attend. */
    public function test_chaque_ligne_porte_les_colonnes_attendues(): void
    {
        $tableau = $this->service([
            'caisses' => $this->caisses(),
            'appros@2026-10-08' => [[
                'id' => 9, 'numero' => 'APP-202610-001', 'montant' => 300000.0, 'devise' => 'XOF',
                'motif' => 'Fonds de caisse de la semaine', 'source' => 'SIEGE',
                'date_effet' => '2026-10-08', 'agence_id' => 3400, 'agence' => 'Abobo Dokui',
                'caissier' => 'Comptable LBP',
            ]],
        ])->tableauDuJour(['date' => '2026-10-08']);

        self::assertSame(
            ['id', 'source', 'date', 'cadre', 'cadre_libelle', 'dossier', 'libelle',
             'reference', 'montant', 'devise', 'tiers', 'caisse', 'caissier'],
            array_keys($tableau['versements'][0])
        );
        self::assertSame('auto', $tableau['versements'][0]['source']);
        self::assertSame('APPRO', $tableau['versements'][0]['cadre']);
        self::assertSame('APP-202610-001', $tableau['versements'][0]['reference']);
        // La caisse du guichet, faute de savoir dans quel tiroir le siège a remis.
        self::assertSame('Caisse guichet Dokui', $tableau['versements'][0]['caisse']);
    }

    // ------------------------------------------------------------------
    // Le double comptage
    // ------------------------------------------------------------------

    /**
     * Un règlement n'est lu qu'une fois.
     *
     * Deux pièges se referment ici. Le premier est dans le SQL : le dossier
     * d'envoi est cherché par sous-requête corrélée et non par jointure, car
     * deux dossiers rattachés à la même expédition dupliqueraient la ligne de
     * paiement. Le second est dans l'ENUM : lbp_mouvements_caisse accepte un
     * type 'APPRO', jamais relu ici, sinon chaque remise du siège compterait
     * deux fois.
     */
    public function test_un_paiement_n_est_jamais_compte_deux_fois(): void
    {
        $depot = (string) file_get_contents(BASE_PATH . '/app/Repositories/Finance/MouvementsCaisseRepository.php');

        self::assertStringContainsString('SELECT de.numero FROM lbp_dossiers_envoi de', $depot);
        self::assertStringNotContainsString('JOIN lbp_dossiers_envoi', $depot);
        self::assertStringContainsString("in_array(\$type, ['ENTREE', 'DECAISSEMENT'], true)", $depot);

        // Et le total ne compte qu'une fois un paiement rendu une fois.
        $tableau = $this->service([
            'caisses' => $this->caisses(),
            'paiements@2026-10-08' => [[
                'id' => 501, 'montant' => 125000.0, 'devise' => 'XOF', 'mode' => 'especes',
                'date_paiement' => '2026-10-08 09:40:00', 'numero_facture' => 'FA-01-2026-000012',
                'agence_id' => 3400, 'agence' => 'Abobo Dokui', 'tiers' => 'KONE Awa',
                'caissier' => 'OUEDRAOGO KADIDIATOU', 'numero_tracking' => 'LBP-0012', 'dossier' => null,
            ]],
        ])->tableauDuJour(['date' => '2026-10-08']);

        self::assertCount(1, $tableau['versements']);
        self::assertSame(125000.0, $tableau['cumuls']['versements']);
    }

    /**
     * Et surtout : on ne peut pas le ressaisir.
     *
     * La saisie manuelle ne sert qu'à ce que LBP ne connaît pas. Une référence
     * que LBP porte déjà est refusée, en disant où la corriger — sans quoi la
     * somme entrerait deux fois dans la journée.
     */
    public function test_une_reference_deja_connue_de_lbp_est_refusee_a_la_saisie(): void
    {
        [$message, $erreurs] = $this->service(
            ['caisses' => $this->caisses()],
            $this->comptable(),
            'une facture'
        )->enregistrerVersement([
            'caisse_id' => 11,
            'montant' => '125 000',
            'libelle' => 'Regoulement facture du matin',
            'date_mouvement' => '2026-10-08',
            'reference' => 'FA-01-2026-000012',
        ], 7);

        self::assertSame('', $message);
        self::assertCount(1, $erreurs);
        self::assertStringContainsString('FA-01-2026-000012', $erreurs[0]);
        self::assertStringContainsString('deux fois', $erreurs[0]);
        self::assertStringContainsString('à sa source', $erreurs[0]);
    }

    /** Une saisie que LBP ne connaît pas, elle, passe. */
    public function test_un_versement_inconnu_de_lbp_est_accepte(): void
    {
        [$message, $erreurs] = $this->service(['caisses' => $this->caisses()])
            ->enregistrerVersement([
                'caisse_id' => 11,
                'montant' => '42 500,50',
                'libelle' => "Remboursement d'un trop-perçu client",
                'date_mouvement' => '2026-10-08',
            ], 7);

        self::assertSame([], $erreurs);
        self::assertStringContainsString('Caisse guichet Dokui', $message);
        self::assertStringContainsString('08/10/2026', $message);
    }

    // ------------------------------------------------------------------
    // Ce qui ne se retire pas d'ici
    // ------------------------------------------------------------------

    /**
     * Une ligne reprise de LBP ne se supprime pas ici.
     *
     * L'effacer de cet écran laisserait la caisse et la facture en désaccord :
     * le refus doit donc nommer l'écran où la correction se fait, sinon l'agent
     * recommence le même geste.
     */
    public function test_un_mouvement_automatique_ne_se_supprime_pas(): void
    {
        // L'identifiant 501 est celui d'un paiement : aucun mouvement saisi ne
        // le porte, le dépôt ne rend donc rien.
        [$message, $erreurs] = $this->service(['saisis' => []])->supprimer(501, 7);

        self::assertSame('', $message);
        self::assertCount(1, $erreurs);
        self::assertStringContainsString('Factures Clients', $erreurs[0]);
        self::assertStringContainsString('Appro Caisse', $erreurs[0]);
        self::assertStringContainsString('Demandes de Fonds', $erreurs[0]);
        self::assertStringContainsString("Rien n'a été modifié", $erreurs[0]);
    }

    /** Une ligne saisie, elle, se retire — et reste au journal. */
    public function test_un_mouvement_saisi_se_retire_de_la_journee(): void
    {
        [$message, $erreurs] = $this->service([
            'saisis' => [[
                'id' => 3, 'type' => 'ENTREE', 'montant' => 42500.0, 'devise' => 'XOF',
                'libelle' => 'Trop-perçu rendu', 'date_mouvement' => '2026-10-08', 'agence_id' => 3400,
            ]],
        ])->supprimer(3, 7);

        self::assertSame([], $erreurs);
        self::assertStringContainsString('journal', $message);
    }

    /** Un lecteur ne saisit rien et ne retire rien. */
    public function test_la_lecture_seule_ne_corrige_rien(): void
    {
        $service = $this->service(['caisses' => $this->caisses()], $this->lecteur());

        self::assertFalse($service->tableauDuJour([])['peutSaisir']);

        foreach ([
            $service->enregistrerVersement(['caisse_id' => 11], 51),
            $service->enregistrerRetrait(['caisse_id' => 11], 51),
            $service->supprimer(3, 51),
        ] as [$message, $erreurs]) {
            self::assertSame('', $message);
            self::assertSame(['Votre profil consulte les mouvements de caisse sans les modifier.'], $erreurs);
        }
    }

    /**
     * Une caissière ne saisit pas dans la caisse d'une autre agence, même en
     * forgeant la requête.
     */
    public function test_une_caisse_d_une_autre_agence_est_refusee(): void
    {
        $service = new MouvementsCaisseService(
            $this->depot([
                'caisses' => [[
                    'id' => 20, 'nom' => 'Caisse guichet Adjamé', 'type' => 'EXPLOITATION',
                    'solde' => 0.0, 'agence_id' => 3401, 'agence' => 'Adjamé',
                ]],
            ]),
            $this->caissiere()
        );

        [, $erreurs] = $service->enregistrerRetrait([
            'caisse_id' => 20,
            'montant' => '10000',
            'libelle' => 'Taxi coursier',
            'date_mouvement' => '2026-10-08',
        ], 42);

        /*
         * Le refus porte désormais sur l'agence et non sur la caisse : depuis
         * le 10/10/2026 l'unité de l'écran est l'agence, et la caisse nommée
         * n'est plus qu'un détail facultatif. Le contrôle, lui, est le même —
         * on ne saisit pas chez le voisin.
         */
        self::assertContains("Cette agence n'est pas la vôtre.", $erreurs);
    }

    // ------------------------------------------------------------------
    // Ce que la journée range où
    // ------------------------------------------------------------------

    /**
     * Les indicateurs séparent le dossier du colis.
     *
     * Une facture rattachée à un dossier d'envoi est un règlement de dossier,
     * les autres sont des règlements de colis. C'est la coupure que la
     * direction lit, entre le transit monté en dossier et la messagerie au
     * guichet.
     */
    public function test_les_reglements_se_rangent_entre_dossier_et_colis(): void
    {
        $tableau = $this->service([
            'caisses' => $this->caisses(),
            'paiements@2026-10-08' => [
                ['id' => 1, 'montant' => 300000.0, 'devise' => 'XOF', 'mode' => 'especes',
                 'date_paiement' => '2026-10-08 08:10:00', 'numero_facture' => 'FA-1',
                 'agence_id' => 3400, 'agence' => 'Abobo Dokui', 'tiers' => 'SOCIETE NIMBA',
                 'caissier' => 'Caissiere', 'numero_tracking' => null, 'dossier' => 'ABJ-2610-0007'],
                ['id' => 2, 'montant' => 55000.0, 'devise' => 'XOF', 'mode' => 'mobile_money',
                 'date_paiement' => '2026-10-08 10:05:00', 'numero_facture' => 'FA-2',
                 'agence_id' => 3400, 'agence' => 'Abobo Dokui', 'tiers' => 'KONE Awa',
                 'caissier' => 'Caissiere', 'numero_tracking' => 'LBP-0002', 'dossier' => null],
            ],
            'saisis_ENTREE@2026-10-08' => [
                ['id' => 3, 'caisse_id' => 11, 'type' => 'ENTREE', 'montant' => 12000.0, 'devise' => 'XOF',
                 'cadre' => 'AUTRE', 'dossier_numero' => null, 'libelle' => 'Vente de cartons vides',
                 'reference' => null, 'mode_reglement' => 'ESPECES', 'tiers' => 'Client de passage',
                 'agence_id' => 3400, 'date_mouvement' => '2026-10-08',
                 'created_at' => '2026-10-08 17:30:00', 'caisse' => 'Caisse guichet Dokui',
                 'agence' => 'Abobo Dokui', 'caissier' => 'Caissiere'],
            ],
            'demandes@2026-10-08' => [
                ['id' => 4, 'numero_demande' => 'D-1026-10001', 'montant' => 180000.0, 'devise' => 'XOF',
                 'motif' => 'Frais de dédouanement', 'cadre' => 'traitement_dossier',
                 'dossier_num' => 'ABJ-2610-0007', 'date_decaissement' => '2026-10-08 11:00:00',
                 'mode_paiement' => 'Espèces', 'agence_id' => 3400, 'agence' => 'Abobo Dokui',
                 'caissier' => 'Caissiere', 'tiers' => 'DIALLO Mamadou'],
                ['id' => 5, 'numero_demande' => 'D-1026-10002', 'montant' => 25000.0, 'devise' => 'XOF',
                 'motif' => 'Achat de ramettes', 'cadre' => 'fonctionnement',
                 'dossier_num' => null, 'date_decaissement' => '2026-10-08 15:20:00',
                 'mode_paiement' => 'Espèces', 'agence_id' => 3400, 'agence' => 'Abobo Dokui',
                 'caissier' => 'Caissiere', 'tiers' => 'DIALLO Mamadou'],
            ],
        ])->tableauDuJour(['date' => '2026-10-08']);

        $entrees = $tableau['kpis']['entrees'];
        self::assertSame(367000.0, $entrees['total']);
        self::assertSame(300000.0, $entrees['dossiers']);
        self::assertSame(55000.0, $entrees['colis']);
        self::assertSame(12000.0, $entrees['autres']);

        $sorties = $tableau['kpis']['sorties'];
        self::assertSame(205000.0, $sorties['total']);
        self::assertSame(180000.0, $sorties['dossiers']);
        self::assertSame(25000.0, $sorties['autres']);

        self::assertSame(162000.0, $tableau['kpis']['solde']['total']);
        self::assertSame(162000.0, $tableau['cumuls']['solde']);

        // Le règlement de dossier porte le numéro du dossier : c'est par lui
        // que le comptable relie la caisse au départ.
        self::assertSame('ABJ-2610-0007', $tableau['versements'][0]['dossier']);
        // Le libellé reprend mot pour mot le sélecteur du formulaire : l'écran
        // et le tableau doivent nommer la même chose de la même façon.
        self::assertSame('Règlements de factures Dossier', $tableau['versements'][0]['cadre_libelle']);
        // Le mode reste lisible : un règlement par mobile money n'est pas du
        // liquide en tiroir, et la ligne doit le dire.
        self::assertStringContainsString('Mobile money', $tableau['versements'][1]['libelle']);
    }

    /**
     * Une demande imputée reste une sortie du jour du décaissement.
     *
     * L'imputation vient souvent le lendemain et fait passer la demande de
     * 'decaissee' à 'imputee'. S'arrêter au premier statut ferait disparaître
     * la sortie de la journée où l'argent a quitté le tiroir.
     */
    public function test_une_demande_imputee_reste_une_sortie_du_jour_du_decaissement(): void
    {
        self::assertSame(['decaissee', 'imputee'], MouvementsCaisseService::STATUTS_SORTIE);
    }

    /**
     * Les cumuls sont tenus en FCFA.
     *
     * Additionner un euro et un franc ne donnerait un total juste dans aucune
     * des deux monnaies. La ligne en euros reste affichée, avec sa devise.
     */
    public function test_les_cumuls_ne_melangent_pas_les_monnaies(): void
    {
        $tableau = $this->service([
            'caisses' => $this->caisses(),
            'paiements@2026-10-08' => [
                ['id' => 1, 'montant' => 100000.0, 'devise' => 'XOF', 'mode' => 'especes',
                 'date_paiement' => '2026-10-08 08:10:00', 'numero_facture' => 'FA-1',
                 'agence_id' => 3400, 'agence' => 'Abobo Dokui', 'tiers' => 'KONE Awa',
                 'caissier' => 'Caissiere', 'numero_tracking' => null, 'dossier' => null],
                ['id' => 2, 'montant' => 150.0, 'devise' => 'EUR', 'mode' => 'virement',
                 'date_paiement' => '2026-10-08 09:10:00', 'numero_facture' => 'FA-2',
                 'agence_id' => 3500, 'agence' => 'Paris', 'tiers' => 'DUPONT',
                 'caissier' => 'Caissiere Paris', 'numero_tracking' => null, 'dossier' => null],
            ],
        ])->tableauDuJour(['date' => '2026-10-08']);

        self::assertCount(2, $tableau['versements']);
        self::assertSame(100000.0, $tableau['cumuls']['versements']);
        self::assertSame('EUR', $tableau['versements'][1]['devise']);
    }

    // ------------------------------------------------------------------
    // Portée et habilitation
    // ------------------------------------------------------------------

    /** Une caissière ne regarde pas la caisse d'à côté, même en le demandant. */
    public function test_une_caissiere_reste_sur_son_agence(): void
    {
        $filtres = $this->service([], $this->caissiere())->filtres(['agence_id' => 3401]);

        self::assertSame(3400, $filtres['agence_id']);
    }

    /** Le comptable, lui, choisit l'agence qu'il veut, et tout le réseau par défaut. */
    public function test_le_comptable_voit_tout_le_reseau(): void
    {
        $service = $this->service([], $this->comptable());

        self::assertSame(3401, $service->filtres(['agence_id' => 3401])['agence_id']);
        self::assertSame(0, $service->filtres([])['agence_id']);
    }

    /** Une date absente ou illisible vaut aujourd'hui : la journée en cours. */
    public function test_la_journee_par_defaut_est_aujourd_hui(): void
    {
        $service = $this->service([], $this->comptable());

        self::assertSame(date('Y-m-d'), $service->filtres([])['date']);
        self::assertSame(date('Y-m-d'), $service->filtres(['date' => '08/10/2026'])['date']);
        self::assertSame('2026-10-08', $service->filtres(['date' => '2026-10-08'])['date']);
    }

    /** Un tiroir ne contient pas l'argent de demain. */
    public function test_un_mouvement_ne_se_date_pas_dans_l_avenir(): void
    {
        [, $erreurs] = $this->service(['caisses' => $this->caisses()])->enregistrerVersement([
            'caisse_id' => 11,
            'montant' => '5000',
            'libelle' => 'Entrée de demain',
            'date_mouvement' => date('Y-m-d', strtotime('+1 day')),
        ], 7);

        self::assertContains("La date du mouvement ne peut pas être dans l'avenir.", $erreurs);
    }

    /**
     * La saisie reste à qui tient le tiroir.
     *
     * Seule Abobo Dokui a une caissière : partout ailleurs l'agent de saisie
     * encaisse et soumet le point de caisse depuis le 17/09/2026. Lui refuser
     * la saisie laisserait son tiroir en désaccord avec son propre comptage.
     *
     * La direction, son assistante et le chef d'agence lisent et contrôlent,
     * sans s'ajouter de lignes.
     */
    public function test_la_saisie_reste_a_qui_tient_la_caisse(): void
    {
        foreach (['caissiere', 'agent_saisie', 'agent_enregistrement', 'comptable'] as $role) {
            self::assertContains($role, MouvementsCaisseAcces::ROLES_SAISIE, $role);
        }

        foreach (['chef_agence', 'dg', 'assistant_dg', 'assistante_dg', 'dg_surveillance'] as $role) {
            self::assertContains($role, MouvementsCaisseAcces::ROLES_LECTURE, $role);
            self::assertNotContains($role, MouvementsCaisseAcces::ROLES_SAISIE, $role);
        }

        // Et personne n'entre par la lecture : tout rôle qui saisit doit aussi
        // pouvoir ouvrir l'écran, sinon le formulaire serait inatteignable.
        foreach (MouvementsCaisseAcces::ROLES_SAISIE as $role) {
            self::assertContains($role, MouvementsCaisseAcces::ROLES_LECTURE, $role);
        }
    }

    // ------------------------------------------------------------------
    // Le socle : migration, routes, menu
    // ------------------------------------------------------------------

    /**
     * La migration n'ajoute que des colonnes et des index, et dans le bon
     * ordre.
     *
     * run() s'exécute à chaque requête HTTP : deux purges de données sont déjà
     * passées par là. Rien d'autre que du DDL idempotent n'a le droit d'y
     * vivre. Et l'index de remplacement doit être créé avant que l'unique ne
     * soit retiré : c'est lui qui porte la clé étrangère vers company_sites.
     */
    public function test_la_migration_etend_sans_rien_detruire(): void
    {
        $source = (string) file_get_contents(BASE_PATH . '/app/Database/MigrationRunner.php');
        $debut = strpos($source, 'private function createMouvementsCaisseTables(): void');

        self::assertIsInt($debut);

        $bloc = substr($source, $debut, 3500);
        $bloc = substr($bloc, 0, strpos($bloc, 'createMouvementsCaisseTables: ') ?: strlen($bloc));

        self::assertStringContainsString('$this->createMouvementsCaisseTables();', $source);

        foreach (['cadre', 'dossier_numero', 'libelle', 'reference', 'mode_reglement',
                  'tiers', 'agence_id', 'devise', 'date_mouvement', 'annule_le', 'annule_par'] as $colonne) {
            self::assertStringContainsString(
                "addColumnIfMissing('lbp_mouvements_caisse', '" . $colonne . "'",
                $bloc,
                $colonne
            );
        }

        foreach (['nom', 'code', 'type', 'actif'] as $colonne) {
            self::assertStringContainsString("addColumnIfMissing('lbp_caisses', '" . $colonne . "'", $bloc, $colonne);
        }

        /*
         * L'index d'abord, l'unique ensuite : la clé étrangère doit toujours
         * trouver un index dont agency_id est en tête, sinon le DROP échoue.
         *
         * L'unique se retire désormais par sa colonne et non par son nom :
         * en production il s'appelle « agency_id » et non
         * « uniq_lbp_caisses_agency », parce que la table a été créée hors
         * migration. Chercher le nom écrit dans le CREATE TABLE revenait à ne
         * rien retirer. Vérifié sur la base du 10/10/2026.
         */
        self::assertStringContainsString("dropUniqueOnColumn('lbp_caisses', 'agency_id')", $bloc);
        self::assertLessThan(
            (int) strpos($bloc, "dropUniqueOnColumn('lbp_caisses'"),
            (int) strpos($bloc, "addIndexIfMissing('lbp_caisses'"),
            "L'index qui porte la clé étrangère doit exister avant que l'unique ne tombe."
        );

        // Aucune écriture de données dans la migration.
        foreach (['UPDATE ', 'DELETE ', 'INSERT ', 'TRUNCATE'] as $interdit) {
            self::assertStringNotContainsString($interdit, $bloc, $interdit);
        }
    }

    /** Les quatre routes de l'écran, et le menu qui y mène. */
    public function test_les_routes_et_la_tuile_existent(): void
    {
        $routes = (string) file_get_contents(BASE_PATH . '/routes/finance.php');

        self::assertStringContainsString("\$router->get('/mouvements-caisse',", $routes);
        self::assertStringContainsString("\$router->post('/mouvements-caisse/versement',", $routes);
        self::assertStringContainsString("\$router->post('/mouvements-caisse/retrait',", $routes);
        self::assertStringContainsString("\$router->post('/mouvements-caisse/{id}/supprimer',", $routes);

        $navigation = (string) file_get_contents(BASE_PATH . '/app/Services/Shared/ModuleDashboardService.php');

        self::assertStringContainsString(
            "'available' => \\App\\Security\\MouvementsCaisseAcces::peutOuvrir()",
            $navigation
        );
        self::assertStringContainsString("'label' => 'Mouvements de Caisse'", $navigation);
    }

    // ------------------------------------------------------------------
    // Le cadre choisi au formulaire
    // ------------------------------------------------------------------

    /**
     * Le cadre choisi est celui qui est écrit.
     *
     * La direction a tranché en faveur du sélecteur : forcer « autres » ferait
     * mentir la ventilation des trois cartes dès la première saisie manuelle,
     * et le sélecteur de l'écran ne serait qu'un ornement.
     */
    public function test_le_cadre_choisi_est_respecte(): void
    {
        $cas = [
            // Ce que le sélecteur de l'écran propose.
            ['versement', 'FACTURE_DOSSIER', 'FACTURE_DOSSIER'],
            ['versement', 'FACTURE_COLIS', 'FACTURE_COLIS'],
            ['versement', 'AUTRE', 'AUTRE'],
            // Le formulaire poste le mot court de la maquette.
            ['retrait', 'DOSSIER', 'TRAITEMENT_DOSSIER'],
            ['retrait', 'COLIS', 'TRAITEMENT_COLIS'],
            ['retrait', 'FONCTIONNEMENT', 'FONCTIONNEMENT'],
            // Et le code du catalogue, s'il arrive tel quel.
            ['retrait', 'TRAITEMENT_DOSSIER', 'TRAITEMENT_DOSSIER'],
            // Les cadres d'une demande de fonds, écrits en minuscules en base.
            ['retrait', 'traitement_dossier', 'TRAITEMENT_DOSSIER'],
            // Un appro ne se saisit pas ici : il entre par son propre écran.
            ['versement', 'APPRO', 'AUTRE'],
            // Une valeur forgée ne crée pas de colonne, et ne gonfle pas les
            // dossiers sans qu'une facture le justifie.
            ['versement', 'N_IMPORTE_QUOI', 'AUTRE'],
            ['retrait', 'N_IMPORTE_QUOI', 'FONCTIONNEMENT'],
            ['retrait', '', 'FONCTIONNEMENT'],
        ];

        foreach ($cas as [$sens, $poste, $attendu]) {
            $depot = $this->depot(['caisses' => $this->caisses()]);
            $service = new MouvementsCaisseService($depot, $this->comptable());

            $saisie = [
                'caisse_id' => 11,
                'montant' => '50000',
                'libelle' => 'Ligne de contrôle',
                'date_mouvement' => '2026-10-08',
                'cadre' => $poste,
            ];

            [, $erreurs] = $sens === 'versement'
                ? $service->enregistrerVersement($saisie, 7)
                : $service->enregistrerRetrait($saisie, 7);

            self::assertSame([], $erreurs, $sens . ' ' . $poste);
            self::assertSame($attendu, $depot->ecrit['cadre'], $sens . ' ' . $poste);
        }
    }

    /**
     * Et le cadre choisi range la somme dans la bonne carte : c'est tout
     * l'objet de l'amendement.
     */
    public function test_une_saisie_alimente_la_colonne_choisie(): void
    {
        $tableau = $this->service([
            'caisses' => $this->caisses(),
            'saisis_ENTREE@2026-10-08' => [
                ['id' => 1, 'type' => 'ENTREE', 'montant' => 90000.0, 'devise' => 'XOF',
                 'cadre' => 'FACTURE_DOSSIER', 'libelle' => 'Reliquat de dossier rendu au guichet',
                 'agence_id' => 3400, 'date_mouvement' => '2026-10-08',
                 'created_at' => '2026-10-08 09:00:00', 'caisse' => 'Caisse guichet Dokui'],
            ],
            'saisis_DECAISSEMENT@2026-10-08' => [
                ['id' => 2, 'type' => 'DECAISSEMENT', 'montant' => 7000.0, 'devise' => 'XOF',
                 'cadre' => 'TRAITEMENT_COLIS', 'libelle' => 'Manutention d\'un colis hors bordereau',
                 'agence_id' => 3400, 'date_mouvement' => '2026-10-08',
                 'created_at' => '2026-10-08 16:00:00', 'caisse' => 'Caisse guichet Dokui'],
            ],
        ])->tableauDuJour(['date' => '2026-10-08']);

        self::assertSame(90000.0, $tableau['kpis']['entrees']['dossiers']);
        self::assertSame(0.0, $tableau['kpis']['entrees']['autres']);
        // La colonne « colis » des sorties n'est plus condamnée à zéro.
        self::assertSame(7000.0, $tableau['kpis']['sorties']['colis']);
        self::assertSame(0.0, $tableau['kpis']['sorties']['autres']);
    }

    /**
     * Un règlement par Wave n'est pas du liquide en tiroir.
     *
     * Le formulaire poste le libellé de l'opérateur, pas un code. Sans
     * traduction, tout ce qui n'était pas « Espèces » retombait sur ESPECES et
     * le comptage du soir ne tombait plus juste.
     */
    public function test_le_mode_de_reglement_poste_en_clair_est_compris(): void
    {
        foreach ([
            'Espèces' => 'ESPECES',
            'Wave' => 'MOBILE_MONEY',
            'Orange Money' => 'MOBILE_MONEY',
            'MTN MoMo' => 'MOBILE_MONEY',
            'Virement bancaire' => 'VIREMENT',
            'Chèque' => 'CHEQUE',
        ] as $poste => $attendu) {
            $depot = $this->depot(['caisses' => $this->caisses()]);

            (new MouvementsCaisseService($depot, $this->comptable()))->enregistrerVersement([
                'caisse_id' => 11,
                'montant' => '1000',
                'libelle' => 'Contrôle du mode',
                'date_mouvement' => '2026-10-08',
                'mode_reglement' => $poste,
            ], 7);

            self::assertSame($attendu, $depot->ecrit['mode_reglement'], $poste);
        }
    }

    // ------------------------------------------------------------------
    // Les euros, à côté des francs
    // ------------------------------------------------------------------

    /**
     * Paris ne doit pas lire des historiques pleins et des compteurs à zéro.
     *
     * Les deux monnaies sont comptées côte à côte, jamais additionnées et
     * jamais converties — comme le point de caisse tient déjà
     * solde_caisse_agence_xof et _eur.
     */
    public function test_les_deux_monnaies_sont_comptees_cote_a_cote(): void
    {
        $tableau = $this->service([
            'caisses' => $this->caisses(),
            'paiements@2026-10-08' => [
                ['id' => 1, 'montant' => 100000.0, 'devise' => 'XOF', 'mode' => 'especes',
                 'date_paiement' => '2026-10-08 08:10:00', 'numero_facture' => 'FA-1',
                 'agence_id' => 3400, 'agence' => 'Abobo Dokui', 'tiers' => 'KONE Awa',
                 'caissier' => 'Caissiere', 'numero_tracking' => null, 'dossier' => 'ABJ-1'],
                ['id' => 2, 'montant' => 150.0, 'devise' => 'EUR', 'mode' => 'virement',
                 'date_paiement' => '2026-10-08 09:10:00', 'numero_facture' => 'FA-2',
                 'agence_id' => 3500, 'agence' => 'Paris', 'tiers' => 'DUPONT',
                 'caissier' => 'Caissiere Paris', 'numero_tracking' => null, 'dossier' => null],
            ],
            'demandes@2026-10-08' => [
                ['id' => 4, 'numero_demande' => 'D-1', 'montant' => 40.0, 'devise' => 'EUR',
                 'motif' => 'Timbres', 'cadre' => 'fonctionnement', 'dossier_num' => null,
                 'date_decaissement' => '2026-10-08 11:00:00', 'mode_paiement' => 'Espèces',
                 'agence_id' => 3500, 'agence' => 'Paris', 'caissier' => 'Caissiere Paris',
                 'tiers' => 'DUPONT'],
            ],
        ])->tableauDuJour(['date' => '2026-10-08']);

        $entrees = $tableau['kpis']['entrees'];
        self::assertSame(100000.0, $entrees['total']);
        self::assertSame(100000.0, $entrees['dossiers']);
        self::assertSame(150.0, $entrees['total_eur']);
        self::assertSame(0.0, $entrees['dossiers_eur']);
        self::assertSame(150.0, $entrees['colis_eur']);

        self::assertSame(0.0, $tableau['kpis']['sorties']['total']);
        self::assertSame(40.0, $tableau['kpis']['sorties']['total_eur']);
        self::assertSame(40.0, $tableau['kpis']['sorties']['autres_eur']);

        // Aucune addition entre les deux, aucun taux.
        self::assertSame(100000.0, $tableau['kpis']['solde']['total']);
        self::assertSame(110.0, $tableau['kpis']['solde']['total_eur']);
        self::assertSame(100000.0, $tableau['cumuls']['versements']);
        self::assertSame(150.0, $tableau['cumuls']['versements_eur']);
        self::assertSame(40.0, $tableau['cumuls']['retraits_eur']);
        self::assertSame(110.0, $tableau['cumuls']['solde_eur']);
    }

    /** La règle du null vaut aussi pour les euros. */
    public function test_la_variation_en_euros_suit_la_meme_regle(): void
    {
        $tableau = $this->service([
            'caisses' => $this->caisses(),
            'paiements@2026-10-08' => [
                ['id' => 2, 'montant' => 150.0, 'devise' => 'EUR', 'mode' => 'virement',
                 'date_paiement' => '2026-10-08 09:10:00', 'numero_facture' => 'FA-2',
                 'agence_id' => 3500, 'agence' => 'Paris', 'tiers' => 'DUPONT',
                 'caissier' => 'Caissiere Paris', 'numero_tracking' => null, 'dossier' => null],
            ],
        ])->tableauDuJour(['date' => '2026-10-08']);

        self::assertSame(0.0, $tableau['kpis']['entrees']['veille_eur']);
        self::assertNull($tableau['kpis']['entrees']['variation_eur']);
        self::assertNull($tableau['kpis']['solde']['variation_eur']);
    }

    /**
     * Les quatre exports sont câblés, et l'écran les atteint.
     *
     * Le composant construit les liens « Exporter en PDF » et « Exporter en
     * Excel » à partir du sens de l'historique : sans ces routes, les quatre
     * boutons de l'écran mènent à une page d'erreur.
     */
    public function test_les_quatre_exports_sont_cables(): void
    {
        $routes = (string) file_get_contents(BASE_PATH . '/routes/finance.php');
        $controleur = (string) file_get_contents(BASE_PATH . '/app/Controllers/Finance/MouvementsCaisseController.php');

        foreach (['versements/pdf' => 'versementsPdf', 'versements/excel' => 'versementsExcel',
                  'retraits/pdf' => 'retraitsPdf', 'retraits/excel' => 'retraitsExcel'] as $url => $action) {
            self::assertStringContainsString("\$router->get('/mouvements-caisse/" . $url . "',", $routes, $url);
            self::assertStringContainsString('public function ' . $action . '(): void', $controleur, $action);
        }

        // Les deux vues existent et ne font qu'appeler le composant.
        foreach (['export_pdf', 'export_excel'] as $vue) {
            $chemin = BASE_PATH . '/views/finance/mouvements-caisse/' . $vue . '.php';
            self::assertFileExists($chemin);
            self::assertStringContainsString('MouvementsCaisse::', (string) file_get_contents($chemin), $vue);
        }

        // La marque d'encodage, sinon le tableur lit « Ã© » à la place des accents.
        self::assertStringContainsString('"\xEF\xBB\xBF"', $controleur);
        self::assertStringContainsString('application/vnd.ms-excel; charset=utf-8', $controleur);
    }

    /**
     * Le document sort exactement les lignes de l'écran.
     *
     * Les filtres sont relus depuis la même requête GET et le tableau est
     * recalculé par le même appel de service : un PDF qui dirait autre chose
     * que l'écran d'où il sort ne servirait qu'à faire douter des deux.
     */
    public function test_l_export_reprend_les_lignes_de_l_ecran(): void
    {
        $controleur = (string) file_get_contents(BASE_PATH . '/app/Controllers/Finance/MouvementsCaisseController.php');
        $bloc = substr($controleur, (int) strpos($controleur, 'private function exporter'), 1400);

        self::assertStringContainsString('$this->service->tableauDuJour($_GET)', $bloc);
        // Et l'export est fermé comme l'écran : mêmes rôles de lecture.
        self::assertStringContainsString('RoleMiddleware::check(MouvementsCaisseAcces::ROLES_LECTURE)', $bloc);
    }

    /** L'écran entre dans la liste des pages chargées avant un push. */
    public function test_l_ecran_est_dans_le_smoke_des_pages(): void
    {
        $smoke = (string) file_get_contents(BASE_PATH . '/tests/Smoke/smoke_pages.php');

        self::assertStringContainsString("'/finance/mouvements-caisse'", $smoke);
    }

    /**
     * L'écran d'appro n'est pas touché : ses appros validés sont seulement lus.
     */
    public function test_l_ecran_d_appro_caisse_n_est_pas_touche(): void
    {
        $service = (string) file_get_contents(BASE_PATH . '/app/Services/Finance/MouvementsCaisseService.php');
        $depot = (string) file_get_contents(BASE_PATH . '/app/Repositories/Finance/MouvementsCaisseRepository.php');

        // Lecture seule de la table des appros, et aucun appel d'écriture au
        // service d'appro.
        self::assertStringContainsString('FROM lbp_appros_caisse a', $depot);
        self::assertStringNotContainsString('lbp_appros_caisse', $service);
        self::assertStringNotContainsString('ApproCaisseService::creer', $service);
    }

    /**
     * Le piège des deux colonnes de mode : seule `mode` est écrite à
     * l'encaissement, `mode_paiement` reste à 'ESPECES' pour tout le monde.
     * S'en servir comme filtre masquerait les trois quarts de la journée.
     */
    public function test_le_mode_de_paiement_ne_sert_pas_de_filtre(): void
    {
        $depot = (string) file_get_contents(BASE_PATH . '/app/Repositories/Finance/MouvementsCaisseRepository.php');
        $paiements = substr($depot, (int) strpos($depot, 'public function paiements'), 2600);

        self::assertStringNotContainsString('p.mode_paiement', $paiements);
        self::assertStringContainsString('p.mode,', $paiements);
    }

    /**
     * PDO tourne sans emulated prepares : un paramètre nommé ne peut pas
     * apparaître deux fois dans la même requête.
     */
    public function test_aucun_parametre_nomme_n_est_repete(): void
    {
        $depot = (string) file_get_contents(BASE_PATH . '/app/Repositories/Finance/MouvementsCaisseRepository.php');

        self::assertSame(0, preg_match_all('/LIKE :q\b/', $depot), 'Les recherches numérotent leurs paramètres.');
        self::assertGreaterThan(0, preg_match_all('/LIKE :q1\b/', $depot));
    }
}
