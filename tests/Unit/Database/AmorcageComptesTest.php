<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Tests\TestCase;

/**
 * L'amorçage des comptes du personnel ne doit rien regouverner.
 *
 * MigrationRunner::run() s'exécute à chaque page servie. Le bloc qui installe
 * les rôles d'origine d'une douzaine de comptes effaçait tous leurs rôles pour
 * réinscrire celui de la liste, forçait leur agence et les réactivait — à
 * chaque requête.
 *
 * Constaté le 30/09/2026 : Mme Carine, nommée caissière principale, reperdait
 * le rôle au clic suivant, et l'appro caisse lui restait fermé. Toute décision
 * prise depuis Administration sur ces comptes était défaite le lendemain.
 *
 * Ce test garde la frontière : amorcer un compte vide, oui ; réécrire un
 * compte vivant, jamais.
 */
final class AmorcageComptesTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(BASE_PATH . '/app/Database/MigrationRunner.php');
    }

    /** Les rôles d'un compte ne s'effacent plus à chaque requête. */
    public function test_l_amorcage_n_efface_plus_les_roles(): void
    {
        $source = $this->source();
        $bloc = $this->blocAmorcage($source);

        self::assertStringNotContainsString('DELETE FROM lbp_user_roles', $bloc);
        self::assertStringContainsString('SELECT COUNT(*) FROM lbp_user_roles WHERE user_id = ?', $bloc);
        self::assertStringContainsString("=== 0", $bloc, "Le rôle ne s'installe que sur un compte sans aucun rôle.");
    }

    /** L'agence ne se réécrit que si le compte n'en a pas. */
    public function test_l_agence_ne_se_reecrit_que_si_elle_manque(): void
    {
        self::assertStringContainsString(
            'UPDATE users SET agence_id = ? WHERE id = ? AND (agence_id IS NULL OR agence_id = 0)',
            $this->blocAmorcage($this->source())
        );
    }

    /** Un compte désactivé l'a été pour une raison : il ne se rallume pas seul. */
    public function test_un_compte_desactive_ne_se_reactive_pas_seul(): void
    {
        self::assertStringNotContainsString("status = 'active' WHERE id = ?", $this->blocAmorcage($this->source()));
    }

    /** Une permission déjà arbitrée par l'administration reste telle qu'il l'a voulue. */
    public function test_les_permissions_arbitrees_survivent(): void
    {
        $bloc = $this->blocAmorcage($this->source());

        self::assertStringContainsString('INSERT IGNORE INTO user_permissions', $bloc);
        self::assertStringNotContainsString('ON DUPLICATE KEY UPDATE can_view', $bloc);
    }

    /**
     * Le bloc d'amorçage seul : le reste du fichier contient d'autres écritures
     * légitimes, que ce test n'a pas à juger.
     */
    private function blocAmorcage(string $source): string
    {
        $debut = strpos($source, 'private function assignStaffRolesAndAgencies(): void');
        self::assertNotFalse($debut, "Le bloc d'amorçage des comptes a disparu.");

        $fin = strpos($source, 'private function', $debut + 60);

        return substr($source, $debut, $fin === false ? 20000 : $fin - $debut);
    }
}
