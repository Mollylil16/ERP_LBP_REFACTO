<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\Csrf;
use App\Helpers\View;

final class AgentsCorrespondants
{
    /**
     * @param array<string, mixed> $donnees
     * @param array<string, mixed>|null $enEdition correspondant en cours de modification
     */
    public static function reseauPage(array $donnees, bool $peutGerer, ?array $enEdition = null): string
    {
        $entete = Ui::pageHeader(
            'Agents & Correspondants',
            'Réseau des correspondants à l\'étranger : qui contacter, dans quel pays, pour quelle zone.',
            [
                'eyebrow' => 'AGC • Réseau international',
                'class' => 'rh-hero-white',
                // Ui::button préfixe toujours l'URL : une ancre seule renverrait
                // à la racine du site, pas au formulaire de cette page.
                'actions' => $peutGerer
                    ? Ui::button('Ajouter un correspondant', [
                        'href' => 'agents-correspondants/dashboard#formulaire',
                        'variant' => 'accent',
                    ])
                    : '',
            ]
        );

        $formulaire = $peutGerer
            ? self::bloc(self::formulaire($enEdition))
            : '';

        return '<div class="finea-shell"><div class="finea-container">'
            . $entete
            . Dashboard::kpis((array) $donnees['kpis'])
            . self::bloc(self::recherche((string) $donnees['recherche']))
            . $formulaire
            . self::bloc(Ui::section(
                'Couverture par pays',
                self::tablePays((array) $donnees['parPays'])
            ))
            . Ui::section(
                'Correspondants',
                self::tableAgents((array) $donnees['agents'], $peutGerer),
                count((array) $donnees['agents']) . ' fiche(s)'
            )
            . '</div></div>';
    }

    private static function bloc(string $contenu): string
    {
        return '<div style="margin-bottom:1.5rem;">' . $contenu . '</div>';
    }

    private static function recherche(string $valeur): string
    {
        $champ = Form::input('q', [
            'label' => 'Rechercher',
            'value' => $valeur,
            'placeholder' => 'Nom, pays, ville, contact ou zone couverte',
        ]);

        return Ui::section(
            'Recherche',
            '<form method="get" action="' . View::url('agents-correspondants/dashboard') . '">'
                . '<div class="rh-form-grid" style="gap:1rem; align-items:end;">'
                . $champ
                . '<div class="finea-field">' . Ui::button('Rechercher', ['variant' => 'primary', 'type' => 'submit']) . '</div>'
                . '</div></form>'
        );
    }

    /**
     * @param array<string, mixed>|null $agent
     */
    private static function formulaire(?array $agent): string
    {
        $edition = $agent !== null;
        $action = View::url($edition
            ? 'agents-correspondants/' . (int) $agent['id'] . '/modifier'
            : 'agents-correspondants/nouveau');

        $champs = Form::input('name', [
                'label' => 'Nom du correspondant',
                'value' => (string) ($agent['name'] ?? ''),
                'required' => true,
                'maxlength' => 180,
            ])
            . Form::input('country', [
                'label' => 'Pays',
                'value' => (string) ($agent['country'] ?? ''),
                'required' => true,
                'maxlength' => 120,
                'hint' => 'Structure l\'annuaire : écrivez-le toujours de la même façon.',
            ])
            . Form::input('city', [
                'label' => 'Ville',
                'value' => (string) ($agent['city'] ?? ''),
                'maxlength' => 120,
            ])
            . Form::input('contact_name', [
                'label' => 'Personne à contacter',
                'value' => (string) ($agent['contact_name'] ?? ''),
                'maxlength' => 160,
            ])
            . Form::input('email', [
                'label' => 'E-mail',
                'value' => (string) ($agent['email'] ?? ''),
                'type' => 'email',
                'maxlength' => 150,
            ])
            . Form::input('phone', [
                'label' => 'Téléphone',
                'value' => (string) ($agent['phone'] ?? ''),
                'maxlength' => 60,
            ])
            . Form::textarea('coverage', [
                'label' => 'Zone couverte',
                'value' => (string) ($agent['coverage'] ?? ''),
                'rows' => 3,
                'hint' => 'Ports, régions ou types d\'opérations pris en charge.',
            ])
            . Form::checkbox('is_active', ['label' => 'Correspondant actif'], !$edition || (int) ($agent['is_active'] ?? 1) === 1);

        $boutons = Ui::button($edition ? 'Enregistrer les modifications' : 'Créer le correspondant', ['variant' => 'accent', 'type' => 'submit'])
            . ($edition
                ? Ui::button('Annuler', ['href' => 'agents-correspondants/dashboard', 'variant' => 'secondary'])
                : '');

        return Ui::section(
            $edition ? 'Modifier « ' . (string) $agent['name'] . ' »' : 'Nouveau correspondant',
            '<form method="post" action="' . $action . '">'
                . Form::hidden('_csrf_token', Csrf::token())
                . '<div class="rh-form-grid" style="gap:1.25rem;">' . $champs . '</div>'
                . '<div style="display:flex; gap:0.75rem; margin-top:1.25rem;">' . $boutons . '</div>'
                . '</form>',
            '',
            ['id' => 'formulaire']
        );
    }

