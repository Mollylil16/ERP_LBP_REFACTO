<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\Csrf;
use App\Helpers\View;

/**
 * Coque et écrans d'accès de l'application de direction.
 *
 * Tout le rendu est produit ici : les vues n'appellent que ces méthodes statiques.
 * Les icônes sont des tracés SVG définis dans self::icone(), jamais des emoji, afin
 * de garder un trait homogène et une couleur pilotée par le thème.
 */
final class MobileDirection
{
    public const MARINE = '#1d2b57';
    public const OR = '#fabd02';

    // =================================================================
    // Coque
    // =================================================================

    /**
     * Enveloppe complète d'une page : en-tête HTML, feuille de style et scripts.
     *
     * @param array<string, mixed> $options
     */
    public static function coque(string $titre, string $contenu, array $options = []): string
    {
        $sombre = (bool) ($options['sombre'] ?? false);
        $scripts = (string) ($options['scripts'] ?? '');
        $couleurBarre = $sombre ? self::MARINE : '#ffffff';

        return '<!DOCTYPE html><html lang="fr"><head>'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=5">'
            . '<title>' . View::e($titre) . ' — LBP Direction</title>'
            . '<meta name="theme-color" content="' . $couleurBarre . '">'
            . '<meta name="color-scheme" content="light">'
            // Sur iOS, ces deux balises font disparaître la barre Safari une fois
            // l'application ajoutée à l'écran d'accueil.
            . '<meta name="apple-mobile-web-app-capable" content="yes">'
            . '<meta name="apple-mobile-web-app-status-bar-style" content="' . ($sombre ? 'black-translucent' : 'default') . '">'
            . '<meta name="apple-mobile-web-app-title" content="LBP Direction">'
            . '<meta name="mobile-web-app-capable" content="yes">'
            . '<link rel="manifest" href="' . View::url('mobile/manifest.webmanifest') . '">'
            . '<link rel="apple-touch-icon" href="' . View::asset('images/mobile/icone-apple-180.png') . '">'
            . '<link rel="icon" type="image/png" href="' . View::asset('images/mobile/icone-192.png') . '">'
            . '<style>' . self::styles() . '</style>'
            . '</head>'
            . '<body class="' . ($sombre ? 'fond-marine' : 'fond-clair') . '">'
            . $contenu
            . '<script>' . self::scriptCommun() . $scripts . '</script>'
            . '</body></html>';
    }

    // =================================================================
    // Écrans d'accès
    // =================================================================

    public static function pageConnexion(?string $erreur = null): string
    {
        $contenu = '<main class="ecran-acces">'
            . '<div class="acces-carte">'
            . self::marque('Application de direction')
            . ($erreur !== null ? self::alerte($erreur) : '')
            . '<form method="post" action="' . View::url('mobile/connexion') . '" class="pile" autocomplete="on">'
            . Form::hidden('_csrf_token', Csrf::token())
            . '<label class="champ">'
            . '<span class="champ-libelle">Adresse email</span>'
            . '<input class="champ-saisie" type="email" name="email" inputmode="email" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>'
            . '</label>'
            . '<label class="champ">'
            . '<span class="champ-libelle">Mot de passe</span>'
            . '<input class="champ-saisie" type="password" name="password" autocomplete="current-password" required>'
            . '</label>'
            . '<button type="submit" class="bouton bouton--or">Se connecter</button>'
            . '</form>'
            . '<p class="note-acces">Cette étape n\'a lieu qu\'une fois par appareil. Vous choisirez ensuite un code à six chiffres pour vos ouvertures quotidiennes.</p>'
            . '</div>'
            . '</main>';

        return self::coque('Connexion', $contenu, ['sombre' => true]);
    }

