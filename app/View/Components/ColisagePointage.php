<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Services\Colisage\PointageColisService;

/**
 * Écrans du pointage des colis : préparer un départ, réceptionner, suivre.
 *
 * À la réception, chaque case cochée est enregistrée tout de suite, sans
 * rechargement : un agent qui pointe 150 colis ne doit rien perdre s'il est
 * interrompu. La douchette emprunte le même chemin. Les messages renvoyés par
 * le serveur sont insérés en texte, jamais en HTML.
 */
final class ColisagePointage
{
    /** @var array<string, array{0:string, 1:string}> */
    private const ETATS = [
        PointageColisService::ETAT_EN_ROUTE => ['En route', 'info'],
        PointageColisService::ETAT_EN_COURS => ['Réception en cours', 'warning'],
        PointageColisService::ETAT_MANQUANTS => ['Colis manquants', 'danger'],
        PointageColisService::ETAT_COMPLET => ['Complet', 'success'],
    ];

    /** @var array<string, array{0:string, 1:string}> */
    private const ETATS_COLIS = [
        'RECU' => ['Reçu', 'success'],
        'HORS_LISTE' => ['Reçu hors liste', 'warning'],
        'RETIRE' => ['Retiré', 'neutral'],
        'MANQUANT' => ['Manquant', 'danger'],
        'ATTENDU' => ['Attendu', 'info'],
    ];

    private const ACTIONS = [
        'DEPART' => 'Marqué parti',
        'RECU' => 'Coché reçu',
        'ANNULE_RECU' => 'Pointage annulé',
        'HORS_LISTE' => 'Reçu hors liste',
    ];

    private const SOURCES = [
        'CASE' => 'case',
        'TOUT' => 'tout cocher',
        'DOUCHETTE' => 'douchette',
        'REPRISE' => "reprise de l'existant",
    ];

    // ------------------------------------------------------------------
    // Préparer un départ
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $p
     */
    public static function departsPage(array $p): string
    {
        $agenceId = $p['agence_id'] ?? null;

        $html = self::styles() . Ui::pageHeader(
            'Préparer un départ',
            "Cochez les colis qui quittent l'agence, puis marquez-les partis : l'agence d'arrivée les verra aussitôt dans sa réception.",
            [
                'eyebrow' => 'Pointage des colis',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Réception des colis', ['href' => 'colisage/reception', 'variant' => 'secondary']),
                    Ui::button('Suivi des départs', ['href' => 'colisage/suivi-departs', 'variant' => 'secondary']),
                ],
            ]
        );

        if (!empty($p['peut_choisir'])) {
            $html .= self::choixAgence('colisage/departs', $p['agences'] ?? [], $agenceId, "Agence d'envoi", false);
        }

        if ($agenceId === null) {
            return $html . Ui::emptyState("Choisissez l'agence d'envoi", "La liste des colis à expédier s'affiche une fois l'agence choisie.");
        }

        if ((int) $agenceId <= 0) {
            return $html . Ui::emptyState('Aucune agence de rattachement', "Votre compte n'est rattaché à aucune agence : demandez à l'administration de le compléter.");
        }

        $html .= self::manquantsSignales($p['manquants'] ?? []);

        $groupes = $p['groupes'] ?? [];

        if ($groupes === []) {
            return $html . Ui::emptyState('Aucun colis à expédier', "Tous les colis enregistrés dans cette agence sont déjà partis, ou n'ont pas d'agence d'arrivée.");
        }

        $sections = '';
        $total = 0;

        foreach ($groupes as $groupe) {
            $groupeId = (int) $groupe['agence_id'];
            $lignes = [];

            foreach ($groupe['colis'] as $colis) {
                $total++;
                $code = (string) $colis['numero_tracking'];
                $lignes[] = [
                    '<input type="checkbox" class="lbp-case lbp-depart-case" name="colis_ids[]" value="' . (int) $colis['id'] . '"'
                        . ' data-tracking="' . View::e(mb_strtoupper($code)) . '" data-groupe="' . $groupeId . '"'
                        . ' aria-label="' . View::e('Marquer le colis ' . $code . ' comme parti') . '">',
                    '<strong>' . View::e($code) . '</strong>',
                    View::e((string) ($colis['expediteur'] ?? '—')),
                    View::e((string) ($colis['destinataire'] ?? '—')),
                    View::e((string) (int) ($colis['nombre_colis'] ?? 1)),
                    View::e(number_format((float) ($colis['poids_total'] ?? 0), 2, ',', ' ')) . ' kg',
                    View::e(self::date((string) ($colis['created_at'] ?? ''), 'd/m/Y')),
                ];
            }

            $entete = '<div class="lbp-pointage-entete">'
                . '<span class="lbp-pointage-compteur">' . count($groupe['colis']) . ' colis à expédier</span>'
                . Ui::button('Tout cocher', ['type' => 'button', 'variant' => 'secondary', 'class' => 'lbp-tout-cocher', 'data-groupe' => (string) $groupeId])
                . '</div>';

            $sections .= Ui::section(
                'Vers ' . (string) $groupe['agence'],
                $entete . ModuleTable::render(self::colonnesColis(true), $lignes)
            );
        }