    /** @param array<int, array<string, mixed>> $pays */
    private static function tablePays(array $pays): string
    {
        $lignes = [];
        foreach ($pays as $p) {
            $inactifs = (int) $p['nb_agents'] - (int) $p['nb_actifs'];

            $lignes[] = [
                '<strong>' . View::e((string) $p['country']) . '</strong>',
                View::e((string) $p['nb_villes']),
                View::e((string) $p['nb_agents']),
                Ui::badge((string) $p['nb_actifs'], (int) $p['nb_actifs'] > 0 ? 'success' : 'warning'),
                $inactifs > 0 ? Ui::badge((string) $inactifs, 'neutral') : '<small>—</small>',
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Pays'],
                ['label' => 'Villes', 'align' => 'center'],
                ['label' => 'Correspondants', 'align' => 'center'],
                ['label' => 'Actifs', 'align' => 'center'],
                ['label' => 'Inactifs', 'align' => 'center'],
            ],
            $lignes,
            'Réseau vide',
            'Aucun correspondant n\'est encore enregistré.'
        );
    }

    /** @param array<int, array<string, mixed>> $agents */
    private static function tableAgents(array $agents, bool $peutGerer): string
    {
        $colonnes = [
            ['label' => 'Correspondant'],
            ['label' => 'Pays / Ville'],
            ['label' => 'Contact'],
            ['label' => 'Zone couverte'],
            ['label' => 'État', 'align' => 'center'],
        ];

        if ($peutGerer) {
            $colonnes[] = ['label' => 'Actions', 'align' => 'center'];
        }

        $lignes = [];
        foreach ($agents as $a) {
            $contact = [];
            if (trim((string) ($a['contact_name'] ?? '')) !== '') {
                $contact[] = '<strong>' . View::e((string) $a['contact_name']) . '</strong>';
            }
            if (trim((string) ($a['phone'] ?? '')) !== '') {
                $contact[] = View::e((string) $a['phone']);
            }
            if (trim((string) ($a['email'] ?? '')) !== '') {
                $contact[] = '<small>' . View::e((string) $a['email']) . '</small>';
            }

            $couverture = trim((string) ($a['coverage'] ?? ''));

            $ligne = [
                '<strong>' . View::e((string) $a['name']) . '</strong>',
                View::e((string) $a['country'])
                    . ($a['city'] ? '<br><small>' . View::e((string) $a['city']) . '</small>' : ''),
                $contact !== [] ? implode('<br>', $contact) : Ui::badge('Aucun contact', 'warning'),
                $couverture !== '' ? View::e($couverture) : '<small>Non précisée</small>',
                Ui::badge(
                    (int) $a['is_active'] === 1 ? 'Actif' : 'Inactif',
                    (int) $a['is_active'] === 1 ? 'success' : 'neutral'
                ),
            ];

            if ($peutGerer) {
                $ligne[] = Ui::button('Modifier', [
                    'href' => 'agents-correspondants/dashboard?modifier=' . (int) $a['id'] . '#formulaire',
                    'variant' => 'secondary',
                ]) . ((int) $a['is_active'] === 1
                    ? Ui::deleteForm(
                        'agents-correspondants/' . (int) $a['id'] . '/desactiver',
                        'Désactiver ce correspondant ? Sa fiche reste consultable.',
                        ['label' => 'Désactiver']
                    )
                    : '');
            }

            $lignes[] = $ligne;
        }

        return ModuleTable::render(
            $colonnes,
            $lignes,
            'Aucun correspondant',
            'Aucune fiche ne correspond à cette recherche.'
        );
    }
}