    public static function pageCreerCode(?string $erreur = null): string
    {
        $contenu = '<main class="ecran-acces">'
            . '<div class="acces-carte">'
            . self::marque('Choisissez votre code')
            . ($erreur !== null ? self::alerte($erreur) : '')
            . '<p class="note-acces note-acces--haut">Ce code remplacera votre mot de passe sur cet appareil. Évitez une suite ou un chiffre répété.</p>'
            . '<form method="post" action="' . View::url('mobile/creer-code') . '" class="pile" id="form-code">'
            . Form::hidden('_csrf_token', Csrf::token())
            . '<label class="champ">'
            . '<span class="champ-libelle">Nouveau code</span>'
            . '<input class="champ-saisie champ-saisie--code" type="password" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="new-password" required autofocus>'
            . '</label>'
            . '<label class="champ">'
            . '<span class="champ-libelle">Confirmez le code</span>'
            . '<input class="champ-saisie champ-saisie--code" type="password" name="confirmation" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="new-password" required>'
            . '</label>'
            . '<button type="submit" class="bouton bouton--or">Enregistrer le code</button>'
            . '</form>'
            . '</div>'
            . '</main>';

        return self::coque('Créer un code', $contenu, ['sombre' => true]);
    }

    public static function pageVerrouillage(string $nom, int $secondesBlocage, ?string $erreur = null): string
    {
        $bloque = $secondesBlocage > 0;

        $pave = '';
        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $chiffre) {
            $pave .= '<button type="button" class="touche" data-chiffre="' . $chiffre . '">' . $chiffre . '</button>';
        }
        $pave = '<div class="pave">'
            . $pave
            . '<button type="button" class="touche touche--vide" disabled></button>'
            . '<button type="button" class="touche" data-chiffre="0">0</button>'
            . '<button type="button" class="touche touche--action" data-action="effacer" aria-label="Effacer">' . self::icone('effacer') . '</button>'
            . '</div>';

        $points = '';
        for ($i = 0; $i < 6; $i++) {
            $points .= '<span class="point" data-index="' . $i . '"></span>';
        }

        $contenu = '<main class="ecran-verrou">'
            . '<div class="verrou-haut">'
            . '<img src="' . View::asset('images/mobile/icone-192.png') . '" alt="" class="verrou-logo">'
            . '<h1 class="verrou-nom">' . View::e($nom) . '</h1>'
            . '<p class="verrou-invite" id="invite">' . ($bloque
                ? 'Application bloquée. Réessayez dans ' . ceil($secondesBlocage / 60) . ' minute(s).'
                : 'Saisissez votre code') . '</p>'
            . '<div class="points" id="points">' . $points . '</div>'
            . ($erreur !== null ? '<p class="verrou-erreur">' . View::e($erreur) . '</p>' : '')
            . '</div>'
            . '<form method="post" action="' . View::url('mobile/verrouillage') . '" id="form-verrou" class="verrou-bas">'
            . Form::hidden('_csrf_token', Csrf::token())
            . '<input type="hidden" name="code" id="code-saisi">'
            . ($bloque ? '<div class="pave pave--bloque">' . self::icone('cadenas') . '<span>Réessayez plus tard</span></div>' : $pave)
            . '<form method="post" action="' . View::url('mobile/oublier-appareil') . '" class="lien-oubli-form">'
            . '</form>'
            . '</form>'
            . '<form method="post" action="' . View::url('mobile/oublier-appareil') . '" class="zone-oubli">'
            . Form::hidden('_csrf_token', Csrf::token())
            . '<button type="submit" class="lien-discret">Utiliser un autre compte</button>'
            . '</form>'
            . '</main>';

