<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Services\Colisage\DossierEnvoiRegles as Regles;

/**
 * Écrans des dossiers d'envoi : liste, saisie, fiche, validation, pièces
 * manquantes, historique et prestataires.
 *
 * Toute donnée venue de la base passe par View::e(). Les repères « LBP 1 » à
 * « LBP 10 » signalent les dix colonnes prioritaires du cahier des charges.
 */
final class ColisageEnvois
{
    /** @var array<string, string> */
    public const TONS_STATUT = [
        'BROUILLON' => 'neutral',
        'RESERVE' => 'info',
        'PARTI' => 'info',
        'ARRIVE' => 'info',
        'LIVRE' => 'success',
        'SOUMIS' => 'warning',
        'A_CORRIGER' => 'danger',
        'VALIDE' => 'success',
        'ANNULE' => 'neutral',
        'REPRIS' => 'neutral',
    ];

    public const ACTIONS_JOURNAL = [
        'CREATION' => 'Création',
        'MODIFICATION' => 'Modification',
        'DOCUMENT_AJOUT' => 'Pièce jointe',
        'DOCUMENT_RETRAIT' => 'Pièce retirée',
        'FACTURE' => 'Montant facturé',
        'SOUMISSION' => 'Soumission',
        'VALIDATION' => 'Validation',
        'RENVOI' => 'Renvoi pour correction',
        'REOUVERTURE' => 'Réouverture',
        'REAFFECTATION' => 'Réaffectation',
        'ANNULATION' => 'Annulation',
    ];

    /** Colonne prioritaire LBP portée par chaque poste de frais. */
    private const LBP_DU_POSTE = [
        'TRANSIT_DEPART' => '6',
        'TRANSIT_ARRIVEE' => '7',
        'LIVRAISON_DEPART' => '8',
        'LIVRAISON_ARRIVEE' => '9',
    ];

    // ------------------------------------------------------------------
    // Liste
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function listePage(array $p): string
    {
        $voitTout = !empty($p['voit_tout']);
        $filtres = $p['filtres'] ?? [];

        $actions = [];
        if (!empty($p['peut_creer'])) {
            $actions[] = Ui::button('Nouveau dossier', ['href' => 'colisage/envois/nouveau', 'variant' => 'primary']);
        }
        $actions[] = Ui::button('Historique et exports', ['href' => 'colisage/envois/historique', 'variant' => 'secondary']);
        $actions[] = Ui::button('Préparer un départ', ['href' => 'colisage/departs', 'variant' => 'secondary']);

        $html = Ui::pageHeader(
            $voitTout ? "Dossiers d'envoi" : "Mes dossiers d'envoi",
            'Un dossier par document de transport : transporteur, frais, emballages et pièces reçues. Il est soumis au Directeur général une fois la marchandise livrée.',
            ['eyebrow' => "Dossiers d'envoi", 'class' => 'rh-hero-white', 'actions' => $actions]
        );

        $statuts = [['value' => 'en_cours', 'label' => 'En cours'], ['value' => 'tous', 'label' => 'Tous']];
        foreach (Regles::STATUTS as $valeur => $libelle) {
            $statuts[] = ['value' => $valeur, 'label' => $libelle];
        }

        $champs = self::filtre('Statut', Form::rawSelect('statut', $statuts, (string) ($filtres['statut'] ?? 'en_cours'), ['id' => 'envois-statut']), 'envois-statut')
            . self::filtre('Mode', Form::rawSelect('mode', self::optionsModes('Tous les modes'), (string) ($filtres['mode'] ?? ''), ['id' => 'envois-mode']), 'envois-mode');

        if ($voitTout) {
            $champs .= self::filtre('Responsable', Form::rawSelect('responsable', self::optionsResponsables($p['responsables'] ?? []), (string) ($filtres['responsable'] ?? ''), ['id' => 'envois-responsable']), 'envois-responsable');
        }

        $champs .= self::filtre('Recherche', Form::rawInput('q', (string) ($filtres['q'] ?? ''), ['id' => 'envois-q', 'placeholder' => 'Dossier, document, conteneur ou colis']), 'envois-q');

        $html .= self::formulaireFiltre('colisage/envois', $champs);

        $colonnes = [
            ['label' => 'Dossier'], ['label' => 'Départ'], ['label' => 'Trajet'], ['label' => 'Transport'],
            ['label' => 'Colis', 'align' => 'right'], ['label' => 'Coût', 'align' => 'right'], ['label' => 'Pièces'],
        ];
        if ($voitTout) {
            $colonnes[] = ['label' => 'Responsable'];
        }
        $colonnes[] = ['label' => 'Statut'];

        $lignes = [];
        foreach (($p['dossiers'] ?? []) as $d) {
            $s = $d['synthese'];
            $ligne = [
                self::lienDossier($d) . '<span class="lbp-envoi-sous">' . View::e(Regles::MODES[(string) $d['mode_transport']] ?? (string) $d['mode_transport']) . '</span>',
                View::e(self::date($d['date_reference'] ?? null)),
                self::trajet($d),
                self::transport($d),
                self::colis($d, $s),
                self::montant((float) $s['cout_retenu_xof'] > 0 ? (float) $s['cout_retenu_xof'] : null),
                self::pieces($s),
            ];
            if ($voitTout) {
                $ligne[] = View::e((string) ($d['responsable'] ?? '—'));
            }
            $ligne[] = self::badgeStatut((string) $d['statut']);
            $lignes[] = $ligne;
        }

        $html .= Ui::section(
            'Dossiers',
            ModuleTable::render(
                $colonnes,
                $lignes,
                'Aucun dossier',
                !empty($p['peut_creer'])
                    ? 'Ouvrez un dossier avec « Nouveau dossier », ou depuis un départ du pointage.'
                    : 'Aucun dossier ne correspond à ces filtres.'
            ),
            count($lignes) . ' dossier(s)'
        );

        $departs = $p['departs_sans_dossier'] ?? [];
        if ($departs !== []) {
            $lignesDeparts = [];
            foreach ($departs as $depart) {
                $lignesDeparts[] = [
                    '<strong>' . View::e((string) $depart['reference']) . '</strong>',
                    View::e(self::date($depart['date_depart'] ?? null)),
                    View::e((string) ($depart['agence_depart'] ?? '—')) . ' → ' . View::e((string) ($depart['agence_arrivee'] ?? '—')),
                    (string) (int) ($depart['nb_colis'] ?? 0),
                    !empty($p['peut_creer'])
                        ? Ui::button('Ouvrir le dossier', ['href' => 'colisage/envois/nouveau?depart=' . (int) $depart['id'], 'variant' => 'secondary'])
                        : Ui::badge('Sans dossier', 'warning'),
                ];
            }

            $html .= Ui::section(
                'Départs du pointage sans dossier',
                ModuleTable::render(
                    [['label' => 'Départ'], ['label' => 'Parti le'], ['label' => 'Trajet'], ['label' => 'Colis', 'align' => 'right'], ['label' => '']],
                    $lignesDeparts
                ),
                'Chaque départ doit avoir son dossier, pour que son transport et ses frais soient suivis'
            );
        }

        return self::envelopper($html);
    }

