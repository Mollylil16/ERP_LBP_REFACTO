<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Services\Colisage\DossierEnvoiRegles as Regles;

/**
 * Écrans des départs préparés par l'agent export : préparer un départ, fiche
 * et contrôle du Directeur général, départs à valider, historique, prestataires.
 *
 * Présentation calquée sur les écrans Finance (factures) : en-tête, bandeau de
 * statut, cartes de sections, filtres en carte, montants alignés à droite.
 * Les dix colonnes LBP portent leur numéro, dans l'ordre demandé.
 *
 * Toute donnée venue de la base passe par View::e().
 */
final class ColisageEnvois
{
    /** @var array<string, string> */
    public const TONS_STATUT = [
        'EN_COURS' => 'info',
        'SOUMIS' => 'warning',
        'A_CORRIGER' => 'danger',
        'VALIDE' => 'success',
        'REPRIS' => 'neutral',
    ];

    public const ACTIONS_JOURNAL = [
        'CREATION' => 'Départ enregistré',
        'MODIFICATION' => 'Modification',
        'DOCUMENT_AJOUT' => 'Pièce jointe',
        'DOCUMENT_RETRAIT' => 'Pièce retirée',
        'FACTURE' => 'Montant facturé',
        'SOUMISSION' => 'Soumis au DG',
        'VALIDATION' => 'Validé',
        'RENVOI' => 'Renvoyé pour correction',
        'REOUVERTURE' => 'Rouvert',
    ];

    private const ICONES = [
        'historique' => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>',
        'valider' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>',
        'imprimer' => '<polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect>',
        'telecharger' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line>',
        'filtrer' => '<circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>',
        'reinitialiser' => '<path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path>',
        'plus' => '<line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>',
        'avion' => '<path d="M17.8 19.2L16 11l3.5-3.5C21 6 21.5 4 21 3.5c-.5-.5-2.5 0-4 1.5L13.5 8.5 5.3 6.7c-.5-.1-1.1.1-1.4.6l-.6.9c-.3.4-.2 1 .2 1.3L8 13l-3 3-2-1c-.4-.2-.9-.1-1.2.2l-.6.6c-.3.3-.3.8 0 1.1l2.5 2.5c.3.3.8.3 1.1 0l.6-.6c.3-.3.4-.8.2-1.2l-1-2 3-3 3.5 4.5c.3.4.9.5 1.3.2l.9-.6c.5-.3.7-.9.6-1.4z"></path>',
        'document' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline>',
        'alerte' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>',
        'info' => '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>',
        'envoyer' => '<line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>',
        'retour' => '<line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline>',
    ];

    // ------------------------------------------------------------------
    // Préparer un départ
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function preparerPage(array $p): string
    {
        $peutCreer = !empty($p['peut_creer']);

        $actions = [Ui::button(self::icone('historique') . 'Historique des envois', ['href' => 'colisage/envois/historique', 'variant' => 'secondary'])];
        if (!empty($p['est_valideur'])) {
            $actions[] = Ui::button(self::icone('valider') . 'Départs à valider', ['href' => 'colisage/envois/a-valider', 'variant' => 'secondary']);
        }

        $html = Ui::pageHeader(
            'Préparer un départ',
            $peutCreer
                ? 'Saisissez les informations du document de la compagnie. En enregistrant, les colis enregistrés pour la destination et pas encore partis partent avec ce départ.'
                : "Départs en cours de préparation par l'agent export.",
            ['eyebrow' => 'Envois', 'class' => 'rh-hero-white', 'actions' => $actions]
        );

        if (!empty($p['erreurs'])) {
            $html .= self::erreurs("Le départ n'a pas été enregistré. À corriger :", $p['erreurs']);
        }

        if ($peutCreer) {
            $html .= self::formulaire($p, 'colisage/departs/enregistrer', true);
        }

        $html .= self::tableauEnCours($p['en_cours'] ?? [], !empty($p['voit_tout']));

        return self::coquille($html);
    }

    /** @param array<int, array<string, mixed>> $dossiers */
    private static function tableauEnCours(array $dossiers, bool $voitTout): string
    {
        $colonnes = [
            ['label' => 'N° de départ'], ['label' => 'Date de départ'], ['label' => 'Trajet'], ['label' => 'Compagnie et document'],
            ['label' => 'Colonnes'], ['label' => 'Pièces'],
        ];
        if ($voitTout) {
            $colonnes[] = ['label' => 'Agent export'];
        }
        $colonnes[] = ['label' => 'Statut'];
        $colonnes[] = ['label' => ''];

        $lignes = [];
        foreach ($dossiers as $d) {
            $s = $d['synthese'];
            $ligne = [
                self::lienDossier($d),
                View::e(self::date($d['date_depart_effective'] ?? null)),
                self::trajet($d),
                self::valeur($d['transporteur'] ?? null) . '<span class="lbp-envoi-sous lbp-envoi-mono">' . self::valeur($d['numero_document'] ?? null) . '</span>',
                self::jaugeColonnes((int) $s['colonnes_renseignees']),
                self::badgePieces($s),
            ];
            if ($voitTout) {
                $ligne[] = View::e((string) ($d['responsable'] ?? '—'));
            }
            $ligne[] = self::badgeStatut((string) $d['statut']);
            $ligne[] = Ui::button(
                in_array((string) $d['statut'], Regles::STATUTS_MODIFIABLES, true) && !$voitTout ? 'Compléter' : 'Consulter',
                ['href' => 'colisage/envois/' . (int) $d['id'], 'variant' => 'secondary', 'class' => 'finea-button-sm']
            );
            $lignes[] = $ligne;
        }

        return Ui::section(
            'Départs en cours',
            ModuleTable::render($colonnes, $lignes, 'Aucun départ en cours', 'Les départs enregistrés apparaissent ici tant qu\'ils ne sont pas validés par le Directeur général.'),
            count($lignes) . ' départ(s)'
        );
    }

