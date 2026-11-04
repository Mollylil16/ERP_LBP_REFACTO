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

        self::assertNull($ia->repondre('Comment je fais ?', $this->createStub(NoelCarte::class)));
    }

    public function testUneMachineInjoignableNeRendPasNoelMuetEtNeLevePasDErreur(): void
    {
        $carte = $this->createStub(NoelCarte::class);
        $carte->method('modulesVisibles')->willReturn([]);
        // Port fermé : l'appel échoue vite, la réponse est null.
        $ia = new NoelIA(['ia' => 'ollama', 'ia_url' => 'http://127.0.0.1:9', 'ia_delai_secondes' => 10]);

        self::assertNull(@$ia->repondre('Comment encaisser une facture ?', $carte));
    }

    public function testLaReflexionVisibleEstRetiree(): void
    {
        self::assertSame('Ouvrez Finance > Factures Clients.', NoelIA::nettoyer("<think>je réfléchis</think>\n\nOuvrez Finance > Factures Clients."));
        self::assertNull(NoelIA::nettoyer('<think>rien</think>   '));
    }

    public function testLIANeLitQueLesEcransDeLaPersonneEtLaProcedureProche(): void
    {
        $carte = $this->createStub(NoelCarte::class);
        $carte->method('modulesVisibles')->willReturn([
            'finance' => ['label' => 'Finance', 'ecrans' => [['label' => 'Factures Clients', 'url' => 'finance/factures']]],
        ]);
        $texte = (new NoelIA(['ia' => 'ollama']))->connaissances('comment encaisser un reglement de facture', $carte);

        self::assertStringContainsString('Finance : Factures Clients', $texte);
        self::assertStringContainsString('Encaisser un règlement', $texte);
        self::assertStringNotContainsString('Ressources humaines', $texte);
    }
}
