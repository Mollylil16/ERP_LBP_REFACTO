<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Models\Finance\Facture;

/**
 * Facture et bordereau de colisage remis au client.
 *
 * Document imprime : il porte son propre <html> et sa feuille de style, sans
 * passer par le gabarit des modules.
 *
 * La feuille vit dans public/assets/css/facture-print.css, reprise telle quelle
 * de l'ancienne vue : la mise en page du document n'a pas bouge.
 */
final class ColisageFacture
{
    /** Delai de retrait annonce au client, en jours. */
    private const DELAI_RETRAIT_JOURS = 3;

    /**
     * @param array<string, mixed> $colis
     */
    public static function document(
        array $colis,
        ?Facture $facture,
        float $montantEur,
        string $operateur
    ): string {
        $devise = (string) ($colis['devise'] ?? 'XOF');
        $reference = (string) $colis['numero_tracking'];
        $marchandises = (array) ($colis['marchandises'] ?? []);

        $sousTotal = 0.0;
        foreach ($marchandises as $ligne) {
            $sousTotal += (float) ($ligne['total_ligne'] ?? 0);
        }

        $montantTotal = (float) ($colis['montant_total'] ?? 0.0);
        $assurance = !empty($colis['assurance_souscrite'])
            ? (float) ($colis['montant_assurance'] ?? 0.0)
            : 0.0;

        if ($montantTotal <= 0.0) {
            $montantTotal = $sousTotal + $assurance;
        }

        return '<!DOCTYPE html><html lang="fr"><head>'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Facture ' . View::e($reference) . '</title>'
            . '<link rel="stylesheet" href="' . View::asset('css/facture-print.css') . '">'
            . '</head><body>'
            . self::barreImpression()
            . '<div class="facture-container">'
            . self::entete($colis, $reference)
            . self::avisRetrait()
            . self::agenceEtService($colis)
            . '<div class="banner-blue">DÉTAILS COLIS &nbsp;&nbsp;' . View::e($reference) . '</div>'
            . self::compteurColis($colis, $marchandises)
            . self::grilleInformations($colis)
            . self::tableMarchandises($marchandises, $sousTotal, $assurance, $montantTotal, $montantEur, $devise, $facture)
            . self::paiementEtSignatures($facture)
            . self::pied($colis, $operateur, $reference)
            . '</div>'
            . self::scriptImpression()
            . '</body></html>';
    }

    private static function barreImpression(): string
    {
        return '<div class="btn-print-container">'
            . '<button type="button" class="btn-print" onclick="window.print()">Imprimer la facture</button>'
            . '</div>';
    }

    /**
     * @param array<string, mixed> $colis
     */
    private static function entete(array $colis, string $reference): string
    {
        return '<div class="facture-header">'
            . '<img src="' . View::asset('images/entete_lbp.png') . '" alt="La Belle Porte" class="header-bg-img">'
            . '<div class="header-overlay-center"><div class="imprime-badge-specific">'
            . '<span class="imprime-title">IMPRIMÉ SPÉCIFIQUE</span>'
            . '<span class="facture-colisage-title">Facture &amp; Colisage</span>'
            . '</div></div>'
            . '<div class="header-overlay-right"><div class="qr-container">'
            // QR code scanne par un telephone : l'adresse doit etre absolue.
            . '<img src="' . View::e(View::qrCodeFor('site/tracking?ref=' . urlencode($reference))) . '"'
            . ' class="qr-code-img" alt="Suivi du colis ' . View::e($reference) . '">'
            . '<div class="qr-label">Suivi colis</div>'
            . '</div></div></div>';
    }

    private static function avisRetrait(): string
    {
        return '<div class="info-bar">'
            . 'VOUS DISPOSEZ DE ' . self::DELAI_RETRAIT_JOURS . ' JOURS POUR RÉCUPÉRER VOTRE COLIS '
            . 'À COMPTER DE LA DATE DE NOTIFICATION. PASSÉ CE DÉLAI, NOUS DÉCLINONS TOUTE RESPONSABILITÉ.'
            . '</div>';
    }

    /**
     * @param array<string, mixed> $colis
     */
    private static function agenceEtService(array $colis): string
    {
        return '<table class="agency-service-table"><tr>'
            . '<td><strong>Agence :</strong> LBP Logistics — '
            . View::e((string) ($colis['agence_depart_name'] ?? 'Siège social')) . '</td>'
            . '<td style="text-align: right;"><strong>SERVICE CLIENT :</strong> 0503497979 / 0509467979</td>'
            . '</tr></table>';
    }