    // ------------------------------------------------------------------
    // Formulaire des dix colonnes
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    private static function formulaire(array $p, string $action, bool $creation): string
    {
        $d = $p['dossier'] ?? [];
        $mode = isset(Regles::MODES[(string) ($d['mode_transport'] ?? '')]) ? (string) $d['mode_transport'] : 'AERIEN';
        $agences = $p['agences'] ?? [];

        $selectMode = Form::rawSelect('mode_transport', self::optionsModes(), $mode, [
            'id' => 'envoi-mode',
            'data-documents' => (string) json_encode(Regles::DOCUMENT_DU_MODE, JSON_UNESCAPED_UNICODE),
        ]);

        $trajet = '<div class="lbp-envoi-grille lbp-envoi-grille--3">'
            . self::champ('Mode de transport', $selectMode, 'envoi-mode');

        if ($creation) {
            $trajet .= self::champ("Agence de départ", Form::rawSelect('agence_depart_id', self::optionsAgences($agences, "Choisir l'agence"), (string) ($d['agence_depart_id'] ?? ''), ['id' => 'envoi-agence-depart', 'required' => true]), 'envoi-agence-depart')
                . self::champ('Destination', Form::rawSelect('agence_arrivee_id', self::optionsAgences($agences, 'Choisir la destination'), (string) ($d['agence_arrivee_id'] ?? ''), ['id' => 'envoi-destination', 'required' => true]), 'envoi-destination', 'Les colis enregistrés pour cette destination partent avec le départ.');
        } else {
            $trajet .= self::lecture("Agence de départ", (string) ($d['agence_depart'] ?? '—'))
                . self::lecture('Destination', (string) ($d['agence_arrivee'] ?? '—'));
        }
        $trajet .= '</div>';

        $document = '<div class="lbp-envoi-grille lbp-envoi-grille--5">'
            . self::champ(Regles::COLONNES[1], Form::rawInput('date_depart_effective', (string) ($d['date_depart_effective'] ?? ''), ['type' => 'date', 'id' => 'envoi-date', 'required' => true]), 'envoi-date', '', 1)
            . self::champ(Regles::COLONNES[2], self::selectCompagnies($p['prestataires'] ?? [], $d['transporteur_id'] ?? null), 'envoi-compagnie', '', 2)
            . self::champ(Regles::DOCUMENT_DU_MODE[$mode], Form::rawInput('numero_document', (string) ($d['numero_document'] ?? ''), [
                'id' => 'envoi-document', 'required' => true, 'maxlength' => '60', 'autocomplete' => 'off',
                'class' => 'lbp-envoi-mono', 'placeholder' => $mode === 'AERIEN' ? '057-30215463' : '',
            ]), 'envoi-document', '', 3, 'lbp-envoi-libelle-document')
            . self::champ(Regles::COLONNES[4], Form::rawInput('nb_colis_declare', self::saisieNombre($d['nb_colis_declare'] ?? null), ['id' => 'envoi-colis', 'inputmode' => 'numeric', 'required' => true]), 'envoi-colis', '', 4)
            . self::champ(Regles::COLONNES[5] . ' (kg)', Form::rawInput('poids_brut_kg', self::saisieNombre($d['poids_brut_kg'] ?? null), ['id' => 'envoi-poids', 'inputmode' => 'decimal', 'required' => true]), 'envoi-poids', '', 5)
            . '</div>';

        if ($creation) {
            $document .= '<div class="lbp-envoi-fichier">'
                . '<label for="envoi-fichier">' . self::icone('document') . 'Joindre le document de la compagnie <small>facultatif · PDF ou photo · 10 Mo au maximum</small></label>'
                . '<input class="finea-input" type="file" id="envoi-fichier" name="document_compagnie" accept=".pdf,.jpg,.jpeg,.png,.webp">'
                . '</div>';
        }

        $parPoste = Regles::fraisParPoste($p['frais'] ?? []);
        $lignesFrais = '';
        foreach (Regles::POSTES as $poste => $libelle) {
            $f = $parPoste[$poste] ?? [];
            $nom = 'frais[' . $poste . ']';
            $lignesFrais .= '<tr>'
                . '<td>' . self::numero(Regles::COLONNE_DU_POSTE[$poste]) . '<strong>' . View::e($libelle) . '</strong></td>'
                . '<td>' . Form::rawInput($nom . '[prestataire]', (string) ($f['prestataire'] ?? $f['prestataire_libre'] ?? ''), [
                    'list' => 'lbp-envoi-prestataires', 'maxlength' => '150', 'aria-label' => $libelle . ' : prestataire',
                    'placeholder' => str_starts_with($poste, 'TRANSIT') ? 'Nom du transitaire' : 'Livreur ou société',
                ]) . '</td>'
                . '<td>' . Form::rawInput($nom . '[montant_prevu]', self::saisieNombre($f['montant_prevu'] ?? null), ['inputmode' => 'decimal', 'class' => 'lbp-envoi-montant', 'aria-label' => $libelle . ' : montant']) . '</td>'
                . '<td>' . Form::rawSelect($nom . '[devise]', self::optionsDevises(), (string) ($f['devise'] ?? 'XOF'), ['aria-label' => $libelle . ' : devise']) . '</td>'
                . ($creation ? '' : '<td class="lbp-envoi-droite">' . self::montantFacture($f) . '</td>')
                . '</tr>';
        }

        $noms = '';
        foreach ($p['prestataires'] ?? [] as $prestataire) {
            if (!empty($prestataire['is_active'])) {
                $noms .= '<option value="' . View::e((string) $prestataire['name']) . '"></option>';
            }
        }

        $frais = '<div class="finea-table-wrapper"><table class="finea-table lbp-envoi-saisie"><thead><tr>'
            . '<th>Colonne</th><th>Prestataire</th><th class="lbp-envoi-droite">Montant</th><th>Devise</th>'
            . ($creation ? '' : '<th class="lbp-envoi-droite">Facturé</th>')
            . '</tr></thead><tbody>' . $lignesFrais . '</tbody></table></div>'
            . '<datalist id="lbp-envoi-prestataires">' . $noms . '</datalist>'
            . '<p class="lbp-envoi-aide">' . self::icone('info')
            . "<span>Laissez le montant vide s'il n'est pas encore connu, saisissez 0 s'il n'y a pas de frais. Le montant facturé se renseigne en joignant la facture, sur la fiche du départ.</span></p>";

        $emballages = array_values($p['emballages'] ?? []);
        if ($emballages === []) {
            $emballages = [['type' => '', 'quantite' => '']];
        }
        $lignesEmballages = '';
        foreach ($emballages as $rang => $emballage) {
            $lignesEmballages .= self::ligneEmballage((string) $rang, (string) ($emballage['type'] ?? ''), self::saisieNombre($emballage['quantite'] ?? null));
        }

        $blocEmballages = '<p class="lbp-envoi-colonne">' . self::numero(10) . "<strong>Type et nombre d'emballages</strong></p>"
            . '<div class="lbp-envoi-emballages" id="lbp-envoi-emballages">' . $lignesEmballages . '</div>'
            . '<template id="lbp-envoi-modele-emballage">' . self::ligneEmballage('__rang__', '', '') . '</template>'
            . '<button type="button" class="rh-filter-btn rh-filter-btn--reset lbp-envoi-ajouter" id="lbp-envoi-ajouter-emballage">'
            . self::icone('plus') . "Ajouter un type d'emballage</button>";

        $bouton = $creation
            ? Ui::button(self::icone('avion') . 'Enregistrer le départ', ['type' => 'submit', 'variant' => 'accent'])
            : Ui::button('Enregistrer les modifications', ['type' => 'submit', 'variant' => 'primary']);

        return '<form method="post" action="' . View::e(View::url($action)) . '" id="lbp-envoi-form"'
            . ($creation
                ? ' enctype="multipart/form-data" data-confirmer="' . View::e('Enregistrer ce départ ? Les colis enregistrés pour cette destination et pas encore partis partiront avec lui.') . '"'
                : '')
            . '>'
            . Form::hidden('_csrf_token', Csrf::token())
            . Ui::section('Trajet', $trajet)
            . Ui::section('Document de la compagnie', $document, 'Colonnes 1 à 5, telles qu\'elles figurent sur le document')
            . Ui::section('Frais', $frais, 'Colonnes 6 à 9')
            . Ui::section('Emballages', $blocEmballages, 'Colonne 10')
            . '<div class="lbp-envoi-barre">' . $bouton . '</div>'
            . '</form>';
    }

    private static function ligneEmballage(string $rang, string $type, string $quantite): string
    {
        $types = [['value' => '', 'label' => 'Type d\'emballage']];
        foreach (Regles::EMBALLAGES as $valeur) {
            $types[] = ['value' => $valeur, 'label' => $valeur];
        }

        return '<div class="lbp-envoi-emballage">'
            . Form::rawSelect('emballages[' . $rang . '][type]', $types, $type, ['aria-label' => "Type d'emballage"])
            . Form::rawInput('emballages[' . $rang . '][quantite]', $quantite, ['inputmode' => 'numeric', 'placeholder' => 'Nombre', 'aria-label' => "Nombre d'emballages"])
            . '<button type="button" class="lbp-envoi-retirer" aria-label="Retirer cette ligne">Retirer</button>'
            . '</div>';
    }

    // ------------------------------------------------------------------
    // Fiche d'un départ
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function fichePage(array $p): string
    {
        $d = $p['dossier'];
        $droits = $p['droits'] ?? [];
        $id = (int) $d['id'];
        $mode = (string) ($d['mode_transport'] ?? 'AERIEN');

        $html = Ui::pageHeader(
            'Départ ' . (string) $d['numero'],
            (Regles::MODES[$mode] ?? $mode) . ' · ' . (string) ($d['agence_depart'] ?? '—') . ' → ' . (string) ($d['agence_arrivee'] ?? '—')
                . ' · agent export : ' . (string) ($d['responsable'] ?? '—'),
            ['eyebrow' => 'Envois', 'class' => 'rh-hero-white', 'actions' => [
                self::badgeStatut((string) $d['statut']),
                Ui::button(self::icone('imprimer') . 'Fiche PDF', ['href' => 'colisage/envois/' . $id . '/pdf', 'variant' => 'primary', 'target' => '_blank']),
                Ui::button(self::icone('retour') . 'Retour', ['href' => !empty($droits['voit_saisie']) ? 'colisage/envois/historique' : 'colisage/departs', 'variant' => 'secondary']),
            ]]
        );

        $html .= self::bandeau($p);

        if (!empty($p['erreurs'])) {
            $html .= self::erreurs("Les modifications n'ont pas été enregistrées. À corriger :", $p['erreurs']);
        }

        if (!empty($droits['voit_saisie'])) {
            $html .= self::controleSaisie($p);
        }

        if (!empty($droits['modifier'])) {
            $html .= self::formulaire($p, 'colisage/envois/' . $id . '/modifier', false);
        } else {
            $html .= Ui::section('Informations du départ', self::informations($p), 'Les dix colonnes');
            $html .= Ui::section('Frais', self::tableauFrais($p), 'Colonnes 6 à 9 · montants prévus et facturés');
        }

        $html .= self::sectionPieces($p);

        if (!empty($droits['modifier'])) {
            $html .= self::sectionSoumission($p);
        }

        if (!empty($droits['valider']) || !empty($droits['rouvrir'])) {
            $html .= self::sectionDecision($p);
        }

        return self::coquille($html . self::journal($p));
    }

    /** @param array<string, mixed> $p */
    private static function bandeau(array $p): string
    {
        $d = $p['dossier'];
        $s = $p['synthese'];
        $colonnes = (int) $s['colonnes_renseignees'] . ' / 10';
        $cout = (float) $s['cout_retenu_xof'] > 0 ? Regles::nombre((float) $s['cout_retenu_xof']) . ' XOF' : '—';

        [$ton, $icone, $titre, $detail, $chiffre, $legende] = match ((string) $d['statut']) {
            'SOUMIS' => ['warning', 'historique', 'EN ATTENTE DU DIRECTEUR GÉNÉRAL', 'Soumis le ' . self::date($d['soumis_le'] ?? null, 'd/m/Y à H:i') . '.', $cout, 'coût du transport'],
            'A_CORRIGER' => ['danger', 'alerte', 'RENVOYÉ POUR CORRECTION', (string) ($d['motif_renvoi'] ?? ''), $colonnes, 'colonnes renseignées'],
            'VALIDE' => ['success', 'valider', 'DÉPART VALIDÉ', 'Par ' . (string) ($d['valide_par'] ?? '—') . ' le ' . self::date($d['valide_le'] ?? null, 'd/m/Y à H:i')
                . (!empty($d['commentaire_dg']) ? ' · « ' . (string) $d['commentaire_dg'] . ' »' : ''), $cout, 'coût du transport'],
            'REPRIS' => ['neutral', 'info', 'DÉPART REPRIS', "Repris de l'ancien fichier de suivi.", $cout, 'coût du transport'],
            default => ['info', 'avion', 'DÉPART EN COURS', 'Complétez les dix colonnes et joignez les pièces, puis soumettez le départ au Directeur général.', $colonnes, 'colonnes renseignées'],
        };

        return '<div class="lbp-envoi-bandeau lbp-envoi-bandeau--' . $ton . '" role="status">'
            . '<div class="lbp-envoi-bandeau__message">' . self::icone($icone, 20) . '<div>' . View::e($titre)
            . ($detail !== '' ? '<span>' . View::e($detail) . '</span>' : '') . '</div></div>'
            . '<div class="lbp-envoi-bandeau__chiffre"><strong>' . View::e($chiffre) . '</strong><small>' . View::e($legende) . '</small></div>'
            . '</div>';
    }