        $transports = '';
        foreach (($p['transports'] ?? PointageColisService::TRANSPORTS) as $valeur => $libelle) {
            $transports .= '<option value="' . View::e((string) $valeur) . '">' . View::e((string) $libelle) . '</option>';
        }

        $scan = '<div class="lbp-pointage-scan">'
            . '<label for="lbp-depart-scan"><strong>Douchette</strong> : scannez un colis pour le cocher</label>'
            . '<input type="text" id="lbp-depart-scan" autocomplete="off" placeholder="Code colis">'
            . '</div>'
            . '<div id="lbp-depart-message" class="lbp-pointage-message" role="status"></div>';

        $barre = '<div class="lbp-pointage-barre">'
            . '<label class="lbp-pointage-champ">Transport <select name="type_transport">' . $transports . '</select></label>'
            . '<span class="lbp-pointage-compteur"><span id="lbp-depart-compteur">0</span> colis coché(s) sur ' . $total . '</span>'
            . Ui::button('Marquer comme partis', ['type' => 'submit', 'variant' => 'primary'])
            . '</div>';

        $formulaire = '<form method="post" action="' . View::e(View::url('colisage/departs/marquer-partis')) . '" id="lbp-form-depart"'
            . ' data-confirmer="' . View::e('Marquer les colis cochés comme partis ? Ils apparaîtront dans la réception de leur agence d\'arrivée.') . '">'
            . Form::hidden('_csrf_token', Csrf::token())
            . Form::hidden('agence_id', (string) (int) $agenceId)
            . $scan
            . $sections
            . $barre
            . '</form>';

