<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

/**
 * Manuel d'aide à la saisie, pour les agents de facturation et de colisage.
 *
 * Le contenu est rédactionnel et volontairement écrit ici plutôt que rangé en
 * base : ce sont des règles de travail, elles évoluent avec le code qui les
 * applique et doivent être relues en même temps que lui.
 *
 * Les seuils cités (7 jours de franchise, 500 FCFA par jour, 2 % d'assurance)
 * sont les valeurs par défaut de logistique_settings. Ils sont paramétrables par
 * agence : le texte le dit, pour qu'une agence au tarif différent ne croie pas
 * lire une règle absolue.
 */
final class ColisageGuide
{
    public static function page(): string
    {
        $entete = Ui::pageHeader(
            'Guide d\'utilisation de la saisie',
            'Manuel d\'aide pour assister les agents de facturation et de colisage au quotidien.',
            ['eyebrow' => 'Aide & documentation', 'class' => 'rh-hero-white']
        );

        $onglets = [
            ['cle' => 'saisie', 'label' => '1. Saisie de colis', 'contenu' => self::saisie()],
            ['cle' => 'trajets', 'label' => '2. Types & trajets', 'contenu' => self::trajets()],
            ['cle' => 'tarifs', 'label' => '3. Tarification & emballages', 'contenu' => self::tarifs()],
            ['cle' => 'retrait', 'label' => '4. Retrait & gardiennage', 'contenu' => self::retrait()],
            ['cle' => 'fraude', 'label' => '5. Règles anti-fraude', 'contenu' => self::fraude()],
        ];

        return '<div class="finea-shell"><div class="finea-container">'
            . $entete
            . self::styles()
            . Tabs::panneaux($onglets, 'guide')
            . '</div></div>';
    }

    // ------------------------------------------------------------------
    // Panneaux
    // ------------------------------------------------------------------

    private static function saisie(): string
    {
        $contacts = self::liste([
            '<strong>Recherche rapide</strong> : saisir les premières lettres du nom ou le téléphone pour charger un client existant.',
            '<strong>Création rapide</strong> : remplir l\'encadré pour un nouveau client.',
            '<strong>Numéro de téléphone</strong> : obligatoirement au format international. Il porte les notifications SMS et WhatsApp d\'arrivée en agence.',
            '<strong>Adresse géographique</strong> : ville et quartier de livraison ou de ramassage.',
        ]);

        $pesee = self::liste([
            '<strong>Poids total (kg)</strong> : peser la marchandise et reporter la valeur brute exacte.',
            '<strong>Valeur déclarée</strong> : elle sert de base d\'indemnisation et au calcul de l\'assurance. Une valeur minorée se retourne contre le client en cas de litige.',
            '<strong>Devise</strong> : XOF par défaut, sinon EUR ou USD.',
            '<strong>Agences départ / arrivée</strong> : agence de prise en charge et point de retrait final.',
            '<strong>Assurance</strong> : à cocher pour appliquer la couverture facultative.',
        ]);

        $etapes = self::etapes([
            'Renseigner l\'expéditeur.',
            'Renseigner le destinataire.',
            'Indiquer le poids et la valeur déclarée globale.',
            'Ajouter les lignes de marchandises : produit, quantité, poids unitaire, emballage.',
            'Enregistrer le colis. L\'ERP génère alors le numéro de suivi.',
            'Sur la fiche colis, cliquer sur « Facturer (1 clic) » pour finaliser la facturation.',
        ]);

        return self::encadre(
            'info',
            'Raccourci opérationnel',
            'Pour éviter toute erreur de trajet, passez par la section <strong>Opérations</strong> du menu latéral '
                . 'et saisissez le colis directement sur un vol ou un cargo. Le trajet est alors verrouillé et ne peut plus être changé par inadvertance.'
        )
            . '<div class="guide-grid-2">'
            . Ui::section('Expéditeur & destinataire', '<p>Chaque colis est lié à deux contacts distincts et joignables :</p>' . $contacts)
            . Ui::section('Pesée & fret', $pesee)
            . '</div>'
            . '<div style="margin-top:1.5rem;">'
            . Ui::section('Étapes pas à pas de l\'enregistrement', $etapes)
            . '</div>';
    }

