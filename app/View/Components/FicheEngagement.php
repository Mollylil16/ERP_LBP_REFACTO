<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Models\Finance\Facture;

/**
 * Fiche d'engagement client — garantie colis, imprimée au verso de la facture.
 *
 * Le document que la direction faisait remplir à la main. Il tient sur une
 * seule page pour que l'impression recto-verso donne la facture devant et la
 * fiche derrière, sur la même feuille.
 *
 * Ce que le logiciel connaît déjà est pré-rempli — le colis, les deux parties,
 * la marchandise, les montants : l'agent n'a pas à recopier ce qu'il vient de
 * saisir, et le client n'a plus qu'à déclarer sa valeur et signer. Le reste
 * reste en pointillés, à remplir au comptoir.
 *
 * Les clauses, les exclusions et les mentions sont reprises mot pour mot de la
 * fiche validée : rien n'y est résumé ni réécrit, c'est un document qui engage.
 */
final class FicheEngagement
{
    /** Taux de la prime de garantie, tel qu'imprimé sur la fiche. */
    private const TAUX_GARANTIE_POURCENT = 10;

    /** Les sept engagements du client, dans l'ordre de la fiche. */
    private const ENGAGEMENTS = [
        'Certifie l\'exactitude des informations fournies dans la présente fiche.',
        'Certifie que le contenu déclaré correspond réellement au contenu du colis.',
        'Reconnaît avoir déclaré la valeur réelle des biens confiés à LBP-CI.',
        'Reconnaît qu\'une fausse déclaration, dissimulation ou sous-évaluation volontaire peut entraîner les conséquences prévues par les conditions contractuelles applicables.',
        'Certifie que le colis ne contient aucun produit interdit ou soumis à restriction non déclaré.',
        'Reconnaît avoir reçu ou avoir eu accès aux conditions générales applicables à la garantie.',
        'Accepte les conditions applicables à la prise en charge du colis et à l\'indemnisation en cas de sinistre.',
    ];

    private const EXCLUSIONS = 'espèces et valeurs assimilées ; bijoux, pierres précieuses et objets de très grande '
        . 'valeur sauf déclaration/acceptation préalable ; armes et munitions ; stupéfiants et substances interdites ; '
        . 'produits dangereux ou illicites ; marchandises interdites par la réglementation ; marchandises '
        . 'insuffisamment emballées ; sous-déclaration volontaire ; faute intentionnelle ou fausse déclaration ; '
        . 'tout autre bien ou risque expressément exclu par le contrat.';

    /**
     * @param array<string, mixed> $colis
     */
    public static function verso(array $colis, ?Facture $facture, string $operateur): string
    {
        $marchandises = (array) ($colis['marchandises'] ?? []);
        $devise = (string) ($colis['devise'] ?? 'XOF');

        /*
         * Deux colonnes partout où c'est possible : la fiche doit tenir sur la
         * seule face arrière de la feuille, et rien n'en est retiré pour y
         * parvenir — c'est la mise en page qui se resserre, pas le texte.
         */
        return '<section class="fiche-verso">'
            . self::entete($colis, $facture, $operateur)
            . self::colonnes(self::parties($colis))
            . self::colonnes(self::marchandise($colis, $marchandises) . self::garantie($colis, $facture, $devise))
            . self::engagements()
            . self::colonnes(self::risques() . self::sinistre())
            . self::colonnes(self::beneficiaire() . self::recu($colis, $facture, $devise))
            . self::signatures($colis)
            . self::avis()
            . '</section>';
    }

    // ------------------------------------------------------------------

    /** Deux blocs côte à côte. */
    private static function colonnes(string $blocs): string
    {
        return '<div class="fiche-colonnes">' . $blocs . '</div>';
    }

    /** @param array<string, mixed> $colis */
    private static function entete(array $colis, ?Facture $facture, string $operateur): string
    {
        $date = (string) ($facture?->dateEmission ?? $colis['created_at'] ?? '');

        return '<header class="fiche-entete">'
            . '<img src="' . View::asset('images/logo-lbp.png') . '" alt="La Belle Porte" class="fiche-logo">'
            . '<div class="fiche-titre">'
            . '<h1>FICHE D\'ENGAGEMENT CLIENT — GARANTIE COLIS</h1>'
            . '<p>Déclaration de valeur • Adhésion à la garantie • Engagement du client</p>'
            . '</div></header>'
            . '<table class="fiche-table fiche-reference"><tr>'
            . self::cellule('N° FICHE', $facture?->numeroFacture)
            . self::cellule('N° COLIS', (string) ($colis['numero_tracking'] ?? ''))
            . self::cellule('DATE', self::date($date))
            . self::cellule('AGENT LBP-CI', $operateur)
            . '</tr></table>';
    }