    // ------------------------------------------------------------------
    // Saisie
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function formulairePage(array $p): string
    {
        $d = $p['dossier'] ?? [];
        $id = isset($p['id']) && $p['id'] !== null ? (int) $p['id'] : null;
        $mode = isset(Regles::MODES[(string) ($d['mode_transport'] ?? '')]) ? (string) $d['mode_transport'] : 'AERIEN';
        $prestataires = $p['prestataires'] ?? [];
        $retour = $id === null ? 'colisage/envois' : 'colisage/envois/' . $id;

        $html = Ui::pageHeader(
            $id === null ? "Nouveau dossier d'envoi" : 'Modifier le dossier ' . (string) ($d['numero'] ?? ''),
            $id === null
                ? "Le numéro est attribué à l'enregistrement. Le dossier se complète au fil de l'envoi : seule l'agence de départ est exigée tout de suite."
                : 'Chaque modification est inscrite au journal du dossier.',
            ['eyebrow' => "Dossiers d'envoi", 'class' => 'rh-hero-white', 'actions' => [Ui::button('Retour', ['href' => $retour, 'variant' => 'secondary'])]]
        );

        if (!empty($p['erreurs'])) {
            $html .= self::erreurs("Le dossier n'a pas été enregistré. À corriger :", $p['erreurs']);
        }
        if (($d['statut'] ?? '') === 'A_CORRIGER' && !empty($d['motif_renvoi'])) {
            $html .= self::alerte('Renvoyé pour correction par le Directeur général', (string) $d['motif_renvoi']);
        }

        // Identification
        $identification = '<div class="lbp-envoi-grille">'
            . self::champ('Mode de transport', Form::rawSelect('mode_transport', self::optionsModes(null), $mode, ['id' => 'envoi-mode']), 'envoi-mode')
            . self::champ("Agence de départ", Form::rawSelect('agence_depart_id', self::optionsAgences($p['agences'] ?? [], 'Choisir'), (string) ($d['agence_depart_id'] ?? ''), ['id' => 'envoi-agence-depart', 'required' => true]), 'envoi-agence-depart', 'Donne son code au numéro de dossier (ABJ, PAR, DKR…).')
            . self::champ("Agence d'arrivée", Form::rawSelect('agence_arrivee_id', self::optionsAgences($p['agences'] ?? [], 'Hors réseau LBP'), (string) ($d['agence_arrivee_id'] ?? ''), ['id' => 'envoi-agence-arrivee']), 'envoi-agence-arrivee')
            . self::champ('Destination', Form::rawInput('destination', (string) ($d['destination'] ?? ''), ['id' => 'envoi-destination', 'maxlength' => '150']), 'envoi-destination', "Ville ou pays, si l'arrivée n'est pas une agence LBP.")
            . self::champ('Départ du pointage', Form::rawSelect('expedition_id', self::optionsDeparts($p['departs'] ?? []), (string) ($d['expedition_id'] ?? ''), ['id' => 'envoi-expedition']), 'envoi-expedition', 'Les colis pointés au départ seront comparés au nombre de colis du document.')
            . self::champ('Date de départ prévue', Form::rawInput('date_depart_prevue', (string) ($d['date_depart_prevue'] ?? ''), ['type' => 'date', 'id' => 'envoi-depart-prevu']), 'envoi-depart-prevu')
            . '</div>';

        // Transport
        $typesDocument = [];
        foreach (Regles::DOCUMENTS_DU_MODE as $modeDocument => $types) {
            foreach ($types as $valeur => $libelle) {
                $typesDocument[] = ['value' => $valeur, 'label' => $libelle, 'attrs' => ['data-mode' => $modeDocument]];
            }
        }

        $transport = '<div class="lbp-envoi-grille">'
            . self::champ('Transporteur', self::selectPrestataires('transporteur_id', $prestataires, $d['transporteur_id'] ?? null, 'envoi-transporteur', 'Choisir', array_values(Regles::TRANSPORTEUR_DU_MODE)), 'envoi-transporteur', 'Compagnie aérienne ou maritime, transporteur routier ou DHL.', '2')
            . self::champ('Type de document', Form::rawSelect('type_document', $typesDocument, (string) ($d['type_document'] ?? ''), ['id' => 'envoi-type-document']), 'envoi-type-document')
            . self::champ('Numéro du document', Form::rawInput('numero_document', (string) ($d['numero_document'] ?? ''), ['id' => 'envoi-numero-document', 'maxlength' => '60', 'class' => 'lbp-envoi-mono', 'autocomplete' => 'off']), 'envoi-numero-document', 'LTA de compagnie : 11 chiffres, dont le dernier est contrôlé (057-30215463).', '3')
            . self::champ('Transitaire émetteur', self::selectPrestataires('emetteur_document_id', $prestataires, $d['emetteur_document_id'] ?? null, 'envoi-emetteur', 'Choisir', ['TRANSITAIRE']), 'envoi-emetteur', 'Celui qui a remis la LTA fille ou le BL fils.', '', ['data-fils' => '1'])
            . self::champ('Document principal', Form::rawInput('document_principal', (string) ($d['document_principal'] ?? ''), ['id' => 'envoi-document-principal', 'maxlength' => '60', 'class' => 'lbp-envoi-mono', 'autocomplete' => 'off']), 'envoi-document-principal', 'LTA principale (MAWB) ou BL principal du groupage, facultatif.', '', ['data-fils' => '1'])
            . self::champ('Lieu de départ', Form::rawInput('lieu_depart', (string) ($d['lieu_depart'] ?? ''), ['id' => 'envoi-lieu-depart', 'maxlength' => '100', 'placeholder' => 'ABJ, CIABJ…']), 'envoi-lieu-depart', 'Aéroport (code IATA), port ou ville.')
            . self::champ("Lieu d'arrivée", Form::rawInput('lieu_arrivee', (string) ($d['lieu_arrivee'] ?? ''), ['id' => 'envoi-lieu-arrivee', 'maxlength' => '100', 'placeholder' => 'CDG, FRLEH…']), 'envoi-lieu-arrivee')
            . self::champ('Date de départ effective', Form::rawInput('date_depart_effective', (string) ($d['date_depart_effective'] ?? ''), ['type' => 'date', 'id' => 'envoi-depart-effectif']), 'envoi-depart-effectif', 'Le dossier passe « parti ».', '1')
            . self::champ("Date d'arrivée estimée", Form::rawInput('date_arrivee_estimee', (string) ($d['date_arrivee_estimee'] ?? ''), ['type' => 'date', 'id' => 'envoi-arrivee-estimee']), 'envoi-arrivee-estimee')
            . self::champ("Date d'arrivée", Form::rawInput('date_arrivee', (string) ($d['date_arrivee'] ?? ''), ['type' => 'date', 'id' => 'envoi-arrivee']), 'envoi-arrivee', 'Le dossier passe « arrivé ».')
            . self::champ('Date de livraison', Form::rawInput('date_livraison', (string) ($d['date_livraison'] ?? ''), ['type' => 'date', 'id' => 'envoi-livraison']), 'envoi-livraison', 'Une fois livré, le dossier peut être soumis.')
            . '</div>';

        // Tranches
        $tranches = array_values($p['tranches'] ?? []);
        $conteneurs = [['value' => '', 'label' => '—']];
        foreach (Regles::TYPES_CONTENEUR as $valeur => $libelle) {
            $conteneurs[] = ['value' => $valeur, 'label' => $libelle];
        }

        $lignesTranches = '';
        for ($i = 0, $n = max(count($tranches) + 1, 2); $i < $n; $i++) {
            $t = $tranches[$i] ?? [];
            $nom = 'tranches[' . $i . ']';
            $rang = ' ' . ($i + 1);
            $lignesTranches .= '<tr>'
                . '<td>' . Form::rawInput($nom . '[reference]', (string) ($t['reference'] ?? ''), ['class' => 'lbp-envoi-mono', 'maxlength' => '60', 'aria-label' => 'Référence de la tranche' . $rang]) . '</td>'
                . '<td data-modes="MARITIME">' . Form::rawSelect($nom . '[type_conteneur]', $conteneurs, (string) ($t['type_conteneur'] ?? ''), ['aria-label' => 'Type de conteneur' . $rang]) . '</td>'
                . '<td data-modes="ROUTIER">' . Form::rawInput($nom . '[chauffeur]', (string) ($t['chauffeur'] ?? ''), ['maxlength' => '120', 'aria-label' => 'Chauffeur' . $rang]) . '</td>'
                . '<td>' . Form::rawInput($nom . '[date_depart]', (string) ($t['date_depart'] ?? ''), ['type' => 'date', 'aria-label' => 'Départ de la tranche' . $rang]) . '</td>'
                . '<td>' . Form::rawInput($nom . '[date_arrivee]', (string) ($t['date_arrivee'] ?? ''), ['type' => 'date', 'aria-label' => 'Arrivée de la tranche' . $rang]) . '</td>'
                . '<td>' . Form::rawInput($nom . '[nb_colis]', self::saisieNombre($t['nb_colis'] ?? null), ['inputmode' => 'numeric', 'aria-label' => 'Colis de la tranche' . $rang]) . '</td>'
                . '<td>' . Form::rawInput($nom . '[poids_kg]', self::saisieNombre($t['poids_kg'] ?? null), ['inputmode' => 'decimal', 'aria-label' => 'Poids de la tranche' . $rang]) . '</td>'
                . '</tr>';
        }

        $tableTranches = '<div class="finea-table-wrapper"><table class="finea-table lbp-envoi-saisie"><thead><tr>'
            . '<th>Vol, conteneur ou véhicule</th><th data-modes="MARITIME">Type</th><th data-modes="ROUTIER">Chauffeur</th>'
            . '<th>Départ</th><th>Arrivée</th><th>Colis</th><th>Poids (kg)</th>'
            . '</tr></thead><tbody>' . $lignesTranches . '</tbody></table></div>';

        // Colis et emballages
        $emballages = array_values($p['emballages'] ?? []);
        $typesEmballage = [['value' => '', 'label' => 'Choisir']];
        foreach (Regles::EMBALLAGES as $type) {
            $typesEmballage[] = ['value' => $type, 'label' => $type];
        }

        $lignesEmballages = '';
        for ($i = 0, $n = max(count($emballages) + 2, 3); $i < $n; $i++) {
            $e = $emballages[$i] ?? [];
            $lignesEmballages .= '<tr>'
                . '<td>' . Form::rawSelect('emballages[' . $i . '][type]', $typesEmballage, (string) ($e['type'] ?? ''), ['aria-label' => "Type d'emballage " . ($i + 1)]) . '</td>'
                . '<td>' . Form::rawInput('emballages[' . $i . '][quantite]', self::saisieNombre($e['quantite'] ?? null), ['inputmode' => 'numeric', 'aria-label' => "Nombre d'emballages " . ($i + 1)]) . '</td>'
                . '</tr>';
        }

        $colis = '<div class="lbp-envoi-grille">'
            . self::champ('Nombre de colis', Form::rawInput('nb_colis_declare', self::saisieNombre($d['nb_colis_declare'] ?? null), ['id' => 'envoi-nb-colis', 'inputmode' => 'numeric']), 'envoi-nb-colis', 'Tel que déclaré sur le document de transport.', '4')
            . self::champ('Poids brut (kg)', Form::rawInput('poids_brut_kg', self::saisieNombre($d['poids_brut_kg'] ?? null), ['id' => 'envoi-poids-brut', 'inputmode' => 'decimal']), 'envoi-poids-brut', '', '5')
            . self::champ('Poids taxable (kg)', Form::rawInput('poids_taxable_kg', self::saisieNombre($d['poids_taxable_kg'] ?? null), ['id' => 'envoi-poids-taxable', 'inputmode' => 'decimal']), 'envoi-poids-taxable', 'Poids facturé par le transporteur, jamais inférieur au brut.')
            . self::champ('Volume (m³)', Form::rawInput('volume_m3', self::saisieNombre($d['volume_m3'] ?? null), ['id' => 'envoi-volume', 'inputmode' => 'decimal']), 'envoi-volume', 'Base de facturation en maritime.')
            . '</div>'
            . '<h3 class="lbp-envoi-sous-titre">Type et nombre d\'emballages <span class="lbp-envoi-lbp">LBP 10</span></h3>'
            . '<div class="finea-table-wrapper lbp-envoi-etroit"><table class="finea-table lbp-envoi-saisie"><thead><tr><th>Type</th><th>Nombre</th></tr></thead><tbody>'
            . $lignesEmballages . '</tbody></table></div>'
            . self::champ("Commentaire d'écart", '<textarea class="finea-input finea-textarea" name="commentaire_ecart" id="envoi-commentaire" rows="2">' . View::e((string) ($d['commentaire_ecart'] ?? '')) . '</textarea>', 'envoi-commentaire', 'Exigé pour soumettre si le nombre de colis diffère du pointage.');

        // Frais
        $parPoste = [];
        $autres = [];
        foreach (($p['frais'] ?? []) as $ligne) {
            if (($ligne['poste'] ?? '') === Regles::POSTE_AUTRE) {
                $autres[] = $ligne;
            } else {
                $parPoste[(string) $ligne['poste']] ??= $ligne;
            }
        }

        $lignesFrais = '';
        foreach (Regles::POSTES as $poste => $libelle) {
            $f = $parPoste[$poste] ?? [];
            $nom = 'frais[' . $poste . ']';
            $cle = 'frais-' . strtolower($poste);
            $typesEnTete = match ($poste) {
                'FRET' => array_values(Regles::TRANSPORTEUR_DU_MODE),
                'TRANSIT_DEPART', 'TRANSIT_ARRIVEE' => ['TRANSITAIRE'],
                default => ['LIVREUR', 'TRANSITAIRE'],
            };

            $facture = ($f['montant_facture'] ?? null) !== null
                ? View::e(self::montantBrut((float) $f['montant_facture'], (string) ($f['devise_facture'] ?? $f['devise'] ?? 'XOF')))
                    . (!empty($f['numero_facture']) ? '<span class="lbp-envoi-sous">n° ' . View::e((string) $f['numero_facture']) . '</span>' : '')
                : '<span class="lbp-envoi-sous">Au dépôt de la facture</span>';

            $lignesFrais .= '<tr>'
                . '<th scope="row">' . View::e($libelle) . ' '
                . (isset(self::LBP_DU_POSTE[$poste]) ? '<span class="lbp-envoi-lbp">LBP ' . self::LBP_DU_POSTE[$poste] . '</span>' : '<span class="lbp-envoi-ajout">ajout</span>') . '</th>'
                . '<td>' . self::selectPrestataires($nom . '[prestataire_id]', $prestataires, $f['prestataire_id'] ?? null, $cle . '-prestataire', '—', $typesEnTete, $libelle . ' : prestataire')
                . Form::rawInput($nom . '[prestataire_libre]', (string) ($f['prestataire_libre'] ?? ''), ['class' => 'lbp-envoi-libre', 'maxlength' => '150', 'placeholder' => 'ou nom libre (livreur interne…)', 'aria-label' => $libelle . ' : nom libre']) . '</td>'
                . '<td>' . Form::rawInput($nom . '[montant_prevu]', self::saisieNombre($f['montant_prevu'] ?? null), ['inputmode' => 'decimal', 'aria-label' => $libelle . ' : montant prévu']) . '</td>'
                . '<td>' . Form::rawSelect($nom . '[devise]', self::optionsDevises(), (string) ($f['devise'] ?? 'XOF'), ['aria-label' => $libelle . ' : devise']) . '</td>'
                . '<td class="lbp-envoi-centre"><input type="checkbox" name="' . View::e($nom . '[sans_frais]') . '" value="1"' . (!empty($f['sans_frais']) ? ' checked' : '') . ' aria-label="' . View::e($libelle . ' : sans frais') . '"></td>'
                . '<td>' . $facture . '</td>'
                . '<td>' . Form::rawInput($nom . '[commentaire_ecart]', (string) ($f['commentaire_ecart'] ?? ''), ['maxlength' => '1000', 'aria-label' => $libelle . " : commentaire d'écart"]) . '</td>'
                . '</tr>';
        }

        $lignesAutres = '';
        for ($i = 0, $n = max(count($autres) + 1, 2); $i < $n; $i++) {
            $f = $autres[$i] ?? [];
            $nom = 'autres[' . $i . ']';
            $rang = ' ' . ($i + 1);
            $lignesAutres .= '<tr>'
                . '<td>' . Form::rawInput($nom . '[libelle]', (string) ($f['libelle'] ?? ''), ['maxlength' => '150', 'placeholder' => 'Douane, manutention…', 'aria-label' => 'Libellé du frais' . $rang]) . '</td>'
                . '<td>' . self::selectPrestataires($nom . '[prestataire_id]', $prestataires, $f['prestataire_id'] ?? null, 'autre-' . $i . '-prestataire', '—', [], 'Prestataire du frais' . $rang) . '</td>'
                . '<td>' . Form::rawInput($nom . '[montant_prevu]', self::saisieNombre($f['montant_prevu'] ?? null), ['inputmode' => 'decimal', 'aria-label' => 'Montant prévu' . $rang]) . '</td>'
                . '<td>' . Form::rawInput($nom . '[montant_facture]', self::saisieNombre($f['montant_facture'] ?? null), ['inputmode' => 'decimal', 'aria-label' => 'Montant facturé' . $rang]) . '</td>'
                . '<td>' . Form::rawInput($nom . '[numero_facture]', (string) ($f['numero_facture'] ?? ''), ['maxlength' => '60', 'aria-label' => 'Numéro de facture' . $rang]) . '</td>'
                . '<td>' . Form::rawSelect($nom . '[devise]', self::optionsDevises(), (string) ($f['devise'] ?? 'XOF'), ['aria-label' => 'Devise' . $rang]) . '</td>'
                . '<td>' . Form::rawInput($nom . '[commentaire_ecart]', (string) ($f['commentaire_ecart'] ?? ''), ['maxlength' => '1000', 'aria-label' => "Commentaire d'écart" . $rang]) . '</td>'
                . '</tr>';
        }

        $frais = '<div class="finea-table-wrapper"><table class="finea-table lbp-envoi-saisie"><thead><tr>'
            . '<th>Poste</th><th>Prestataire</th><th>Montant prévu</th><th>Devise</th><th>Sans frais</th><th>Facturé</th><th>Commentaire d\'écart</th>'
            . '</tr></thead><tbody>' . $lignesFrais . '</tbody></table></div>'
            . '<h3 class="lbp-envoi-sous-titre">Autres frais</h3>'
            . '<div class="finea-table-wrapper"><table class="finea-table lbp-envoi-saisie"><thead><tr>'
            . '<th>Libellé</th><th>Prestataire</th><th>Prévu</th><th>Facturé</th><th>N° facture</th><th>Devise</th><th>Commentaire d\'écart</th>'
            . '</tr></thead><tbody>' . $lignesAutres . '</tbody></table></div>';

        $action = $id === null ? 'colisage/envois/enregistrer' : 'colisage/envois/' . $id . '/modifier';

        $html .= '<form method="post" action="' . View::e(View::url($action)) . '" id="lbp-envoi-form" novalidate>'
            . Form::hidden('_csrf_token', Csrf::token())
            . Ui::section('Identification', $identification)
            . Ui::section('Transport', $transport)
            . Ui::section('Tranches', $tableTranches, 'Une ligne par vol, conteneur ou véhicule. Laissez vide si tout part d\'un bloc')
            . Ui::section('Colis et emballages', $colis)
            . Ui::section('Frais', $frais, 'Le montant facturé se saisit en joignant la facture, sur la fiche du dossier')
            . '<div class="lbp-envoi-barre">'
            . Ui::button('Annuler', ['href' => $retour, 'variant' => 'secondary'])
            . Ui::button('Enregistrer le dossier', ['type' => 'submit', 'variant' => 'primary'])
            . '</div>'
            . '</form>';

        return self::envelopper($html) . self::scriptFormulaire();
    }