    /** @param array<string, mixed> $p */
    private static function controleSaisie(array $p): string
    {
        $d = $p['dossier'];
        $ecart = $p['synthese']['ecart_saisie'] ?? null;

        if ($ecart === null) {
            return '';
        }

        $document = Regles::DOCUMENT_DU_MODE[(string) ($d['mode_transport'] ?? 'AERIEN')] ?? 'Document';
        $lignes = '<tr>'
            . '<td><strong>' . self::numero(4) . 'Nombre de colis</strong></td>'
            . '<td class="lbp-envoi-droite lbp-envoi-chiffre">' . View::e(Regles::nombre((float) ($d['nb_colis_declare'] ?? 0))) . '</td>'
            . '<td class="lbp-envoi-droite lbp-envoi-chiffre">' . View::e(Regles::nombre((float) ($d['colis_erp'] ?? 0))) . '</td>'
            . '<td class="lbp-envoi-droite">' . self::badgeEcart((float) $ecart['colis'], $ecart['pourcent_colis'], '') . '</td>'
            . '</tr><tr>'
            . '<td><strong>' . self::numero(5) . 'Poids total</strong></td>'
            . '<td class="lbp-envoi-droite lbp-envoi-chiffre">' . View::e(Regles::nombre((float) ($d['poids_brut_kg'] ?? 0), 1)) . ' kg</td>'
            . '<td class="lbp-envoi-droite lbp-envoi-chiffre">' . View::e(Regles::nombre((float) ($d['poids_erp_kg'] ?? 0), 1)) . ' kg</td>'
            . '<td class="lbp-envoi-droite">' . self::badgeEcart((float) $ecart['poids'], $ecart['pourcent_poids'], ' kg') . '</td>'
            . '</tr>';

        $tableau = '<div class="finea-table-wrapper"><table class="finea-table lbp-envoi-comparaison"><thead><tr>'
            . '<th></th><th class="lbp-envoi-droite">' . View::e($document) . '</th><th class="lbp-envoi-droite">Saisie des colis</th><th class="lbp-envoi-droite">Écart</th>'
            . '</tr></thead><tbody>' . $lignes . '</tbody></table></div>';

        $seuil = Regles::nombre(Regles::SEUIL_ECART_SAISIE_POURCENT);
        $message = $ecart['depasse']
            ? '<div class="lbp-envoi-alerte lbp-envoi-alerte--danger">' . self::icone('alerte', 18)
                . '<span>Écart au-delà de ' . $seuil . ' % : des colis ont pu partir sans être enregistrés, ou avec un poids sous-évalué. '
                . 'Appelez les agents de saisie ci-dessous avant de valider.</span></div>'
            : '<div class="lbp-envoi-alerte lbp-envoi-alerte--success">' . self::icone('valider', 18)
                . '<span>Le document de la compagnie correspond à la saisie des colis, dans la tolérance de ' . $seuil . ' %.</span></div>';

        $agents = [];
        foreach ($p['agents_saisie'] ?? [] as $agent) {
            $agents[] = [
                '<strong>' . View::e((string) $agent['agent']) . '</strong>',
                View::e((string) (int) $agent['enregistrements']),
                View::e(Regles::nombre((float) $agent['colis'])),
                View::e(Regles::nombre((float) $agent['poids'], 1)) . ' kg',
            ];
        }

        $blocAgents = '<h4 class="lbp-envoi-sous-titre">Agents de saisie des colis partis</h4>'
            . ModuleTable::render(
                [['label' => 'Agent de saisie'], ['label' => 'Enregistrements', 'align' => 'right'], ['label' => 'Colis', 'align' => 'right'], ['label' => 'Poids', 'align' => 'right']],
                $agents,
                "Aucun colis enregistré n'est parti avec ce départ",
                'Tout le contenu du document de la compagnie est absent de la saisie.'
            );

        $lien = !empty($d['expedition_id'])
            ? '<div class="lbp-envoi-actions">' . Ui::button('Voir les colis du départ', ['href' => 'colisage/departs/' . (int) $d['expedition_id'], 'variant' => 'secondary']) . '</div>'
            : '';

        return Ui::section(
            'Contrôle : document de la compagnie et saisie des colis',
            $tableau . $message . $blocAgents . $lien,
            'Visible par le Directeur général uniquement'
        );
    }

    /** @param array<string, mixed> $p */
    private static function informations(array $p): string
    {
        $d = $p['dossier'];
        $s = $p['synthese'];
        $mode = (string) ($d['mode_transport'] ?? 'AERIEN');

        $valeurs = [
            1 => [Regles::COLONNES[1], self::date($d['date_depart_effective'] ?? null)],
            2 => [Regles::COLONNES[2], (string) ($d['transporteur'] ?? '—')],
            3 => [Regles::DOCUMENT_DU_MODE[$mode] ?? Regles::COLONNES[3], (string) ($d['numero_document'] ?? '—')],
            4 => [Regles::COLONNES[4], ($d['nb_colis_declare'] ?? null) !== null ? Regles::nombre((float) $d['nb_colis_declare']) : '—'],
            5 => [Regles::COLONNES[5], ($d['poids_brut_kg'] ?? null) !== null ? Regles::nombre((float) $d['poids_brut_kg'], 1) . ' kg' : '—'],
        ];

        foreach (Regles::POSTES as $poste => $libelle) {
            $textes = self::textesPoste($s, $poste);
            $valeurs[Regles::COLONNE_DU_POSTE[$poste]] = [
                $libelle,
                trim(($textes['prestataire'] !== '' ? $textes['prestataire'] . ' · ' : '') . ($textes['prevu'] !== '' ? $textes['prevu'] : '—')),
            ];
        }

        $valeurs[10] = [Regles::COLONNES[10], $s['emballages_texte'] !== '' ? (string) $s['emballages_texte'] : '—'];

        $html = '<div class="lbp-envoi-infos">';
        foreach ($valeurs as $colonne => [$libelle, $valeur]) {
            $html .= '<div class="lbp-envoi-info">' . self::numero($colonne)
                . '<div><small>' . View::e($libelle) . '</small><strong' . ($colonne === 3 ? ' class="lbp-envoi-mono"' : '') . '>' . View::e($valeur) . '</strong></div></div>';
        }

        return $html . '</div>';
    }

    /** @param array<string, mixed> $p */
    private static function tableauFrais(array $p): string
    {
        $s = $p['synthese'];
        $taux = (float) $s['taux'];
        $lignes = '';

        foreach (Regles::POSTES as $poste => $libelle) {
            $f = $s['frais_par_poste'][$poste] ?? [];
            $ecart = $f !== [] ? Regles::ecartFacture($f, $taux) : null;
            $prestataire = trim((string) ($f['prestataire'] ?? $f['prestataire_libre'] ?? ''));

            $lignes .= '<tr>'
                . '<td>' . self::numero(Regles::COLONNE_DU_POSTE[$poste]) . '<strong>' . View::e($libelle) . '</strong></td>'
                . '<td>' . ($prestataire !== '' ? View::e($prestataire) : '<span class="lbp-envoi-muet">—</span>') . '</td>'
                . '<td class="lbp-envoi-droite">' . (($f['montant_prevu'] ?? null) !== null ? View::e(self::montantBrut((float) $f['montant_prevu'], (string) ($f['devise'] ?? 'XOF'))) : '<span class="lbp-envoi-muet">—</span>') . '</td>'
                . '<td class="lbp-envoi-droite">' . self::montantFacture($f) . '</td>'
                . '<td class="lbp-envoi-droite">' . ($ecart === null ? '<span class="lbp-envoi-muet">—</span>'
                    : Ui::badge(self::signe($ecart['montant_xof']) . ' XOF' . ($ecart['pourcent'] !== null ? ' · ' . self::signe($ecart['pourcent'], 1) . ' %' : ''), $ecart['depasse'] ? 'danger' : 'success')) . '</td>'
                . '</tr>';
        }

        return '<div class="finea-table-wrapper"><table class="finea-table lbp-envoi-table"><thead><tr>'
            . '<th>Colonne</th><th>Prestataire</th><th class="lbp-envoi-droite">Prévu</th><th class="lbp-envoi-droite">Facturé</th><th class="lbp-envoi-droite">Écart</th>'
            . '</tr></thead><tbody>' . $lignes . '</tbody><tfoot><tr>'
            . '<td colspan="2">Total en XOF <span class="lbp-envoi-sous">taux EUR → XOF figé à ' . View::e(Regles::nombre($taux, 3)) . '</span></td>'
            . '<td class="lbp-envoi-droite">' . View::e(Regles::nombre((float) $s['cout_prevu_xof'])) . '</td>'
            . '<td class="lbp-envoi-droite">' . View::e(Regles::nombre((float) $s['cout_facture_xof'])) . '</td>'
            . '<td class="lbp-envoi-droite">' . View::e(self::signe((float) $s['ecart_facture_xof'])) . '</td>'
            . '</tr></tfoot></table></div>';
    }

