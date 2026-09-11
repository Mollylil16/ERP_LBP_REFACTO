<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Tests\TestCase;

/**
 * Un paramètre nommé ne doit apparaître qu'une fois par requête.
 *
 * La connexion est ouverte avec PDO::ATTR_EMULATE_PREPARES à false : MySQL
 * prépare lui-même la requête et attend autant de valeurs que de marqueurs.
 * Réutiliser « :agence » deux fois lève alors SQLSTATE[HY093], alors que la même
 * écriture fonctionne quand l'émulation est active — d'où des requêtes qui
 * passent en développement et tombent ailleurs.
 *
 * Ce test a été écrit après en avoir trouvé six, dont la recherche de colis du
 * Call Center et la lecture du portefeuille client.
 *
 * Portée : les requêtes écrites d'un seul tenant. Celles assemblées par
 * concaténation d'un fragment conditionnel échappent à l'analyse statique ; pour
 * celles-là, c'est le chargement réel des écrans qui fait foi
 * (tests/Smoke/smoke_pages.php).
 */
final class ParametresSqlUniquesTest extends TestCase
{
    /** Mots-clés qui distinguent une requête d'une chaîne quelconque. */
    private const MOTS_SQL = ['SELECT ', 'INSERT ', 'UPDATE ', 'DELETE ', 'REPLACE '];

    /**
     * Faux positifs connus : des chaînes qui contiennent « : » suivi d'un mot
     * sans être des marqueurs — déclarations CSS, constantes de classe.
     */
    private const IGNORES = ['FETCH_ASSOC', 'FETCH_COLUMN', 'FETCH_KEY_PAIR', 'FETCH_OBJ'];

    public function test_aucune_requete_ne_repete_un_parametre_nomme(): void
    {
        $fautifs = [];

        foreach ($this->fichiers() as $chemin) {
            $contenu = (string) file_get_contents($chemin);
            $relatif = str_replace(BASE_PATH . DIRECTORY_SEPARATOR, '', $chemin);

            foreach ($this->requetes($contenu) as [$sql, $decalage]) {
                $repetes = $this->parametresRepetes($sql);

                if ($repetes === []) {
                    continue;
                }

                $ligne = substr_count(substr($contenu, 0, $decalage), "\n") + 1;
                $fautifs[] = $relatif . ':' . $ligne . ' — ' . implode(', ', $repetes);
            }
        }

        self::assertSame(
            [],
            $fautifs,
            "Paramètre(s) nommé(s) répété(s) dans une requête.\n"
                . "MySQL attend une valeur par marqueur : la requête lèvera SQLSTATE[HY093].\n"
                . "Donnez un nom distinct à chaque occurrence :\n  "
                . implode("\n  ", $fautifs)
        );
    }

    /**
     * Un test qui ne trouve rien ne prouve rien tant qu'on n'a pas vérifié
     * qu'il sait trouver. On lui repasse les requêtes réellement fautives
     * rencontrées dans le projet.
     */
    public function test_le_detecteur_reconnait_le_motif_fautif(): void
    {
        $fautives = [
            'call center' => 'SELECT * FROM c WHERE phone LIKE :q_like OR name LIKE :q_like',
            'portefeuille' => "SELECT * FROM w WHERE (nom = :c_name AND :c_name != '') LIMIT 1",
            'emballages' => 'INSERT INTO s VALUES (:qte) ON DUPLICATE KEY UPDATE q = q + :qte',
            'agence' => 'SELECT 1 FROM c WHERE depart = :agence OR arrivee = :agence',
        ];

        foreach ($fautives as $nom => $sql) {
            self::assertNotSame([], $this->parametresRepetes($sql), "Motif « {$nom} » non détecté.");
        }

        $correctes = [
            'deux noms distincts' => 'SELECT 1 FROM c WHERE depart = :agence_depart OR arrivee = :agence_arrivee',
            'marqueur unique' => 'SELECT 1 FROM c WHERE id = :id',
            'heure dans un texte' => "SELECT 'Ouvert 08:30 a 17:30' AS horaire FROM c WHERE id = :id",
        ];

        foreach ($correctes as $nom => $sql) {
            self::assertSame([], $this->parametresRepetes($sql), "Faux positif sur « {$nom} ».");
        }
    }

    /**
     * Chaînes du fichier qui ressemblent à une requête.
     *
     * L'extraction passe par le tokenizer de PHP plutôt que par une expression
     * régulière : les commentaires français sont pleins d'apostrophes, qu'une
     * regex prend pour des délimiteurs de chaîne et qui lui font fusionner deux
     * requêtes voisines en une seule.
     *
     * @return array<int, array{0: string, 1: int}>
     */
    private function requetes(string $contenu): array
    {
        $requetes = [];

        foreach (token_get_all($contenu) as $jeton) {
            if (!is_array($jeton)) {
                continue;
            }

            [$type, $texte, $ligne] = $jeton;

            // Chaîne simple, et morceau de chaîne interpolée ou de heredoc :
            // les requêtes du projet utilisent les trois formes.
            if ($type !== T_CONSTANT_ENCAPSED_STRING && $type !== T_ENCAPSED_AND_WHITESPACE) {
                continue;
            }

            $majuscules = strtoupper($texte);
            foreach (self::MOTS_SQL as $mot) {
                if (str_contains($majuscules, $mot)) {
                    $requetes[] = [$texte, (int) $ligne];
                    break;
                }
            }
        }

        return $requetes;
    }

    /**
     * @return array<int, string>
     */
    private function parametresRepetes(string $sql): array
    {
        // Le deux-points doublé de PostgreSQL et les heures « 12:30 » ne sont
        // pas des marqueurs : on n'accepte qu'une lettre ou un souligné après.
        if (!preg_match_all('/(?<![:\w]):([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $trouves)) {
            return [];
        }

        $comptes = array_count_values($trouves[1]);
        $repetes = [];

        foreach ($comptes as $nom => $nombre) {
            if ($nombre > 1 && !in_array($nom, self::IGNORES, true)) {
                $repetes[] = ':' . $nom . ' (×' . $nombre . ')';
            }
        }

        sort($repetes);

        return $repetes;
    }

    /**
     * @return array<int, string>
     */
    private function fichiers(): array
    {
        $fichiers = [];

        $iterateur = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(BASE_PATH . '/app', \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterateur as $fichier) {
            if ($fichier->isFile() && $fichier->getExtension() === 'php') {
                $fichiers[] = $fichier->getPathname();
            }
        }

        return $fichiers;
    }
}