    // ------------------------------------------------------------------
    // Fiche
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function fichePage(array $p): string
    {
        $d = $p['dossier'];
        $s = $p['synthese'];
        $droits = $p['droits'] ?? [];
        $id = (int) $d['id'];
        $mode = (string) $d['mode_transport'];

        $actions = [];
        if (!empty($droits['modifier'])) {
            $actions[] = Ui::button('Modifier', ['href' => 'colisage/envois/' . $id . '/modifier', 'variant' => 'primary']);
        }
        $actions[] = Ui::button('Fiche PDF', ['href' => 'colisage/envois/' . $id . '/pdf', 'variant' => 'secondary', 'target' => '_blank']);
        $actions[] = Ui::button('Tous les dossiers', ['href' => 'colisage/envois', 'variant' => 'secondary']);

        $html = Ui::pageHeader(
            'Dossier ' . (string) $d['numero'],
            (Regles::MODES[$mode] ?? $mode) . ' · ' . (string) ($d['agence_depart'] ?? '—') . ' → '
                . (string) ($d['agence_arrivee'] ?? $d['destination'] ?? '—') . ' · responsable : ' . (string) ($d['responsable'] ?? '—'),
            ['eyebrow' => "Dossier d'envoi", 'class' => 'rh-hero-white', 'badge' => self::badgeStatut((string) $d['statut']), 'actions' => $actions]
        );

        if ($d['statut'] === 'A_CORRIGER' && !empty($d['motif_renvoi'])) {
            $html .= self::alerte('Renvoyé pour correction par le Directeur général', (string) $d['motif_renvoi']);
        }
        if ($d['statut'] === 'ANNULE' && !empty($d['motif_annulation'])) {
            $html .= self::alerte('Dossier annulé', (string) $d['motif_annulation']);
        }

        $ecartColis = $s['ecart_colis'];
        $html .= '<div class="lbp-envoi-kpis">'
            . self::kpi('Colis document / pointés', self::texteOuTiret($d['nb_colis_declare'] ?? null) . ' / ' . self::texteOuTiret($s['colis_pointes']), $ecartColis !== null && $ecartColis !== 0)
            . self::kpi('Poids brut / taxable', self::kg($d['poids_brut_kg'] ?? null) . ' / ' . self::kg($d['poids_taxable_kg'] ?? null))
            . self::kpi('Coût retenu', (float) $s['cout_retenu_xof'] > 0 ? Regles::nombre((float) $s['cout_retenu_xof']) . ' XOF' : '—')
            . self::kpi('Coût par kg', $s['cout_par_kg'] !== null ? Regles::nombre((float) $s['cout_par_kg']) . ' XOF' : '—')
            . self::kpi('Écart factures', self::signe((float) $s['ecart_facture_xof']) . ' XOF', (bool) $s['ecart_facture_depasse'])
            . self::kpi('Pièces', (int) $s['pieces_presentes'] . ' / ' . (int) $s['pieces_attendues'], (int) $s['pieces_presentes'] < (int) $s['pieces_attendues'])
            . '</div>';

        $html .= self::circuit($p);

        // Transport
        $typeDocument = Regles::DOCUMENTS_DU_MODE[$mode][(string) ($d['type_document'] ?? '')] ?? Regles::libelleDocument($mode);
        $depart = !empty($d['expedition_id'])
            ? '<a href="' . View::e(View::url('colisage/departs/' . (int) $d['expedition_id'])) . '">' . View::e((string) ($d['expedition_reference'] ?? 'Départ')) . '</a>'
            : '—';

        $html .= Ui::section('Transport', self::kv([
            [Regles::libelleTransporteur($mode), self::valeur($d['transporteur'] ?? null)],
            [$typeDocument, '<span class="lbp-envoi-mono">' . self::valeur($d['numero_document'] ?? null) . '</span>'],
            ['Transitaire émetteur', self::valeur($d['emetteur_document'] ?? null)],
            ['Document principal', '<span class="lbp-envoi-mono">' . self::valeur($d['document_principal'] ?? null) . '</span>'],
            ['Départ du pointage', $depart],
            ['Lieux', self::valeur(implode(' → ', array_filter([(string) ($d['lieu_depart'] ?? ''), (string) ($d['lieu_arrivee'] ?? '')], 'strlen')))],
            ['Départ prévu', View::e(self::date($d['date_depart_prevue'] ?? null))],
            ['Départ effectif', View::e(self::date($d['date_depart_effective'] ?? null))],
            ['Arrivée estimée', View::e(self::date($d['date_arrivee_estimee'] ?? null))],
            ['Arrivée', View::e(self::date($d['date_arrivee'] ?? null))],
            ['Livraison', View::e(self::date($d['date_livraison'] ?? null))],
            ['Destination', self::valeur($d['destination'] ?? null)],
        ]));

        // Tranches
        $tranches = $p['tranches'] ?? [];
        if ($tranches !== []) {
            $lignes = [];
            foreach ($tranches as $t) {
                $lignes[] = [
                    View::e(Regles::LIBELLES_TRANCHE[(string) $t['type']] ?? (string) $t['type']) . ' ' . (int) $t['rang'],
                    '<span class="lbp-envoi-mono">' . self::valeur($t['reference'] ?? null) . '</span>',
                    self::valeur(Regles::TYPES_CONTENEUR[(string) ($t['type_conteneur'] ?? '')] ?? ($t['chauffeur'] ?? null)),
                    View::e(self::date($t['date_depart'] ?? null)),
                    View::e(self::date($t['date_arrivee'] ?? null)),
                    self::texteOuTiret($t['nb_colis'] ?? null),
                    View::e(self::kg($t['poids_kg'] ?? null)),
                ];
            }
            $html .= Ui::section('Tranches', ModuleTable::render([
                ['label' => 'Tranche'], ['label' => 'Référence'], ['label' => 'Conteneur ou chauffeur'], ['label' => 'Départ'],
                ['label' => 'Arrivée'], ['label' => 'Colis', 'align' => 'right'], ['label' => 'Poids', 'align' => 'right'],
            ], $lignes));
        }

        // Colis et emballages
        $html .= Ui::section('Colis et emballages', self::kv([
            ['Nombre de colis (document)', self::texteOuTiret($d['nb_colis_declare'] ?? null)],
            ['Colis pointés au départ', $s['colis_pointes'] === null ? 'Aucun départ rattaché' : (string) (int) $s['colis_pointes']
                . ($ecartColis ? ' ' . Ui::badge('écart de ' . abs((int) $ecartColis), 'danger') : '')],
            ['Poids brut', View::e(self::kg($d['poids_brut_kg'] ?? null))],
            ['Poids taxable', View::e(self::kg($d['poids_taxable_kg'] ?? null))],
            ['Volume', View::e(($d['volume_m3'] ?? null) !== null ? Regles::nombre((float) $d['volume_m3'], 2) . ' m³' : '—')],
            ['Emballages', self::valeur($s['emballages_texte'] !== '' ? $s['emballages_texte'] : null)],
            ["Commentaire d'écart", self::valeur($d['commentaire_ecart'] ?? null)],
        ]));

        $html .= self::sectionFrais($p);
        $html .= self::sectionDocuments($p);

        // Journal
        $journal = [];
        foreach (($p['journal'] ?? []) as $j) {
            $journal[] = [
                View::e(self::date($j['created_at'] ?? null, 'd/m/Y H:i')),
                View::e(self::ACTIONS_JOURNAL[(string) $j['action']] ?? (string) $j['action']),
                self::valeur($j['champ'] ?? null),
                self::valeur($j['ancienne_valeur'] ?? null),
                self::valeur($j['nouvelle_valeur'] ?? null),
                self::valeur($j['motif'] ?? null),
                self::valeur($j['par'] ?? null),
            ];
        }
        $html .= Ui::section('Journal', ModuleTable::render([
            ['label' => 'Quand'], ['label' => 'Geste'], ['label' => 'Champ'], ['label' => 'Avant'], ['label' => 'Après'], ['label' => 'Motif'], ['label' => 'Par'],
        ], $journal, 'Aucun geste enregistré'));

        return self::envelopper($html) . self::scriptFiche();
    }

