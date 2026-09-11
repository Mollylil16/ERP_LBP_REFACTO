<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class FlotteTransport
{
    /**
     * @param array<string, mixed> $donnees
     */
    public static function flottePage(array $donnees, string $agenceLabel): string
    {
        $entete = Ui::pageHeader(
            'Flotte / Transport',
            'Livreurs, véhicules et missions en cours. Les véhicules sont ceux déclarés sur la fiche du livreur qui les conduit.',
            [
                'eyebrow' => 'FLT • ' . $agenceLabel,
                'class' => 'rh-hero-white',
                'actions' => Ui::button('Suivi GPS des colis', ['href' => 'colisage/exploitation/tracking', 'variant' => 'secondary'])
                    . Ui::button('Groupage & expéditions', ['href' => 'colisage/groupage', 'variant' => 'ghost']),
            ]
        );

        return '<div class="finea-shell"><div class="finea-container">'
            . $entete
            . Dashboard::kpis((array) $donnees['kpis'])
            . self::bloc(Ui::section(
                'Missions en retard',
                self::tableMissions((array) $donnees['enRetard'], true),
                'Arrivée estimée dépassée'
            ))
            . self::bloc(Ui::section(
                'Livreurs et véhicules',
                self::tableLivreurs((array) $donnees['livreurs']),
                count((array) $donnees['livreurs']) . ' livreur(s)'
            ))
            . self::bloc(Ui::section(
                'Missions en cours',
                self::tableMissions((array) $donnees['missions'], false)
            ))
            . Ui::section(
                'Dernières missions terminées',
                self::tableTerminees((array) $donnees['terminees'])
            )
            . '</div></div>';
    }

    private static function bloc(string $contenu): string
    {
        return '<div style="margin-bottom:1.5rem;">' . $contenu . '</div>';
    }

    /** @param array<int, array<string, mixed>> $livreurs */
    private static function tableLivreurs(array $livreurs): string
    {
        $lignes = [];
        foreach ($livreurs as $l) {
            $plaque = trim((string) ($l['plaque_immatriculation'] ?? ''));
            $modele = trim((string) ($l['modele_vehicule'] ?? ''));

            $vehicule = $plaque !== '' || $modele !== ''
                ? '<strong>' . View::e($plaque !== '' ? $plaque : 'Sans plaque') . '</strong>'
                    . ($modele !== '' ? '<br><small>' . View::e($modele) . '</small>' : '')
                : Ui::badge('Non renseigné', 'warning');

            $lignes[] = [
                '<strong>' . View::e((string) $l['livreur']) . '</strong>'
                    . ($l['phone'] ? '<br><small>' . View::e((string) $l['phone']) . '</small>' : ''),
                View::e((string) ($l['agence_name'] ?? '—')),
                $vehicule,
                Ui::badge((string) $l['statut'], (string) $l['statut'] === 'Disponible' ? 'success' : 'info'),
                View::e((string) $l['missions_ouvertes']),
                self::position($l),
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Livreur'],
                ['label' => 'Agence'],
                ['label' => 'Véhicule'],
                ['label' => 'Disponibilité', 'align' => 'center'],
                ['label' => 'Missions', 'align' => 'center'],
                ['label' => 'Dernière position'],
            ],
            $lignes,
            'Aucun livreur',
            'Aucun livreur n\'est déclaré sur ce périmètre.'
        );
    }

    /** @param array<string, mixed> $livreur */
    private static function position(array $livreur): string
    {
        if (!($livreur['a_position'] ?? false)) {
            return '<small>Jamais localisé</small>';
        }

        $date = (string) ($livreur['derniere_localisation'] ?? '');
        $horodatage = strtotime($date);
        $libelle = $horodatage !== false ? date('d/m/Y H:i', $horodatage) : '—';

        return View::e($libelle) . ' '
            . Ui::badge(
                ($livreur['position_fraiche'] ?? false) ? 'à jour' : 'ancienne',
                ($livreur['position_fraiche'] ?? false) ? 'success' : 'warning'
            );
    }

    /** @param array<int, array<string, mixed>> $missions */
    private static function tableMissions(array $missions, bool $retardUniquement): string
    {
        $lignes = [];
        foreach ($missions as $m) {
            $retard = (int) $m['jours_retard'];

            $lignes[] = [
                '<strong>' . View::e((string) $m['reference']) . '</strong>',
                Ui::badge((string) $m['type_transport'], 'info'),
                View::e((string) ($m['agence_depart'] ?? '—') . ' → ' . (string) ($m['agence_arrivee'] ?? '—')),
                View::e((string) ($m['livreur'] ?? 'Non affecté'))
                    . ($m['plaque_immatriculation'] ? '<br><small>' . View::e((string) $m['plaque_immatriculation']) . '</small>' : ''),
                View::e((string) $m['nb_colis']),
                ModuleTable::montant((float) $m['poids_kg'], 'kg'),
                View::e(self::date((string) ($m['date_arrivee_estimee'] ?? ''))),
                $retard > 0
                    ? Ui::badge('+' . $retard . ' j', $retard >= 7 ? 'danger' : 'warning')
                    : Ui::badge((string) $m['statut'], 'neutral'),
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Expédition'],
                ['label' => 'Transport', 'align' => 'center'],
                ['label' => 'Trajet'],
                ['label' => 'Livreur'],
                ['label' => 'Colis', 'align' => 'center'],
                ['label' => 'Poids', 'align' => 'right'],
                ['label' => 'Arrivée prévue'],
                ['label' => $retardUniquement ? 'Retard' : 'Statut', 'align' => 'center'],
            ],
            $lignes,
            $retardUniquement ? 'Aucun retard' : 'Aucune mission en cours',
            $retardUniquement
                ? 'Toutes les missions ouvertes tiennent leur date d\'arrivée estimée.'
                : 'Aucune expédition n\'est ouverte sur ce périmètre.'
        );
    }

    /** @param array<int, array<string, mixed>> $missions */
    private static function tableTerminees(array $missions): string
    {
        $lignes = [];
        foreach ($missions as $m) {
            $lignes[] = [
                '<strong>' . View::e((string) $m['reference']) . '</strong>',
                Ui::badge((string) $m['type_transport'], 'info'),
                View::e((string) ($m['agence_depart'] ?? '—') . ' → ' . (string) ($m['agence_arrivee'] ?? '—')),
                View::e((string) ($m['livreur'] ?? 'Non affecté')),
                View::e(self::date((string) ($m['date_arrivee_estimee'] ?? ''))),
                View::e(self::date((string) ($m['updated_at'] ?? ''))),
                Ui::badge((string) $m['statut'], 'success'),
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Expédition'],
                ['label' => 'Transport', 'align' => 'center'],
                ['label' => 'Trajet'],
                ['label' => 'Livreur'],
                ['label' => 'Arrivée prévue'],
                ['label' => 'Clôturée le'],
                ['label' => 'Statut', 'align' => 'center'],
            ],
            $lignes,
            'Aucune mission terminée',
            'Aucune expédition n\'a encore été clôturée sur ce périmètre.'
        );
    }

    private static function date(string $valeur): string
    {
        if ($valeur === '') {
            return '—';
        }

        $horodatage = strtotime($valeur);

        return $horodatage !== false ? date('d/m/Y', $horodatage) : '—';
    }
}
