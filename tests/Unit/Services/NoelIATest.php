<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Noel\NoelCarte;
use App\Services\Noel\NoelIA;
use PHPUnit\Framework\TestCase;

final class NoelIATest extends TestCase
{
    public function testSansIAConfigureeNoelGardeSesRegles(): void
    {
        $ia = new NoelIA(['ia' => 'aucun']);

        self::assertNull($ia->repondre('Comment je fais ?', new NoelCarte()));
    }

    public function testSansAucunEcranLIALeSaitEtNInventeRien(): void
    {
        // Port fermé : l'appel échoue vite, la réponse est null, aucune exception.
        $ia = new NoelIA(['ia' => 'ollama', 'ia_url' => 'http://127.0.0.1:9', 'ia_delai_secondes' => 10]);
        $texte = @$ia->connaissances('Comment encaisser une facture ?', []);

        self::assertStringContainsString('aucun', $texte);
    }

    public function testLaReflexionVisibleEstRetiree(): void
    {
        self::assertSame('Ouvrez Finance > Factures Clients.', NoelIA::nettoyer("<think>je réfléchis</think>\n\nOuvrez Finance > Factures Clients."));
        self::assertNull(NoelIA::nettoyer('<think>rien</think>   '));
        // Réflexion dont seule la fin est marquée : on garde ce qui suit.
        self::assertSame('Désolé Serge, je transmets.', NoelIA::nettoyer("Okay, let's see...\n</think>\n\nDésolé Serge, je transmets."));
    }

    public function testLIANeLitQueLesEcransDeLaPersonneEtLaProcedureProche(): void
    {
        $texte = (new NoelIA(['ia' => 'ollama']))->connaissances('comment encaisser un reglement de facture', [
            'finance' => ['label' => 'Finance', 'ecrans' => [['label' => 'Factures Clients', 'url' => 'finance/factures']]],
        ]);

        self::assertStringContainsString('Finance : Factures Clients', $texte);
        self::assertStringContainsString('Encaisser un règlement', $texte);
        self::assertStringNotContainsString('Ressources humaines', $texte);
    }
}