    /**
     * @param array<string, mixed> $colis
     * @param array<int, array<string, mixed>> $marchandises
     */
    private static function compteurColis(array $colis, array $marchandises): string
    {
        // Le nombre de colis vient des lignes de marchandise quand elles sont
        // detaillees : c'est ce que le client compte au comptoir.
        $depuisLignes = 0;
        foreach ($marchandises as $ligne) {
            $depuisLignes += (int) ($ligne['nbre_colis'] ?? 1);
        }

        $nombre = $depuisLignes > 0 ? $depuisLignes : (int) ($colis['nombre_colis'] ?? 1);

        return '<div class="colis-count-bar">Nombre total de colis : ' . View::e((string) $nombre) . '</div>';
    }

    /**
     * @param array<string, mixed> $colis
     */
    private static function grilleInformations(array $colis): string
    {
        $lignes = [
            [
                ['Code colis :', View::e((string) $colis['numero_tracking']), 'font-weight: 700; color: #0f172a;'],
                ['Date d\'envoi :', View::e(self::date((string) $colis['created_at'])), ''],
            ],
            [
                ['EXPÉDITEUR :', View::e((string) $colis['expediteur_name']), 'font-weight: 600;'],
                ['DESTINATION :', View::e((string) ($colis['agence_arrivee_name'] ?? '—')), 'font-weight: 700; color: #1e3a5f;'],
            ],
            [
                ['TÉL EXP. :', View::e((string) ($colis['expediteur_phone'] ?? '—')), ''],
                ['DESTINATAIRE :', View::e((string) $colis['destinataire_name']), 'font-weight: 600;'],
            ],
            [
                ['TRAFIC :', View::e((string) ($colis['trafic'] ?? 'Groupage aérien')), 'font-weight: 600;'],
                ['TÉL DEST. :', View::e((string) ($colis['destinataire_phone'] ?? '—')), ''],
            ],
        ];

        $html = '<table class="grid-table">';
        foreach ($lignes as $paire) {
            $html .= '<tr>';
            foreach ($paire as $index => [$libelle, $valeur, $style]) {
                $bordure = $index === 0 ? ' border-right: 1px solid #e2e8f0;' : '';
                $html .= '<td style="width: 50%;' . $bordure . '">'
                    . '<span class="label">' . View::e($libelle) . '</span>'
                    . '<span class="value"' . ($style !== '' ? ' style="' . $style . '"' : '') . '>' . $valeur . '</span>'
                    . '</td>';
            }
            $html .= '</tr>';
        }

        return $html . '</table>';
    }

    /**
     * @param array<int, array<string, mixed>> $marchandises
     */
    private static function tableMarchandises(
        array $marchandises,
        float $sousTotal,
        float $assurance,
        float $montantTotal,
        float $montantEur,
        string $devise,
        ?Facture $facture
    ): string {
        $entetes = '<thead><tr>'
            . '<th style="width: 5%; text-align: center;">N°</th>'
            . '<th style="width: 7%; text-align: center;">Nbre colis</th>'
            . '<th>Description</th>'
            . '<th style="width: 12%;">Emballage</th>'
            . '<th style="width: 7%; text-align: center;">Qté emb.</th>'
            . '<th style="width: 11%; text-align: right;">Prix emb.</th>'
            . '<th style="width: 11%; text-align: right;">Poids (kg)</th>'
            . '<th style="width: 11%; text-align: right;">Prix / kg</th>'
            . '<th style="width: 13%; text-align: right;">Total</th>'
            . '</tr></thead>';

        $corps = '<tbody>';
        if ($marchandises === []) {
            $corps .= '<tr><td colspan="9" style="text-align: center; padding: 15px; color: #64748b;">'
                . 'Aucune marchandise répertoriée.</td></tr>';
        } else {
            foreach (array_values($marchandises) as $index => $ligne) {
                $prixEmballage = (float) ($ligne['prix_emballage'] ?? 0);

                $corps .= '<tr>'
                    . '<td style="text-align: center; font-weight: 600;">' . ($index + 1) . '</td>'
                    . '<td style="text-align: center;">' . View::e((string) ($ligne['nbre_colis'] ?? 1)) . '</td>'
                    . '<td><strong>' . View::e((string) $ligne['description']) . '</strong></td>'
                    . '<td>' . View::e((string) ($ligne['emballage'] ?? '—')) . '</td>'
                    . '<td style="text-align: center;">' . View::e((string) ($ligne['qte_emballage'] ?? 1)) . '</td>'
                    . '<td style="text-align: right;">'
                    . ($prixEmballage > 0 ? View::e(self::montant($prixEmballage, $devise)) : '—') . '</td>'
                    . '<td style="text-align: right;">'
                    . View::e(number_format((float) $ligne['poids_unitaire'], 2, ',', ' ')) . '</td>'
                    . '<td style="text-align: right;">'
                    . View::e(self::montant((float) ($ligne['prix_kg'] ?? 0), $devise)) . '</td>'
                    . '<td style="text-align: right; font-weight: 600;">'
                    . View::e(self::montant((float) ($ligne['total_ligne'] ?? 0), $devise)) . '</td>'
                    . '</tr>';
            }
        }
        $corps .= '</tbody>';

        return '<table class="items-table">' . $entetes . $corps
            . self::piedMontants($sousTotal, $assurance, $montantTotal, $montantEur, $devise, $facture)
            . '</table>';
    }