    private static function trajets(): string
    {
        $cartes = [
            [
                'titre' => 'Maritime standard (LB-CI)',
                'exemple' => 'Sacs de vêtements d\'Abidjan vers la France.',
                'texte' => 'Saisir le poids total (75 kg par exemple) et choisir l\'emballage « Sac Bôrô » dans le tableau. '
                    . 'Le système applique le tarif au kilo propre à ce trajet et calcule douane et fret en conséquence.',
            ],
            [
                'titre' => 'Aérien urgent (CA-CI)',
                'exemple' => 'Ordinateur portable ou téléphones.',
                'texte' => 'Cocher l\'assurance et utiliser du papier film. Renseigner marque et valeur avec précision : '
                    . 'c\'est ce que la douane aérienne contrôle.',
            ],
            [
                'titre' => 'Express international (DHL)',
                'exemple' => 'Documents ou enveloppe.',
                'texte' => 'Appliquer le forfait DHL et utiliser l\'emballage carton officiel. '
                    . 'Conserver le numéro de bordereau DHL dans la description.',
            ],
        ];

        $html = '';
        foreach ($cartes as $carte) {
            $html .= '<article class="guide-scenario">'
                . '<h4>' . View::e($carte['titre']) . '</h4>'
                . '<p><strong>Exemple</strong> : ' . View::e($carte['exemple']) . '</p>'
                . '<p>' . $carte['texte'] . '</p>'
                . '</article>';
        }

        return self::encadre(
            'attention',
            'Fret aérien ou maritime',
            'Vérifiez le trajet avant de valider. L\'aérien se facture au kilo et convient aux marchandises légères ou de valeur ; '
                . 'le maritime accepte de plus grands volumes mais impose des délais plus longs.'
        )
            . '<div class="guide-grid-3">' . $html . '</div>';
    }

    private static function tarifs(): string
    {
        $table = ModuleTable::render(
            [
                ['label' => 'Élément'],
                ['label' => 'Formule de calcul'],
                ['label' => 'Explication'],
            ],
            [
                [
                    '<strong>Total ligne marchandise</strong>',
                    '<code>(Nb colis × poids unitaire × prix/kg) + (Qté emballage × prix emballage)</code>',
                    'Montant brut des marchandises et des cartons fournis par l\'agence.',
                ],
                [
                    '<strong>Montant assurance</strong>',
                    '<code>Valeur déclarée × 2 %</code>',
                    'Calculé automatiquement si la case « Assurance souscrite » est cochée.',
                ],
                [
                    '<strong>Grand total</strong>',
                    '<code>Somme des lignes + montant d\'assurance</code>',
                    'Montant final dû par le client, affiché en XOF et en EUR.',
                ],
            ]
        );

        return Ui::section(
            'Comment l\'ERP calcule les tarifs',
            $table
                . self::encadre(
                    'attention',
                    'Cohérence des prix sur une même ligne',
                    'Si vous sélectionnez plusieurs produits sur une même ligne du tableau, ils doivent avoir le même tarif au kilo. '
                        . 'Sinon la validation est bloquée : créez une ligne distincte par tarif.'
                )
        );
    }

    private static function retrait(): string
    {
        $controle = '<p>Avant de remettre un colis physique à son destinataire :</p>'
            . self::encadre(
                'attention',
                'Règle d\'or',
                'La facture liée au colis doit être au statut <strong>payée</strong>. '
                    . 'Si elle est « émise » ou « partiellement payée », refusez la remise et orientez le client vers la caisse.'
            );

        $gardiennage = '<p>Le destinataire dispose de <strong>7 jours de franchise</strong> à compter de l\'arrivée du colis en agence, '
            . 'puis le gardiennage court à <strong>500 FCFA par colis et par jour</strong>.</p>'
            . '<p>Ces deux valeurs sont les réglages par défaut : elles se paramètrent par agence dans '
            . 'Logistique › Délais & gardiennage, et peuvent donc différer sur votre site.</p>'
            . self::liste([
                'L\'ERP calcule seul le dépassement, à partir de la date d\'entrée en rayon.',
                'Les pénalités sont dues au moment du retrait.',
                'Le module Entrepôts affiche à tout moment ce qui est facturable et pour quel montant.',
            ]);

        $identite = '';
        foreach ([
            ['Nom complet', 'Nom de la personne présente au guichet'],
            ['N° de CNI ou passeport', 'Numéro de la pièce d\'identité présentée'],
            ['Téléphone', 'Numéro du récupérateur'],
        ] as $index => [$titre, $aide]) {
            $identite .= '<div class="guide-champ">'
                . '<strong>' . ($index + 1) . '. ' . View::e($titre) . '</strong>'
                . '<span>' . View::e($aide) . '</span>'
                . '</div>';
        }

        return '<div class="guide-grid-2">'
            . Ui::section('Contrôle de facture obligatoire', $controle)
            . Ui::section('Frais de gardiennage en cas de retard', $gardiennage)
            . '</div>'
            . '<div style="margin-top:1.5rem;">'
            . Ui::section(
                'Procédure de retrait',
                '<p>Sur la fiche colis, section « Remise du colis / Retrait », complétez l\'identité du récupérateur :</p>'
                    . '<div class="guide-grid-3">' . $identite . '</div>'
                    . '<p style="margin-top:1.5rem;">Une fois les champs remplis, cliquez sur <strong>Confirmer le retrait</strong>. '
                    . 'Le statut du colis passe à <code>RETIRÉ</code>.</p>',
                'Fiche colis › Remise du colis'
            )
            . '</div>';
    }

