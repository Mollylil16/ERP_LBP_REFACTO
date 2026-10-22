<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\MessagerieDirection;
use Tests\TestCase;

final class MessagerieDirectionTest extends TestCase
{
    /** @param array<int, array<string, mixed>> $conversations */
    private function vue(array $conversations = [], array $acces = []): array
    {
        return ['conversations' => $conversations, 'acces' => $acces, 'plafond' => 200];
    }

    public function test_les_noms_sont_echappes_et_chaque_ligne_ouvre_la_discussion(): void
    {
        $html = MessagerieDirection::ecran($this->vue([[
            'id' => 12, 'type' => 'groupe', 'nom' => '<script>x</script>',
            'participants' => 'Awa <b>K</b>', 'nb_messages' => 3,
            'dernier_message_at' => '2026-10-22 09:05:00', 'created_at' => '2026-10-22 09:00:00',
        ]], [[
            'created_at' => '2026-10-22 10:00:00', 'lecteur' => '<i>DG</i>',
            'conversation_id' => 12, 'type' => 'groupe', 'nom' => null,
        ]]), ['q' => '"><img>', 'type' => '']);

        self::assertStringNotContainsString('<script>x</script>', $html);
        self::assertStringNotContainsString('<b>K</b>', $html);
        self::assertStringNotContainsString('<i>DG</i>', $html);
        self::assertStringNotContainsString('"><img>', $html);
        self::assertStringContainsString('#c=12', $html);
        self::assertStringContainsString('22/10/2026 09:05', $html);
        self::assertStringContainsString('Discussion n° 12', $html);
    }

    public function test_une_directe_porte_le_nom_des_deux_participants(): void
    {
        $html = MessagerieDirection::ecran($this->vue([[
            'id' => 4, 'type' => 'direct', 'nom' => null, 'participants' => 'Awa, Bakary',
            'nb_messages' => 0, 'dernier_message_at' => null, 'created_at' => '2026-10-22 09:00:00',
        ]]), ['q' => '', 'type' => '']);

        self::assertStringContainsString('Awa, Bakary', $html);
        self::assertStringContainsString('Discussion à deux', $html);
    }

    public function test_les_etats_vides_disent_quoi_faire(): void
    {
        $sans = MessagerieDirection::ecran($this->vue(), ['q' => '', 'type' => '']);
        $filtre = MessagerieDirection::ecran($this->vue(), ['q' => 'Zoé', 'type' => '']);

        self::assertStringContainsString('Aucune discussion pour l’instant', $sans);
        self::assertStringContainsString('Aucune discussion ne correspond', $filtre);
        self::assertStringContainsString('Aucune ouverture', $sans);
    }
}