    /** @param array<string, mixed> $p */
    private static function sectionPieces(array $p): string
    {
        $d = $p['dossier'];
        $id = (int) $d['id'];
        $documents = $p['documents'] ?? [];
        $peutModifier = !empty($p['droits']['modifier']);
        $presents = array_map('strval', array_column($documents, 'type_document'));

        $liste = '';
        foreach (Regles::piecesAttendues($p['frais'] ?? []) as $type) {
            $liste .= '<li>' . (in_array($type, $presents, true) ? Ui::badge('Jointe', 'success') : Ui::badge('À joindre', 'danger'))
                . ' ' . View::e(Regles::PIECES[$type]) . '</li>';
        }
        $html = '<ul class="lbp-envoi-checklist">' . $liste . '</ul>';

        if ($peutModifier) {
            $types = [['value' => '', 'label' => 'Choisir le type']];
            $facturables = array_values(Regles::PIECE_DU_POSTE);
            foreach (Regles::PIECES as $valeur => $libelle) {
                $types[] = ['value' => $valeur, 'label' => $libelle, 'attrs' => in_array($valeur, $facturables, true) ? ['data-facture' => '1'] : []];
            }

            $html .= '<form method="post" enctype="multipart/form-data" action="' . View::e(View::url('colisage/envois/' . $id . '/documents')) . '" class="lbp-envoi-depot">'
                . Form::hidden('_csrf_token', Csrf::token())
                . '<div class="lbp-envoi-grille">'
                . self::champ('Type de document', Form::rawSelect('type_document', $types, '', ['id' => 'depot-type', 'required' => true]), 'depot-type')
                . self::champ('Fichier', '<input class="finea-input" type="file" name="fichier" id="depot-fichier" required accept=".pdf,.jpg,.jpeg,.png,.webp,.xls,.xlsx">', 'depot-fichier', 'PDF, photo ou Excel · 10 Mo au maximum')
                . self::champ('Montant facturé', Form::rawInput('montant_facture', '', ['id' => 'depot-montant', 'inputmode' => 'decimal']), 'depot-montant', 'Comparé au montant prévu', 0, '', ['data-facture-champ' => '1'])
                . self::champ('Devise', Form::rawSelect('devise_facture', self::optionsDevises(), 'XOF', ['id' => 'depot-devise']), 'depot-devise', '', 0, '', ['data-facture-champ' => '1'])
                . self::champ('N° de facture', Form::rawInput('numero_facture', '', ['id' => 'depot-numero', 'maxlength' => '60']), 'depot-numero', '', 0, '', ['data-facture-champ' => '1'])
                . '</div>'
                . '<div class="lbp-envoi-actions">' . Ui::button(self::icone('telecharger') . 'Joindre la pièce', ['type' => 'submit', 'variant' => 'primary']) . '</div>'
                . '</form>';
        }

        $lignes = [];
        foreach ($documents as $doc) {
            $ligne = [
                View::e(Regles::PIECES[(string) $doc['type_document']] ?? (string) $doc['type_document']),
                '<a class="lbp-envoi-mono" href="' . View::e(View::url('colisage/envois/' . $id . '/documents/' . (int) $doc['id'])) . '" target="_blank" rel="noopener">'
                    . View::e((string) $doc['nom_fichier']) . '</a><span class="lbp-envoi-sous">' . View::e((string) $doc['original_name']) . '</span>',
                View::e(self::taille((int) $doc['size_bytes'])),
                View::e(self::date($doc['uploaded_at'] ?? null, 'd/m/Y H:i')) . '<span class="lbp-envoi-sous">' . View::e((string) ($doc['depose_par'] ?? '—')) . '</span>',
            ];
            if ($peutModifier) {
                $ligne[] = '<form method="post" action="' . View::e(View::url('colisage/envois/' . $id . '/documents/' . (int) $doc['id'] . '/retirer')) . '"'
                    . ' data-confirmer="' . View::e('Retirer la pièce ' . $doc['nom_fichier'] . ' ? Le retrait reste inscrit au journal.') . '">'
                    . Form::hidden('_csrf_token', Csrf::token())
                    . Ui::button('Retirer', ['type' => 'submit', 'variant' => 'danger', 'class' => 'finea-button-sm'])
                    . '</form>';
            }
            $lignes[] = $ligne;
        }

        $colonnes = [['label' => 'Type'], ['label' => 'Fichier'], ['label' => 'Taille', 'align' => 'right'], ['label' => 'Déposé']];
        if ($peutModifier) {
            $colonnes[] = ['label' => ''];
        }

        return Ui::section(
            'Pièces jointes',
            $html . ModuleTable::render($colonnes, $lignes, 'Aucune pièce jointe', $peutModifier ? 'Joignez le document de la compagnie et les factures reçues.' : ''),
            count($documents) . ' pièce(s)',
            ['id' => 'pieces']
        );
    }

    /** @param array<string, mixed> $p */
    private static function sectionSoumission(array $p): string
    {
        $id = (int) $p['dossier']['id'];
        $manques = $p['manques'] ?? [];

        if ($manques === []) {
            $corps = '<div class="lbp-envoi-alerte lbp-envoi-alerte--success">' . self::icone('valider', 18)
                . '<span>Les dix colonnes sont renseignées et les pièces sont jointes.</span></div>'
                . '<form method="post" class="lbp-envoi-actions" action="' . View::e(View::url('colisage/envois/' . $id . '/soumettre')) . '"'
                . ' data-confirmer="' . View::e("Soumettre ce départ au Directeur général ? Vous ne pourrez plus le modifier, sauf s'il vous est renvoyé.") . '">'
                . Form::hidden('_csrf_token', Csrf::token())
                . Ui::button(self::icone('envoyer') . 'Soumettre au Directeur général', ['type' => 'submit', 'variant' => 'accent'])
                . '</form>';
        } else {
            $items = '';
            foreach ($manques as $manque) {
                $items .= '<li>' . View::e((string) $manque) . '</li>';
            }
            $corps = '<div class="lbp-envoi-manques"><strong>Avant de soumettre, il reste :</strong><ul>' . $items . '</ul></div>'
                . '<div class="lbp-envoi-actions">' . Ui::button(self::icone('envoyer') . 'Soumettre au Directeur général', ['type' => 'button', 'variant' => 'accent', 'disabled' => true]) . '</div>';
        }

        return Ui::section('Soumettre au Directeur général', $corps);
    }

    /** @param array<string, mixed> $p */
    private static function sectionDecision(array $p): string
    {
        $d = $p['dossier'];
        $id = (int) $d['id'];
        $droits = $p['droits'];
        $ecart = $p['synthese']['ecart_saisie'] ?? null;
        $commentaireExige = $ecart !== null && $ecart['depasse'];
        $formulaires = '';

        if (!empty($droits['valider'])) {
            $formulaires .= '<form method="post" action="' . View::e(View::url('colisage/envois/' . $id . '/valider')) . '">'
                . Form::hidden('_csrf_token', Csrf::token())
                . '<h4>' . self::icone('valider') . 'Valider le départ</h4>'
                . '<label for="decision-commentaire">' . ($commentaireExige
                    ? 'Commentaire <strong class="lbp-envoi-rouge">obligatoire : écart au-delà de ' . Regles::nombre(Regles::SEUIL_ECART_SAISIE_POURCENT) . ' %</strong>'
                    : 'Commentaire (facultatif)') . '</label>'
                . '<textarea class="finea-input finea-textarea" name="commentaire" id="decision-commentaire" rows="3" maxlength="2000"'
                . ($commentaireExige ? ' required placeholder="Ce que vous avez vérifié auprès des agents de saisie"' : '') . '></textarea>'
                . Ui::button('Valider le départ', ['type' => 'submit', 'variant' => 'success'])
                . '</form>';

            $formulaires .= '<form method="post" action="' . View::e(View::url('colisage/envois/' . $id . '/renvoyer')) . '">'
                . Form::hidden('_csrf_token', Csrf::token())
                . '<h4>' . self::icone('retour') . "Renvoyer à l'agent export</h4>"
                . '<label for="decision-motif">Motif du renvoi</label>'
                . '<textarea class="finea-input finea-textarea" name="motif" id="decision-motif" rows="3" required maxlength="1000" placeholder="Ce que l\'agent doit corriger"></textarea>'
                . Ui::button('Renvoyer pour correction', ['type' => 'submit', 'variant' => 'danger'])
                . '</form>';
        }

        if (!empty($droits['rouvrir'])) {
            $formulaires .= '<form method="post" action="' . View::e(View::url('colisage/envois/' . $id . '/rouvrir')) . '"'
                . ' data-confirmer="' . View::e("Rouvrir ce départ validé ? L'agent export pourra le modifier.") . '">'
                . Form::hidden('_csrf_token', Csrf::token())
                . '<h4>' . self::icone('retour') . 'Rouvrir le départ</h4>'
                . '<label for="decision-reouverture">Motif de la réouverture</label>'
                . '<textarea class="finea-input finea-textarea" name="motif" id="decision-reouverture" rows="3" required maxlength="1000"></textarea>'
                . Ui::button('Rouvrir', ['type' => 'submit', 'variant' => 'secondary'])
                . '</form>';
        }

        return Ui::section('Décision du Directeur général', '<div class="lbp-envoi-decision">' . $formulaires . '</div>');
    }

    /** @param array<string, mixed> $p */
    private static function journal(array $p): string
    {
        $lignes = [];
        foreach (($p['journal'] ?? []) as $j) {
            $lignes[] = [
                View::e(self::date($j['created_at'] ?? null, 'd/m/Y H:i')),
                View::e(self::ACTIONS_JOURNAL[(string) $j['action']] ?? (string) $j['action']),
                self::valeur($j['champ'] ?? null),
                self::valeur($j['ancienne_valeur'] ?? null),
                self::valeur($j['nouvelle_valeur'] ?? null),
                self::valeur($j['motif'] ?? null),
                self::valeur($j['par'] ?? null),
            ];
        }

        return '<section class="finea-section-card lbp-envoi-journal"><details><summary>Journal des modifications (' . count($lignes) . ')</summary>'
            . ModuleTable::render([
                ['label' => 'Quand'], ['label' => 'Geste'], ['label' => 'Champ'], ['label' => 'Avant'], ['label' => 'Après'], ['label' => 'Motif'], ['label' => 'Par'],
            ], $lignes, 'Aucun geste enregistré')
            . '</details></section>';
    }

