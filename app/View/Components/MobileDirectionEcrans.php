<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\Csrf;
use App\Helpers\View;

/**
 * Écrans métier de l'application de direction.
 *
 * Barre d'onglets en bas sur téléphone, rail latéral dès la largeur d'une tablette :
 * le directeur ouvre l'application sur trois appareils différents, la mise en page
 * doit tirer parti de chacun plutôt que d'étirer une maquette de téléphone.
 */
final class MobileDirectionEcrans
{
    /** @var array<int, array{cle: string, url: string, libelle: string, icone: string}> */
    private const ONGLETS = [
        ['cle' => 'tableau', 'url' => 'mobile/tableau-de-bord', 'libelle' => 'Activité', 'icone' => 'tableau'],
        ['cle' => 'validations', 'url' => 'mobile/validations', 'libelle' => 'Validations', 'icone' => 'validation'],
        ['cle' => 'personnel', 'url' => 'mobile/personnel', 'libelle' => 'Personnel', 'icone' => 'personnel'],
        ['cle' => 'anomalies', 'url' => 'mobile/anomalies', 'libelle' => 'Anomalies', 'icone' => 'anomalie'],
        ['cle' => 'reglages', 'url' => 'mobile/reglages', 'libelle' => 'Réglages', 'icone' => 'reglages'],
    ];

    // =================================================================
    // Écrans
    // =================================================================

    /**
     * @param array<string, mixed> $donnees
     */
    public static function pageTableauDeBord(array $donnees, int $validationsEnAttente): string
    {
        $kpis = (array) ($donnees['kpis'] ?? []);
        $agences = (array) ($donnees['agenceStats'] ?? []);
        $indisponibles = (array) ($donnees['indicateursIndisponibles'] ?? []);

        $corps = '';

        if ($indisponibles !== []) {
            $corps .= MobileDirection::alerte(
                'Indicateurs momentanément indisponibles : ' . implode(', ', array_map('strval', $indisponibles)) . '. Ces chiffres ne sont pas à zéro, ils n\'ont pas pu être calculés.',
                'info'
            );
        }

        if ($validationsEnAttente > 0) {
            $corps .= '<a href="' . View::url('mobile/validations') . '" class="banniere-action">'
                . '<span class="banniere-icone">' . MobileDirection::icone('validation', 22) . '</span>'
                . '<span class="banniere-texte">'
                . '<strong>' . $validationsEnAttente . ' décision' . ($validationsEnAttente > 1 ? 's' : '') . ' en attente</strong>'
                . '<small>Workflows, demandes du personnel et paiements</small>'
                . '</span>'
                . MobileDirection::icone('fleche', 18)
                . '</a>';
        }

        $cartes = '';
        foreach ($kpis as $kpi) {
            $ton = (string) ($kpi['tone'] ?? 'neutre');
            $cartes .= '<div class="kpi kpi--' . View::e($ton) . '">'
                . '<span class="kpi-libelle">' . View::e((string) $kpi['label']) . '</span>'
                . '<strong class="kpi-valeur">' . View::e((string) $kpi['value']) . '</strong>'
                . '<small class="kpi-meta">' . View::e((string) ($kpi['meta'] ?? '')) . '</small>'
                . '</div>';
        }
        $corps .= '<div class="grille-kpi">' . $cartes . '</div>';

        if ($agences !== []) {
            $lignes = '';
            foreach ($agences as $a) {
                $ca = (float) ($a['ca_total'] ?? 0);
                $impaye = (float) ($a['impaye'] ?? 0);
                $part = $ca > 0 ? min(100, ($impaye / $ca) * 100) : 0.0;

                $lignes .= '<div class="ligne-agence">'
                    . '<div class="ligne-agence-tete">'
                    . '<strong>' . View::e((string) ($a['agence_name'] ?? 'Agence')) . '</strong>'
                    . '<span>' . self::xof($ca) . '</span>'
                    . '</div>'
                    . '<div class="jauge"><span style="width:' . round(100 - $part, 1) . '%"></span></div>'
                    . '<div class="ligne-agence-pied">'
                    . '<small>' . (int) ($a['nb_factures'] ?? 0) . ' facture(s)</small>'
                    . '<small class="' . ($impaye > 0 ? 'rouge' : 'vert') . '">' . self::xof($impaye) . ' impayé</small>'
                    . '</div>'
                    . '</div>';
            }

            $corps .= self::section('Activité par agence — mois en cours', $lignes);
        }

        return self::coqueApp('Activité', 'tableau', $corps);
    }

