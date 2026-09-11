<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Tests\TestCase;

/**
 * Les scripts d'audit ne doivent jamais écrire.
 *
 * Ils sont faits pour être lancés sur la base de production, par quelqu'un qui
 * cherche à comprendre un écart. Une écriture glissée là, même bien
 * intentionnée, modifierait ce qu'on est en train d'observer — et le ferait sans
 * sauvegarde préalable, puisque personne n'en prend avant de lire.
 *
 * Les scripts qui corrigent, eux, portent « Recalculer » dans leur nom, exigent
 * --appliquer et rappellent de sauvegarder.
 */
final class AuditLectureSeuleTest extends TestCase
{
    /** Scripts dont la vocation est d'observer sans rien changer. */
    private const SCRIPTS_AUDIT = [
        'app/Console/AuditEncaissements.php',
    ];

    private const MOTS_ECRITURE = [
        'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'TRUNCATE',
        'DROP', 'ALTER', 'CREATE', 'GRANT', 'LOCK TABLES',
    ];

    public function test_aucun_script_d_audit_ne_contient_d_ecriture_sql(): void
    {
        foreach (self::SCRIPTS_AUDIT as $relatif) {
            $chemin = BASE_PATH . '/' . $relatif;
            self::assertFileExists($chemin);

            $contenu = (string) file_get_contents($chemin);
            $trouves = [];

            foreach (self::MOTS_ECRITURE as $mot) {
                if (preg_match('/\b' . preg_quote($mot, '/') . '\b/i', $contenu)) {
                    $trouves[] = $mot;
                }
            }

            self::assertSame(
                [],
                $trouves,
                "{$relatif} contient un mot-clé d'écriture : " . implode(', ', $trouves)
                    . ". Un audit se lance sur la production et ne doit rien y modifier."
            );
        }
    }

    /**
     * bootstrap/app.php déclenche le MigrationRunner, qui exécute du DDL à
     * chaque appel. Un script d'audit qui le chargerait écrirait dans la base
     * avant même d'avoir lu quoi que ce soit.
     */
    public function test_aucun_script_d_audit_ne_charge_le_bootstrap_applicatif(): void
    {
        foreach (self::SCRIPTS_AUDIT as $relatif) {
            $contenu = (string) file_get_contents(BASE_PATH . '/' . $relatif);

            self::assertDoesNotMatchRegularExpression(
                '#(require|include)(_once)?\s*.{0,40}bootstrap/app\.php#',
                $contenu,
                "{$relatif} charge bootstrap/app.php, qui lance les migrations."
            );
        }
    }

    /**
     * Un script qui corrige ne doit pas le faire sans qu'on le lui demande.
     */
    public function test_les_scripts_de_correction_exigent_un_drapeau_explicite(): void
    {
        $correcteurs = [
            'app/Console/RecalculerPointsCaisse.php',
            'app/Console/RecalculerSoldesCaisse.php',
        ];

        foreach ($correcteurs as $relatif) {
            $chemin = BASE_PATH . '/' . $relatif;
            if (!is_file($chemin)) {
                continue;
            }

            $contenu = (string) file_get_contents($chemin);

            self::assertStringContainsString('--appliquer', $contenu, $relatif);
            self::assertStringContainsString(
                'mysqldump',
                $contenu,
                "{$relatif} doit rappeler de sauvegarder la table avant d'écrire."
            );
        }
    }
}