    private static function fraude(): string
    {
        $regles = [
            ['ton' => 'interdit', 'titre' => 'Sous-déclaration', 'texte' => 'Ne minorez pas la valeur déclarée des colis lourds. Le système compare le rapport valeur/poids à l\'historique de l\'agence et signale les écarts.'],
            ['ton' => 'interdit', 'titre' => 'Non-cumul des tâches', 'texte' => 'L\'agent qui a saisi le colis ne peut pas en encaisser le paiement. L\'encaissement revient à la caissière ou à un autre utilisateur habilité.'],
            ['ton' => 'vigilance', 'titre' => 'Actions hors horaires', 'texte' => 'Créer ou modifier un colis ou une facture la nuit ou le week-end sans autorisation est enregistré comme suspect.', 'icone' => self::icoreHorloge()],
            ['ton' => 'vigilance', 'titre' => 'Modification après validation', 'texte' => 'Modifier un colis ou une facture déjà clôturée ou payée déclenche une alerte. Obtenez l\'accord écrit de la direction au préalable.'],
        ];

        $html = '';
        foreach ($regles as $regle) {
            $icone = $regle['icone'] ?? '';
            $html .= '<div class="guide-regle guide-regle--' . View::e($regle['ton']) . '">'
                . '<h4>' . $icone . View::e($regle['titre']) . '</h4>'
                . '<p>' . View::e($regle['texte']) . '</p>'
                . '</div>';
        }

        return Ui::section(
            'Surveillance anti-fraude',
            '<p>Chaque action est analysée automatiquement. Ces quatre règles évitent les signalements sur votre profil :</p>'
                . '<div class="guide-grid-2">' . $html . '</div>',
            'Contrôle permanent des saisies'
        );
    }

    // ------------------------------------------------------------------
    // Briques de mise en forme
    // ------------------------------------------------------------------

    /** @param array<int, string> $elements */
    private static function liste(array $elements): string
    {
        $html = '<ul class="guide-liste">';
        foreach ($elements as $element) {
            $html .= '<li>' . $element . '</li>';
        }

        return $html . '</ul>';
    }

    /** @param array<int, string> $etapes */
    private static function etapes(array $etapes): string
    {
        $html = '<ol class="guide-etapes">';
        foreach ($etapes as $etape) {
            $html .= '<li>' . View::e($etape) . '</li>';
        }

        return $html . '</ol>';
    }

    private static function encadre(string $ton, string $titre, string $texte): string
    {
        return '<aside class="guide-encadre guide-encadre--' . View::e($ton) . '">'
            . '<h4>' . View::e($titre) . '</h4>'
            . '<p>' . $texte . '</p>'
            . '</aside>';
    }

    /** Sablier, en SVG : un caractère emoji se rend différemment sur chaque poste. */
    private static function icoreHorloge(): string
    {
        return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"'
            . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"'
            . ' style="vertical-align:-2px; margin-right:6px;">'
            . '<circle cx="12" cy="12" r="9"></circle>'
            . '<polyline points="12 7 12 12 15 14"></polyline></svg>';
    }

    private static function styles(): string
    {
        return '<style>'
            . '.guide-grid-2{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.5rem;}'
            . '.guide-grid-3{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;margin-top:1rem;}'
            . '.guide-liste{padding-left:1.25rem;line-height:1.6;}'
            . '.guide-liste li{margin-bottom:.4rem;}'
            . '.guide-etapes{padding-left:1.5rem;line-height:1.9;}'
            . '.guide-etapes li{margin-bottom:.35rem;}'
            . '.guide-encadre{border-left:4px solid;padding:1rem 1.15rem;border-radius:0 8px 8px 0;margin-bottom:1.5rem;}'
            . '.guide-encadre h4{margin:0 0 .4rem;font-size:.95rem;}'
            . '.guide-encadre p{margin:0;line-height:1.6;}'
            . '.guide-encadre--info{background:#eff6ff;border-color:#3b82f6;}'
            . '.guide-encadre--info h4{color:#1d4ed8;}'
            . '.guide-encadre--attention{background:#fffbeb;border-color:#d97706;}'
            . '.guide-encadre--attention h4{color:#b45309;}'
            . '.guide-scenario{background:var(--finea-surface,#fff);border:1px solid #e2e8f0;border-radius:8px;padding:1.25rem;}'
            . '.guide-scenario h4{margin:0 0 .6rem;color:#1e3a5f;border-bottom:1px solid #e2e8f0;padding-bottom:.5rem;}'
            . '.guide-champ{background:#f8fafc;padding:.75rem;border-radius:6px;border:1px solid #e2e8f0;}'
            . '.guide-champ span{display:block;color:#64748b;font-size:.85rem;margin-top:.2rem;}'
            . '.guide-regle{border-left:4px solid;padding-left:1rem;}'
            . '.guide-regle h4{margin:0 0 .35rem;display:flex;align-items:center;}'
            . '.guide-regle p{font-size:.9rem;color:#475569;margin:0;line-height:1.6;}'
            . '.guide-regle--interdit{border-color:#ef4444;}'
            . '.guide-regle--interdit h4{color:#ef4444;}'
            . '.guide-regle--vigilance{border-color:#d97706;}'
            . '.guide-regle--vigilance h4{color:#d97706;}'
            . '</style>';
    }
}