    /**
     * @param array<int, array<string, mixed>> $workflows
     * @param array<int, array<string, mixed>> $demandes
     * @param array<int, array<string, mixed>> $paiements
     */
    public static function pageValidations(array $workflows, array $demandes, array $paiements, ?string $succes = null, ?string $erreur = null): string
    {
        $corps = '';
        if ($succes !== null) {
            $corps .= MobileDirection::alerte($succes, 'info');
        }
        if ($erreur !== null) {
            $corps .= MobileDirection::alerte($erreur);
        }

        if ($workflows === [] && $demandes === [] && $paiements === []) {
            return self::coqueApp('Validations', 'validations', $corps . self::vide(
                'valider',
                'Rien en attente',
                'Toutes les demandes qui vous étaient adressées ont été traitées.'
            ));
        }

        if ($workflows !== []) {
            $cartes = '';
            foreach ($workflows as $w) {
                $cartes .= self::carteDecision(
                    (string) $w['process_type'],
                    (string) ($w['employee_name'] ?? 'Employé'),
                    'Étape : ' . (string) $w['current_step'],
                    self::dateCourte((string) $w['created_at']),
                    View::url('mobile/validations/workflow/' . (int) $w['id']),
                    false
                );
            }
            $corps .= self::section('Workflows RH (' . count($workflows) . ')', $cartes);
        }

        if ($demandes !== []) {
            $cartes = '';
            foreach ($demandes as $d) {
                $cartes .= self::carteDecision(
                    (string) $d['request_type'],
                    (string) ($d['employee_name'] ?? 'Employé'),
                    'Statut : ' . (string) $d['status'],
                    self::dateCourte((string) $d['submitted_at']),
                    View::url('mobile/validations/demande/' . (int) $d['id']),
                    true
                );
            }
            $corps .= self::section('Demandes du personnel (' . count($demandes) . ')', $cartes);
        }

        if ($paiements !== []) {
            $cartes = '';
            foreach ($paiements as $p) {
                $cartes .= '<div class="carte">'
                    . '<div class="carte-tete">'
                    . '<strong>' . View::e((string) ($p['prestataire_name'] ?? 'Prestataire')) . '</strong>'
                    . '<span class="montant">' . number_format((float) $p['montant'], 0, ',', ' ') . ' ' . View::e((string) $p['devise']) . '</span>'
                    . '</div>'
                    . '<p class="carte-detail">' . View::e((string) $p['motif']) . '</p>'
                    . '<small class="carte-date">' . View::e(self::dateCourte((string) $p['date_demande'])) . '</small>'
                    . '<form method="post" action="' . View::url('finance/depenses/' . (int) $p['id'] . '/valider') . '" class="actions-decision">'
                    . Form::hidden('_csrf_token', Csrf::token())
                    . '<button type="submit" name="decision" value="rejeter" class="btn-decision btn-decision--rejet">Rejeter</button>'
                    . '<button type="submit" name="decision" value="approuver" class="btn-decision btn-decision--accord">Approuver</button>'
                    . '</form>'
                    . '<small class="carte-note">Traité par le module Finance : double contrôle et écritures comptables.</small>'
                    . '</div>';
            }
            $corps .= self::section('Paiements prestataires (' . count($paiements) . ')', $cartes);
        }

        return self::coqueApp('Validations', 'validations', $corps);
    }