    // ------------------------------------------------------------------
    // Départs à valider
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function aValiderPage(array $p): string
    {
        $html = Ui::pageHeader(
            'Départs à valider',
            "Départs soumis par l'agent export, du plus ancien au plus récent. Le document de la compagnie y est comparé à la saisie des colis.",
            ['eyebrow' => 'Envois', 'class' => 'rh-hero-white', 'actions' => [
                Ui::button(self::icone('historique') . 'Historique des envois', ['href' => 'colisage/envois/historique', 'variant' => 'secondary']),
            ]]
        );

        $lignes = [];
        foreach (($p['dossiers'] ?? []) as $d) {
            $s = $d['synthese'];
            $ecart = $s['ecart_saisie'];
            $lignes[] = [
                self::lienDossier($d),
                View::e((string) ($d['responsable'] ?? '—')),
                View::e(self::date($d['soumis_le'] ?? null, 'd/m/Y H:i')),
                self::trajet($d),
                self::valeur($d['transporteur'] ?? null) . '<span class="lbp-envoi-sous lbp-envoi-mono">' . self::valeur($d['numero_document'] ?? null) . '</span>',
                View::e(Regles::nombre((float) ($d['nb_colis_declare'] ?? 0))) . '<span class="lbp-envoi-sous">saisie : ' . View::e(Regles::nombre((float) ($d['colis_erp'] ?? 0))) . '</span>',
                View::e(Regles::nombre((float) ($d['poids_brut_kg'] ?? 0), 1)) . ' kg<span class="lbp-envoi-sous">saisie : ' . View::e(Regles::nombre((float) ($d['poids_erp_kg'] ?? 0), 1)) . ' kg</span>',
                $ecart === null ? '—' : self::badgeEcart((float) $ecart['poids'], $ecart['pourcent_poids'], ' kg', (bool) $ecart['depasse']),
                View::e((float) $s['cout_retenu_xof'] > 0 ? Regles::nombre((float) $s['cout_retenu_xof']) . ' XOF' : '—'),
                Ui::button('Examiner', ['href' => 'colisage/envois/' . (int) $d['id'], 'variant' => 'primary', 'class' => 'finea-button-sm']),
            ];
        }

        $html .= Ui::section('En attente de validation', ModuleTable::render([
            ['label' => 'N° de départ'], ['label' => 'Agent export'], ['label' => 'Soumis le'], ['label' => 'Trajet'], ['label' => 'Compagnie et document'],
            ['label' => 'Colis', 'align' => 'right'], ['label' => 'Poids', 'align' => 'right'], ['label' => 'Écart de poids'], ['label' => 'Coût', 'align' => 'right'], ['label' => ''],
        ], $lignes, 'Aucun départ en attente', "L'agent export n'a soumis aucun départ."), count($lignes) . ' départ(s)');

        return self::coquille($html);
    }

    // ------------------------------------------------------------------
    // Historique
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function historiquePage(array $p): string
    {
        $f = $p['filtres'];
        $voitTout = !empty($p['voit_tout']);
        $voitSaisie = !empty($p['voit_saisie']);
        $requete = http_build_query(array_filter([
            'periode' => $f['periode'], 'du' => $f['du'], 'au' => $f['au'],
            'agent' => $voitTout ? $f['agent'] : null, 'q' => $f['q'],
        ], static fn (mixed $v): bool => $v !== null && $v !== ''));

        $html = Ui::pageHeader(
            'Historique des envois',
            "Filtrez par agent et par période, puis exportez : le PDF et l'Excel reprennent exactement les départs affichés, pour les comparer aux vrais documents.",
            ['eyebrow' => 'Envois', 'class' => 'rh-hero-white', 'actions' => [
                Ui::button(self::icone('imprimer') . 'Exporter en PDF', ['href' => 'colisage/envois/historique/pdf?' . $requete, 'variant' => 'primary', 'target' => '_blank']),
                Ui::button(self::icone('telecharger') . 'Exporter en Excel', ['href' => 'colisage/envois/historique/excel?' . $requete, 'variant' => 'secondary']),
            ]]
        );

        $periodes = [];
        foreach (Regles::PERIODES as $valeur => $libelle) {
            $periodes[] = ['value' => $valeur, 'label' => $libelle];
        }

        $grille = '<div class="lbp-envoi-filtres-grille">';
        if ($voitTout) {
            $agents = [['value' => '', 'label' => 'Tous les agents']];
            foreach ($p['agents'] ?? [] as $agent) {
                $agents[] = ['value' => (string) $agent['id'], 'label' => (string) $agent['full_name']];
            }
            $grille .= Form::select('agent', $agents, (string) ($f['agent'] ?? ''), ['label' => 'Agent export', 'id' => 'historique-agent']);
        }
        $grille .= Form::select('periode', $periodes, (string) $f['periode'], ['label' => 'Période', 'id' => 'historique-periode'])
            . Form::input('du', ['label' => 'Du', 'type' => 'date', 'value' => (string) $f['du'], 'id' => 'historique-du'])
            . Form::input('au', ['label' => 'Au', 'type' => 'date', 'value' => (string) $f['au'], 'id' => 'historique-au'])
            . Form::input('q', ['label' => 'N° de départ ou document', 'value' => (string) $f['q'], 'id' => 'historique-q', 'placeholder' => 'ENV-ABJ-2609-0001, 057-30215463'])
            . '</div>';

        $html .= '<form method="get" action="' . View::e(View::url('colisage/envois/historique')) . '" class="rh-personnel-filters">'
            . $grille
            . '<div class="rh-personnel-filter-actions">'
            . '<button type="submit" class="rh-filter-btn rh-filter-btn--primary">' . self::icone('filtrer') . 'Filtrer</button>'
            . '<a href="' . View::e(View::url('colisage/envois/historique')) . '" class="rh-filter-btn rh-filter-btn--reset">' . self::icone('reinitialiser') . 'Réinitialiser</a>'
            . '</div></form>';

        $t = $p['totaux'];
        $ecartPoids = round((float) $t['poids'] - (float) $t['poids_erp'], 1);
        $html .= '<div class="lbp-envoi-kpis">'
            . self::kpi('Départs', Regles::nombre((float) $t['dossiers']))
            . self::kpi('Colis (documents)', Regles::nombre((float) $t['colis']))
            . self::kpi('Poids (documents)', Regles::nombre((float) $t['poids'], 1) . ' kg')
            . self::kpi('Frais prévus', Regles::nombre((float) $t['cout_prevu']) . ' XOF')
            . ($voitSaisie ? self::kpi('Écart avec la saisie', self::signe($ecartPoids, 1) . ' kg', abs($ecartPoids) > 0) : '')
            . '</div>';

        $html .= Ui::section(
            'Envois du ' . self::date($f['du']) . ' au ' . self::date($f['au']),
            self::tableauHistorique($p),
            'Totaux des frais en XOF, au taux figé de chaque départ'
        );

        return self::coquille($html);
    }

