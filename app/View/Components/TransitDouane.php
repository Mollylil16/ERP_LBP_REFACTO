<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class TransitDouane
{
    /**
     * @param array<string, mixed> $donnees
     */
    public static function dossiersPage(array $donnees, string $agenceLabel): string
    {
        $entete = Ui::pageHeader(
            'Transit Douane',
            'Lots de dédouanement, ventilation des frais et coût de revient au kilo. Les frais sont saisis dans Finance › Coûts d\'approche.',
            [
                'eyebrow' => 'TDO • ' . $agenceLabel,
                'class' => 'rh-hero-white',
                'actions' => Ui::button('Saisir des coûts d\'approche', ['href' => 'finance/couts-approche', 'variant' => 'secondary']),
            ]
        );

        return '<div class="finea-shell"><div class="finea-container">'
            . $entete
            . Dashboard::kpis((array) $donnees['kpis'])
            . self::bloc(Ui::section(
                'Coût de revient par trajet',
                self::tableTrajets((array) $donnees['parTrajet']),
                'Lots validés et clôturés uniquement'
            ))
            . self::bloc(Ui::section(
                'Lots de dédouanement',
                self::tableLots((array) $donnees['lots'])
            ))
            . Ui::section(
                'Marchandise encore en transit',
                self::tableTransit((array) $donnees['enTransit']),
                'Ce qui reste à dédouaner'
            )
            . '</div></div>';
    }

    private static function bloc(string $contenu): string
    {
        return '<div style="margin-bottom:1.5rem;">' . $contenu . '</div>';
    }

    /** @param array<int, array<string, mixed>> $lots */
    private static function tableLots(array $lots): string
    {
        $lignes = [];
        foreach ($lots as $lot) {
            $ecart = $lot['ecart_moyenne'];

            $reference = '<strong>' . View::e((string) $lot['reference_lot']) . '</strong>';
            if ($lot['cout_kg_desynchronise']) {
                $reference .= '<br>' . Ui::badge('Frais corrigés après saisie', 'warning');
            }

            $lignes[] = [
                $reference,
                View::e((string) ($lot['trajet_libelle'] ?? $lot['trajet_code'])),
                ModuleTable::montant((float) $lot['poids_total_kg'], 'kg', 2),
                ModuleTable::montant((float) $lot['frais_douane_xof']),
                ModuleTable::montant((float) $lot['frais_fret_xof']),
                ModuleTable::montant((float) $lot['frais_manutention_xof']),
                '<strong>' . ModuleTable::montant((float) $lot['total_xof']) . '</strong>',
                $lot['cout_kg'] !== null
                    ? ModuleTable::montant((float) $lot['cout_kg'], 'XOF/kg', 2)
                    : '<small>Poids non renseigné</small>',
                $ecart !== null
                    ? Ui::badge(($ecart > 0 ? '+' : '') . $ecart . ' %', $lot['derive'] ? 'danger' : 'success')
                    : '<small>—</small>',
                Ui::badge((string) $lot['statut'], match ((string) $lot['statut']) {
                    'CLÔTURÉ' => 'success',
                    'VALIDÉ' => 'info',
                    default => 'warning',
                }),
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Lot'],
                ['label' => 'Trajet'],
                ['label' => 'Poids', 'align' => 'right'],
                ['label' => 'Douane', 'align' => 'right'],
                ['label' => 'Fret', 'align' => 'right'],
                ['label' => 'Manutention', 'align' => 'right'],
                ['label' => 'Total', 'align' => 'right'],
                ['label' => 'Coût / kg', 'align' => 'right'],
                ['label' => 'vs trajet', 'align' => 'center'],
                ['label' => 'Statut', 'align' => 'center'],
            ],
            $lignes,
            'Aucun lot',
            'Aucun coût d\'approche n\'a encore été saisi.'
        );
    }

    /** @param array<int, array<string, mixed>> $trajets */
    private static function tableTrajets(array $trajets): string
    {
        $lignes = [];
        foreach ($trajets as $t) {
            $lignes[] = [
                '<strong>' . View::e((string) ($t['trajet_libelle'] ?? $t['trajet_code'])) . '</strong>'
                    . '<br><small>' . View::e((string) $t['trajet_code']) . '</small>',
                View::e((string) $t['nb_lots']),
                ModuleTable::montant((float) $t['poids_kg'], 'kg'),
                ModuleTable::montant((float) $t['douane_xof']),
                ModuleTable::montant((float) $t['fret_xof']),
                ModuleTable::montant((float) $t['manutention_xof']),
                '<strong>' . ModuleTable::montant((float) $t['total_xof']) . '</strong>',
                $t['cout_kg_moyen'] !== null
                    ? '<strong>' . ModuleTable::montant((float) $t['cout_kg_moyen'], 'XOF/kg', 2) . '</strong>'
                    : '<small>—</small>',
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Trajet'],
                ['label' => 'Lots', 'align' => 'center'],
                ['label' => 'Poids', 'align' => 'right'],
                ['label' => 'Douane', 'align' => 'right'],
                ['label' => 'Fret', 'align' => 'right'],
                ['label' => 'Manutention', 'align' => 'right'],
                ['label' => 'Total', 'align' => 'right'],
                ['label' => 'Coût / kg moyen', 'align' => 'right'],
            ],
            $lignes,
            'Aucune référence de coût',
            'Aucun lot validé ou clôturé : la moyenne par trajet ne peut pas être calculée.'
        );
    }

    /** @param array<int, array<string, mixed>> $transit */
    private static function tableTransit(array $transit): string
    {
        $lignes = [];
        foreach ($transit as $t) {
            $lignes[] = [
                '<strong>' . View::e((string) ($t['trajet_libelle'] ?? $t['trajet_code'])) . '</strong>',
                View::e((string) $t['nb_envois']),
                View::e((string) $t['nb_colis']),
                ModuleTable::montant((float) $t['poids_kg'], 'kg'),
                ModuleTable::montant((float) $t['valeur_declaree']),
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Trajet'],
                ['label' => 'Envois', 'align' => 'center'],
                ['label' => 'Colis', 'align' => 'center'],
                ['label' => 'Poids', 'align' => 'right'],
                ['label' => 'Valeur déclarée', 'align' => 'right'],
            ],
            $lignes,
            'Rien en transit',
            'Aucun envoi n\'est actuellement en transit ou en préparation sur ce périmètre.'
        );
    }
}