    /** @param array<string, mixed> $colis */
    private static function parties(array $colis): string
    {
        $expediteur = '<div class="fiche-bloc"><h2>1. Identification de l\'expéditeur / client</h2>'
            . '<table class="fiche-table">'
            . '<tr>' . self::cellule('Nom / Raison sociale', (string) ($colis['expediteur_name'] ?? ''))
            . self::cellule('Téléphone', (string) ($colis['expediteur_phone'] ?? '')) . '</tr>'
            . '<tr>' . self::cellule('Adresse', (string) ($colis['expediteur_address'] ?? ''))
            . self::cellule('Pièce d\'identité / Réf.', null) . '</tr>'
            . '<tr>' . self::cellule('Ville / Pays', (string) ($colis['agence_depart_name'] ?? ''))
            . self::cellule('Qualité', null, self::cases(['Expéditeur', 'Autre'])) . '</tr>'
            . '</table></div>';

        $destinataire = '<div class="fiche-bloc"><h2>2. Identification du destinataire</h2>'
            . '<table class="fiche-table">'
            . '<tr>' . self::cellule('Nom / Raison sociale', (string) ($colis['destinataire_name'] ?? ''))
            . self::cellule('Téléphone', (string) ($colis['destinataire_phone'] ?? '')) . '</tr>'
            . '<tr>' . self::cellule('Adresse de livraison', (string) ($colis['destinataire_address'] ?? ''))
            . self::cellule('Ville / Pays', (string) ($colis['agence_arrivee_name'] ?? '')) . '</tr>'
            . '<tr>' . self::cellule('Trafic', (string) ($colis['trafic'] ?? ''))
            . self::cellule('N° de suivi', (string) ($colis['numero_tracking'] ?? '')) . '</tr>'
            . '</table></div>';

        return $expediteur . $destinataire;
    }

    /**
     * @param array<string, mixed> $colis
     * @param array<int, array<string, mixed>> $marchandises
     */
    private static function marchandise(array $colis, array $marchandises): string
    {
        $natures = [];
        $nombre = 0;

        foreach ($marchandises as $ligne) {
            $description = trim((string) ($ligne['description'] ?? ''));
            if ($description !== '') {
                $natures[] = $description;
            }
            $nombre += (int) ($ligne['nbre_colis'] ?? 1);
        }

        if ($nombre === 0) {
            $nombre = (int) ($colis['nombre_colis'] ?? 0);
        }

        $poids = (float) ($colis['poids_total'] ?? 0);
        $valeur = (float) ($colis['valeur_declaree'] ?? 0);

        return '<div class="fiche-bloc"><h2>3. Description du colis et déclaration de valeur</h2>'
            . '<table class="fiche-table">'
            . '<tr>' . self::cellule('Nature du contenu', implode(' + ', array_unique($natures)), '', 2) . '</tr>'
            . '<tr>' . self::cellule('Nombre de colis', $nombre > 0 ? (string) $nombre : null)
            . self::cellule('Poids (kg)', $poids > 0 ? self::nombre($poids, 2) : null) . '</tr>'
            . '<tr>' . self::cellule('État au dépôt', null, self::cases(['Bon état', 'Emballage endommagé', 'Autre']), 2) . '</tr>'
            . '</table>'
            . '<p class="fiche-valeur"><strong>VALEUR DÉCLARÉE :</strong> '
            . ($valeur > 0 ? '<span class="fiche-rempli">' . View::e(self::nombre($valeur)) . '</span>' : self::pointilles(28))
            . ' FCFA</p>'
            . '<p class="fiche-note">Je certifie que la valeur déclarée correspond à la valeur réelle des biens confiés '
            . 'à LBP-CI et m\'engage, sur demande, à fournir tout justificatif pertinent.</p>'
            . '</div>';
    }