        return $html . $formulaire . self::scriptDepart();
    }

    /**
     * @param array<int, array<string, mixed>> $departs
     */
    private static function manquantsSignales(array $departs): string
    {
        if ($departs === []) {
            return '';
        }

        $lignes = [];
        foreach ($departs as $d) {
            $lignes[] = [
                self::lienDepart($d),
                View::e((string) ($d['agence_arrivee'] ?? '—')),
                View::e(self::date((string) ($d['date_depart'] ?? ''))),
                '<strong>' . (int) $d['manquants'] . '</strong> sur ' . (int) $d['envoyes'],
            ];
        }

        return Ui::section(
            'Colis manquants signalés par les agences d\'arrivée',
            ModuleTable::render(
                [['label' => 'Départ'], ['label' => "Agence d'arrivée"], ['label' => 'Parti le'], ['label' => 'Manquants', 'align' => 'right']],
                $lignes
            ),
            "Non cochés plus de " . PointageColisService::DELAI_MANQUANT_HEURES . " h après le premier colis reçu"
        );
    }

    // ------------------------------------------------------------------
    // Réception
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $p
     */
    public static function receptionPage(array $p): string
    {
        $agenceId = $p['agence_id'] ?? null;
        $peutChoisir = !empty($p['peut_choisir']);

        $html = self::styles() . Ui::pageHeader(
            'Réception des colis',
            "Cochez chaque colis dès qu'il est en main. Ce qui reste décoché n'est pas encore arrivé ; "
                . PointageColisService::DELAI_MANQUANT_HEURES . " h après le premier colis coché, l'agence d'envoi est prévenue.",
            [
                'eyebrow' => 'Pointage des colis',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Préparer un départ', ['href' => 'colisage/departs', 'variant' => 'secondary']),
                    Ui::button('Suivi des départs', ['href' => 'colisage/suivi-departs', 'variant' => 'secondary']),
                ],
            ]
        );

        if ($peutChoisir) {
            $html .= self::choixAgence('colisage/reception', $p['agences'] ?? [], $agenceId, 'Agence qui réceptionne', true);
        } elseif ((int) $agenceId <= 0) {
            return $html . Ui::emptyState('Aucune agence de rattachement', "Votre compte n'est rattaché à aucune agence : demandez à l'administration de le compléter.");
        }

        $scanActif = $agenceId !== null && (int) $agenceId > 0;
        $scan = '<div class="lbp-pointage-scan">'
            . '<label for="lbp-reception-scan"><strong>Douchette</strong> : scannez chaque colis reçu</label>'
            . '<input type="text" id="lbp-reception-scan" autocomplete="off" placeholder="Code colis"'
            . ($scanActif ? ' data-agence="' . (int) $agenceId . '"' : ' disabled') . '>'
            . ($scanActif ? '' : '<small>Choisissez l\'agence qui réceptionne pour utiliser la douchette.</small>')
            . '</div>';

        $blocs = '';
        foreach (($p['departs'] ?? []) as $depart) {
            $blocs .= self::blocReception($depart, $peutChoisir ? $agenceId : null);
        }

        if ($blocs === '') {
            $blocs = Ui::emptyState('Aucun départ en attente', "Aucune agence n'a encore marqué de colis partis vers cette agence, ou tout a déjà été reçu.");
        }

        return $html
            . '<div id="lbp-reception" data-csrf="' . View::e(Csrf::token()) . '"'
            . ' data-url-pointer="' . View::e(View::url('colisage/reception/pointer')) . '"'
            . ' data-url-scanner="' . View::e(View::url('colisage/reception/scanner')) . '">'
            . Ui::section('Scanner', $scan . '<div id="lbp-reception-message" class="lbp-pointage-message" role="status"></div>')
            . $blocs
            . '</div>'
            . self::scriptReception();
    }

    /**
     * @param array<string, mixed> $depart
     */
    private static function blocReception(array $depart, ?int $agenceFiltre): string
    {
        $departId = (int) $depart['id'];
        $lignes = [];

        foreach (($depart['colis'] ?? []) as $colis) {
            $termine = in_array((string) $colis['statut'], ['retire', 'livre'], true);
            $recu = $termine || !empty($colis['date_reception']);
            $code = (string) $colis['numero_tracking'];

            $lignes[] = [
                '<input type="checkbox" class="lbp-case lbp-reception-case" data-colis-id="' . (int) $colis['id'] . '" data-depart-id="' . $departId . '"'
                    . ($recu ? ' checked' : '') . ($termine ? ' disabled' : '')
                    . ' aria-label="' . View::e('Colis ' . $code . ' reçu') . '">',
                '<strong>' . View::e($code) . '</strong>',
                View::e((string) ($colis['expediteur'] ?? '—')),
                View::e((string) ($colis['destinataire'] ?? '—')),
                View::e((string) (int) ($colis['nombre_colis'] ?? 1)),
                View::e(number_format((float) ($colis['poids_total'] ?? 0), 2, ',', ' ')) . ' kg',
                $termine
                    ? Ui::badge('Retiré', 'neutral')
                    : View::e(!empty($colis['date_reception']) ? self::date((string) $colis['date_reception']) . ' par ' . ($colis['recu_par'] ?? '—') : '—'),
            ];
        }

        [$libelle, $ton] = self::ETATS[(string) $depart['etat']] ?? ['—', 'neutral'];

        $delai = '';
        if (!empty($depart['echeance']) && (int) $depart['manquants'] === 0) {
            $delai = '<span class="lbp-pointage-note">Les colis non cochés seront signalés manquants à partir du ' . View::e(self::date((string) $depart['echeance'])) . '.</span>';
        } elseif ((int) $depart['manquants'] > 0) {
            $delai = '<span class="lbp-pointage-note is-alerte">' . (int) $depart['manquants'] . " colis manquant(s) signalé(s) à l'agence d'envoi.</span>";
        }

        $filtre = $agenceFiltre !== null ? Form::hidden('agence_filtre', (string) $agenceFiltre) : '';

        $actions = '<div class="lbp-pointage-actions">'
            . '<form method="post" action="' . View::e(View::url('colisage/reception/' . $departId . '/tout-pointer')) . '"'
            . ' data-confirmer="' . View::e('Cocher tous les colis encore attendus de ce départ ?') . '">'
            . Form::hidden('_csrf_token', Csrf::token()) . $filtre
            . Ui::button('Tout cocher', ['type' => 'submit', 'variant' => 'secondary'])
            . '</form>'
            . '<form method="post" action="' . View::e(View::url('colisage/reception/' . $departId . '/prevenir-clients')) . '">'
            . Form::hidden('_csrf_token', Csrf::token()) . $filtre
            . Ui::button('Prévenir les clients', ['type' => 'submit', 'variant' => 'secondary'])
            . '</form>'
            . Ui::button('Détail et historique', ['href' => 'colisage/departs/' . $departId, 'variant' => 'secondary'])
            . '</div>';

        $entete = '<div class="lbp-pointage-entete">'
            . '<div>' . Ui::badge($libelle, $ton)
            . ' <span class="lbp-pointage-compteur"><span data-role="recus">' . (int) $depart['recus'] . '</span> reçu(s) sur '
            . (int) $depart['envoyes'] . ', <span data-role="restants">' . (int) $depart['restants'] . '</span> encore attendu(s)</span>'
            . ' ' . $delai . '</div>'
            . $actions
            . '</div>';

        $titre = 'De ' . (string) ($depart['agence_depart'] ?? '—')
            . ($agenceFiltre === null && !empty($depart['agence_arrivee']) ? ' vers ' . $depart['agence_arrivee'] : '')
            . ', parti le ' . self::date((string) ($depart['date_depart'] ?? ''));

        return '<div class="lbp-reception-depart" data-depart-id="' . $departId . '">'
            . Ui::section(
                $titre,
                $entete . ModuleTable::render(self::colonnesColis(false, 'Reçu'), $lignes, 'Aucun colis dans ce départ'),
                'Réf. ' . (string) $depart['reference'] . (!empty($depart['est_reprise']) ? " (reprise de l'existant)" : '')
            )
            . '</div>';
    }

    // ------------------------------------------------------------------
    // Suivi des départs
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $p
     */
    public static function suiviPage(array $p): string
    {
        $suivi = $p['suivi'] ?? [];
        $totaux = $suivi['totaux'] ?? ['departs' => 0, 'envoyes' => 0, 'recus' => 0, 'manquants' => 0, 'en_attente' => 0];

        $html = self::styles() . Ui::pageHeader(
            'Suivi des départs',
            "Colis envoyés, reçus et manquants, agence par agence.",
            [
                'eyebrow' => 'Pointage des colis',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('Préparer un départ', ['href' => 'colisage/departs', 'variant' => 'secondary']),
                    Ui::button('Réception des colis', ['href' => 'colisage/reception', 'variant' => 'secondary']),
                ],
            ]
        );

        $options = '';
        if (!empty($p['peut_choisir'])) {
            $options = '<option value="">Toutes les agences</option>';
            foreach (($p['agences'] ?? []) as $a) {
                $options .= '<option value="' . (int) $a['id'] . '"' . ((int) ($p['agence_id'] ?? 0) === (int) $a['id'] ? ' selected' : '') . '>'
                    . View::e((string) $a['name']) . '</option>';
            }
        }

        $html .= '<form method="get" action="' . View::e(View::url('colisage/suivi-departs')) . '" class="lbp-pointage-filtre">'
            . '<label>Du <input type="date" name="du" value="' . View::e((string) ($p['du'] ?? '')) . '"></label>'
            . '<label>Au <input type="date" name="au" value="' . View::e((string) ($p['au'] ?? '')) . '"></label>'
            . ($options !== '' ? '<label>Agence <select name="agence">' . $options . '</select></label>' : '')
            . Ui::button('Afficher', ['type' => 'submit', 'variant' => 'primary'])
            . '</form>';

        $html .= '<div class="lbp-pointage-kpis">'
            . self::kpi('Départs', (int) $totaux['departs'])
            . self::kpi('Colis envoyés', (int) $totaux['envoyes'])
            . self::kpi('Reçus', (int) $totaux['recus'])
            . self::kpi('En attente', (int) $totaux['en_attente'])
            . self::kpi('Manquants', (int) $totaux['manquants'], (int) $totaux['manquants'] > 0)
            . '</div>';

        $lignesTrajets = [];
        foreach (($suivi['trajets'] ?? []) as $t) {
            $taux = (int) $t['envoyes'] > 0 ? (int) round((int) $t['recus'] * 100 / (int) $t['envoyes']) : 0;
            $lignesTrajets[] = [
                View::e((string) $t['agence_depart']),
                View::e((string) $t['agence_arrivee']),
                (string) (int) $t['departs'],
                (string) (int) $t['envoyes'],
                (string) (int) $t['recus'],
                (int) $t['manquants'] > 0 ? '<strong class="lbp-alerte">' . (int) $t['manquants'] . '</strong>' : '0',
                ModuleTable::jauge($taux, $taux >= 100 ? 'success' : ((int) $t['manquants'] > 0 ? 'danger' : 'warning')),
            ];
        }

        $html .= Ui::section('Par trajet', ModuleTable::render([
            ['label' => "Agence d'envoi"], ['label' => "Agence d'arrivée"], ['label' => 'Départs', 'align' => 'right'],
            ['label' => 'Envoyés', 'align' => 'right'], ['label' => 'Reçus', 'align' => 'right'], ['label' => 'Manquants', 'align' => 'right'],
            ['label' => 'Reçus / envoyés'],
        ], $lignesTrajets, 'Aucun départ sur la période', 'Élargissez la période, ou vérifiez que les agences marquent bien leurs départs.'));

        $lignesDeparts = [];
        foreach (($suivi['departs'] ?? []) as $d) {
            [$libelle, $ton] = self::ETATS[(string) $d['etat']] ?? ['—', 'neutral'];
            $lignesDeparts[] = [
                self::lienDepart($d),
                View::e(self::date((string) ($d['date_depart'] ?? ''))),
                View::e((string) ($d['agence_depart'] ?? '—')) . ' → ' . View::e((string) ($d['agence_arrivee'] ?? '—')),
                (string) (int) $d['envoyes'],
                (string) (int) $d['recus'],
                (int) $d['manquants'] > 0 ? '<strong class="lbp-alerte">' . (int) $d['manquants'] . '</strong>' : '0',
                Ui::badge($libelle, $ton),
            ];
        }

        $html .= Ui::section('Départs', ModuleTable::render([
            ['label' => 'Départ'], ['label' => 'Parti le'], ['label' => 'Trajet'], ['label' => 'Envoyés', 'align' => 'right'],
            ['label' => 'Reçus', 'align' => 'right'], ['label' => 'Manquants', 'align' => 'right'], ['label' => 'État'],
        ], $lignesDeparts, 'Aucun départ sur la période'));

        $lignesHors = [];
        foreach (($suivi['hors_liste'] ?? []) as $h) {
            $lignesHors[] = [
                View::e(self::date((string) ($h['created_at'] ?? ''))),
                '<strong>' . View::e((string) $h['numero_tracking']) . '</strong>',
                View::e((string) ($h['agence_reception'] ?? '—')),
                View::e((string) ($h['agence_depart'] ?? '—')),
                View::e((string) ($h['agence_prevue'] ?? '—')),
                View::e((string) ($h['par'] ?? '—')),
            ];
        }

        $html .= Ui::section('Colis reçus hors liste', ModuleTable::render([
            ['label' => 'Reçu le'], ['label' => 'Code colis'], ['label' => 'Reçu à'], ['label' => 'Envoyé par'],
            ['label' => 'Attendu à'], ['label' => 'Pointé par'],
        ], $lignesHors, 'Aucun colis reçu hors liste sur la période'));

        return $html;
    }

    // ------------------------------------------------------------------
    // Détail d'un départ
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $p
     */
    public static function detailPage(array $p): string
    {
        $depart = $p['depart'];
        $departId = (int) $depart['id'];
        [$libelle, $ton] = self::ETATS[(string) $depart['etat']] ?? ['—', 'neutral'];

        $html = self::styles() . Ui::pageHeader(
            'Départ ' . (string) $depart['reference'],
            (string) ($depart['agence_depart'] ?? '—') . ' → ' . (string) ($depart['agence_arrivee'] ?? '—')
                . ', parti le ' . self::date((string) ($depart['date_depart'] ?? '')),
            [
                'eyebrow' => 'Pointage des colis',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::badge($libelle, $ton, ['class' => 'finea-badge--large']),
                    Ui::button('Manifeste', ['href' => 'colisage/groupage/' . $departId . '/manifeste', 'variant' => 'secondary', 'target' => '_blank']),
                    Ui::button('Réception des colis', ['href' => 'colisage/reception', 'variant' => 'secondary']),
                    Ui::button('Suivi des départs', ['href' => 'colisage/suivi-departs', 'variant' => 'secondary']),
                ],
            ]
        );

        $html .= '<div class="lbp-pointage-kpis">'
            . self::kpi('Colis envoyés', (int) $depart['envoyes'])
            . self::kpi('Reçus', (int) $depart['recus'])
            . self::kpi('Encore attendus', (int) $depart['restants'])
            . self::kpi('Manquants', (int) $depart['manquants'], (int) $depart['manquants'] > 0)
            . '</div>';

        if (!empty($depart['echeance'])) {
            $html .= '<p class="lbp-pointage-note">Premier colis reçu : délai des manquants atteint le ' . View::e(self::date((string) $depart['echeance'])) . '.</p>';
        }

        $lignes = [];
        foreach (($p['colis'] ?? []) as $c) {
            [$etat, $tonEtat] = self::ETATS_COLIS[(string) $c['etat']] ?? ['—', 'neutral'];
            $lignes[] = [
                '<strong>' . View::e((string) $c['numero_tracking']) . '</strong>',
                View::e((string) ($c['expediteur'] ?? '—')),
                View::e((string) ($c['destinataire'] ?? '—')),
                View::e((string) (int) ($c['nombre_colis'] ?? 1)),
                View::e(number_format((float) ($c['poids_total'] ?? 0), 2, ',', ' ')) . ' kg',
                Ui::badge($etat, $tonEtat),
                View::e(!empty($c['date_reception']) ? self::date((string) $c['date_reception']) . ' par ' . ($c['recu_par'] ?? '—') : '—'),
            ];
        }

        $html .= Ui::section('Colis du départ', ModuleTable::render([
            ['label' => 'Code colis'], ['label' => 'Expéditeur'], ['label' => 'Destinataire'], ['label' => 'Pièces', 'align' => 'right'],
            ['label' => 'Poids', 'align' => 'right'], ['label' => 'État'], ['label' => 'Reçu'],
        ], $lignes, 'Aucun colis dans ce départ'));

        $historique = [];
        foreach (($p['historique'] ?? []) as $h) {
            $historique[] = [
                View::e(self::date((string) ($h['created_at'] ?? ''))),
                View::e(self::ACTIONS[(string) $h['action']] ?? (string) $h['action']),
                View::e(self::SOURCES[(string) $h['source']] ?? (string) $h['source']),
                '<strong>' . View::e((string) $h['numero_tracking']) . '</strong>',
                View::e((string) ($h['agence'] ?? '—')),
                View::e((string) ($h['par'] ?? '—')),
            ];
        }

        return $html . Ui::section('Historique des pointages', ModuleTable::render([
            ['label' => 'Quand'], ['label' => 'Geste'], ['label' => 'Par'], ['label' => 'Code colis'], ['label' => 'Agence'], ['label' => 'Utilisateur'],
        ], $historique, 'Aucun pointage enregistré'));
    }

    // ------------------------------------------------------------------
    // Éléments communs
    // ------------------------------------------------------------------

    /**
     * @return array<int, array{label:string, align?:string}>
     */
    private static function colonnesColis(bool $caseEnTete, string $derniere = 'Enregistré le'): array
    {
        return [
            ['label' => $caseEnTete ? 'Part' : 'Reçu'],
            ['label' => 'Code colis'],
            ['label' => 'Expéditeur'],
            ['label' => 'Destinataire'],
            ['label' => 'Pièces', 'align' => 'right'],
            ['label' => 'Poids', 'align' => 'right'],
            ['label' => $caseEnTete ? $derniere : 'Pointé'],
        ];
    }

    /**
     * @param array<int, array{id:int, name:string}> $agences
     */
    private static function choixAgence(string $chemin, array $agences, ?int $choisie, string $libelle, bool $toutes): string
    {
        $options = '<option value="">' . ($toutes ? 'Toutes les agences' : 'Choisir une agence') . '</option>';

        foreach ($agences as $agence) {
            $options .= '<option value="' . (int) $agence['id'] . '"' . ($choisie === (int) $agence['id'] ? ' selected' : '') . '>'
                . View::e((string) $agence['name']) . '</option>';
        }

        return '<form method="get" action="' . View::e(View::url($chemin)) . '" class="lbp-pointage-filtre">'
            . '<label>' . View::e($libelle) . ' <select name="agence" class="lbp-envoi-auto">' . $options . '</select></label>'
            . Ui::button('Afficher', ['type' => 'submit', 'variant' => 'secondary'])
            . '</form>';
    }

    /**
     * @param array<string, mixed> $depart
     */
    private static function lienDepart(array $depart): string
    {
        return '<a href="' . View::e(View::url('colisage/departs/' . (int) $depart['id'])) . '"><strong>'
            . View::e((string) $depart['reference']) . '</strong></a>';
    }

    private static function kpi(string $libelle, int $valeur, bool $alerte = false): string
    {
        return '<div class="lbp-pointage-kpi' . ($alerte ? ' is-alerte' : '') . '"><span>' . View::e($libelle) . '</span><strong>' . $valeur . '</strong></div>';
    }

    private static function date(string $valeur, string $format = 'd/m/Y H:i'): string
    {
        $horodatage = $valeur !== '' ? strtotime($valeur) : false;

        return $horodatage ? date($format, $horodatage) : '—';
    }

    private static function styles(): string
    {
        return <<<'CSS'
<style>
.lbp-pointage-filtre{display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;margin:0 0 1rem}
.lbp-pointage-filtre label,.lbp-pointage-champ{display:flex;flex-direction:column;gap:.25rem;font-size:.8rem;font-weight:600;color:#475569}
.lbp-pointage-filtre select,.lbp-pointage-filtre input,.lbp-pointage-champ select{padding:.45rem .6rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff}
.lbp-pointage-scan{display:flex;flex-direction:column;gap:.35rem}
.lbp-pointage-scan input{font-family:Consolas,Monaco,monospace;font-size:1.1rem;font-weight:700;padding:.7rem 1rem;border:2px solid #2563eb;border-radius:10px;max-width:26rem}
.lbp-pointage-scan input:disabled{border-color:#cbd5e1;background:#f1f5f9}
.lbp-pointage-message{display:none;margin:.75rem 0 0;padding:.6rem .9rem;border-radius:8px;font-weight:600}
.lbp-pointage-message.is-ok{display:block;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.lbp-pointage-message.is-erreur{display:block;background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.lbp-pointage-entete{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:.75rem}
.lbp-pointage-compteur{font-weight:700;color:#0f172a;font-variant-numeric:tabular-nums}
.lbp-pointage-note{display:block;font-size:.82rem;color:#475569;margin-top:.25rem}
.lbp-pointage-note.is-alerte,.lbp-alerte{color:#b91c1c}
.lbp-pointage-actions{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
.lbp-pointage-actions form{margin:0}
.lbp-pointage-barre{position:sticky;bottom:0;z-index:5;display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:flex-end;gap:1rem;margin-top:1rem;padding:.75rem 1rem;background:#fff;border-top:1px solid #e2e8f0;box-shadow:0 -6px 16px rgba(15,23,42,.06)}
.lbp-pointage-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(9rem,1fr));gap:.75rem;margin:0 0 1rem}
.lbp-pointage-kpi{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:.75rem 1rem}
.lbp-pointage-kpi span{display:block;font-size:.72rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#64748b}
.lbp-pointage-kpi strong{font-size:1.4rem;font-variant-numeric:tabular-nums;color:#0f172a}
.lbp-pointage-kpi.is-alerte strong{color:#b91c1c}
input.lbp-case{width:1.15rem;height:1.15rem;cursor:pointer}
input.lbp-case:focus-visible{outline:2px solid #2563eb;outline-offset:2px}
</style>
CSS;
    }

    private static function scriptCommun(): string
    {
        return <<<'JS'
document.querySelectorAll('form[data-confirmer]').forEach(function (formulaire) {
    formulaire.addEventListener('submit', function (evenement) {
        if (!window.confirm(formulaire.dataset.confirmer)) {
            evenement.preventDefault();
        }
    });
});
document.querySelectorAll('select.lbp-envoi-auto').forEach(function (liste) {
    liste.addEventListener('change', function () { liste.form.submit(); });
});
JS;
    }

    private static function scriptDepart(): string
    {
        return '<script>(function () {' . self::scriptCommun() . <<<'JS'
var formulaire = document.getElementById('lbp-form-depart');
if (!formulaire) { return; }
var compteur = document.getElementById('lbp-depart-compteur');
var zone = document.getElementById('lbp-depart-message');
function compter() {
    compteur.textContent = formulaire.querySelectorAll('.lbp-depart-case:checked').length;
}
function dire(texte, ok) {
    zone.textContent = texte;
    zone.className = 'lbp-pointage-message ' + (ok ? 'is-ok' : 'is-erreur');
}
formulaire.addEventListener('change', function (evenement) {
    if (evenement.target.classList.contains('lbp-depart-case')) { compter(); }
});
formulaire.querySelectorAll('.lbp-tout-cocher').forEach(function (bouton) {
    bouton.addEventListener('click', function () {
        var cases = formulaire.querySelectorAll('.lbp-depart-case[data-groupe="' + bouton.dataset.groupe + '"]');
        var toutesCochees = Array.prototype.every.call(cases, function (c) { return c.checked; });
        cases.forEach(function (c) { c.checked = !toutesCochees; });
        compter();
    });
});
var scan = document.getElementById('lbp-depart-scan');
scan.addEventListener('keydown', function (evenement) {
    if (evenement.key !== 'Enter') { return; }
    evenement.preventDefault();
    var code = scan.value.trim().toUpperCase();
    scan.value = '';
    if (code === '') { return; }
    var trouve = null;
    formulaire.querySelectorAll('.lbp-depart-case').forEach(function (c) { if (c.dataset.tracking === code) { trouve = c; } });
    if (trouve === null) {
        dire('Le colis ' + code + " n'est pas dans la liste des colis à expédier de cette agence.", false);
        return;
    }
    trouve.checked = true;
    compter();
    dire('Colis ' + code + ' coché.', true);
});
formulaire.addEventListener('submit', function (evenement) {
    if (formulaire.querySelectorAll('.lbp-depart-case:checked').length === 0) {
        evenement.preventDefault();
        dire('Cochez au moins un colis avant de marquer le départ.', false);
    }
});
compter();
JS . '})();</script>';
    }

    private static function scriptReception(): string
    {
        return '<script>(function () {' . self::scriptCommun() . <<<'JS'
var racine = document.getElementById('lbp-reception');
if (!racine) { return; }
var zone = document.getElementById('lbp-reception-message');
function dire(texte, ok) {
    zone.textContent = texte;
    zone.className = 'lbp-pointage-message ' + (ok ? 'is-ok' : 'is-erreur');
}
function envoyer(url, donnees) {
    var corps = new FormData();
    corps.append('_csrf_token', racine.dataset.csrf);
    Object.keys(donnees).forEach(function (cle) { corps.append(cle, donnees[cle]); });
    return fetch(url, { method: 'POST', body: corps, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (reponse) { return reponse.json(); });
}
function actualiser(depart) {
    if (!depart) { return; }
    var bloc = racine.querySelector('.lbp-reception-depart[data-depart-id="' + depart.id + '"]');
    if (!bloc) { return; }
    var recus = bloc.querySelector('[data-role="recus"]');
    var restants = bloc.querySelector('[data-role="restants"]');
    if (recus) { recus.textContent = depart.recus; }
    if (restants) { restants.textContent = depart.restants; }
}
racine.addEventListener('change', function (evenement) {
    var caseCochee = evenement.target;
    if (!caseCochee.classList.contains('lbp-reception-case')) { return; }
    var voulu = caseCochee.checked;
    caseCochee.disabled = true;
    envoyer(racine.dataset.urlPointer, { colis_id: caseCochee.dataset.colisId, recu: voulu ? '1' : '0' })
        .then(function (resultat) {
            caseCochee.disabled = false;
            if (!resultat.ok) { caseCochee.checked = !voulu; }
            dire(resultat.message, resultat.ok);
            actualiser(resultat.depart);
        })
        .catch(function () {
            caseCochee.disabled = false;
            caseCochee.checked = !voulu;
            dire("Connexion perdue : ce pointage n'a pas été enregistré.", false);
        });
});
var scan = document.getElementById('lbp-reception-scan');
if (scan && !scan.disabled) {
    scan.focus();
    scan.addEventListener('keydown', function (evenement) {
        if (evenement.key !== 'Enter') { return; }
        evenement.preventDefault();
        var code = scan.value.trim();
        scan.value = '';
        if (code === '') { return; }
        envoyer(racine.dataset.urlScanner, { code: code, agence_id: scan.dataset.agence || '' })
            .then(function (resultat) {
                dire(resultat.message, resultat.ok);
                if (resultat.ok && resultat.colis_id) {
                    var caseColis = racine.querySelector('.lbp-reception-case[data-colis-id="' + resultat.colis_id + '"]');
                    if (caseColis) { caseColis.checked = true; }
                }
                actualiser(resultat.depart);
                scan.focus();
            })
            .catch(function () {
                dire("Connexion perdue : ce scan n'a pas été enregistré.", false);
                scan.focus();
            });
    });
}
JS . '})();</script>';
    }
}
