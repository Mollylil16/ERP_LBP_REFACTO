<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

/**
 * Écran d'occupation des magasins.
 *
 * L'ordre des sections suit la question que se pose le chef de magasin, de la
 * plus urgente à la plus froide : qu'est-ce qui déborde, qu'est-ce qui dort,
 * qu'est-ce qui a bougé, où en sont les inventaires.
 */
final class Entrepots
{
    /**
     * @param array<string, mixed> $donnees
     */
    public static function occupationPage(array $donnees, string $agenceLabel): string
    {
        $parametres = (array) $donnees['parametres'];

        $entete = Ui::pageHeader(
            'Entrepôts',
            'Occupation des rayons, colis en souffrance et gardiennage dû. '
                . 'Délai de gratuité : ' . (int) $parametres['delai_gratuit_jours'] . ' jours, puis '
                . number_format((float) $parametres['frais_gardiennage_par_jour'], 0, ',', ' ') . ' XOF par colis et par jour.',
            [
                'eyebrow' => 'ENT • ' . $agenceLabel,
                'class' => 'rh-hero-white',
                'actions' => Ui::button('Gérer les rayons', ['href' => 'logistique/rayons', 'variant' => 'secondary'])
                    . Ui::button('Délais & gardiennage', ['href' => 'logistique/parametres', 'variant' => 'ghost']),
            ]
        );

        return '<div class="finea-shell"><div class="finea-container">'
            . $entete
            . Dashboard::kpis((array) $donnees['kpis'])
            . self::bloc(Ui::section(
                'Remplissage des rayons',
                self::tableRayons((array) $donnees['rayons']),
                count((array) $donnees['rayons']) . ' rayon(s)'
            ))
            . self::bloc(Ui::section(
                'Colis au-delà du délai gratuit',
                self::tableSouffrance((array) $donnees['enSouffrance']),
                'Gardiennage facturable'
            ))
            . self::bloc(Ui::section(
                'Tout ce qui est en magasin',
                self::tableStock((array) $donnees['colis']),
                'Du plus ancien au plus récent'
            ))
            . self::bloc(Ui::section(
                'Derniers mouvements',
                self::tableMouvements((array) $donnees['mouvements'])
            ))
            . Ui::section(
                'Inventaires',
                self::tableInventaires((array) $donnees['inventaires'])
            )
            . '</div></div>';
    }

    private static function bloc(string $contenu): string
    {
        return '<div style="margin-bottom:1.5rem;">' . $contenu . '</div>';
    }

    /** @param array<int, array<string, mixed>> $rayons */
    private static function tableRayons(array $rayons): string
    {
        $lignes = [];
        foreach ($rayons as $rayon) {
            $lignes[] = [
                '<strong>' . View::e((string) $rayon['code_rayon']) . '</strong><br>'
                    . '<small>' . View::e((string) $rayon['nom_rayon']) . '</small>',
                View::e((string) $rayon['agence_name']),
                ModuleTable::jauge((int) $rayon['taux'], (string) $rayon['tone']),
                View::e($rayon['nb_colis'] . ' / ' . $rayon['capacite_max']),
                ModuleTable::montant((float) $rayon['poids_kg'], 'kg'),
                Ui::badge((string) $rayon['statut'], match ((string) $rayon['statut']) {
                    'PLEIN' => 'danger',
                    'MAINTENANCE' => 'warning',
                    default => 'success',
                }),
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Rayon'],
                ['label' => 'Agence'],
                ['label' => 'Remplissage'],
                ['label' => 'Colis', 'align' => 'center'],
                ['label' => 'Poids', 'align' => 'right'],
                ['label' => 'État', 'align' => 'center'],
            ],
            $lignes,
            'Aucun rayon paramétré',
            'Créez les rayons de l\'agence depuis Logistique › Gestion des rayons pour suivre leur remplissage ici.'
        );
    }