    /** @param array<string, mixed> $p */
    private static function tableauHistorique(array $p): string
    {
        $dossiers = $p['dossiers'] ?? [];
        $voitSaisie = !empty($p['voit_saisie']);

        if ($dossiers === []) {
            return Ui::emptyState('Aucun départ sur cette période', 'Élargissez la période ou retirez un filtre.');
        }

        $entetes = '<th>N° de départ</th><th>Agent</th>';
        foreach (Regles::COLONNES as $numero => $libelle) {
            $entetes .= '<th' . (in_array($numero, [4, 5, 6, 7, 8, 9], true) ? ' class="lbp-envoi-droite"' : '') . '>' . self::numero($numero) . View::e($numero === 3 ? 'Document' : $libelle) . '</th>';
        }
        $entetes .= '<th class="lbp-envoi-droite">Facturé</th><th class="lbp-envoi-droite">Écart factures</th>';
        if ($voitSaisie) {
            $entetes .= '<th class="lbp-envoi-droite">Saisie colis</th><th class="lbp-envoi-droite">Saisie poids</th><th class="lbp-envoi-droite">Écart saisie</th>';
        }
        $entetes .= '<th>Statut</th>';

        $lignes = '';
        foreach ($dossiers as $d) {
            $s = $d['synthese'];
            $lignes .= '<tr>'
                . '<td>' . self::lienDossier($d) . '<span class="lbp-envoi-sous">' . View::e(Regles::MODES[(string) $d['mode_transport']] ?? '') . '</span></td>'
                . '<td>' . self::valeur($d['responsable'] ?? null) . '</td>'
                . '<td>' . View::e(self::date($d['date_depart_effective'] ?? null)) . '</td>'
                . '<td>' . self::valeur($d['transporteur'] ?? null) . '</td>'
                . '<td class="lbp-envoi-mono">' . self::valeur($d['numero_document'] ?? null) . '</td>'
                . '<td class="lbp-envoi-droite">' . View::e(Regles::nombre((float) ($d['nb_colis_declare'] ?? 0))) . '</td>'
                . '<td class="lbp-envoi-droite">' . View::e(Regles::nombre((float) ($d['poids_brut_kg'] ?? 0), 1)) . '</td>';

            foreach (array_keys(Regles::POSTES) as $poste) {
                $lignes .= '<td class="lbp-envoi-droite">' . self::cellulePoste($s, $poste) . '</td>';
            }

            $lignes .= '<td>' . self::valeur($s['emballages_texte'] !== '' ? $s['emballages_texte'] : null) . '</td>'
                . '<td class="lbp-envoi-droite">' . View::e((float) $s['cout_facture_xof'] > 0 ? Regles::nombre((float) $s['cout_facture_xof']) : '—') . '</td>'
                . '<td class="lbp-envoi-droite">' . ($s['ecart_facture_depasse'] ? Ui::badge(self::signe((float) $s['ecart_facture_xof']), 'danger') : View::e(self::signe((float) $s['ecart_facture_xof']))) . '</td>';

            if ($voitSaisie) {
                $ecart = $s['ecart_saisie'];
                $lignes .= '<td class="lbp-envoi-droite">' . View::e(Regles::nombre((float) ($d['colis_erp'] ?? 0))) . '</td>'
                    . '<td class="lbp-envoi-droite">' . View::e(Regles::nombre((float) ($d['poids_erp_kg'] ?? 0), 1)) . '</td>'
                    . '<td class="lbp-envoi-droite">' . ($ecart === null ? '—' : self::badgeEcart((float) $ecart['poids'], $ecart['pourcent_poids'], ' kg', (bool) $ecart['depasse'])) . '</td>';
            }

            $lignes .= '<td>' . self::badgeStatut((string) $d['statut']) . '</td></tr>';
        }

        $t = $p['totaux'];
        $pied = '<td colspan="5">' . (int) $t['dossiers'] . ' départ(s)</td>'
            . '<td class="lbp-envoi-droite">' . View::e(Regles::nombre((float) $t['colis'])) . '</td>'
            . '<td class="lbp-envoi-droite">' . View::e(Regles::nombre((float) $t['poids'], 1)) . '</td>';
        foreach (array_keys(Regles::POSTES) as $poste) {
            $pied .= '<td class="lbp-envoi-droite">' . View::e(Regles::nombre((float) $t['postes'][$poste])) . '</td>';
        }
        $pied .= '<td></td>'
            . '<td class="lbp-envoi-droite">' . View::e(Regles::nombre((float) $t['cout_facture'])) . '</td>'
            . '<td class="lbp-envoi-droite">' . View::e(self::signe((float) $t['ecart_facture'])) . '</td>';
        if ($voitSaisie) {
            $pied .= '<td class="lbp-envoi-droite">' . View::e(Regles::nombre((float) $t['colis_erp'])) . '</td>'
                . '<td class="lbp-envoi-droite">' . View::e(Regles::nombre((float) $t['poids_erp'], 1)) . '</td>'
                . '<td class="lbp-envoi-droite">' . View::e(self::signe(round((float) $t['poids'] - (float) $t['poids_erp'], 1), 1)) . ' kg</td>';
        }
        $pied .= '<td></td>';

        return '<div class="finea-table-wrapper"><table class="finea-table lbp-envoi-table">'
            . '<thead><tr>' . $entetes . '</tr></thead><tbody>' . $lignes . '</tbody><tfoot><tr>' . $pied . '</tr></tfoot>'
            . '</table></div>';
    }

    // ------------------------------------------------------------------
    // Prestataires
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function prestatairesPage(array $p): string
    {
        $html = Ui::pageHeader(
            'Transporteurs et prestataires',
            "Compagnies, transitaires et livreurs proposés dans les départs. Le préfixe LTA d'une compagnie aérienne sert à contrôler ses numéros.",
            ['eyebrow' => 'Envois', 'class' => 'rh-hero-white', 'actions' => [
                Ui::button(self::icone('avion') . 'Préparer un départ', ['href' => 'colisage/departs', 'variant' => 'secondary']),
            ]]
        );

        $types = [['value' => '', 'label' => 'Choisir le type']];
        foreach (Regles::TYPES_PRESTATAIRE as $valeur => $libelle) {
            $types[] = ['value' => $valeur, 'label' => $libelle];
        }

        $html .= Ui::section('Ajouter un prestataire', '<form method="post" action="' . View::e(View::url('colisage/envois/prestataires/enregistrer')) . '">'
            . Form::hidden('_csrf_token', Csrf::token())
            . '<div class="lbp-envoi-filtres-grille">'
            . Form::input('name', ['label' => 'Nom', 'id' => 'prestataire-nom', 'maxlength' => '150', 'required' => true])
            . Form::select('type', $types, '', ['label' => 'Type', 'id' => 'prestataire-type', 'required' => true])
            . Form::input('country', ['label' => 'Pays', 'id' => 'prestataire-pays', 'maxlength' => '100'])
            . Form::input('prefixe_lta', ['label' => 'Préfixe LTA', 'id' => 'prestataire-prefixe', 'maxlength' => '3', 'inputmode' => 'numeric', 'hint' => 'Compagnies aériennes : 3 chiffres (057 pour Air France)'])
            . '</div>'
            . '<div class="lbp-envoi-actions">' . Ui::button(self::icone('plus') . 'Ajouter', ['type' => 'submit', 'variant' => 'accent']) . '</div>'
            . '</form>');

        $lignes = [];
        foreach (($p['prestataires'] ?? []) as $pr) {
            $formulaire = 'prestataire-' . (int) $pr['id'];
            $type = (string) ($pr['type'] ?? '');
            $lignes[] = [
                '<strong>' . View::e((string) $pr['name']) . '</strong>' . ($type === '' ? ' ' . Ui::badge('Type à préciser', 'warning') : ''),
                Form::rawSelect('type', $types, $type, ['form' => $formulaire, 'aria-label' => 'Type de ' . $pr['name']]),
                Form::rawInput('country', (string) ($pr['country'] ?? ''), ['form' => $formulaire, 'maxlength' => '100', 'aria-label' => 'Pays de ' . $pr['name']]),
                Form::rawInput('prefixe_lta', (string) ($pr['prefixe_lta'] ?? ''), ['form' => $formulaire, 'maxlength' => '3', 'inputmode' => 'numeric', 'class' => 'lbp-envoi-mono lbp-envoi-prefixe', 'aria-label' => 'Préfixe LTA de ' . $pr['name']]),
                '<input type="checkbox" name="is_active" value="1" form="' . View::e($formulaire) . '"' . (!empty($pr['is_active']) ? ' checked' : '') . ' aria-label="' . View::e($pr['name'] . ' actif') . '">',
                '<form method="post" id="' . View::e($formulaire) . '" action="' . View::e(View::url('colisage/envois/prestataires/enregistrer')) . '">'
                    . Form::hidden('_csrf_token', Csrf::token())
                    . Form::hidden('id', (string) (int) $pr['id'])
                    . Ui::button('Enregistrer', ['type' => 'submit', 'variant' => 'secondary', 'class' => 'finea-button-sm'])
                    . '</form>',
            ];
        }

        $html .= Ui::section('Prestataires', ModuleTable::render([
            ['label' => 'Nom'], ['label' => 'Type'], ['label' => 'Pays'], ['label' => 'Préfixe LTA'], ['label' => 'Actif'], ['label' => ''],
        ], $lignes, 'Aucun prestataire', 'Ajoutez les compagnies, transitaires et livreurs avec lesquels LBP travaille.'), count($lignes) . ' prestataire(s)');

        return self::coquille($html);
    }

    // ------------------------------------------------------------------
    // Éléments communs, aussi utilisés par les exports
    // ------------------------------------------------------------------

    public static function date(mixed $valeur, string $format = 'd/m/Y'): string
    {
        $texte = (string) ($valeur ?? '');
        $horodatage = $texte !== '' ? strtotime($texte) : false;

        return $horodatage ? date($format, $horodatage) : '—';
    }

    public static function montantBrut(float $montant, string $devise): string
    {
        return Regles::nombre($montant, abs($montant - round($montant)) > 0.001 ? 2 : 0) . ' ' . $devise;
    }

    public static function signe(float $valeur, int $decimales = 0): string
    {
        return ($valeur > 0 ? '+' : '') . Regles::nombre($valeur, $decimales);
    }

    /**
     * Prestataire, montant prévu et montant facturé d'un poste, en texte brut.
     *
     * @param array<string, mixed> $synthese
     * @return array{prestataire:string, prevu:string, facture:string}
     */
    public static function textesPoste(array $synthese, string $poste): array
    {
        $ligne = $synthese['frais_par_poste'][$poste] ?? null;

        if ($ligne === null) {
            return ['prestataire' => '', 'prevu' => '', 'facture' => ''];
        }

        return [
            'prestataire' => trim((string) ($ligne['prestataire'] ?? $ligne['prestataire_libre'] ?? '')),
            'prevu' => ($ligne['montant_prevu'] ?? null) !== null
                ? ((float) $ligne['montant_prevu'] === 0.0 ? 'sans frais' : self::montantBrut((float) $ligne['montant_prevu'], (string) ($ligne['devise'] ?? 'XOF')))
                : '',
            'facture' => ($ligne['montant_facture'] ?? null) !== null
                ? self::montantBrut((float) $ligne['montant_facture'], (string) ($ligne['devise_facture'] ?? $ligne['devise'] ?? 'XOF'))
                : '',
        ];
    }

    private static function coquille(string $html): string
    {
        return self::styles() . '<div class="finea-shell lbp-envoi"><div class="finea-container">' . $html . '</div></div>' . self::script();
    }

    private static function icone(string $nom, int $taille = 15): string
    {
        return '<svg class="lbp-envoi-icone" width="' . $taille . '" height="' . $taille . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . (self::ICONES[$nom] ?? '') . '</svg>';
    }

    private static function numero(int $colonne): string
    {
        return $colonne > 0 ? '<span class="lbp-envoi-num">' . $colonne . '</span>' : '';
    }

    private static function badgeStatut(string $statut): string
    {
        return Ui::badge(Regles::STATUTS[$statut] ?? $statut, self::TONS_STATUT[$statut] ?? 'neutral');
    }