    private static function piedMontants(
        float $sousTotal,
        float $assurance,
        float $montantTotal,
        float $montantEur,
        string $devise,
        ?Facture $facture
    ): string {
        $html = '<tfoot>';
        $html .= self::ligneMontant('SOUS-TOTAL', self::montant($sousTotal, $devise));

        /*
         * L'ecart entre le total et la somme des lignes, quand il existe, est
         * l'assurance : elle etait affichee sous le libelle « EMBALLAGE », alors
         * que le prix des emballages figure deja dans chaque ligne.
         */
        $complement = round($montantTotal - $sousTotal, 2);
        if ($complement > 0) {
            $libelle = $assurance > 0 ? 'ASSURANCE' : 'FRAIS COMPLÉMENTAIRES';
            $html .= self::ligneMontant($libelle, self::montant($complement, $devise));
        }

        $html .= '<tr class="total-row">'
            . '<td colspan="7" style="border: none;"></td>'
            . '<td style="text-align: right; font-size: 11px; font-weight: 800; background: #1e3a5f;'
            . ' color: #ffffff; border: 1px solid #1e3a5f;">MONTANT TOTAL</td>'
            . '<td style="text-align: right; font-weight: 900; font-size: 13px; background: #e0f2fe;'
            . ' color: #0369a1; border: 1px solid #0369a1;">'
            . View::e(self::montant($montantTotal, $devise))
            // La contre-valeur n'a de sens que si la facture n'est pas deja en euros.
            . ($devise !== 'EUR' && $montantEur > 0
                ? '<br><span style="font-size: 9.5px; font-weight: 600; color: #0284c7;">'
                    . View::e('≈ ' . number_format($montantEur, 2, ',', ' ') . ' EUR') . '</span>'
                : '')
            . '</td></tr>';

        if ($facture !== null) {
            $html .= self::ligneMontant(
                'DÉJÀ ENCAISSÉ',
                self::montant($facture->montantEncaisse, $devise),
                '#f0fdf4',
                '#166534'
            );
            $html .= self::ligneMontant(
                'RESTE À PAYER',
                self::montant($facture->montantRestant, $devise),
                '#fef2f2',
                '#991b1b'
            );
            $html .= self::ligneStatut($facture);
        }

        return $html . '</tfoot>';
    }

    private static function ligneMontant(
        string $libelle,
        string $valeur,
        string $fond = '#f8fafc',
        string $couleur = '#1e293b'
    ): string {
        $cellule = 'text-align: right; border: 1px solid #e2e8f0; background: ' . $fond
            . '; color: ' . $couleur . '; font-weight: 700;';

        return '<tr>'
            . '<td colspan="7" style="border: none;"></td>'
            . '<td style="' . $cellule . ' font-size: 10px;">' . View::e($libelle) . '</td>'
            . '<td style="' . $cellule . ' font-size: 11px;">' . View::e($valeur) . '</td>'
            . '</tr>';
    }

    private static function ligneStatut(Facture $facture): string
    {
        [$libelle, $couleur] = match ($facture->statut) {
            'payee' => ['FACTURE PAYÉE', '#15803d'],
            'partiellement_payee' => ['PAYÉE PARTIELLEMENT', '#d97706'],
            'annulee' => ['FACTURE ANNULÉE', '#64748b'],
            default => ['FACTURE ÉMISE / IMPAYÉE', '#2563eb'],
        };

        return '<tr>'
            . '<td colspan="7" style="border: none;"></td>'
            . '<td style="text-align: right; border: 1px solid #cbd5e1; background: #f8fafc;'
            . ' font-weight: 700; font-size: 10px;">STATUT PAIEMENT</td>'
            . '<td style="text-align: right; border: 1px solid #cbd5e1; background: #f8fafc;'
            . ' font-weight: 900; font-size: 10.5px; color: ' . $couleur . ';">'
            . View::e($libelle) . '</td></tr>';
    }