    /** @param array<string, mixed> $p */
    private static function circuit(array $p): string
    {
        $d = $p['dossier'];
        $droits = $p['droits'] ?? [];
        $id = (int) $d['id'];
        $manques = $p['manques'] ?? [];
        $corps = '';

        $infos = [];
        if (!empty($d['soumis_le'])) {
            $infos[] = 'Soumis le ' . self::date($d['soumis_le'], 'd/m/Y à H:i');
        }
        if (!empty($d['valide_le'])) {
            $infos[] = 'Validé le ' . self::date($d['valide_le'], 'd/m/Y à H:i') . ' par ' . (string) ($d['valide_par'] ?? '—');
        }
        if ($infos !== []) {
            $corps .= '<p class="lbp-envoi-note">' . View::e(implode(' · ', $infos)) . '</p>';
        }

        if (!empty($droits['modifier'])) {
            if ($manques !== []) {
                $items = '';
                foreach ($manques as $manque) {
                    $items .= '<li>' . View::e((string) $manque) . '</li>';
                }
                $corps .= '<div class="lbp-envoi-manques"><strong>Pour soumettre au Directeur général, il reste :</strong><ul>' . $items . '</ul></div>';
            }

            $corps .= !empty($droits['soumettre']) && $manques === []
                ? self::formAction($id, 'soumettre', 'Soumettre au Directeur général', 'primary', "Soumettre ce dossier ? Vous ne pourrez plus le modifier, sauf s'il vous est renvoyé.")
                : Ui::button('Soumettre au Directeur général', ['type' => 'button', 'variant' => 'primary', 'disabled' => true]);
        }

        if (!empty($droits['valider'])) {
            $corps .= self::formAction($id, 'valider', 'Valider le dossier', 'primary', 'Valider ce dossier ? Il sera verrouillé.');
            $corps .= self::formAction($id, 'renvoyer', "Renvoyer à l'agent export", 'secondary', '', self::motif('renvoyer', "Motif du renvoi, visible par l'agent export"));
        }

        if (!empty($droits['rouvrir'])) {
            $corps .= self::formAction($id, 'rouvrir', 'Rouvrir le dossier', 'secondary', "Rouvrir ce dossier validé ? L'agent export pourra le modifier.", self::motif('rouvrir', 'Motif de la réouverture'));
        }

        $responsables = $p['responsables'] ?? [];
        if (!empty($droits['reaffecter']) && $responsables !== []) {
            $options = [];
            foreach ($responsables as $r) {
                $options[] = ['value' => (string) $r['id'], 'label' => (string) $r['full_name']];
            }
            $corps .= self::formAction(
                $id,
                'reaffecter',
                'Confier le dossier',
                'secondary',
                '',
                '<label class="lbp-envoi-filtre-champ" for="envoi-reaffecter">Responsable '
                    . Form::rawSelect('responsable_id', $options, (string) ($d['responsable_id'] ?? ''), ['id' => 'envoi-reaffecter']) . '</label>'
            );
        }

        if (!empty($droits['annuler'])) {
            $corps .= '<details class="lbp-envoi-details"><summary>Annuler ce dossier</summary>'
                . self::formAction($id, 'annuler', "Confirmer l'annulation", 'danger', 'Annuler ce dossier ? Son numéro ne sera pas réutilisé.', self::motif('annuler', "Motif de l'annulation"))
                . '</details>';
        }

        if ($corps === '') {
            $corps = '<p class="lbp-envoi-note">' . View::e(match ((string) $d['statut']) {
                'VALIDE' => 'Dossier validé et verrouillé.',
                'SOUMIS' => 'En attente de la décision du Directeur général.',
                'ANNULE' => 'Dossier annulé.',
                default => "L'agent export complète encore ce dossier.",
            }) . '</p>';
        }

        return Ui::section('Circuit de validation', '<div class="lbp-envoi-circuit">' . $corps . '</div>', Regles::STATUTS[(string) $d['statut']] ?? '');
    }