    private static function badgePieces(array $synthese): string
    {
        $attendues = (int) $synthese['pieces_attendues'];
        $presentes = (int) $synthese['pieces_presentes'];

        return Ui::badge($presentes . ' / ' . $attendues, $presentes >= $attendues ? 'success' : ($presentes === 0 ? 'danger' : 'warning'));
    }

    private static function badgeEcart(float $ecart, ?float $pourcent, string $unite, ?bool $depasse = null): string
    {
        $depasse ??= $pourcent === null ? abs($ecart) > 0 : abs($pourcent) > Regles::SEUIL_ECART_SAISIE_POURCENT;
        $texte = self::signe($ecart, $unite === ' kg' ? 1 : 0) . $unite . ($pourcent !== null ? ' · ' . self::signe($pourcent, 1) . ' %' : '');

        return Ui::badge($texte, $depasse ? 'danger' : 'success');
    }

    private static function jaugeColonnes(int $remplies): string
    {
        return ModuleTable::jauge((int) round($remplies * 10), $remplies >= 10 ? 'success' : ($remplies >= 5 ? 'warning' : 'danger'));
    }

    /** @param array<string, mixed> $d */
    private static function lienDossier(array $d): string
    {
        return '<a href="' . View::e(View::url('colisage/envois/' . (int) $d['id'])) . '"><strong class="lbp-envoi-mono">'
            . View::e((string) $d['numero']) . '</strong></a>';
    }

    /** @param array<string, mixed> $d */
    private static function trajet(array $d): string
    {
        return View::e((string) ($d['agence_depart'] ?? '—')) . ' → ' . View::e((string) ($d['agence_arrivee'] ?? '—'));
    }

    /** @param array<string, mixed> $synthese */
    private static function cellulePoste(array $synthese, string $poste): string
    {
        $textes = self::textesPoste($synthese, $poste);

        if ($textes['prestataire'] === '' && $textes['prevu'] === '') {
            return '<span class="lbp-envoi-muet">—</span>';
        }

        return '<strong>' . View::e($textes['prevu'] !== '' ? $textes['prevu'] : '—') . '</strong>'
            . '<span class="lbp-envoi-sous">' . View::e($textes['prestataire'] !== '' ? $textes['prestataire'] : '—') . '</span>'
            . ($textes['facture'] !== '' ? '<span class="lbp-envoi-sous">facturé ' . View::e($textes['facture']) . '</span>' : '');
    }

    /** @param array<string, mixed> $ligne */
    private static function montantFacture(array $ligne): string
    {
        if (($ligne['montant_facture'] ?? null) === null) {
            return '<span class="lbp-envoi-muet">—</span>';
        }

        return '<strong>' . View::e(self::montantBrut((float) $ligne['montant_facture'], (string) ($ligne['devise_facture'] ?? $ligne['devise'] ?? 'XOF'))) . '</strong>'
            . (!empty($ligne['numero_facture']) ? '<span class="lbp-envoi-sous">n° ' . View::e((string) $ligne['numero_facture']) . '</span>' : '');
    }

    private static function kpi(string $libelle, string $valeur, bool $alerte = false): string
    {
        return '<div class="lbp-envoi-kpi' . ($alerte ? ' is-alerte' : '') . '"><span>' . View::e($libelle) . '</span><strong>' . View::e($valeur) . '</strong></div>';
    }

    private static function valeur(mixed $valeur): string
    {
        $texte = trim((string) ($valeur ?? ''));

        return $texte === '' ? '—' : View::e($texte);
    }

    private static function taille(int $octets): string
    {
        return $octets >= 1024 * 1024 ? Regles::nombre($octets / 1024 / 1024, 1) . ' Mo' : Regles::nombre(max(1, $octets / 1024)) . ' Ko';
    }

    private static function saisieNombre(mixed $valeur): string
    {
        if ($valeur === null || $valeur === '') {
            return '';
        }

        if (!is_numeric($valeur)) {
            return (string) $valeur;
        }

        return str_replace('.', ',', rtrim(rtrim(number_format((float) $valeur, 2, '.', ''), '0'), '.'));
    }

    /**
     * Champ de formulaire, avec le numéro de sa colonne LBP.
     *
     * @param array<string, string> $attributs
     */
    private static function champ(string $libelle, string $controle, string $id, string $aide = '', int $colonne = 0, string $classeLibelle = '', array $attributs = []): string
    {
        return '<div class="finea-field"' . Html::attrs($attributs) . '>'
            . '<label for="' . View::e($id) . '">' . self::numero($colonne)
            . ($classeLibelle !== '' ? '<span class="' . View::e($classeLibelle) . '">' . View::e($libelle) . '</span>' : View::e($libelle)) . '</label>'
            . $controle
            . ($aide !== '' ? '<small class="finea-field-hint">' . View::e($aide) . '</small>' : '')
            . '</div>';
    }

    private static function lecture(string $libelle, string $valeur): string
    {
        return '<div class="finea-field"><label>' . View::e($libelle) . '</label><div class="lbp-envoi-lecture">' . View::e($valeur) . '</div></div>';
    }

    /** @param array<int, string> $erreurs */
    private static function erreurs(string $titre, array $erreurs): string
    {
        $items = '';
        foreach ($erreurs as $erreur) {
            $items .= '<li>' . View::e((string) $erreur) . '</li>';
        }

        return '<div class="lbp-envoi-erreurs" role="alert"><strong>' . self::icone('alerte', 18) . View::e($titre) . '</strong><ul>' . $items . '</ul></div>';
    }

    /**
     * Compagnies, celles du bon type en tête, les prestataires sans type ensuite.
     *
     * @param array<int, array<string, mixed>> $prestataires
     */
    private static function selectCompagnies(array $prestataires, mixed $choisi): string
    {
        $choisi = (int) ($choisi ?? 0);
        $groupes = [];
        foreach ($prestataires as $pr) {
            if (empty($pr['is_active']) && (int) $pr['id'] !== $choisi) {
                continue;
            }
            $groupes[(string) ($pr['type'] ?? '')][] = $pr;
        }

        $ordre = array_merge(array_values(Regles::TRANSPORTEUR_DU_MODE), [''], array_diff(array_keys(Regles::TYPES_PRESTATAIRE), array_values(Regles::TRANSPORTEUR_DU_MODE)));

        $html = '<select class="finea-select" name="transporteur_id" id="envoi-compagnie" required><option value="">Choisir la compagnie</option>';
        foreach (array_unique($ordre) as $type) {
            if (empty($groupes[$type])) {
                continue;
            }
            $html .= '<optgroup label="' . View::e($type === '' ? 'Type à préciser' : (Regles::TYPES_PRESTATAIRE[$type] ?? $type)) . '">';
            foreach ($groupes[$type] as $pr) {
                $html .= '<option value="' . (int) $pr['id'] . '"' . ((int) $pr['id'] === $choisi ? ' selected' : '') . '>' . View::e((string) $pr['name']) . '</option>';
            }
            $html .= '</optgroup>';
        }

        return $html . '</select>';
    }

    /** @return array<int, array{value:string, label:string}> */
    private static function optionsModes(): array
    {
        $options = [];
        foreach (Regles::MODES as $valeur => $libelle) {
            $options[] = ['value' => $valeur, 'label' => $libelle];
        }

        return $options;
    }

    /** @return array<int, array{value:string, label:string}> */
    private static function optionsDevises(): array
    {
        return array_map(static fn (string $d): array => ['value' => $d, 'label' => $d], Regles::DEVISES);
    }

    /**
     * @param array<int, array<string, mixed>> $agences
     * @return array<int, array{value:string, label:string}>
     */
    private static function optionsAgences(array $agences, string $vide): array
    {
        $options = [['value' => '', 'label' => $vide]];
        foreach ($agences as $agence) {
            $options[] = ['value' => (string) (int) $agence['id'], 'label' => (string) $agence['name']];
        }

        return $options;
    }