    /**
     * @param array<int, array<string, mixed>> $employes
     * @param array<int, array<string, string>> $alertes
     * @param array<int, array<string, mixed>> $meilleurs
     */
    public static function pagePersonnel(array $employes, array $alertes, array $meilleurs): string
    {
        $actifs = count($employes);
        $presents = 0;
        foreach ($employes as $e) {
            if (($e['taux_presence'] ?? null) !== null && (float) $e['taux_presence'] >= 80) {
                $presents++;
            }
        }

        $corps = '<div class="grille-kpi grille-kpi--trio">'
            . '<div class="kpi"><span class="kpi-libelle">Effectif actif</span><strong class="kpi-valeur">' . $actifs . '</strong></div>'
            . '<div class="kpi kpi--success"><span class="kpi-libelle">Assidus</span><strong class="kpi-valeur">' . $presents . '</strong><small class="kpi-meta">Présence ≥ 80 %</small></div>'
            . '<div class="kpi ' . ($alertes !== [] ? 'kpi--warning' : '') . '"><span class="kpi-libelle">Alertes</span><strong class="kpi-valeur">' . count($alertes) . '</strong></div>'
            . '</div>';

        if ($alertes !== []) {
            $lignes = '';
            foreach (array_slice($alertes, 0, 25) as $a) {
                $lignes .= '<div class="ligne-alerte">'
                    . '<span class="etiquette">' . View::e((string) $a['type']) . '</span>'
                    . '<strong>' . View::e((string) $a['employee']) . '</strong>'
                    . '<small>' . View::e((string) $a['detail']) . '</small>'
                    . '</div>';
            }
            $corps .= self::section('Points de vigilance', $lignes);
        }

        if ($meilleurs !== []) {
            $lignes = '';
            foreach ($meilleurs as $m) {
                $score = (int) ($m['score_integrite'] ?? 0);
                $lignes .= '<div class="ligne-score">'
                    . '<div class="ligne-score-nom">'
                    . '<strong>' . View::e((string) $m['full_name']) . '</strong>'
                    . '<small>' . View::e((string) ($m['site_name'] ?? 'Agence')) . '</small>'
                    . '</div>'
                    . '<span class="pastille-score ' . self::tonScore($score) . '">' . $score . '</span>'
                    . '</div>';
            }
            $corps .= self::section('Meilleurs scores d\'intégrité', $lignes);
        }

        $lignes = '';
        foreach ($employes as $e) {
            $taux = $e['taux_presence'] ?? null;
            $score = (int) ($e['score_integrite'] ?? 0);
            $lignes .= '<div class="ligne-employe">'
                . '<div class="ligne-employe-nom">'
                . '<strong>' . View::e((string) $e['full_name']) . '</strong>'
                . '<small>' . View::e((string) ($e['function_name'] ?? 'Agent')) . ' · ' . View::e((string) ($e['site_name'] ?? '—')) . '</small>'
                . '</div>'
                . '<div class="ligne-employe-chiffres">'
                . '<span class="mini">' . ($taux !== null ? round((float) $taux) . ' %' : '—') . '</span>'
                . '<span class="pastille-score ' . self::tonScore($score) . '">' . $score . '</span>'
                . '</div>'
                . '</div>';
        }
        $corps .= self::section('Tout le personnel (' . $actifs . ')', $lignes !== '' ? $lignes : self::vide('personnel', 'Aucun employé actif', 'Le registre du personnel est vide.'));

        return self::coqueApp('Personnel', 'personnel', $corps);
    }

    /**
     * @param array<int, array<string, mixed>> $signalements
     */
    public static function pageAnomalies(array $signalements): string
    {
        if ($signalements === []) {
            return self::coqueApp('Anomalies', 'anomalies', self::vide(
                'valider',
                'Aucun signalement',
                'Aucune anomalie détectée sur la période analysée.'
            ));
        }

        $parDegre = [4 => 0, 3 => 0, 2 => 0];
        foreach ($signalements as $s) {
            $d = (int) $s['degre'];
            if (isset($parDegre[$d])) {
                $parDegre[$d]++;
            }
        }

        $corps = '<div class="grille-kpi grille-kpi--trio">'
            . '<div class="kpi kpi--danger"><span class="kpi-libelle">Très graves</span><strong class="kpi-valeur">' . $parDegre[4] . '</strong></div>'
            . '<div class="kpi kpi--warning"><span class="kpi-libelle">Graves</span><strong class="kpi-valeur">' . $parDegre[3] . '</strong></div>'
            . '<div class="kpi"><span class="kpi-libelle">Moyens</span><strong class="kpi-valeur">' . $parDegre[2] . '</strong></div>'
            . '</div>';

        $cartes = '';
        foreach (array_slice($signalements, 0, 60) as $s) {
            $degre = (int) $s['degre'];
            $montant = (float) ($s['montant'] ?? 0);

            $cartes .= '<div class="carte carte--signalement degre-' . $degre . '">'
                . '<div class="carte-tete">'
                . '<span class="etiquette etiquette--' . View::e((string) $s['badgeTone']) . '">' . View::e((string) $s['gravite']) . '</span>'
                . ($montant > 0 ? '<span class="montant montant--rouge">' . self::xof($montant) . '</span>' : '')
                . '</div>'
                . '<strong class="carte-titre">' . View::e((string) $s['type']) . '</strong>'
                . '<div class="carte-qui">' . View::e((string) $s['employee']) . ' · ' . View::e((string) $s['agence']) . '</div>'
                . '<p class="carte-detail">' . View::e((string) $s['description']) . '</p>'
                . '<small class="carte-date">' . View::e(self::dateCourte((string) $s['date'])) . '</small>'
                . '</div>';
        }

        $corps .= self::section('Signalements (' . count($signalements) . ')', $cartes);

        return self::coqueApp('Anomalies', 'anomalies', $corps);
    }