    /** @param array<string, mixed> $p */
    private static function sectionFrais(array $p): string
    {
        $d = $p['dossier'];
        $s = $p['synthese'];
        $taux = (float) $s['taux'];

        $parPoste = [];
        $autres = [];
        foreach (($p['frais'] ?? []) as $ligne) {
            if (($ligne['poste'] ?? '') === Regles::POSTE_AUTRE) {
                $autres[] = $ligne;
            } else {
                $parPoste[(string) $ligne['poste']] ??= $ligne;
            }
        }

        $lignes = [];
        $ajouter = static function (string $libelle, array $f) use (&$lignes, $taux): void {
            $ecart = Regles::ecartFacture($f, $taux);
            $prevu = ($f['montant_prevu'] ?? null) !== null
                ? View::e(self::montantBrut((float) $f['montant_prevu'], (string) ($f['devise'] ?? 'XOF')))
                : (!empty($f['sans_frais']) ? 'Sans frais' : '—');
            $facture = ($f['montant_facture'] ?? null) !== null
                ? View::e(self::montantBrut((float) $f['montant_facture'], (string) ($f['devise_facture'] ?? $f['devise'] ?? 'XOF')))
                    . (!empty($f['numero_facture']) ? '<span class="lbp-envoi-sous">n° ' . View::e((string) $f['numero_facture']) . '</span>' : '')
                : '—';
            $cellEcart = $ecart === null
                ? '—'
                : Ui::badge(
                    self::signe($ecart['montant_xof']) . ' XOF' . ($ecart['pourcent'] !== null ? ' (' . self::signe($ecart['pourcent'], 1) . ' %)' : ''),
                    $ecart['depasse'] ? 'danger' : 'success'
                );

            $lignes[] = [
                View::e($libelle),
                self::valeur($f['prestataire'] ?? $f['prestataire_libre'] ?? null),
                $prevu,
                $facture,
                $cellEcart,
                self::valeur($f['commentaire_ecart'] ?? null),
            ];
        };

        foreach (Regles::POSTES as $poste => $libelle) {
            $ajouter($libelle, $parPoste[$poste] ?? []);
        }
        foreach ($autres as $f) {
            $ajouter((string) ($f['libelle'] ?? 'Autre frais'), $f);
        }

        $lignes[] = [
            '<strong>Total en XOF</strong>', '',
            '<strong>' . View::e(Regles::nombre((float) $s['cout_prevu_xof'])) . '</strong>',
            '<strong>' . View::e(Regles::nombre((float) $s['cout_facture_xof'])) . '</strong>',
            '<strong>' . View::e(self::signe((float) $s['ecart_facture_xof'])) . '</strong>',
            '<strong>Retenu : ' . View::e(Regles::nombre((float) $s['cout_retenu_xof'])) . '</strong>',
        ];

        return Ui::section('Frais', ModuleTable::render([
            ['label' => 'Poste'], ['label' => 'Prestataire'], ['label' => 'Prévu', 'align' => 'right'], ['label' => 'Facturé', 'align' => 'right'],
            ['label' => 'Écart'], ['label' => 'Commentaire'],
        ], $lignes), 'Taux EUR → XOF figé à ' . Regles::nombre((float) ($d['taux_eur_xof'] ?? $taux), 3) . ' · écart signalé au-delà de ' . Regles::nombre(Regles::SEUIL_ECART_FACTURE_POURCENT) . ' %');
    }