    private static function styles(): string
    {
        return <<<'CSS'
<style>
.lbp-envoi [hidden]{display:none!important}
.lbp-envoi-icone{display:inline-block;vertical-align:-2px;margin-right:6px;flex-shrink:0}
.lbp-envoi-num{display:inline-flex;align-items:center;justify-content:center;min-width:1.4rem;height:1.4rem;padding:0 .3rem;margin-right:.4rem;border-radius:999px;background:#1e40af;color:#fff;font-size:.72rem;font-weight:800;font-variant-numeric:tabular-nums;vertical-align:1px}
.lbp-envoi-grille{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem 1.25rem}
.lbp-envoi-grille--3{grid-template-columns:repeat(auto-fit,minmax(230px,1fr))}
.lbp-envoi-grille--5{grid-template-columns:repeat(auto-fit,minmax(175px,1fr))}
.lbp-envoi-filtres-grille{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:1rem}
.lbp-envoi-mono{font-family:Consolas,Monaco,monospace;letter-spacing:.02em}
.lbp-envoi-lecture{padding:.6rem .8rem;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc;font-weight:700;color:#0f172a}
.lbp-envoi-fichier{margin-top:1.1rem;padding:.95rem 1.1rem;border:1px dashed #93c5fd;border-radius:12px;background:#eff6ff;display:flex;flex-direction:column;gap:.5rem}
.lbp-envoi-fichier label{font-weight:700;color:#1e3a8a;display:flex;flex-wrap:wrap;align-items:center;gap:.35rem}
.lbp-envoi-fichier small{font-weight:500;color:#475569}
.lbp-envoi-saisie td{vertical-align:middle}
.lbp-envoi-saisie .finea-input,.lbp-envoi-saisie .finea-select{width:100%;min-width:7rem}
.lbp-envoi-droite{text-align:right;font-variant-numeric:tabular-nums}
.lbp-envoi-montant{text-align:right;font-variant-numeric:tabular-nums}
.lbp-envoi-aide{display:flex;gap:.3rem;align-items:flex-start;font-size:.84rem;color:#475569;margin:.85rem 0 0}
.lbp-envoi-colonne{display:flex;align-items:center;margin:0 0 .75rem;color:#0f172a}
.lbp-envoi-emballages{display:flex;flex-direction:column;gap:.6rem;margin-bottom:.85rem}
.lbp-envoi-emballage{display:grid;grid-template-columns:minmax(170px,280px) 150px auto;gap:.6rem;align-items:center;justify-content:start}
.lbp-envoi-retirer{border:1px solid #fecaca;background:#fff;color:#b91c1c;border-radius:8px;padding:.45rem .75rem;font-weight:700;font-size:.8rem;cursor:pointer}
.lbp-envoi-retirer:hover{background:#fef2f2}
.lbp-envoi-barre{position:sticky;bottom:0;z-index:5;display:flex;justify-content:flex-end;gap:.75rem;margin:1.25rem 0 1.5rem;padding:.9rem 1.2rem;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 -6px 18px rgba(15,23,42,.06)}
.lbp-envoi-bandeau{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:1rem;border:2px solid #94a3b8;background:#f8fafc;border-radius:12px;padding:1.15rem 1.5rem;margin-bottom:1.5rem}
.lbp-envoi-bandeau__message{display:flex;gap:.5rem;align-items:flex-start;font-weight:800;font-size:1.02rem;color:#0f172a;max-width:75ch}
.lbp-envoi-bandeau__message span{display:block;font-weight:500;font-size:.92rem;color:#334155;margin-top:.2rem}
.lbp-envoi-bandeau__chiffre{text-align:right}
.lbp-envoi-bandeau__chiffre strong{display:block;font-size:1.45rem;font-variant-numeric:tabular-nums;color:#0f172a}
.lbp-envoi-bandeau__chiffre small{color:#64748b}
.lbp-envoi-bandeau--info{border-color:#3b82f6;background:#eff6ff}
.lbp-envoi-bandeau--info .lbp-envoi-bandeau__chiffre strong,.lbp-envoi-bandeau--info .lbp-envoi-icone{color:#2563eb}
.lbp-envoi-bandeau--warning{border-color:#f59e0b;background:#fffbeb}
.lbp-envoi-bandeau--warning .lbp-envoi-bandeau__chiffre strong,.lbp-envoi-bandeau--warning .lbp-envoi-icone{color:#d97706}
.lbp-envoi-bandeau--danger{border-color:#ef4444;background:#fef2f2}
.lbp-envoi-bandeau--danger .lbp-envoi-bandeau__chiffre strong,.lbp-envoi-bandeau--danger .lbp-envoi-icone{color:#dc2626}
.lbp-envoi-bandeau--success{border-color:#10b981;background:#ecfdf5}
.lbp-envoi-bandeau--success .lbp-envoi-bandeau__chiffre strong,.lbp-envoi-bandeau--success .lbp-envoi-icone{color:#059669}
.lbp-envoi-erreurs{border:2px solid #ef4444;background:#fef2f2;color:#7f1d1d;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem}
.lbp-envoi-erreurs ul,.lbp-envoi-manques ul{margin:.5rem 0 0 1.2rem;padding:0}
.lbp-envoi-erreurs li,.lbp-envoi-manques li{margin:.15rem 0}
.lbp-envoi-manques{border:1px solid #fcd34d;background:#fffbeb;color:#713f12;border-radius:10px;padding:.95rem 1.15rem;margin-bottom:1rem}
.lbp-envoi-alerte{display:flex;gap:.5rem;align-items:flex-start;border-radius:10px;padding:.85rem 1rem;margin:1rem 0;font-weight:600}
.lbp-envoi-alerte--danger{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
.lbp-envoi-alerte--success{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}
.lbp-envoi-chiffre{font-size:1.1rem;font-weight:800}
.lbp-envoi-rouge{color:#dc2626}
.lbp-envoi-sous-titre{margin:1.25rem 0 .5rem;font-size:.98rem;font-weight:800;color:#0f172a}
.lbp-envoi-infos{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:.85rem 1.25rem}
.lbp-envoi-info{display:flex;align-items:flex-start;gap:.35rem;padding:.75rem .9rem;border:1px solid #e2e8f0;border-radius:10px;background:#fff}
.lbp-envoi-info small{display:block;font-size:.74rem;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:#64748b}
.lbp-envoi-info strong{display:block;margin-top:.1rem;font-size:.98rem;color:#0f172a}
.lbp-envoi-sous{display:block;font-size:.8rem;font-weight:500;color:#64748b}
.lbp-envoi-muet{color:#94a3b8}
.lbp-envoi-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin:1.5rem 0}
.lbp-envoi-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1rem 1.15rem;box-shadow:0 2px 6px rgba(15,23,42,.03)}
.lbp-envoi-kpi span{display:block;font-size:.78rem;font-weight:700;color:#64748b}
.lbp-envoi-kpi strong{display:block;margin-top:.2rem;font-size:1.35rem;font-variant-numeric:tabular-nums;color:#0f172a}
.lbp-envoi-kpi.is-alerte strong{color:#dc2626}
.lbp-envoi-table th{white-space:nowrap}
.lbp-envoi-table tfoot td{font-weight:800;background:#f8fafc;border-top:2px solid #0f172a}
.lbp-envoi-checklist{list-style:none;margin:0 0 1rem;padding:0;display:flex;flex-wrap:wrap;gap:.55rem 1.4rem}
.lbp-envoi-depot{display:flex;flex-direction:column;gap:.9rem;border:1px dashed #cbd5e1;border-radius:12px;padding:1rem 1.15rem;background:#f8fafc;margin-bottom:1rem}
.lbp-envoi-actions{display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;justify-content:flex-end;margin:.5rem 0 0}
.lbp-envoi-decision{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1.25rem}
.lbp-envoi-decision form{display:flex;flex-direction:column;gap:.6rem;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem;background:#fff}
.lbp-envoi-decision h4{margin:0 0 .2rem;font-size:1.02rem;color:#0f172a}
.lbp-envoi-decision label{font-size:.85rem;font-weight:600;color:#334155}
.lbp-envoi-decision .finea-action-btn{align-self:flex-end}
.lbp-envoi-journal summary{cursor:pointer;font-weight:800;color:#0f172a}
.lbp-envoi-journal details[open] summary{margin-bottom:.85rem}
.lbp-envoi-prefixe{max-width:5rem}
.lbp-envoi a:focus-visible,.lbp-envoi input:focus-visible,.lbp-envoi select:focus-visible,.lbp-envoi textarea:focus-visible,.lbp-envoi button:focus-visible{outline:2px solid #2563eb;outline-offset:2px}
@media (max-width:640px){.lbp-envoi-emballage{grid-template-columns:1fr 110px}.lbp-envoi-retirer{grid-column:1 / -1;justify-self:start}.lbp-envoi-bandeau__chiffre{text-align:left}}
</style>
CSS;
    }

    private static function script(): string
    {
        return <<<'HTML'
<script>(function () {
document.querySelectorAll('.lbp-envoi form[data-confirmer]').forEach(function (formulaire) {
    formulaire.addEventListener('submit', function (evenement) {
        if (!window.confirm(formulaire.dataset.confirmer)) { evenement.preventDefault(); }
    });
});

var mode = document.getElementById('envoi-mode');
var libelle = document.querySelector('.lbp-envoi-libelle-document');
var numero = document.getElementById('envoi-document');
if (mode && libelle) {
    var documents = JSON.parse(mode.dataset.documents || '{}');
    mode.addEventListener('change', function () {
        libelle.textContent = documents[mode.value] || 'Document';
        if (numero) { numero.placeholder = mode.value === 'AERIEN' ? '057-30215463' : ''; }
    });
}

var liste = document.getElementById('lbp-envoi-emballages');
var modele = document.getElementById('lbp-envoi-modele-emballage');
var ajouter = document.getElementById('lbp-envoi-ajouter-emballage');
if (liste && modele && ajouter) {
    var rang = liste.querySelectorAll('.lbp-envoi-emballage').length + 100;
    ajouter.addEventListener('click', function () {
        liste.insertAdjacentHTML('beforeend', modele.innerHTML.split('__rang__').join(String(rang++)));
    });
    liste.addEventListener('click', function (evenement) {
        var bouton = evenement.target.closest('.lbp-envoi-retirer');
        if (!bouton) { return; }
        var ligne = bouton.closest('.lbp-envoi-emballage');
        if (liste.querySelectorAll('.lbp-envoi-emballage').length > 1) {
            ligne.remove();
        } else {
            ligne.querySelectorAll('input, select').forEach(function (champ) { champ.value = ''; });
        }
    });
}

var type = document.getElementById('depot-type');
if (type) {
    var appliquer = function () {
        var option = type.options[type.selectedIndex];
        var facture = !!(option && option.dataset.facture);
        document.querySelectorAll('[data-facture-champ]').forEach(function (champ) { champ.hidden = !facture; });
        var montant = document.getElementById('depot-montant');
        if (montant) { montant.required = facture; }
    };
    type.addEventListener('change', appliquer);
    appliquer();
}

var periode = document.getElementById('historique-periode');
['historique-du', 'historique-au'].forEach(function (id) {
    var champ = document.getElementById(id);
    if (champ && periode) { champ.addEventListener('change', function () { periode.value = 'libre'; }); }
});
})();</script>
HTML;
    }
}