    private static function paiementEtSignatures(?Facture $facture): string
    {
        // Le QR mene au paiement de la facture quand elle existe, au suivi sinon.
        $cible = $facture !== null
            ? 'api/paiements/pay/' . $facture->id
            : 'site/tracking';

        return '<div class="payment-signature-section">'
            . '<div class="payment-row">'
            . '<div class="payment-notice">Les frais de transaction sont à la charge du client.</div>'
            . '<div class="payment-qr-card">'
            . '<img src="' . View::e(View::qrCodeFor($cible)) . '" class="payment-qr-img"'
            . ' alt="' . View::e($facture !== null ? 'QR code de paiement' : 'QR code de suivi') . '">'
            . '<div class="payment-qr-label">'
            . ($facture !== null ? 'SCANNEZ POUR PAYER' : 'SCANNEZ POUR SUIVRE')
            . '</div>'
            . '<div class="payment-qr-sublabel">(Wave / Orange Money)</div>'
            . '</div></div>'
            . '<div class="signatures-row">'
            . '<div class="signature-box">CLIENT (date et visa)</div>'
            . '<div class="signature-box">SOCIÉTÉ (date et visa)</div>'
            . '</div></div>';
    }

    /**
     * @param array<string, mixed> $colis
     */
    private static function pied(array $colis, string $operateur, string $reference): string
    {
        $creation = (string) $colis['created_at'];
        $horodatage = strtotime($creation);
        $suffixe = substr($reference, -3);

        return '<div class="footer-address">'
            . '<strong>ADRESSE : PARIS 17 CHEMIN DES VIGNES 93000 BOBIGNY</strong><br>'
            . 'Tél : +33 7 75 73 27 97 / +33 7 51 19 83 82 / +33 7 45 93 56 92'
            . '</div>'
            . '<table class="agencies-grid-table"><tr>'
            . '<td style="width: 50%; text-align: left;"><strong>ABIDJAN</strong><br>'
            . 'Lun–Ven : 08h–17h | Sam–Dim : 08h–14h30</td>'
            . '<td style="width: 50%; text-align: right;"><strong>PARIS</strong><br>'
            . 'Lun–Sam : 10h30–18h | Dim : 10h–14h</td>'
            . '</tr></table>'
            . '<div class="meta-footer-row">'
            /*
             * Deux dates distinctes. Celle du colis est une donnee du dossier ;
             * celle de l'impression change a chaque tirage. Les confondre, comme
             * le faisait la version precedente, datait un reexemplaire du jour
             * de l'enregistrement.
             */
            . '<div>Colis enregistré le ' . View::e(self::date($creation))
            . ' — imprimé le ' . View::e(date('d/m/Y à H:i')) . ' par ' . View::e($operateur) . '</div>'
            . '<div>Réf. FCO-' . View::e($horodatage !== false ? date('my', $horodatage) : date('my'))
            . '-' . View::e($suffixe) . '</div>'
            . '</div>'
            . '<img src="' . View::asset('images/footer_lbp.png') . '" alt="" class="footer-img">';
    }

    private static function scriptImpression(): string
    {
        return '<script>'
            . 'if(window.location.search.indexOf("autoprint")!==-1){'
            . 'window.addEventListener("load",function(){window.print();});}'
            . '</script>';
    }

    /**
     * Montant dans la devise du colis.
     *
     * Le document ecrivait « FCFA » en dur sur tous ses totaux, y compris pour
     * un colis facture en euros, et melangeait le point et l'espace comme
     * separateur de milliers d'une ligne a l'autre.
     */
    private static function montant(float $valeur, string $devise): string
    {
        $libelle = $devise === 'XOF' ? 'FCFA' : $devise;
        $decimales = $devise === 'XOF' ? 0 : 2;

        return number_format($valeur, $decimales, ',', ' ') . ' ' . $libelle;
    }

    private static function date(string $valeur): string
    {
        $horodatage = strtotime($valeur);

        return $horodatage !== false ? date('d/m/Y', $horodatage) : '—';
    }
}