    /** @param array<int, array<string, mixed>> $colis */
    private static function tableSouffrance(array $colis): string
    {
        $lignes = [];
        foreach ($colis as $c) {
            $jours = (int) $c['jours_stockes'];
            $lignes[] = [
                '<strong>' . View::e((string) $c['numero_tracking']) . '</strong>',
                View::e((string) ($c['destinataire'] ?? '—'))
                    . ($c['destinataire_tel'] ? '<br><small>' . View::e((string) $c['destinataire_tel']) . '</small>' : ''),
                View::e((string) ($c['code_rayon'] ?? '—')),
                Ui::badge($jours . ' j', $jours >= 30 ? 'danger' : ($jours >= 14 ? 'warning' : 'neutral')),
                View::e((string) $c['jours_factures'] . ' j'),
                ModuleTable::montant((float) $c['gardiennage_xof']),
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Colis'],
                ['label' => 'Destinataire'],
                ['label' => 'Rayon'],
                ['label' => 'En magasin', 'align' => 'center'],
                ['label' => 'Facturable', 'align' => 'center'],
                ['label' => 'Gardiennage dû', 'align' => 'right'],
            ],
            $lignes,
            'Rien en souffrance',
            'Aucun colis ne dépasse le délai de gratuité.'
        );
    }

    /** @param array<int, array<string, mixed>> $colis */
    private static function tableStock(array $colis): string
    {
        $lignes = [];
        foreach ($colis as $c) {
            $lignes[] = [
                '<strong>' . View::e((string) $c['numero_tracking']) . '</strong>',
                View::e((string) ($c['expediteur'] ?? '—')),
                View::e((string) ($c['destinataire'] ?? '—')),
                View::e((string) ($c['agence_name'] ?? '—') . ' • ' . (string) ($c['code_rayon'] ?? '—')),
                View::e((string) $c['nombre_colis']),
                ModuleTable::montant((float) $c['poids_total'], 'kg', 2),
                View::e(self::date((string) $c['entre_le'])),
                Ui::badge((string) $c['statut'], 'info'),
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Colis'],
                ['label' => 'Expéditeur'],
                ['label' => 'Destinataire'],
                ['label' => 'Emplacement'],
                ['label' => 'Nb', 'align' => 'center'],
                ['label' => 'Poids', 'align' => 'right'],
                ['label' => 'Entré le'],
                ['label' => 'Statut', 'align' => 'center'],
            ],
            $lignes,
            'Magasin vide',
            'Aucun colis n\'est actuellement placé en rayon.'
        );
    }

    /** @param array<int, array<string, mixed>> $mouvements */
    private static function tableMouvements(array $mouvements): string
    {
        $lignes = [];
        foreach ($mouvements as $m) {
            $lignes[] = [
                View::e(self::date((string) $m['created_at'], true)),
                Ui::badge((string) $m['type_mouvement'], match ((string) $m['type_mouvement']) {
                    'ENTREE' => 'success',
                    'SORTIE' => 'warning',
                    default => 'info',
                }),
                View::e((string) ($m['numero_tracking'] ?? '—')),
                View::e((string) ($m['code_rayon'] ?? '—')),
                View::e((string) ($m['auteur'] ?? '—')),
                View::e((string) ($m['commentaires'] ?? '')),
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Date'],
                ['label' => 'Type', 'align' => 'center'],
                ['label' => 'Colis'],
                ['label' => 'Rayon'],
                ['label' => 'Par'],
                ['label' => 'Commentaire'],
            ],
            $lignes,
            'Aucun mouvement',
            'Les entrées et sorties de rayon apparaîtront ici.'
        );
    }

    /** @param array<int, array<string, mixed>> $inventaires */
    private static function tableInventaires(array $inventaires): string
    {
        $lignes = [];
        foreach ($inventaires as $i) {
            $manquants = (int) $i['nb_manquants'];
            $lignes[] = [
                View::e(self::date((string) $i['date_inventaire'])),
                View::e((string) ($i['agence_name'] ?? '—')),
                View::e((string) ($i['auteur'] ?? '—')),
                View::e((string) $i['nb_lignes']),
                Ui::badge((string) $manquants, $manquants > 0 ? 'danger' : 'success'),
                Ui::badge((string) $i['statut'], (string) $i['statut'] === 'CLOTURE' ? 'success' : 'warning'),
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Date'],
                ['label' => 'Agence'],
                ['label' => 'Réalisé par'],
                ['label' => 'Lignes', 'align' => 'center'],
                ['label' => 'Écarts', 'align' => 'center'],
                ['label' => 'Statut', 'align' => 'center'],
            ],
            $lignes,
            'Aucun inventaire',
            'Aucun inventaire n\'a encore été ouvert sur ce périmètre.'
        );
    }

    private static function date(string $valeur, bool $avecHeure = false): string
    {
        $horodatage = strtotime($valeur);
        if ($horodatage === false) {
            return '—';
        }

        return date($avecHeure ? 'd/m/Y H:i' : 'd/m/Y', $horodatage);
    }
}