    /** @param array<string, mixed> $colis */
    private static function garantie(array $colis, ?Facture $facture, string $devise): string
    {
        $souscrite = !empty($colis['assurance_souscrite']);
        $prime = (float) ($colis['montant_assurance'] ?? 0);
        $total = (float) ($facture?->montantTotal ?? $colis['montant_total'] ?? 0);
        $expedition = max(0.0, $total - $prime);

        return '<div class="fiche-bloc"><h2>4. Garantie souscrite et paiement</h2>'
            . '<table class="fiche-table">'
            . '<tr>' . self::cellule('Garantie choisie', null, self::case(
                'GARANTIE SOUSCRITE — ' . self::TAUX_GARANTIE_POURCENT . ' %',
                $souscrite
            ))
            . self::cellule('Prime / frais de garantie (' . self::TAUX_GARANTIE_POURCENT . ' %)', $prime > 0 ? self::montant($prime, $devise) : null) . '</tr>'
            . '<tr>' . self::cellule('Frais d\'expédition', $expedition > 0 ? self::montant($expedition, $devise) : null)
            . self::cellule('Total payé', $total > 0 ? self::montant($total, $devise) : null) . '</tr>'
            . '<tr>' . self::cellule('Plafond de garantie applicable', null, '', 2) . '</tr>'
            . '</table>'
            . '<p class="fiche-note"><strong>IMPORTANT</strong> — Le paiement de ' . self::TAUX_GARANTIE_POURCENT
            . ' % constitue la prime / les frais de garantie. Il ne signifie pas automatiquement que l\'indemnisation '
            . 'sera égale à ' . self::TAUX_GARANTIE_POURCENT . ' % de la valeur du colis. L\'indemnisation est '
            . 'déterminée selon les conditions contractuelles et les justificatifs applicables.</p>'
            . '</div>';
    }

    private static function engagements(): string
    {
        $items = '';
        foreach (self::ENGAGEMENTS as $engagement) {
            $items .= '<li>' . View::e($engagement) . '</li>';
        }

        return '<div class="fiche-bloc"><h2>5. Engagement du client</h2>'
            . '<ol class="fiche-liste">' . $items . '</ol></div>';
    }

    private static function risques(): string
    {
        return '<div class="fiche-bloc"><h2>6. Risques garantis et exclusions</h2>'
            . '<p class="fiche-note fiche-note--cases"><strong>RISQUES GARANTIS</strong> (sous réserve des conditions générales) : '
            . self::cases(['Perte', 'Vol', 'Destruction totale', 'Dommages matériels'])
            . self::case('Autre : ') . self::pointilles(20) . '</p>'
            . '<p class="fiche-note"><strong>EXCLUSIONS PRINCIPALES :</strong> ' . View::e(self::EXCLUSIONS) . '</p>'
            . '</div>';
    }

    private static function sinistre(): string
    {
        return '<div class="fiche-bloc"><h2>7. Déclaration de sinistre</h2>'
            . '<p class="fiche-note">En cas de perte, vol ou dommage, le client doit informer LBP-CI dans les meilleurs '
            . 'délais et fournir les documents nécessaires à l\'étude de sa demande. Pièces pouvant être demandées : '
            . 'présente fiche, preuve de dépôt, pièce d\'identité, preuve de valeur, facture ou justificatif d\'achat, '
            . 'photographies du colis ou des dommages, et tout document nécessaire à l\'instruction.</p></div>';
    }

    private static function beneficiaire(): string
    {
        return '<div class="fiche-bloc"><h2>8. Bénéficiaire de l\'indemnisation</h2>'
            . '<table class="fiche-table">'
            . '<tr>' . self::cellule('Nom et prénom', null)
            . self::cellule('Téléphone', null) . '</tr>'
            . '<tr>' . self::cellule('Qualité', null, self::cases(['Expéditeur', 'Destinataire', 'Autre']))
            . self::cellule('N° réclamation', null) . '</tr>'
            . '</table></div>';
    }

    /** @param array<string, mixed> $colis */
    private static function signatures(array $colis): string
    {
        return '<div class="fiche-bloc"><h2>9. Déclaration et signatures</h2>'
            . '<p class="fiche-note">Je reconnais avoir lu et compris la présente fiche ainsi que les conditions '
            . 'générales applicables à la garantie. Je confirme l\'exactitude de ma déclaration de valeur et accepte '
            . 'les conditions de prise en charge du colis.</p>'
            . '<table class="fiche-table fiche-signatures"><tr>'
            . '<td><strong>CLIENT</strong><br>Nom : <span class="fiche-rempli">'
            . View::e((string) ($colis['expediteur_name'] ?? '')) . '</span><br>'
            . 'Date : ' . self::pointilles(14) . '<br><br>'
            . 'Signature précédée de « Lu et approuvé » :<br><br></td>'
            . '<td><strong>POUR LBP-CI</strong><br>Nom / Fonction : ' . self::pointilles(22) . '<br>'
            . 'Date : ' . self::pointilles(14) . '<br><br>'
            . 'Signature et cachet LBP-CI :<br><br></td>'
            . '</tr></table></div>';
    }