    /**
     * @param array<int, array<string, mixed>> $appareils
     */
    public static function pageReglages(string $nom, array $appareils, ?string $succes = null): string
    {
        $corps = $succes !== null ? MobileDirection::alerte($succes, 'info') : '';

        $corps .= '<div class="carte carte--profil">'
            . '<img src="' . View::asset('images/mobile/icone-192.png') . '" alt="" class="profil-logo">'
            . '<div><strong>' . View::e($nom) . '</strong><small>Application de direction</small></div>'
            . '</div>';

        $corps .= self::section('Notifications',
            '<div class="ligne-reglage">'
            . '<div><strong>Alertes sur ce téléphone</strong><small id="etat-push">Vérification…</small></div>'
            . '<button type="button" class="btn-bascule" id="bascule-push" disabled>—</button>'
            . '</div>'
            . '<p class="aide-reglage">Écarts de caisse, signalements graves et nouvelles décisions à prendre vous parviennent même application fermée.</p>'
        );

        $lignes = '';
        foreach ($appareils as $a) {
            $lignes .= '<div class="ligne-appareil">'
                . '<div><strong>' . View::e((string) ($a['label'] ?? 'Appareil')) . '</strong>'
                . '<small>Dernière ouverture : ' . View::e($a['last_unlocked_at'] !== null ? self::dateCourte((string) $a['last_unlocked_at']) : 'jamais') . '</small></div>'
                . '</div>';
        }
        $corps .= self::section('Mes appareils (' . count($appareils) . ')',
            $lignes . '<p class="aide-reglage">Chaque appareil possède son propre code. Verrouiller ici ne déconnecte pas les autres.</p>'
        );

        $corps .= '<form method="post" action="' . View::url('mobile/verrouiller') . '" class="bloc-bouton">'
            . Form::hidden('_csrf_token', Csrf::token())
            . '<button type="submit" class="bouton bouton--fantome">' . MobileDirection::icone('cadenas') . ' Verrouiller l\'application</button>'
            . '</form>';

        $corps .= '<form method="post" action="' . View::url('mobile/oublier-appareil') . '" class="bloc-bouton" onsubmit="return confirm(\'Cet appareil sera désappairé. Vous devrez saisir votre mot de passe pour le réutiliser. Continuer ?\');">'
            . Form::hidden('_csrf_token', Csrf::token())
            . '<button type="submit" class="bouton bouton--danger">' . MobileDirection::icone('sortie') . ' Désappairer cet appareil</button>'
            . '</form>';

        return self::coqueApp('Réglages', 'reglages', $corps, self::scriptPush());
    }

    public static function pageHorsLigne(): string
    {
        $contenu = '<main class="ecran-install">'
            . '<div class="install-carte">'
            . '<span class="hors-ligne-icone">' . MobileDirection::icone('hors-ligne', 44) . '</span>'
            . '<h1 class="install-titre">Pas de connexion</h1>'
            . '<p class="install-sous-titre">Les chiffres de pilotage sont toujours lus en direct : ils ne sont jamais affichés depuis une copie qui pourrait être périmée.</p>'
            . '<button type="button" class="bouton bouton--or" onclick="location.reload()">Réessayer</button>'
            . '</div>'
            . '</main>';

        return MobileDirection::coque('Hors ligne', $contenu);
    }

    // =================================================================
    // Coque applicative
    // =================================================================

