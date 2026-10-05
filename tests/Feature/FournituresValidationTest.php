<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\View\Components\Colisage;
use Tests\TestCase;

/**
 * Fournitures de bureau : les deux signatures doivent s'afficher.
 *
 * Le tableau testait $droits['peutApprouver'], mais fournituresPage() gardait
 * $droits pour lui et ne le passait pas a fournituresTable(). empty() sur une
 * variable inexistante ne previent de rien : le test etait silencieusement
 * toujours faux, et le bouton « Approuver » n'apparaissait pour personne —
 * pas meme pour le superviseur regional. Les demandes restaient donc en
 * attente indefiniment, sans que l'ecran n'explique pourquoi.
 */
final class FournituresValidationTest extends TestCase
{
    /** @return array<int, array<string, mixed>> */
    private function demande(string $statut, int $approuvePar = 0): array
    {
        return [[
            'id' => 7,
            'status' => $statut,
            'validated_by' => $approuvePar,
            'rejection_reason' => null,
            'quantite' => 3,
            'prix_unitaire' => 1500,
            'montant' => 4500,
            'items_requested' => 'RAMETTES A4',
            'agence_name' => 'Agence Abobo Dokui',
            'demandeur_name' => 'KOUAKOU SALES',
            'created_at' => '2026-10-05 09:00:00',
        ]];
    }

    public function testLeSuperviseurVoitLeBoutonApprouver(): void
    {
        $html = Colisage::fournituresTable($this->demande('EN_ATTENTE'), ['peutApprouver' => true]);

        $this->assertStringContainsString('Approuver', $html);
        $this->assertStringContainsString('value="APPROUVEE"', $html);
    }

    public function testSansLeDroitLeBoutonApprouverDisparait(): void
    {
        $html = Colisage::fournituresTable($this->demande('EN_ATTENTE'), ['peutApprouver' => false]);

        $this->assertStringNotContainsString('value="APPROUVEE"', $html);
        $this->assertStringContainsString('En attente du superviseur', $html);
    }

    public function testLeComptableVoitLeBoutonConfirmer(): void
    {
        $html = Colisage::fournituresTable($this->demande('APPROUVEE'), ['peutConfirmer' => true]);

        $this->assertStringContainsString('value="CONFIRMEE"', $html);
    }

    public function testLeSuperviseurNeConfirmePas(): void
    {
        // La seconde signature revient au comptable : celui qui a approuve ne
        // doit pas pouvoir engager la depense seul.
        $html = Colisage::fournituresTable($this->demande('APPROUVEE'), ['peutApprouver' => true]);

        $this->assertStringNotContainsString('value="CONFIRMEE"', $html);
        $this->assertStringContainsString('en attente du comptable', $html);
    }

    public function testLaDirectionPeutConfirmerElleAussi(): void
    {
        // Demande du 05/10/2026 : le DG et son assistante ne doivent pas
        // attendre le comptable pour debloquer une fourniture.
        $html = Colisage::fournituresTable(
            $this->demande('APPROUVEE', 12),
            ['peutConfirmer' => true, 'utilisateur' => 3]
        );

        $this->assertStringContainsString('value="CONFIRMEE"', $html);
    }

    public function testPersonneNeConfirmeCeQuIlAApprouve(): void
    {
        // Meme avec le droit, poser les deux signatures soi-meme viderait le
        // controle a deux mains de son sens.
        $html = Colisage::fournituresTable(
            $this->demande('APPROUVEE', 3),
            ['peutConfirmer' => true, 'utilisateur' => 3]
        );

        $this->assertStringNotContainsString('value="CONFIRMEE"', $html);
        $this->assertStringContainsString('un autre doit confirmer', $html);
    }

    public function testLeStatutConfirmeEstEcritEnFrancais(): void
    {
        $html = Colisage::fournituresTable($this->demande('CONFIRMEE'), ['peutConfirmer' => true]);

        $this->assertStringContainsString('CONFIRM', $html);
        $this->assertStringContainsString('value="LIVREE"', $html);
    }
}