        return self::coque('Déverrouillage', $contenu, [
            'sombre' => true,
            'scripts' => $bloque ? '' : self::scriptPave(),
        ]);
    }

    public static function pageInstallation(string $plateforme): string
    {
        $etapes = match ($plateforme) {
            'iphone', 'ipad' => [
                ['partager', 'Appuyez sur le bouton Partager', 'Il se trouve en bas de l\'écran sur iPhone, en haut à droite sur iPad.'],
                ['plus', 'Choisissez « Sur l\'écran d\'accueil »', 'Faites défiler la liste des actions jusqu\'à cette ligne.'],
                ['valider', 'Confirmez avec « Ajouter »', 'L\'icône LBP apparaît alors sur votre écran d\'accueil.'],
            ],
            default => [
                ['menu', 'Ouvrez le menu du navigateur', 'Les trois points en haut à droite de Chrome.'],
                ['plus', 'Choisissez « Installer l\'application »', 'Ou « Ajouter à l\'écran d\'accueil » selon la version.'],
                ['valider', 'Confirmez', 'L\'icône LBP apparaît sur votre écran d\'accueil.'],
            ],
        };

        $listeEtapes = '';
        $numero = 0;
        foreach ($etapes as [$icone, $titre, $detail]) {
            $numero++;
            $listeEtapes .= '<li class="etape">'
                . '<span class="etape-num">' . $numero . '</span>'
                . '<span class="etape-icone">' . self::icone($icone) . '</span>'
                . '<span class="etape-texte"><strong>' . View::e($titre) . '</strong><small>' . View::e($detail) . '</small></span>'
                . '</li>';
        }

        $estApple = $plateforme === 'iphone' || $plateforme === 'ipad';

        $contenu = '<main class="ecran-install">'
            . '<div class="install-carte">'
            . '<img src="' . View::asset('images/mobile/icone-192.png') . '" alt="" class="install-logo">'
            . '<h1 class="install-titre">Installez l\'application</h1>'
            . '<p class="install-sous-titre">Pour la retrouver d\'un geste et recevoir les alertes, ajoutez-la à votre écran d\'accueil.</p>'
            . ($estApple
                ? '<div class="install-note">' . self::icone('info') . '<span>Sur iPhone et iPad, les notifications ne fonctionnent qu\'une fois l\'application installée. iOS 16.4 ou plus récent est nécessaire.</span></div>'
                : '<button type="button" class="bouton bouton--or" id="bouton-installer" hidden>' . self::icone('telecharger') . ' Installer l\'application</button>')
            . '<ol class="etapes">' . $listeEtapes . '</ol>'
            . '<a href="' . View::url('mobile/tableau-de-bord') . '" class="bouton bouton--fantome">Continuer sans installer</a>'
            . '</div>'
            . '</main>';

        // Apple n'expose pas d'invite d'installation programmable : inutile d'envoyer
        // ce script sur iPhone et iPad, il n'y aurait aucun bouton à piloter.
        return self::coque('Installation', $contenu, [
            'scripts' => $estApple ? '' : self::scriptInstallation(),
        ]);
    }

    // =================================================================
    // Fragments
    // =================================================================

    private static function marque(string $sousTitre): string
    {
        return '<div class="marque">'
            . '<img src="' . View::asset('images/mobile/icone-192.png') . '" alt="LBP" class="marque-logo">'
            . '<div class="marque-texte">'
            . '<strong>LBP Direction</strong>'
            . '<small>' . View::e($sousTitre) . '</small>'
            . '</div>'
            . '</div>';
    }

    public static function alerte(string $message, string $ton = 'danger'): string
    {
        return '<div class="alerte alerte--' . $ton . '">'
            . self::icone($ton === 'danger' ? 'alerte' : 'info')
            . '<span>' . View::e($message) . '</span>'
            . '</div>';
    }

    /**
     * Bibliothèque d'icônes : tracés SVG au trait, taille et couleur héritées.
     */
    public static function icone(string $nom, int $taille = 20): string
    {
        $tracés = [
            'tableau' => '<path d="M3 13h8V3H3zM13 21h8V11h-8zM13 7h8V3h-8zM3 21h8v-4H3z"/>',
            'validation' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
            'personnel' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'anomalie' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
            'reglages' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
            'cadenas' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            'effacer' => '<path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"/><line x1="18" y1="9" x2="12" y2="15"/><line x1="12" y1="9" x2="18" y2="15"/>',
            'alerte' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
            'info' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
            'partager' => '<path d="M12 16V4"/><path d="m8 8 4-4 4 4"/><path d="M20 14v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-6"/>',
            'plus' => '<rect x="3" y="3" width="18" height="18" rx="3"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
            'valider' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
            'menu' => '<circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/>',
            'telecharger' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
            'cloche' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
            'sortie' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
            'fleche' => '<polyline points="9 18 15 12 9 6"/>',
            'hors-ligne' => '<line x1="1" y1="1" x2="23" y2="23"/><path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/><path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/><path d="M10.71 5.05A16 16 0 0 1 22.58 9"/><path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>',
        ];

        $tracé = $tracés[$nom] ?? $tracés['info'];

        return '<svg class="ico" width="' . $taille . '" height="' . $taille . '" viewBox="0 0 24 24" fill="none" '
            . 'stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . $tracé . '</svg>';
    }

    // =================================================================
    // Styles
    // =================================================================

    public static function styles(): string
    {
        return <<<CSS
*,*::before,*::after{box-sizing:border-box}
:root{
  --marine:#1d2b57; --marine-clair:#2a3d75; --or:#fabd02; --or-fonce:#e0a800;
  --encre:#0f172a; --gris:#64748b; --gris-clair:#94a3b8; --bord:#e2e8f0;
  --fond:#f4f6fb; --blanc:#fff;
  --vert:#059669; --rouge:#dc2626; --ambre:#d97706;
  --r:14px; --r-sm:10px;
  --ombre:0 1px 2px rgba(15,23,42,.06),0 8px 24px rgba(15,23,42,.06);
  --sat:env(safe-area-inset-top,0px); --sab:env(safe-area-inset-bottom,0px);
}
html,body{margin:0;padding:0;min-height:100%}
body{
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
  color:var(--encre);-webkit-font-smoothing:antialiased;
  -webkit-tap-highlight-color:transparent;overscroll-behavior-y:contain;
}
body.fond-clair{background:var(--fond)}
body.fond-marine{background:linear-gradient(165deg,#22336a 0%,var(--marine) 55%,#16204180 100%);background-color:var(--marine)}
.ico{flex:none;vertical-align:-.2em}

/* ---------- Écrans d'accès ---------- */
.ecran-acces{min-height:100svh;display:flex;align-items:center;justify-content:center;padding:calc(24px + var(--sat)) 20px calc(24px + var(--sab))}
.acces-carte{width:100%;max-width:400px;background:var(--blanc);border-radius:22px;padding:28px 24px 24px;box-shadow:0 24px 60px rgba(8,15,40,.35)}
.marque{display:flex;align-items:center;gap:12px;margin-bottom:22px}
.marque-logo{width:52px;height:52px;border-radius:13px;border:1px solid var(--bord);object-fit:contain;background:#fff}
.marque-texte{display:flex;flex-direction:column;line-height:1.25}
.marque-texte strong{font-size:1.08rem;color:var(--marine);letter-spacing:-.2px}
.marque-texte small{color:var(--gris);font-size:.82rem;margin-top:2px}
.pile{display:flex;flex-direction:column;gap:14px}
.champ{display:flex;flex-direction:column;gap:6px}
.champ-libelle{font-size:.78rem;font-weight:700;color:var(--gris);text-transform:uppercase;letter-spacing:.4px}
.champ-saisie{
  width:100%;padding:14px 15px;border:1.5px solid var(--bord);border-radius:var(--r-sm);
  font-size:16px;font-family:inherit;color:var(--encre);background:#fbfcfe;
  transition:border-color .15s,box-shadow .15s;
}
.champ-saisie:focus{outline:none;border-color:var(--marine);box-shadow:0 0 0 3px rgba(29,43,87,.1);background:#fff}
.champ-saisie--code{letter-spacing:.5em;text-align:center;font-size:20px;font-weight:700}
.bouton{
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  padding:15px 20px;border:none;border-radius:var(--r-sm);font-size:1rem;font-weight:700;
  font-family:inherit;cursor:pointer;text-decoration:none;transition:transform .12s,filter .15s;
  min-height:50px;width:100%;
}
.bouton:active{transform:scale(.985)}
.bouton--or{background:var(--or);color:var(--marine)}
.bouton--or:hover{filter:brightness(1.04)}
.bouton--fantome{background:transparent;color:var(--gris);border:1.5px solid var(--bord)}
.note-acces{margin:18px 0 0;font-size:.82rem;line-height:1.55;color:var(--gris-clair);text-align:center}
.note-acces--haut{margin:0 0 16px;text-align:left;color:var(--gris)}
.alerte{display:flex;gap:9px;align-items:flex-start;padding:12px 14px;border-radius:var(--r-sm);font-size:.87rem;line-height:1.45;margin-bottom:16px}
.alerte--danger{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.alerte--info{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe}

/* ---------- Déverrouillage ---------- */
.ecran-verrou{min-height:100svh;display:flex;flex-direction:column;justify-content:space-between;padding:calc(40px + var(--sat)) 24px calc(20px + var(--sab));max-width:420px;margin:0 auto}
.verrou-haut{display:flex;flex-direction:column;align-items:center;text-align:center;padding-top:6vh}
.verrou-logo{width:66px;height:66px;border-radius:17px;object-fit:contain;background:#fff;box-shadow:0 8px 28px rgba(0,0,0,.28)}
.verrou-nom{margin:16px 0 4px;font-size:1.22rem;font-weight:700;color:#fff;letter-spacing:-.2px}
.verrou-invite{margin:0;color:rgba(255,255,255,.62);font-size:.9rem}
.verrou-erreur{margin:14px 0 0;color:#fecaca;font-size:.86rem;font-weight:600}
.points{display:flex;gap:16px;margin-top:30px}
.point{width:14px;height:14px;border-radius:50%;border:1.8px solid rgba(255,255,255,.42);transition:background .18s,transform .18s,border-color .18s}
.point.rempli{background:var(--or);border-color:var(--or);transform:scale(1.12)}
.points.erreur{animation:secousse .4s}
@keyframes secousse{0%,100%{transform:translateX(0)}20%{transform:translateX(-9px)}40%{transform:translateX(9px)}60%{transform:translateX(-6px)}80%{transform:translateX(6px)}}
.verrou-bas{margin-top:auto}
.pave{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;max-width:300px;margin:0 auto}
.touche{
  aspect-ratio:1;border:none;border-radius:50%;background:rgba(255,255,255,.1);
  color:#fff;font-size:1.6rem;font-weight:500;font-family:inherit;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:background .12s,transform .1s;-webkit-user-select:none;user-select:none;
}
.touche:active{background:rgba(255,255,255,.26);transform:scale(.94)}
.touche--vide{background:none;cursor:default}
.touche--action{background:none}
.pave--bloque{display:flex;flex-direction:column;align-items:center;gap:10px;color:rgba(255,255,255,.6);padding:26px 0;max-width:300px}
.zone-oubli{text-align:center;margin-top:22px}
.lien-oubli-form{display:none}
.lien-discret{background:none;border:none;color:rgba(255,255,255,.5);font-size:.84rem;font-family:inherit;cursor:pointer;padding:8px;text-decoration:underline}

/* ---------- Installation ---------- */
.ecran-install{min-height:100svh;display:flex;align-items:center;justify-content:center;padding:calc(24px + var(--sat)) 20px calc(24px + var(--sab))}
.install-carte{width:100%;max-width:440px;background:var(--blanc);border-radius:22px;padding:30px 24px;box-shadow:var(--ombre);text-align:center}
.install-logo{width:70px;height:70px;border-radius:18px;border:1px solid var(--bord);object-fit:contain}
.install-titre{margin:16px 0 8px;font-size:1.34rem;color:var(--marine);letter-spacing:-.3px}
.install-sous-titre{margin:0 0 20px;color:var(--gris);font-size:.92rem;line-height:1.55}
.install-note{display:flex;gap:9px;align-items:flex-start;text-align:left;background:#fffbeb;border:1px solid #fde68a;color:#92400e;padding:12px 14px;border-radius:var(--r-sm);font-size:.84rem;line-height:1.5;margin-bottom:20px}
.etapes{list-style:none;margin:22px 0;padding:0;display:flex;flex-direction:column;gap:14px;text-align:left}
.etape{display:flex;align-items:flex-start;gap:12px}
.etape-num{flex:none;width:26px;height:26px;border-radius:50%;background:var(--marine);color:#fff;font-size:.8rem;font-weight:700;display:flex;align-items:center;justify-content:center}
.etape-icone{flex:none;color:var(--marine);margin-top:2px}
.etape-texte{display:flex;flex-direction:column;gap:2px}
.etape-texte strong{font-size:.93rem;color:var(--encre)}
.etape-texte small{font-size:.82rem;color:var(--gris);line-height:1.45}

/* ---------- Tablette ---------- */
@media (min-width:768px){
  .acces-carte,.install-carte{max-width:480px;padding:36px 34px}
  .ecran-verrou{max-width:460px}
  .verrou-haut{padding-top:8vh}
}
@media (prefers-reduced-motion:reduce){
  *{animation-duration:.01ms!important;transition-duration:.01ms!important}
}
CSS;
    }

    // =================================================================
    // Scripts
    // =================================================================

    private static function scriptCommun(): string
    {
        $sw = View::url('mobile/sw.js');
        $portee = View::url('mobile/');

        return <<<JS
(function(){
  if('serviceWorker' in navigator){
    window.addEventListener('load',function(){
      navigator.serviceWorker.register('{$sw}',{scope:'{$portee}'}).catch(function(){});
    });
  }
})();
JS;
    }

    private static function scriptPave(): string
    {
        return <<<'JS'
(function(){
  var code='', points=document.getElementById('points'), champ=document.getElementById('code-saisi'),
      form=document.getElementById('form-verrou'), invite=document.getElementById('invite');
  if(!points||!champ||!form){return;}
  var pastilles=points.querySelectorAll('.point');

  function dessiner(){
    for(var i=0;i<pastilles.length;i++){
      pastilles[i].classList.toggle('rempli', i<code.length);
    }
  }
  function vibrer(ms){ if(navigator.vibrate){try{navigator.vibrate(ms);}catch(e){}} }

  function ajouter(c){
    if(code.length>=6){return;}
    code+=c; dessiner(); vibrer(8);
    if(code.length===6){
      champ.value=code;
      if(invite){invite.textContent='Vérification…';}
      setTimeout(function(){form.submit();},140);
    }
  }
  function effacer(){ if(!code.length){return;} code=code.slice(0,-1); dessiner(); vibrer(8); }

  document.querySelectorAll('.touche[data-chiffre]').forEach(function(b){
    b.addEventListener('click',function(){ajouter(b.dataset.chiffre);});
  });
  var eff=document.querySelector('[data-action="effacer"]');
  if(eff){eff.addEventListener('click',effacer);}

  // Clavier physique, utile sur tablette avec étui clavier
  document.addEventListener('keydown',function(e){
    if(e.key>='0'&&e.key<='9'){ajouter(e.key);}
    else if(e.key==='Backspace'){effacer();}
  });
})();
JS;
    }

    private static function scriptInstallation(): string
    {
        return <<<'JS'
(function(){
  var invite=null, bouton=document.getElementById('bouton-installer');
  window.addEventListener('beforeinstallprompt',function(e){
    e.preventDefault(); invite=e;
    if(bouton){bouton.hidden=false;}
  });
  if(bouton){
    bouton.addEventListener('click',function(){
      if(!invite){return;}
      invite.prompt();
      invite.userChoice.finally(function(){invite=null;bouton.hidden=true;});
    });
  }
})();
JS;
    }
}