    private static function coqueApp(string $titre, string $ongletActif, string $corps, string $scripts = ''): string
    {
        $nav = '';
        foreach (self::ONGLETS as $onglet) {
            $actif = $onglet['cle'] === $ongletActif ? ' actif' : '';
            $nav .= '<a href="' . View::url($onglet['url']) . '" class="onglet' . $actif . '">'
                . MobileDirection::icone($onglet['icone'], 22)
                . '<span>' . View::e($onglet['libelle']) . '</span>'
                . '</a>';
        }

        $contenu = '<div class="app">'
            . '<header class="app-entete">'
            . '<img src="' . View::asset('images/mobile/icone-192.png') . '" alt="" class="entete-logo">'
            . '<h1>' . View::e($titre) . '</h1>'
            . '</header>'
            . '<main class="app-corps">' . $corps . '</main>'
            . '<nav class="app-nav">' . $nav . '</nav>'
            . '</div>';

        return MobileDirection::coque($titre, $contenu, [
            'scripts' => self::stylesApp() . $scripts,
        ]);
    }

    // =================================================================
    // Fragments
    // =================================================================

    private static function section(string $titre, string $contenu): string
    {
        return '<section class="bloc">'
            . '<h2 class="bloc-titre">' . View::e($titre) . '</h2>'
            . $contenu
            . '</section>';
    }

    private static function carteDecision(string $titre, string $qui, string $detail, string $date, string $action, bool $avecMotif): string
    {
        return '<div class="carte">'
            . '<strong class="carte-titre">' . View::e($titre) . '</strong>'
            . '<div class="carte-qui">' . View::e($qui) . '</div>'
            . '<p class="carte-detail">' . View::e($detail) . '</p>'
            . '<small class="carte-date">' . View::e($date) . '</small>'
            . '<form method="post" action="' . $action . '" class="actions-decision">'
            . Form::hidden('_csrf_token', Csrf::token())
            . ($avecMotif ? '<input type="text" name="comment" class="saisie-motif" placeholder="Motif (facultatif)">' : '')
            . '<button type="submit" name="decision" value="reject" class="btn-decision btn-decision--rejet">Rejeter</button>'
            . '<button type="submit" name="decision" value="approve" class="btn-decision btn-decision--accord">Approuver</button>'
            . '</form>'
            . '</div>';
    }

    private static function vide(string $icone, string $titre, string $detail): string
    {
        return '<div class="etat-vide">'
            . MobileDirection::icone($icone, 42)
            . '<strong>' . View::e($titre) . '</strong>'
            . '<small>' . View::e($detail) . '</small>'
            . '</div>';
    }

    private static function xof(float $montant): string
    {
        return number_format($montant, 0, ',', ' ') . ' XOF';
    }

    private static function dateCourte(string $date): string
    {
        $ts = strtotime($date);

        return $ts === false ? $date : date('d/m/Y à H:i', $ts);
    }

    private static function tonScore(int $score): string
    {
        if ($score >= 80) {
            return 'score--bon';
        }

        return $score >= 55 ? 'score--moyen' : 'score--faible';
    }

    // =================================================================
    // Styles et scripts propres aux écrans
    // =================================================================