    /** @param array<string, mixed> $p */
    private static function sectionDocuments(array $p): string
    {
        $d = $p['dossier'];
        $id = (int) $d['id'];
        $documents = $p['documents'] ?? [];
        $peutModifier = !empty($p['droits']['modifier']);
        $presents = array_map('strval', array_column($documents, 'type_document'));

        $liste = '';
        foreach (Regles::piecesAttendues($p['frais'] ?? []) as $type) {
            $liste .= '<li>' . (in_array($type, $presents, true) ? Ui::badge('jointe', 'success') : Ui::badge('manquante', 'danger'))
                . ' ' . View::e(Regles::PIECES[$type]) . '</li>';
        }
        $html = '<p class="lbp-envoi-note">Pièces exigées pour soumettre :</p><ul class="lbp-envoi-checklist">' . $liste . '</ul>';

        if ($peutModifier) {
            $types = [['value' => '', 'label' => 'Choisir']];
            $facturables = array_values(Regles::PIECE_DU_POSTE);
            foreach (Regles::PIECES as $valeur => $libelle) {
                $types[] = ['value' => $valeur, 'label' => $libelle, 'attrs' => in_array($valeur, $facturables, true) ? ['data-facture' => '1'] : []];
            }

            $html .= '<form method="post" enctype="multipart/form-data" action="' . View::e(View::url('colisage/envois/' . $id . '/documents')) . '" class="lbp-envoi-depot" id="lbp-envoi-depot">'
                . Form::hidden('_csrf_token', Csrf::token())
                . '<div class="lbp-envoi-grille">'
                . self::champ('Type de document', Form::rawSelect('type_document', $types, '', ['id' => 'depot-type', 'required' => true]), 'depot-type')
                . self::champ('Fichier', '<input class="finea-input" type="file" name="fichier" id="depot-fichier" required accept=".pdf,.jpg,.jpeg,.png,.webp,.xls,.xlsx">', 'depot-fichier', 'PDF, image ou Excel, 10 Mo au maximum. Sur téléphone, une photo convient.')
                . self::champ('Montant facturé', Form::rawInput('montant_facture', '', ['id' => 'depot-montant', 'inputmode' => 'decimal']), 'depot-montant', 'Comparé au montant prévu du poste.', '', ['data-facture-champ' => '1'])
                . self::champ('Devise', Form::rawSelect('devise_facture', self::optionsDevises(), 'XOF', ['id' => 'depot-devise']), 'depot-devise', '', '', ['data-facture-champ' => '1'])
                . self::champ('N° de facture', Form::rawInput('numero_facture', '', ['id' => 'depot-numero', 'maxlength' => '60']), 'depot-numero', '', '', ['data-facture-champ' => '1'])
                . '</div>'
                . '<div class="lbp-envoi-action">' . Ui::button('Joindre la pièce', ['type' => 'submit', 'variant' => 'primary']) . '</div>'
                . '</form>';
        }

        $lignes = [];
        foreach ($documents as $doc) {
            $lien = '<a class="lbp-envoi-mono" href="' . View::e(View::url('colisage/envois/' . $id . '/documents/' . (int) $doc['id'])) . '" target="_blank" rel="noopener">'
                . View::e((string) $doc['nom_fichier']) . '</a>';
            $ligne = [
                View::e(Regles::PIECES[(string) $doc['type_document']] ?? (string) $doc['type_document']),
                $lien,
                View::e((string) $doc['original_name']),
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

        $colonnes = [['label' => 'Type'], ['label' => 'Fichier'], ['label' => "Nom d'origine"], ['label' => 'Taille', 'align' => 'right'], ['label' => 'Déposé']];
        if ($peutModifier) {
            $colonnes[] = ['label' => ''];
        }

        return Ui::section(
            'Pièces jointes',
            $html . ModuleTable::render($colonnes, $lignes, 'Aucune pièce jointe', $peutModifier ? 'Joignez la LTA, le manifeste et les factures reçues.' : ''),
            count($documents) . ' pièce(s)',
            ['id' => 'pieces']
        );
    }

    // ------------------------------------------------------------------
    // Validation et pièces manquantes
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function aValiderPage(array $p): string
    {
        $html = Ui::pageHeader(
            'Dossiers à valider',
            "Dossiers soumis par l'agent export, du plus ancien au plus récent. Vérifiez les écarts et les pièces avant de valider.",
            ['eyebrow' => "Dossiers d'envoi", 'class' => 'rh-hero-white', 'actions' => [
                Ui::button('Tous les dossiers', ['href' => 'colisage/envois', 'variant' => 'secondary']),
                Ui::button('Historique et exports', ['href' => 'colisage/envois/historique', 'variant' => 'secondary']),
            ]]
        );

        $lignes = [];
        foreach (($p['dossiers'] ?? []) as $d) {
            $s = $d['synthese'];
            $lignes[] = [
                self::lienDossier($d),
                View::e((string) ($d['responsable'] ?? '—')),
                View::e(self::date($d['soumis_le'] ?? null, 'd/m/Y H:i')),
                self::trajet($d),
                self::transport($d),
                self::colis($d, $s),
                self::montant((float) $s['cout_retenu_xof'] > 0 ? (float) $s['cout_retenu_xof'] : null),
                $s['ecart_facture_depasse'] ? Ui::badge(self::signe((float) $s['ecart_facture_xof']) . ' XOF', 'danger') : View::e(self::signe((float) $s['ecart_facture_xof'])),
                self::pieces($s),
            ];
        }

        $html .= Ui::section('En attente de validation', ModuleTable::render([
            ['label' => 'Dossier'], ['label' => 'Agent'], ['label' => 'Soumis le'], ['label' => 'Trajet'], ['label' => 'Transport'],
            ['label' => 'Colis', 'align' => 'right'], ['label' => 'Coût', 'align' => 'right'], ['label' => 'Écart factures'], ['label' => 'Pièces'],
        ], $lignes, 'Aucun dossier en attente', "L'agent export n'a soumis aucun dossier."), count($lignes) . ' dossier(s)');

        return self::envelopper($html);
    }

    /** @param array<string, mixed> $p */
    public static function piecesPage(array $p): string
    {
        $html = Ui::pageHeader(
            'Pièces manquantes',
            'Pièces exigées pour soumettre et pas encore jointes, sur les dossiers partis, arrivés, livrés ou à corriger.',
            ['eyebrow' => "Dossiers d'envoi", 'class' => 'rh-hero-white', 'actions' => [
                Ui::button('Tous les dossiers', ['href' => 'colisage/envois', 'variant' => 'secondary']),
            ]]
        );

        $lignes = [];
        foreach (($p['lignes'] ?? []) as $ligne) {
            $d = $ligne['dossier'];
            $lignes[] = [
                self::lienDossier($d),
                self::badgeStatut((string) $d['statut']),
                View::e((string) $ligne['libelle']),
                View::e(self::date($d['date_reference'] ?? null)),
                View::e((string) ($d['responsable'] ?? '—')),
                !empty($ligne['peut_deposer'])
                    ? Ui::button('Joindre', ['href' => 'colisage/envois/' . (int) $d['id'] . '#pieces', 'variant' => 'secondary'])
                    : '',
            ];
        }

        $html .= Ui::section('À joindre', ModuleTable::render([
            ['label' => 'Dossier'], ['label' => 'Statut'], ['label' => 'Pièce manquante'], ['label' => 'Départ'], ['label' => 'Responsable'], ['label' => ''],
        ], $lignes, 'Aucune pièce manquante', 'Tous les dossiers en cours ont leurs pièces.'), count($lignes) . ' pièce(s)');

        return self::envelopper($html);
    }

    // ------------------------------------------------------------------
    // Historique
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function historiquePage(array $p): string
    {
        $f = $p['filtres'];
        $voitTout = !empty($p['voit_tout']);
        $requete = self::requeteHistorique($f, $voitTout);

        $html = Ui::pageHeader(
            'Historique des envois',
            "Filtrez, puis exportez : le PDF et l'Excel reprennent exactement les dossiers affichés, pour les comparer aux vrais documents.",
            ['eyebrow' => "Dossiers d'envoi", 'class' => 'rh-hero-white', 'actions' => [
                Ui::button('Exporter en PDF', ['href' => 'colisage/envois/historique/pdf?' . $requete, 'variant' => 'primary', 'target' => '_blank']),
                Ui::button('Exporter en Excel', ['href' => 'colisage/envois/historique/excel?' . $requete, 'variant' => 'secondary']),
            ]]
        );

        $periodes = [];
        foreach (Regles::PERIODES as $valeur => $libelle) {
            $periodes[] = ['value' => $valeur, 'label' => $libelle];
        }
        $statuts = [['value' => '', 'label' => 'Tous sauf annulés']];
        foreach (Regles::STATUTS as $valeur => $libelle) {
            $statuts[] = ['value' => $valeur, 'label' => $libelle];
        }
        $prestataires = $p['prestataires'] ?? [];

        $champs = self::filtre('Période', Form::rawSelect('periode', $periodes, (string) $f['periode'], ['id' => 'historique-periode']), 'historique-periode')
            . self::filtre('Du', Form::rawInput('du', (string) $f['du'], ['type' => 'date', 'id' => 'historique-du']), 'historique-du')
            . self::filtre('Au', Form::rawInput('au', (string) $f['au'], ['type' => 'date', 'id' => 'historique-au']), 'historique-au');

        if ($voitTout) {
            $champs .= self::filtre('Responsable', Form::rawSelect('responsable', self::optionsResponsables($p['responsables'] ?? []), (string) ($f['responsable'] ?? ''), ['id' => 'historique-responsable']), 'historique-responsable');
        }

        $champs .= self::filtre('Mode', Form::rawSelect('mode', self::optionsModes('Tous les modes'), (string) $f['mode'], ['id' => 'historique-mode']), 'historique-mode')
            . self::filtre('Agence de départ', Form::rawSelect('agence', self::optionsAgences($p['agences'] ?? [], 'Toutes'), (string) ($f['agence'] ?? ''), ['id' => 'historique-agence']), 'historique-agence')
            . self::filtre('Transporteur', self::selectPrestataires('transporteur', $prestataires, $f['transporteur'] ?? null, 'historique-transporteur', 'Tous', array_values(Regles::TRANSPORTEUR_DU_MODE)), 'historique-transporteur')
            . self::filtre('Transitaire', self::selectPrestataires('transitaire', $prestataires, $f['transitaire'] ?? null, 'historique-transitaire', 'Tous', ['TRANSITAIRE']), 'historique-transitaire')
            . self::filtre('Statut', Form::rawSelect('statut', $statuts, (string) $f['statut'], ['id' => 'historique-statut']), 'historique-statut')
            . self::filtre('Recherche', Form::rawInput('q', (string) $f['q'], ['id' => 'historique-q', 'placeholder' => 'Dossier, document, conteneur ou colis']), 'historique-q')
            . '<div class="lbp-envoi-cases">'
            . self::caseFiltre('pieces', 'Pièces manquantes', (bool) $f['pieces'])
            . self::caseFiltre('ecart_colis', 'Écarts de colis', (bool) $f['ecart_colis'])
            . self::caseFiltre('ecart_facture', 'Écarts de facture', (bool) $f['ecart_facture'])
            . '</div>';

        $html .= self::formulaireFiltre('colisage/envois/historique', $champs);

        $t = $p['totaux'];
        $html .= '<div class="lbp-envoi-kpis">'
            . self::kpi('Dossiers', (string) (int) $t['dossiers'])
            . self::kpi('Colis', Regles::nombre((float) $t['colis']))
            . self::kpi('Poids brut', Regles::nombre((float) $t['poids'], 1) . ' kg')
            . self::kpi('Coût prévu', Regles::nombre((float) $t['cout_prevu']) . ' XOF')
            . self::kpi('Coût retenu', Regles::nombre((float) $t['cout_retenu']) . ' XOF')
            . self::kpi('Écart factures', self::signe((float) $t['ecart_facture']) . ' XOF', abs((float) $t['ecart_facture']) > 0)
            . '</div>';

        $colonnes = [
            ['label' => 'Dossier'], ['label' => 'Mode'], ['label' => 'Départ'], ['label' => 'Transporteur'], ['label' => 'Document'],
            ['label' => 'Colis', 'align' => 'right'], ['label' => 'Poids kg', 'align' => 'right'],
            ['label' => 'Transit. départ'], ['label' => 'Transit. dest.'], ['label' => 'Livr. départ'], ['label' => 'Livr. arrivée'], ['label' => 'Fret'],
            ['label' => 'Emballages'], ['label' => 'Coût XOF', 'align' => 'right'], ['label' => 'Écart fact.', 'align' => 'right'], ['label' => 'Pièces'], ['label' => 'Statut'],
        ];
        if ($voitTout) {
            $colonnes[] = ['label' => 'Responsable'];
        }

        $lignes = [];
        foreach ($p['dossiers'] as $d) {
            $s = $d['synthese'];
            $ligne = [
                self::lienDossier($d),
                View::e(Regles::MODES[(string) $d['mode_transport']] ?? (string) $d['mode_transport']),
                View::e(self::date($d['date_reference'] ?? null)),
                self::valeur($d['transporteur'] ?? null),
                '<span class="lbp-envoi-mono">' . self::valeur($d['numero_document'] ?? null) . '</span>',
                self::colis($d, $s),
                View::e(($d['poids_brut_kg'] ?? null) !== null ? Regles::nombre((float) $d['poids_brut_kg'], 1) : '—'),
                self::cellulePoste($s, 'TRANSIT_DEPART'),
                self::cellulePoste($s, 'TRANSIT_ARRIVEE'),
                self::cellulePoste($s, 'LIVRAISON_DEPART'),
                self::cellulePoste($s, 'LIVRAISON_ARRIVEE'),
                self::cellulePoste($s, 'FRET'),
                self::valeur($s['emballages_texte'] !== '' ? $s['emballages_texte'] : null),
                View::e((float) $s['cout_retenu_xof'] > 0 ? Regles::nombre((float) $s['cout_retenu_xof']) : '—'),
                $s['ecart_facture_depasse'] ? Ui::badge(self::signe((float) $s['ecart_facture_xof']), 'danger') : View::e(self::signe((float) $s['ecart_facture_xof'])),
                self::pieces($s),
                self::badgeStatut((string) $d['statut']),
            ];
            if ($voitTout) {
                $ligne[] = View::e((string) ($d['responsable'] ?? '—'));
            }
            $lignes[] = $ligne;
        }

        if ($lignes !== []) {
            $postes = $t['postes'];
            $total = [
                '<strong>' . (int) $t['dossiers'] . ' dossier(s)</strong>', '', '', '', '',
                '<strong>' . View::e(Regles::nombre((float) $t['colis'])) . '</strong>',
                '<strong>' . View::e(Regles::nombre((float) $t['poids'], 1)) . '</strong>',
                self::totalPoste($postes['TRANSIT_DEPART']),
                self::totalPoste($postes['TRANSIT_ARRIVEE']),
                self::totalPoste($postes['LIVRAISON_DEPART']),
                self::totalPoste($postes['LIVRAISON_ARRIVEE']),
                self::totalPoste($postes['FRET']),
                '',
                '<strong>' . View::e(Regles::nombre((float) $t['cout_retenu'])) . '</strong>',
                '<strong>' . View::e(self::signe((float) $t['ecart_facture'])) . '</strong>',
                '', '',
            ];
            if ($voitTout) {
                $total[] = '';
            }
            $lignes[] = $total;
        }

        $html .= Ui::section(
            'Envois du ' . self::date($f['du']) . ' au ' . self::date($f['au']),
            ModuleTable::render($colonnes, $lignes, 'Aucun dossier sur cette période', 'Élargissez la période ou retirez un filtre.'),
            'Totaux des postes en XOF, au taux figé de chaque dossier'
        );

        return self::envelopper($html) . self::scriptHistorique();
    }

    // ------------------------------------------------------------------
    // Prestataires
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function prestatairesPage(array $p): string
    {
        $html = Ui::pageHeader(
            'Transporteurs et prestataires',
            "Compagnies, transitaires et livreurs proposés dans les dossiers. Le préfixe LTA d'une compagnie aérienne sert à contrôler ses numéros.",
            ['eyebrow' => "Dossiers d'envoi", 'class' => 'rh-hero-white', 'actions' => [
                Ui::button('Tous les dossiers', ['href' => 'colisage/envois', 'variant' => 'secondary']),
            ]]
        );

        $types = [['value' => '', 'label' => 'Choisir']];
        foreach (Regles::TYPES_PRESTATAIRE as $valeur => $libelle) {
            $types[] = ['value' => $valeur, 'label' => $libelle];
        }

        $ajout = '<form method="post" action="' . View::e(View::url('colisage/envois/prestataires/enregistrer')) . '">'
            . Form::hidden('_csrf_token', Csrf::token())
            . '<div class="lbp-envoi-grille">'
            . self::champ('Nom', Form::rawInput('name', '', ['id' => 'prestataire-nom', 'maxlength' => '150', 'required' => true]), 'prestataire-nom')
            . self::champ('Type', Form::rawSelect('type', $types, '', ['id' => 'prestataire-type', 'required' => true]), 'prestataire-type')
            . self::champ('Pays', Form::rawInput('country', '', ['id' => 'prestataire-pays', 'maxlength' => '100']), 'prestataire-pays')
            . self::champ('Préfixe LTA', Form::rawInput('prefixe_lta', '', ['id' => 'prestataire-prefixe', 'maxlength' => '3', 'inputmode' => 'numeric', 'class' => 'lbp-envoi-mono']), 'prestataire-prefixe', 'Compagnies aériennes : 3 chiffres (057 pour Air France).')
            . '</div>'
            . '<div class="lbp-envoi-action">' . Ui::button('Ajouter', ['type' => 'submit', 'variant' => 'primary']) . '</div>'
            . '</form>';

        $html .= Ui::section('Ajouter un prestataire', $ajout);

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

        return self::envelopper($html);
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
        $decimales = abs($montant - round($montant)) > 0.001 ? 2 : 0;

        return Regles::nombre($montant, $decimales) . ' ' . $devise;
    }

    public static function signe(float $valeur, int $decimales = 0): string
    {
        return ($valeur > 0 ? '+' : '') . Regles::nombre($valeur, $decimales);
    }

    /**
     * Prestataire et montants d'un poste, en texte brut.
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
            'prestataire' => (string) ($ligne['prestataire'] ?? $ligne['prestataire_libre'] ?? ''),
            'prevu' => ($ligne['montant_prevu'] ?? null) !== null
                ? self::montantBrut((float) $ligne['montant_prevu'], (string) ($ligne['devise'] ?? 'XOF'))
                : (!empty($ligne['sans_frais']) ? 'sans frais' : ''),
            'facture' => ($ligne['montant_facture'] ?? null) !== null
                ? self::montantBrut((float) $ligne['montant_facture'], (string) ($ligne['devise_facture'] ?? $ligne['devise'] ?? 'XOF'))
                : '',
        ];
    }

    private static function envelopper(string $html): string
    {
        return self::styles() . '<div class="lbp-envoi">' . $html . '</div>';
    }

    private static function badgeStatut(string $statut): string
    {
        return Ui::badge(Regles::STATUTS[$statut] ?? $statut, self::TONS_STATUT[$statut] ?? 'neutral');
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
        return View::e((string) ($d['agence_depart'] ?? '—')) . ' → ' . View::e((string) ($d['agence_arrivee'] ?? $d['destination'] ?? '—'));
    }

    /** @param array<string, mixed> $d */
    private static function transport(array $d): string
    {
        return self::valeur($d['transporteur'] ?? null)
            . '<span class="lbp-envoi-sous lbp-envoi-mono">' . self::valeur($d['numero_document'] ?? null) . '</span>';
    }

    /**
     * @param array<string, mixed> $d
     * @param array<string, mixed> $s
     */
    private static function colis(array $d, array $s): string
    {
        $html = self::texteOuTiret($d['nb_colis_declare'] ?? null);

        if (($s['ecart_colis'] ?? null) !== null && (int) $s['ecart_colis'] !== 0) {
            $html .= ' ' . Ui::badge((int) $s['colis_pointes'] . ' pointés', 'danger');
        }

        return $html;
    }

    /** @param array<string, mixed> $s */
    private static function pieces(array $s): string
    {
        $attendues = (int) $s['pieces_attendues'];
        $presentes = (int) $s['pieces_presentes'];

        return Ui::badge($presentes . '/' . $attendues, match (true) {
            $presentes >= $attendues => 'success',
            $presentes === 0 => 'danger',
            default => 'warning',
        });
    }

    /** @param array<string, mixed> $s */
    private static function cellulePoste(array $s, string $poste): string
    {
        $textes = self::textesPoste($s, $poste);

        if ($textes['prestataire'] === '' && $textes['prevu'] === '' && $textes['facture'] === '') {
            return '—';
        }

        return View::e($textes['prestataire'] !== '' ? $textes['prestataire'] : '—')
            . '<span class="lbp-envoi-sous">' . View::e($textes['prevu'] !== '' ? $textes['prevu'] : '—') . '</span>'
            . ($textes['facture'] !== '' ? '<span class="lbp-envoi-sous">facturé ' . View::e($textes['facture']) . '</span>' : '');
    }

    /** @param array{prevu:float, facture:float} $poste */
    private static function totalPoste(array $poste): string
    {
        return '<strong>' . View::e(Regles::nombre($poste['prevu'])) . '</strong>'
            . ($poste['facture'] > 0 ? '<span class="lbp-envoi-sous">facturé ' . View::e(Regles::nombre($poste['facture'])) . '</span>' : '');
    }

    private static function montant(?float $valeur): string
    {
        return $valeur === null ? '—' : ModuleTable::montant($valeur);
    }

    private static function kg(mixed $valeur): string
    {
        return $valeur === null || $valeur === '' ? '—' : Regles::nombre((float) $valeur, 1) . ' kg';
    }

    private static function texteOuTiret(mixed $valeur): string
    {
        return $valeur === null || $valeur === '' ? '—' : View::e((string) $valeur);
    }

    private static function valeur(mixed $valeur): string
    {
        $texte = trim((string) ($valeur ?? ''));

        return $texte === '' ? '—' : View::e($texte);
    }

    private static function taille(int $octets): string
    {
        return $octets >= 1024 * 1024
            ? Regles::nombre($octets / 1024 / 1024, 1) . ' Mo'
            : Regles::nombre(max(1, $octets / 1024)) . ' Ko';
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

    private static function kpi(string $libelle, string $valeur, bool $alerte = false): string
    {
        return '<div class="lbp-envoi-kpi' . ($alerte ? ' is-alerte' : '') . '"><span>' . View::e($libelle) . '</span><strong>' . View::e($valeur) . '</strong></div>';
    }

    /** @param array<int, array{0:string, 1:string}> $paires libellé brut, valeur déjà rendue */
    private static function kv(array $paires): string
    {
        $html = '<dl class="lbp-envoi-kv">';
        foreach ($paires as [$libelle, $valeur]) {
            $html .= '<div><dt>' . View::e($libelle) . '</dt><dd>' . $valeur . '</dd></div>';
        }

        return $html . '</dl>';
    }

    /**
     * Champ de formulaire avec son repère LBP éventuel.
     *
     * @param array<string, string> $attributs
     */
    private static function champ(string $libelle, string $controle, string $id, string $aide = '', string $colonneLbp = '', array $attributs = []): string
    {
        return '<div class="finea-field"' . Html::attrs($attributs) . '>'
            . '<label for="' . View::e($id) . '">' . View::e($libelle)
            . ($colonneLbp !== '' ? ' <span class="lbp-envoi-lbp">LBP ' . View::e($colonneLbp) . '</span>' : '') . '</label>'
            . $controle
            . ($aide !== '' ? '<small class="finea-field-hint">' . View::e($aide) . '</small>' : '')
            . '</div>';
    }

    private static function filtre(string $libelle, string $controle, string $id): string
    {
        return '<div class="lbp-envoi-filtre-champ"><label for="' . View::e($id) . '">' . View::e($libelle) . '</label>' . $controle . '</div>';
    }

    private static function caseFiltre(string $nom, string $libelle, bool $coche): string
    {
        return '<label class="lbp-envoi-case"><input type="checkbox" name="' . View::e($nom) . '" value="1"' . ($coche ? ' checked' : '') . '> ' . View::e($libelle) . '</label>';
    }

    private static function formulaireFiltre(string $chemin, string $champs): string
    {
        return '<form method="get" action="' . View::e(View::url($chemin)) . '" class="lbp-envoi-filtres">'
            . $champs
            . Ui::button('Afficher', ['type' => 'submit', 'variant' => 'primary'])
            . '</form>';
    }

    private static function formAction(int $id, string $chemin, string $libelle, string $variante, string $confirmation, string $champs = ''): string
    {
        return '<form method="post" class="lbp-envoi-action" action="' . View::e(View::url('colisage/envois/' . $id . '/' . $chemin)) . '"'
            . ($confirmation !== '' ? ' data-confirmer="' . View::e($confirmation) . '"' : '') . '>'
            . Form::hidden('_csrf_token', Csrf::token())
            . $champs
            . Ui::button($libelle, ['type' => 'submit', 'variant' => $variante])
            . '</form>';
    }

    private static function motif(string $cle, string $invite): string
    {
        return '<textarea class="finea-input finea-textarea" name="motif" rows="2" required maxlength="1000" id="motif-' . View::e($cle) . '"'
            . ' placeholder="' . View::e($invite) . '" aria-label="' . View::e($invite) . '"></textarea>';
    }

    /** @param array<int, string> $erreurs */
    private static function erreurs(string $titre, array $erreurs): string
    {
        $items = '';
        foreach ($erreurs as $erreur) {
            $items .= '<li>' . View::e((string) $erreur) . '</li>';
        }

        return '<div class="lbp-envoi-erreurs" role="alert"><strong>' . View::e($titre) . '</strong><ul>' . $items . '</ul></div>';
    }

    private static function alerte(string $titre, string $texte): string
    {
        return '<div class="lbp-envoi-alerte" role="status"><strong>' . View::e($titre) . ' :</strong> ' . View::e($texte) . '</div>';
    }

    /**
     * Liste de prestataires regroupée par type, les types utiles au champ en tête.
     *
     * @param array<int, array<string, mixed>> $prestataires
     * @param array<int, string> $typesEnTete
     */
    private static function selectPrestataires(string $nom, array $prestataires, mixed $choisi, string $id, string $vide, array $typesEnTete, string $etiquette = ''): string
    {
        $choisi = (int) ($choisi ?? 0);
        $groupes = [];
        foreach ($prestataires as $pr) {
            if (empty($pr['is_active']) && (int) $pr['id'] !== $choisi) {
                continue;
            }
            $groupes[(string) ($pr['type'] ?? '')][] = $pr;
        }

        $ordre = array_merge($typesEnTete, array_diff(array_keys(Regles::TYPES_PRESTATAIRE), $typesEnTete), ['']);

        $html = '<select class="finea-select" name="' . View::e($nom) . '" id="' . View::e($id) . '"'
            . ($etiquette !== '' ? ' aria-label="' . View::e($etiquette) . '"' : '') . '>'
            . '<option value="">' . View::e($vide) . '</option>';

        foreach ($ordre as $type) {
            if (empty($groupes[$type])) {
                continue;
            }
            $html .= '<optgroup label="' . View::e($type === '' ? 'Type à préciser' : (Regles::TYPES_PRESTATAIRE[$type] ?? $type)) . '">';
            foreach ($groupes[$type] as $pr) {
                $html .= '<option value="' . (int) $pr['id'] . '"' . ((int) $pr['id'] === $choisi ? ' selected' : '') . '>'
                    . View::e((string) $pr['name']) . '</option>';
            }
            $html .= '</optgroup>';
        }

        return $html . '</select>';
    }

    /** @return array<int, array{value:string, label:string}> */
    private static function optionsModes(?string $vide): array
    {
        $options = $vide !== null ? [['value' => '', 'label' => $vide]] : [];
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

    /**
     * @param array<int, array{id:int, full_name:string}> $responsables
     * @return array<int, array{value:string, label:string}>
     */
    private static function optionsResponsables(array $responsables): array
    {
        $options = [['value' => '', 'label' => 'Tous']];
        foreach ($responsables as $r) {
            $options[] = ['value' => (string) (int) $r['id'], 'label' => (string) $r['full_name']];
        }

        return $options;
    }

    /**
     * @param array<int, array<string, mixed>> $departs
     * @return array<int, array{value:string, label:string}>
     */
    private static function optionsDeparts(array $departs): array
    {
        $options = [['value' => '', 'label' => 'Aucun départ rattaché']];
        foreach ($departs as $depart) {
            $options[] = [
                'value' => (string) (int) $depart['id'],
                'label' => (string) $depart['reference'] . ' — ' . (string) ($depart['agence_depart'] ?? '?') . ' → ' . (string) ($depart['agence_arrivee'] ?? '?')
                    . ', ' . self::date($depart['date_depart'] ?? null) . ', ' . (int) ($depart['nb_colis'] ?? 0) . ' colis',
            ];
        }

        return $options;
    }

    /** @param array<string, mixed> $f */
    private static function requeteHistorique(array $f, bool $voitTout): string
    {
        return http_build_query(array_filter([
            'periode' => $f['periode'] ?? null,
            'du' => $f['du'] ?? null,
            'au' => $f['au'] ?? null,
            'responsable' => $voitTout ? ($f['responsable'] ?? null) : null,
            'mode' => $f['mode'] ?? null,
            'agence' => $f['agence'] ?? null,
            'transporteur' => $f['transporteur'] ?? null,
            'transitaire' => $f['transitaire'] ?? null,
            'statut' => $f['statut'] ?? null,
            'pieces' => !empty($f['pieces']) ? '1' : null,
            'ecart_colis' => !empty($f['ecart_colis']) ? '1' : null,
            'ecart_facture' => !empty($f['ecart_facture']) ? '1' : null,
            'q' => $f['q'] ?? null,
        ], static fn (mixed $v): bool => $v !== null && $v !== ''));
    }

    private static function styles(): string
    {
        return <<<'CSS'
<style>
.lbp-envoi [hidden]{display:none!important}
.lbp-envoi-filtres{display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;margin:0 0 1rem}
.lbp-envoi-filtre-champ{display:flex;flex-direction:column;gap:.25rem;font-size:.8rem;font-weight:600;color:#475569}
.lbp-envoi-filtre-champ .finea-input,.lbp-envoi-filtre-champ .finea-select{min-width:9rem}
.lbp-envoi-cases{display:flex;flex-wrap:wrap;gap:.4rem 1rem;align-items:center;font-size:.85rem;padding-bottom:.45rem}
.lbp-envoi-case{display:inline-flex;gap:.35rem;align-items:center;cursor:pointer}
.lbp-envoi-grille{display:grid;grid-template-columns:repeat(auto-fill,minmax(15rem,1fr));gap:.9rem 1.25rem}
.lbp-envoi-lbp{display:inline-block;font-size:.66rem;font-weight:700;letter-spacing:.03em;padding:0 .35rem;border-radius:3px;background:#fde68a;color:#422006;vertical-align:1px}
.lbp-envoi-ajout{display:inline-block;font-size:.66rem;font-weight:600;padding:0 .35rem;border-radius:3px;border:1px solid #cbd5e1;color:#64748b;vertical-align:1px}
.lbp-envoi-mono{font-family:Consolas,Monaco,monospace;letter-spacing:.02em}
.lbp-envoi-sous{display:block;font-size:.78rem;color:#64748b}
.lbp-envoi-note{font-size:.85rem;color:#475569;margin:0}
.lbp-envoi-erreurs,.lbp-envoi-alerte{border:1px solid #fecaca;background:#fef2f2;color:#7f1d1d;border-radius:10px;padding:.8rem 1rem;margin:0 0 1rem}
.lbp-envoi-erreurs ul,.lbp-envoi-manques ul{margin:.4rem 0 0 1.1rem;padding:0}
.lbp-envoi-manques{border:1px solid #fde68a;background:#fffbeb;color:#713f12;border-radius:10px;padding:.8rem 1rem}
.lbp-envoi-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(10rem,1fr));gap:.75rem;margin:0 0 1rem}
.lbp-envoi-kpi{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:.7rem .9rem}
.lbp-envoi-kpi span{display:block;font-size:.7rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#64748b}
.lbp-envoi-kpi strong{font-size:1.1rem;font-variant-numeric:tabular-nums;color:#0f172a}
.lbp-envoi-kpi.is-alerte strong{color:#b91c1c}
.lbp-envoi-kv{display:grid;grid-template-columns:repeat(auto-fill,minmax(13rem,1fr));gap:.7rem 1.25rem;margin:0}
.lbp-envoi-kv dt{font-size:.7rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#64748b}
.lbp-envoi-kv dd{margin:.1rem 0 0;font-variant-numeric:tabular-nums;color:#0f172a}
.lbp-envoi-saisie input,.lbp-envoi-saisie select{min-width:6.5rem;width:100%}
.lbp-envoi-saisie th[scope=row]{white-space:nowrap;text-align:left}
.lbp-envoi-etroit{max-width:28rem}
.lbp-envoi-libre{margin-top:.35rem}
.lbp-envoi-centre{text-align:center}
.lbp-envoi-prefixe{max-width:5rem}
.lbp-envoi-sous-titre{font-size:.95rem;font-weight:700;margin:1.1rem 0 .5rem;color:#0f172a}
.lbp-envoi-barre{position:sticky;bottom:0;z-index:5;display:flex;flex-wrap:wrap;justify-content:flex-end;gap:.75rem;margin-top:1rem;padding:.75rem 1rem;background:#fff;border-top:1px solid #e2e8f0;box-shadow:0 -6px 16px rgba(15,23,42,.06)}
.lbp-envoi-circuit{display:flex;flex-direction:column;gap:.75rem;align-items:flex-start}
.lbp-envoi-action{display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;margin:0}
.lbp-envoi-action textarea{min-width:18rem;flex:1}
.lbp-envoi-details summary{cursor:pointer;color:#b91c1c;font-weight:600;font-size:.85rem}
.lbp-envoi-details[open] summary{margin-bottom:.5rem}
.lbp-envoi-checklist{list-style:none;margin:.35rem 0 1rem;padding:0;display:flex;flex-wrap:wrap;gap:.5rem 1.25rem}
.lbp-envoi-depot{border:1px dashed #cbd5e1;border-radius:10px;padding:.9rem 1rem;margin:0 0 1rem;background:#f8fafc;display:flex;flex-direction:column;gap:.75rem}
.lbp-envoi a:focus-visible,.lbp-envoi input:focus-visible,.lbp-envoi select:focus-visible,.lbp-envoi textarea:focus-visible{outline:2px solid #2563eb;outline-offset:2px}
</style>
CSS;
    }

    private static function scriptCommun(): string
    {
        return <<<'JS'
document.querySelectorAll('.lbp-envoi form[data-confirmer]').forEach(function (formulaire) {
    formulaire.addEventListener('submit', function (evenement) {
        if (!window.confirm(formulaire.dataset.confirmer)) {
            evenement.preventDefault();
        }
    });
});
JS;
    }

    private static function scriptFormulaire(): string
    {
        return '<script>(function () {' . self::scriptCommun() . <<<'JS'
var formulaire = document.getElementById('lbp-envoi-form');
if (!formulaire) { return; }
var mode = document.getElementById('envoi-mode');
var typeDocument = document.getElementById('envoi-type-document');
var fils = ['LTA_FILLE', 'BL_FILS'];
function appliquer() {
    var choisi = mode.value;
    formulaire.querySelectorAll('[data-modes]').forEach(function (element) {
        element.hidden = element.dataset.modes.split(' ').indexOf(choisi) === -1;
    });
    var premier = null;
    var valide = false;
    Array.prototype.forEach.call(typeDocument.options, function (option) {
        var possible = option.dataset.mode === choisi;
        option.hidden = !possible;
        option.disabled = !possible;
        if (possible && premier === null) { premier = option; }
        if (possible && option.selected) { valide = true; }
    });
    if (!valide && premier) { premier.selected = true; }
    var estFils = fils.indexOf(typeDocument.value) !== -1;
    formulaire.querySelectorAll('[data-fils]').forEach(function (element) { element.hidden = !estFils; });
}
mode.addEventListener('change', appliquer);
typeDocument.addEventListener('change', appliquer);
appliquer();
JS . '})();</script>';
    }

    private static function scriptFiche(): string
    {
        return '<script>(function () {' . self::scriptCommun() . <<<'JS'
var depot = document.getElementById('lbp-envoi-depot');
if (!depot) { return; }
var type = document.getElementById('depot-type');
function appliquer() {
    var option = type.options[type.selectedIndex];
    var facture = !!(option && option.dataset.facture);
    depot.querySelectorAll('[data-facture-champ]').forEach(function (element) { element.hidden = !facture; });
    document.getElementById('depot-montant').required = facture;
}
type.addEventListener('change', appliquer);
appliquer();
JS . '})();</script>';
    }

    private static function scriptHistorique(): string
    {
        return '<script>(function () {' . self::scriptCommun() . <<<'JS'
var periode = document.getElementById('historique-periode');
['historique-du', 'historique-au'].forEach(function (id) {
    var champ = document.getElementById(id);
    if (champ && periode) {
        champ.addEventListener('change', function () { periode.value = 'libre'; });
    }
});
JS . '})();</script>';
    }
}