    /** @param array<string, mixed> $colis */
    private static function recu(array $colis, ?Facture $facture, string $devise): string
    {
        $prime = (float) ($colis['montant_assurance'] ?? 0);
        $total = (float) ($facture?->montantTotal ?? $colis['montant_total'] ?? 0);
        $valeur = (float) ($colis['valeur_declaree'] ?? 0);

        return '<div class="fiche-bloc"><h2>10. Reçu client</h2>'
            . '<table class="fiche-table">'
            . '<tr>' . self::cellule('N° colis', (string) ($colis['numero_tracking'] ?? ''))
            . self::cellule('Valeur déclarée', $valeur > 0 ? self::montant($valeur, $devise) : null) . '</tr>'
            . '<tr>' . self::cellule('Garantie ' . self::TAUX_GARANTIE_POURCENT . ' %', $prime > 0 ? self::montant($prime, $devise) : null)
            . self::cellule('Frais d\'expédition', $total > 0 ? self::montant(max(0.0, $total - $prime), $devise) : null) . '</tr>'
            . '<tr>' . self::cellule('Total payé', $total > 0 ? self::montant($total, $devise) : null)
            . self::cellule('Mode de paiement', null, self::cases(['Espèces', 'Mobile Money', 'Virement', 'Autre'])) . '</tr>'
            . '</table></div>';
    }

    private static function avis(): string
    {
        return '<p class="fiche-avis"><strong>AVIS IMPORTANT :</strong> la présente fiche constitue une déclaration de '
            . 'valeur et une preuve de souscription/adhésion à la garantie applicable au colis. Elle doit être '
            . 'conservée avec la preuve de dépôt et les documents contractuels. Les limites, exclusions, plafonds et '
            . 'modalités d\'indemnisation sont déterminés par les conditions contractuelles applicables.</p>';
    }

    // ------------------------------------------------------------------

    /**
     * Une case du formulaire : son libellé, et soit la valeur que le logiciel
     * connaît, soit des pointillés à remplir au stylo.
     */
    private static function cellule(string $libelle, ?string $valeur, string $brut = '', int $colonnes = 1): string
    {
        $valeur = $valeur === null ? '' : trim($valeur);

        $contenu = $brut !== ''
            ? '<span class="fiche-cases">' . $brut . '</span>'
            : ($valeur !== ''
                ? '<span class="fiche-rempli">' . View::e($valeur) . '</span>'
                : self::pointilles(24));

        return '<td' . ($colonnes > 1 ? ' colspan="' . $colonnes . '"' : '') . '>'
            . '<span class="fiche-libelle">' . View::e($libelle) . '</span>'
            . $contenu . '</td>';
    }

    /**
     * Une case a cocher, dessinee par la feuille de style.
     *
     * Le caractere « boite » des traitements de texte s'imprime mal et compte
     * pour un emoji dans ce projet : la case est donc un carre trace en CSS,
     * qui sort net a l'impression et se coche au stylo.
     */
    private static function case(string $libelle, bool $cochee = false): string
    {
        return '<span class="fiche-choix">'
            . '<span class="fiche-case' . ($cochee ? ' is-cochee' : '') . '" aria-hidden="true"></span>'
            . View::e($libelle) . '</span>';
    }

    /** @param array<int, string> $libelles */
    private static function cases(array $libelles): string
    {
        return implode('', array_map(static fn (string $l): string => self::case($l), $libelles));
    }

    private static function pointilles(int $largeur): string
    {
        return '<span class="fiche-pointilles" style="min-width:' . $largeur . 'mm"></span>';
    }

    private static function montant(float $valeur, string $devise): string
    {
        return self::nombre($valeur) . ' ' . ($devise === 'EUR' ? 'EUR' : 'FCFA');
    }

    private static function nombre(float $valeur, int $decimales = 0): string
    {
        return number_format($valeur, $decimales, ',', ' ');
    }

    private static function date(string $valeur): string
    {
        $date = $valeur === '' ? false : date_create($valeur);

        return $date === false ? '' : $date->format('d/m/Y');
    }
}