    private static function stylesApp(): string
    {
        $css = <<<CSS
.app{min-height:100svh;display:flex;flex-direction:column;background:var(--fond)}
.app-entete{position:sticky;top:0;z-index:20;display:flex;align-items:center;gap:11px;padding:calc(12px + var(--sat)) 18px 12px;background:rgba(255,255,255,.86);backdrop-filter:saturate(180%) blur(14px);-webkit-backdrop-filter:saturate(180%) blur(14px);border-bottom:1px solid var(--bord)}
.entete-logo{width:32px;height:32px;border-radius:9px;object-fit:contain;border:1px solid var(--bord)}
.app-entete h1{margin:0;font-size:1.12rem;font-weight:700;color:var(--marine);letter-spacing:-.2px}
.app-corps{flex:1;padding:16px 16px calc(96px + var(--sab));max-width:900px;width:100%;margin:0 auto}
.app-nav{position:fixed;left:0;right:0;bottom:0;z-index:30;display:flex;background:rgba(255,255,255,.94);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border-top:1px solid var(--bord);padding-bottom:var(--sab)}
.onglet{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;padding:9px 2px 7px;color:var(--gris-clair);text-decoration:none;font-size:.67rem;font-weight:600;transition:color .15s}
.onglet span{letter-spacing:-.1px}
.onglet.actif{color:var(--marine)}
.onglet.actif .ico{transform:translateY(-1px)}
.onglet:active{opacity:.6}

.bloc{margin-top:22px}
.bloc-titre{margin:0 0 11px;font-size:.76rem;font-weight:800;color:var(--gris);text-transform:uppercase;letter-spacing:.6px}

.grille-kpi{display:grid;grid-template-columns:repeat(2,1fr);gap:11px}
.grille-kpi--trio{grid-template-columns:repeat(3,1fr)}
.kpi{background:var(--blanc);border:1px solid var(--bord);border-radius:var(--r);padding:13px 14px;display:flex;flex-direction:column;gap:3px;box-shadow:var(--ombre)}
.kpi-libelle{font-size:.72rem;font-weight:700;color:var(--gris);text-transform:uppercase;letter-spacing:.3px}
.kpi-valeur{font-size:1.24rem;font-weight:800;color:var(--marine);letter-spacing:-.4px;line-height:1.2}
.kpi-meta{font-size:.71rem;color:var(--gris-clair);line-height:1.35}
.kpi--success{border-left:3px solid var(--vert)}
.kpi--success .kpi-valeur{color:var(--vert)}
.kpi--warning{border-left:3px solid var(--ambre)}
.kpi--danger{border-left:3px solid var(--rouge)}
.kpi--danger .kpi-valeur{color:var(--rouge)}

.banniere-action{display:flex;align-items:center;gap:12px;background:linear-gradient(135deg,var(--marine),var(--marine-clair));color:#fff;padding:15px 16px;border-radius:var(--r);text-decoration:none;box-shadow:0 8px 22px rgba(29,43,87,.26);margin-bottom:18px}
.banniere-icone{flex:none;width:40px;height:40px;border-radius:11px;background:rgba(250,189,2,.18);color:var(--or);display:flex;align-items:center;justify-content:center}
.banniere-texte{flex:1;display:flex;flex-direction:column;gap:2px}
.banniere-texte strong{font-size:.98rem}
.banniere-texte small{font-size:.79rem;opacity:.72}

.carte{background:var(--blanc);border:1px solid var(--bord);border-radius:var(--r);padding:14px 15px;margin-bottom:11px;box-shadow:var(--ombre)}
.carte-tete{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:8px}
.carte-titre{display:block;font-size:.98rem;color:var(--encre);margin-bottom:3px;line-height:1.35}
.carte-qui{font-size:.84rem;color:var(--marine);font-weight:600;margin-bottom:6px}
.carte-detail{margin:0 0 8px;font-size:.85rem;color:var(--gris);line-height:1.5}
.carte-date{display:block;font-size:.75rem;color:var(--gris-clair)}
.carte-note{display:block;margin-top:8px;font-size:.72rem;color:var(--gris-clair);line-height:1.4}
.carte--signalement{border-left:3px solid var(--bord)}
.carte--signalement.degre-4{border-left-color:var(--rouge)}
.carte--signalement.degre-3{border-left-color:var(--ambre)}
.montant{font-weight:800;color:var(--marine);font-size:.95rem;white-space:nowrap}
.montant--rouge{color:var(--rouge)}

.etiquette{display:inline-block;padding:3px 9px;border-radius:20px;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.4px;background:#f1f5f9;color:var(--gris)}
.etiquette--danger{background:#fef2f2;color:#b91c1c}
.etiquette--warning{background:#fffbeb;color:#b45309}

.actions-decision{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap}
.saisie-motif{flex:1 1 100%;padding:10px 12px;border:1.5px solid var(--bord);border-radius:var(--r-sm);font-size:16px;font-family:inherit}
.saisie-motif:focus{outline:none;border-color:var(--marine)}
.btn-decision{flex:1;padding:12px;border:none;border-radius:var(--r-sm);font-size:.9rem;font-weight:700;font-family:inherit;cursor:pointer;min-height:46px;transition:transform .1s}
.btn-decision:active{transform:scale(.97)}
.btn-decision--accord{background:var(--vert);color:#fff}
.btn-decision--rejet{background:#fff;color:var(--rouge);border:1.5px solid #fecaca}

.ligne-agence{background:var(--blanc);border:1px solid var(--bord);border-radius:var(--r);padding:13px 15px;margin-bottom:9px}
.ligne-agence-tete{display:flex;justify-content:space-between;align-items:baseline;gap:10px;font-size:.92rem}
.ligne-agence-tete strong{color:var(--encre)}
.ligne-agence-tete span{font-weight:700;color:var(--marine);white-space:nowrap}
.ligne-agence-pied{display:flex;justify-content:space-between;margin-top:6px;font-size:.76rem;color:var(--gris-clair)}
.jauge{height:6px;background:#eef2f7;border-radius:3px;overflow:hidden;margin-top:9px}
.jauge span{display:block;height:100%;background:linear-gradient(90deg,var(--vert),#34d399);border-radius:3px}
.rouge{color:var(--rouge);font-weight:600}
.vert{color:var(--vert);font-weight:600}

.ligne-alerte,.ligne-score,.ligne-employe,.ligne-appareil,.ligne-reglage{background:var(--blanc);border:1px solid var(--bord);border-radius:var(--r);padding:12px 14px;margin-bottom:8px}
.ligne-alerte{display:flex;flex-direction:column;gap:3px}
.ligne-alerte strong{font-size:.9rem}
.ligne-alerte small{font-size:.8rem;color:var(--gris);line-height:1.45}
.ligne-alerte .etiquette{align-self:flex-start;margin-bottom:2px}
.ligne-score,.ligne-employe,.ligne-appareil,.ligne-reglage{display:flex;justify-content:space-between;align-items:center;gap:12px}
.ligne-score-nom,.ligne-employe-nom,.ligne-appareil div,.ligne-reglage div{display:flex;flex-direction:column;gap:2px;min-width:0}
.ligne-score-nom strong,.ligne-employe-nom strong,.ligne-appareil strong,.ligne-reglage strong{font-size:.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ligne-score-nom small,.ligne-employe-nom small,.ligne-appareil small,.ligne-reglage small{font-size:.77rem;color:var(--gris-clair)}
.ligne-employe-chiffres{display:flex;align-items:center;gap:10px;flex:none}
.mini{font-size:.82rem;color:var(--gris);font-weight:600}
.pastille-score{min-width:38px;text-align:center;padding:5px 8px;border-radius:9px;font-size:.85rem;font-weight:800}
.score--bon{background:#ecfdf5;color:#047857}
.score--moyen{background:#fffbeb;color:#b45309}
.score--faible{background:#fef2f2;color:#b91c1c}

.carte--profil{display:flex;align-items:center;gap:13px}
.profil-logo{width:46px;height:46px;border-radius:12px;object-fit:contain;border:1px solid var(--bord)}
.carte--profil div{display:flex;flex-direction:column;gap:2px}
.carte--profil strong{font-size:1rem;color:var(--marine)}
.carte--profil small{font-size:.79rem;color:var(--gris-clair)}
.aide-reglage{margin:8px 2px 0;font-size:.78rem;color:var(--gris-clair);line-height:1.5}
.btn-bascule{flex:none;min-width:84px;padding:9px 12px;border-radius:20px;border:1.5px solid var(--bord);background:#fff;font-size:.8rem;font-weight:700;font-family:inherit;cursor:pointer;color:var(--gris)}
.btn-bascule.actif{background:var(--vert);border-color:var(--vert);color:#fff}
.bloc-bouton{margin-top:14px}
.bouton--danger{background:#fff;color:var(--rouge);border:1.5px solid #fecaca}

.etat-vide{display:flex;flex-direction:column;align-items:center;text-align:center;gap:7px;padding:52px 24px;color:var(--gris-clair)}
.etat-vide strong{font-size:1.04rem;color:var(--encre)}
.etat-vide small{font-size:.85rem;line-height:1.5;max-width:280px}
.hors-ligne-icone{display:inline-flex;color:var(--gris-clair)}

/* Tablette : rail lateral, l ecran est assez large pour tout montrer d un coup */
@media (min-width:768px){
  .app{flex-direction:row}
  .app-nav{position:sticky;top:0;left:0;right:auto;bottom:auto;height:100svh;width:206px;flex-direction:column;border-top:none;border-right:1px solid var(--bord);padding:calc(20px + var(--sat)) 12px 20px;gap:4px}
  .onglet{flex:none;flex-direction:row;justify-content:flex-start;gap:12px;padding:12px 14px;border-radius:var(--r-sm);font-size:.9rem}
  .onglet.actif{background:rgba(29,43,87,.07)}
  .app-entete{position:static;background:none;backdrop-filter:none;-webkit-backdrop-filter:none;border-bottom:none;padding:calc(24px + var(--sat)) 28px 4px}
  .app-entete h1{font-size:1.5rem}
  .app-corps{padding:12px 28px 40px;max-width:none}
  .grille-kpi{grid-template-columns:repeat(4,1fr)}
  .grille-kpi--trio{grid-template-columns:repeat(3,1fr)}
  .app>div:first-child{flex:1}
}
@media (min-width:768px){
  .app{display:flex}
  .app-nav{order:-1}
}
@media (min-width:1100px){
  .grille-kpi{grid-template-columns:repeat(4,1fr)}
  .app-corps{padding-left:40px;padding-right:40px}
}
CSS;

        // La feuille est injectée par script : la coque n'accepte qu'un bloc de style
        // et cette partie ne concerne que les écrans applicatifs.
        $css = str_replace(['\\', '`', '${'], ['\\\\', '\\`', '\\${'], $css);

        return "(function(){var s=document.createElement('style');s.textContent=`{$css}`;document.head.appendChild(s);})();";
    }

    private static function scriptPush(): string
    {
        $cleUrl = View::url('mobile/push/cle-publique');
        $abonnerUrl = View::url('mobile/push/abonner');
        $desabonnerUrl = View::url('mobile/push/desabonner');
        $jeton = Csrf::token();

        return <<<JS
(function(){
  var bouton=document.getElementById('bascule-push'), etat=document.getElementById('etat-push');
  if(!bouton||!etat){return;}

  var supporte=('serviceWorker' in navigator)&&('PushManager' in window)&&('Notification' in window);
  var autonome=window.matchMedia('(display-mode: standalone)').matches||window.navigator.standalone===true;
  var estApple=/iPhone|iPad|iPod/.test(navigator.userAgent);

  if(!supporte){
    etat.textContent=estApple&&!autonome
      ? "Ajoutez d'abord l'application à votre écran d'accueil"
      : 'Non pris en charge par ce navigateur';
    return;
  }
  if(estApple&&!autonome){
    etat.textContent="Ajoutez d'abord l'application à votre écran d'accueil";
    return;
  }

  function b64(chaine){
    var p='='.repeat((4-chaine.length%4)%4);
    var b=(chaine+p).replace(/-/g,'+').replace(/_/g,'/');
    var brut=atob(b), tab=new Uint8Array(brut.length);
    for(var i=0;i<brut.length;i++){tab[i]=brut.charCodeAt(i);}
    return tab;
  }
  function poster(url,corps){
    return fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify(Object.assign({_csrf_token:'{$jeton}'},corps))});
  }
  function peindre(actif){
    bouton.disabled=false;
    bouton.textContent=actif?'Activées':'Activer';
    bouton.classList.toggle('actif',actif);
    etat.textContent=actif?'Vous recevez les alertes sur cet appareil':'Aucune alerte sur cet appareil';
  }

  navigator.serviceWorker.ready.then(function(reg){
    return reg.pushManager.getSubscription().then(function(ab){peindre(!!ab);});
  }).catch(function(){etat.textContent='Indisponible';});

  bouton.addEventListener('click',function(){
    bouton.disabled=true;
    navigator.serviceWorker.ready.then(function(reg){
      return reg.pushManager.getSubscription().then(function(ab){
        if(ab){
          return poster('{$desabonnerUrl}',{endpoint:ab.endpoint})
            .then(function(){return ab.unsubscribe();})
            .then(function(){peindre(false);});
        }
        return Notification.requestPermission().then(function(perm){
          if(perm!=='granted'){
            etat.textContent='Autorisation refusée dans les réglages du téléphone';
            bouton.disabled=false;
            return;
          }
          return fetch('{$cleUrl}').then(function(r){return r.json();}).then(function(d){
            if(!d.cle){throw new Error('cle absente');}
            return reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:b64(d.cle)});
          }).then(function(ab2){
            var j=ab2.toJSON();
            return poster('{$abonnerUrl}',{endpoint:j.endpoint,p256dh:j.keys.p256dh,auth:j.keys.auth});
          }).then(function(){peindre(true);});
        });
      });
    }).catch(function(){
      etat.textContent="L'activation a échoué";
      bouton.disabled=false;
    });
  });
})();
JS;
    }
}
