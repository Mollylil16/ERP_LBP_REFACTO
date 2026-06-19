<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Models\User;
use App\View\Pages\Admin\SystemTestsPage;
use App\View\Pages\Admin\UserShowPage;

final class Admin
{
    /** @param array<int,mixed> $entities */
    public static function entityList(array $entities): string
    {
        $html = '<div class="admin-entity-list">';
        foreach ($entities as $entity) {
            $html .= '<div><span class="admin-module-chip">' . View::e((string) ($entity->module ?? ''))
                . '</span><span><strong>' . View::e((string) ($entity->name ?? ''))
                . '</strong><small>' . View::e((string) ($entity->description ?? ''))
                . '</small></span></div>';
        }
        return $html . '</div>';
    }

    public static function securityCard(): string
    {
        return '<aside class="admin-security-card"><p class="admin-eyebrow">Bonnes pratiques</p>'
            . '<h2>Contrôle d’accès explicite</h2>'
            . '<p>Les administrateurs possèdent tous les droits. Les autres comptes reçoivent uniquement les permissions enregistrées dans leur profil.</p>'
            . '<a href="' . View::url('admin/users') . '">Gérer les utilisateurs</a>'
            . '<a href="' . View::url('admin/permissions') . '">Auditer la matrice</a></aside>';
    }

    /** @param array<int,array<string,mixed>> $users */
    public static function userTable(array $users): string
    {
        $body = '';
        foreach ($users as $user) {
            $actions = '';
            foreach ((array) ($user['actions'] ?? []) as $action) {
                $actions .= '<a href="' . View::url(ltrim((string) ($action['href'] ?? ''), '/')) . '">'
                    . View::e((string) ($action['label'] ?? 'Ouvrir')) . '</a>';
            }
            $body .= '<tr><td><strong>' . View::e((string) ($user['name'] ?? ''))
                . '</strong><small class="admin-table-subtitle">' . View::e((string) ($user['profile_reference'] ?? ''))
                . '</small></td><td>' . View::e((string) ($user['email'] ?? ''))
                . '<small class="admin-table-subtitle">' . View::e((string) ($user['phone'] ?? ''))
                . '</small></td><td><span class="admin-profile-badge'
                . (!empty($user['is_admin']) ? ' is-admin' : '') . '">'
                . View::e((string) ($user['profile'] ?? '')) . '</span></td>'
                . '<td><span class="finea-status-badge finea-status-badge--'
                . View::e((string) ($user['status_tone'] ?? 'warning')) . '">'
                . View::e((string) ($user['status'] ?? '')) . '</span></td>'
                . '<td>' . View::e((string) ($user['created_at'] ?? '—')) . '</td>'
                . '<td><div class="admin-row-actions">' . $actions . '</div></td></tr>';
        }

        if ($body === '') {
            $body = '<tr><td colspan="6">' . Ui::emptyState(
                'Aucun utilisateur',
                'Aucun compte ne correspond aux critères.'
            ) . '</td></tr>';
        }

        return '<div class="finea-table-wrap"><table class="finea-table"><thead><tr>'
            . '<th>Utilisateur</th><th>Contact</th><th>Profil</th><th>Statut</th><th>Création</th><th>Actions</th>'
            . '</tr></thead><tbody>' . $body . '</tbody></table></div>';
    }

    /** @param array<int,array{number:int,href:string,active:bool}> $links */
    public static function pagination(array $links): string
    {
        if (count($links) <= 1) {
            return '';
        }
        $html = '<nav class="admin-pagination" aria-label="Pagination">';
        foreach ($links as $link) {
            $html .= '<a class="' . ($link['active'] ? 'is-active' : '') . '" href="'
                . View::e($link['href']) . '"' . ($link['active'] ? ' aria-current="page"' : '') . '>'
                . (int) $link['number'] . '</a>';
        }
        return $html . '</nav>';
    }

    /** @param array<string,mixed> $employee */
    public static function employeeProfile(array $employee): string
    {
        $fields = [
            'Collaborateur' => $employee['full_name'] ?? '',
            'Matricule' => ($employee['employee_number'] ?? '') ?: 'Non renseigné',
            'Email' => ($employee['email'] ?? '') ?: 'Non renseigné',
            'Téléphone' => ($employee['phone'] ?? '') ?: 'Non renseigné',
            'Service' => $employee['service_name'] ?? '',
            'Fonction' => $employee['function_name'] ?? '',
        ];
        $html = '<div class="admin-rh-profile">';
        foreach ($fields as $label => $value) {
            $html .= '<div><small>' . View::e($label) . '</small><strong>'
                . View::e((string) $value) . '</strong></div>';
        }
        return $html . '</div>';
    }

    public static function employeePreview(): string
    {
        $fields = [
            'name' => 'Collaborateur',
            'number' => 'Matricule',
            'email' => 'Email de connexion',
            'phone' => 'Téléphone',
            'service' => 'Service',
            'function' => 'Fonction',
        ];
        $html = '<div class="admin-rh-profile is-preview" data-rh-preview hidden>';
        foreach ($fields as $key => $label) {
            $html .= '<div><small>' . View::e($label) . '</small><strong data-rh-field="'
                . View::e($key) . '"></strong></div>';
        }
        return $html . '</div>';
    }

    /** @param array<int,array<string,mixed>> $permissions */
    public static function permissionTable(array $permissions, bool $useCurrentValues = false): string
    {
        $currentModule = null;
        $body = '';
        foreach ($permissions as $permission) {
            $module = (string) ($permission['module'] ?? '');
            if ($currentModule !== $module) {
                $currentModule = $module;
                $body .= '<tr class="admin-module-row"><td colspan="5">'
                    . View::e($currentModule) . '</td></tr>';
            }
            $body .= '<tr data-permission-row><td><strong>'
                . View::e((string) ($permission['name'] ?? '')) . '</strong><small>'
                . View::e((string) ($permission['description'] ?? '')) . '</small></td>';
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $body .= '<td>' . Form::checkbox(
                    'permissions[' . (int) ($permission['entity_id'] ?? 0) . '][' . $action . ']',
                    [
                        'label' => '',
                        'value' => '1',
                        'checked' => $useCurrentValues && !empty($permission['can_' . $action]),
                        'data-action' => $action,
                        'fieldClass' => 'admin-checkbox-field',
                    ]
                ) . '</td>';
            }
            $body .= '</tr>';
        }

        return '<div class="finea-table-wrap"><table class="finea-table admin-permission-table">'
            . '<thead><tr><th>Module / entité</th><th>Lire</th><th>Créer</th><th>Modifier</th><th>Supprimer</th></tr></thead>'
            . '<tbody>' . $body . '</tbody></table></div>';
    }

    public static function permissionToolbar(bool $allowAll = false): string
    {
        $actions = Ui::button('Tout retirer', [
            'variant' => 'secondary',
            'type' => 'button',
            'data-permissions-clear' => true,
        ]) . Ui::button('Lecture seule', [
            'variant' => 'secondary',
            'type' => 'button',
            'data-permissions-read' => true,
        ]);
        if ($allowAll) {
            $actions .= Ui::button('Tout autoriser', [
                'variant' => 'secondary',
                'type' => 'button',
                'data-permissions-all' => true,
            ]);
        }

        return '<div class="admin-permission-toolbar"><div><h2 class="finea-section-title">Permissions CRUD</h2>'
            . '<p>La lecture est activée automatiquement lorsqu’une action d’écriture est accordée.</p></div>'
            . '<div class="finea-actions">' . $actions . '</div></div>';
    }

    /** @param array<string,string> $details */
    public static function detailList(array $details): string
    {
        $html = '<dl class="admin-detail-list">';
        foreach ($details as $label => $value) {
            $html .= '<div><dt>' . View::e($label) . '</dt><dd>' . View::e($value) . '</dd></div>';
        }
        return $html . '</dl>';
    }

    /** @param array<int,array{name:string,rights:string}> $permissions */
    public static function permissionSummary(User $user, array $permissions): string
    {
        if ($user->isAdmin) {
            return '<div class="admin-full-access">Accès administrateur complet à toutes les entités.</div>';
        }
        if ($permissions === []) {
            return Ui::emptyState('Aucune permission attribuée.');
        }
        $html = '<div class="admin-permission-summary">';
        foreach ($permissions as $permission) {
            $html .= '<div><strong>' . View::e($permission['name']) . '</strong><span>'
                . View::e($permission['rights']) . '</span></div>';
        }
        return $html . '</div>';
    }

    public static function accessState(UserShowPage $page): string
    {
        return '<section class="finea-section-card admin-access-state"><div>'
            . '<h2 class="finea-section-title">'
            . View::e($page->active ? 'Désactiver les accès' : 'Réactiver les accès')
            . '</h2><p>'
            . View::e($page->active
                ? 'Le compte sera conservé mais toute session et toute nouvelle connexion seront refusées.'
                : 'Le collaborateur pourra de nouveau se connecter avec ses permissions existantes.')
            . '</p></div><form method="post" action="' . View::url($page->accessAction())
            . '" data-confirm-access-state>' . Csrf::input()
            . Ui::button(
                $page->active ? 'Désactiver le compte' : 'Réactiver le compte',
                ['variant' => $page->active ? 'danger' : 'primary', 'type' => 'submit']
            )
            . '</form></section>';
    }

    /** @param array<int,mixed> $entities @param array<int,array<string,mixed>> $users */
    public static function permissionMatrix(array $entities, array $users): string
    {
        $head = '<th class="admin-sticky-column">Utilisateur</th>';
        foreach ($entities as $entity) {
            $head .= '<th><span>' . View::e((string) ($entity->module ?? '')) . '</span>'
                . View::e((string) ($entity->name ?? '')) . '</th>';
        }
        $head .= '<th>Action</th>';

        $body = '';
        foreach ($users as $user) {
            $body .= '<tr><td class="admin-sticky-column"><strong>'
                . View::e((string) ($user['full_name'] ?? '')) . '</strong><small>'
                . View::e((string) ($user['email'] ?? '')) . '</small></td>';
            foreach ($entities as $entity) {
                $label = !empty($user['is_admin'])
                    ? 'Tous'
                    : self::rightLabel((array) (($user['permissions'] ?? [])[$entity->code] ?? []));
                $class = Html::classes([
                    'admin-matrix-rights',
                    'is-full' => !empty($user['is_admin']),
                    'is-empty' => $label === '',
                ]);
                $body .= '<td><span class="' . View::e($class) . '">'
                    . View::e($label !== '' ? $label : '—') . '</span></td>';
            }
            $body .= '<td><a class="admin-matrix-edit" href="'
                . View::url('admin/users/' . (int) ($user['id'] ?? 0) . '/permissions')
                . '">Éditer</a></td></tr>';
        }

        return '<div class="admin-matrix-legend"><span><b>L</b> Lire</span><span><b>C</b> Créer</span>'
            . '<span><b>M</b> Modifier</span><span><b>S</b> Supprimer</span></div>'
            . '<div class="finea-table-wrap"><table class="finea-table admin-matrix-table">'
            . '<thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table></div>';
    }

    public static function systemTests(SystemTestsPage $page): string
    {
        $summary = $page->summary;
        $html = '<section class="health-hero health-status-' . View::e($page->status) . '">'
            . '<div class="health-hero__content"><p class="finea-eyebrow">Module système • Santé & Tests</p>'
            . '<h2>Centre de contrôle qualité ERP LBP</h2>'
            . '<p>Testez toute l’application ou un module précis : PHPUnit, smoke tests, syntaxe PHP, routes, vues et accès BDD métier.</p>'
            . '<div class="health-actions">'
            . Ui::button('Lancer le test complet', ['variant' => 'plain', 'type' => 'button', 'class' => 'health-btn health-btn-primary', 'data-health-run-all' => true])
            . Ui::button('Retour portail', ['href' => 'selection_portail', 'variant' => 'plain', 'class' => 'health-btn health-btn-secondary'])
            . '</div></div><div class="health-gauge" aria-label="Score santé global"><div class="health-gauge__ring" style="--score: '
            . $page->score . '"><span data-health-score>' . $page->score . '%</span></div><strong data-health-global-label>'
            . View::e($page->score >= 90 ? 'Très stable' : ($page->score >= 60 ? 'À surveiller' : 'Critique'))
            . '</strong><small>Dernier score enregistré</small></div></section>';

        $html .= '<section class="health-strip">'
            . self::healthStat('PHP', (string) ($summary['phpVersion'] ?? ''))
            . self::healthStat('Environnement', (string) ($summary['environment'] ?? ''))
            . self::healthStat('Dernière exécution', (string) ($summary['latest']['created_at'] ?? 'Jamais'))
            . '</section>';

        $html .= '<section class="health-console" data-health-console hidden><div class="health-console__header"><div>'
            . '<p class="finea-eyebrow">Exécution en cours</p><h3 data-health-console-title>Préparation du test...</h3>'
            . '</div><span class="health-loader" aria-hidden="true"></span></div>'
            . '<div class="health-progress"><span data-health-progress style="width: 8%"></span></div>'
            . '<p data-health-console-message>Initialisation des contrôles.</p></section>';

        $html .= '<section class="health-module-grid" aria-label="Modules testables">';
        foreach ($page->modules as $module) {
            $slug = (string) ($module['slug'] ?? '');
            $html .= '<article class="health-module-card" style="--accent: '
                . View::e((string) ($module['accent'] ?? '#2563eb')) . '" data-health-module-card="'
                . View::e($slug) . '"><header><span class="health-module-code">'
                . View::e((string) ($module['code'] ?? '')) . '</span>'
                . '<span class="health-pill health-pill-neutral" data-health-card-status>Non testé</span></header><h3>'
                . View::e((string) ($module['label'] ?? '')) . '</h3>'
                . '<p>Contrôle routes, vues, tables, requêtes SQL et cohérence d’exécution du module.</p>'
                . '<div class="health-mini-gauge"><span data-health-card-bar style="width: 0%"></span></div><footer>'
                . Ui::button('Tester ce module', ['variant' => 'plain', 'type' => 'button', 'class' => 'health-link-btn', 'data-health-run-module' => $slug])
                . Ui::button('Détails', ['variant' => 'plain', 'type' => 'button', 'class' => 'health-link-btn muted', 'data-health-open-details' => $slug])
                . '</footer></article>';
        }
        $html .= '</section>';

        $html .= '<section class="health-results" data-health-results hidden><div class="health-results__header"><div>'
            . '<p class="finea-eyebrow">Rapport d’exécution</p><h3 data-health-results-title>Résultat</h3></div>'
            . '<span class="health-pill" data-health-results-status>—</span></div>'
            . '<div class="health-check-list" data-health-check-list></div></section>';

        $html .= '<section class="health-history"><div class="health-results__header"><div>'
            . '<p class="finea-eyebrow">Historique</p><h3>Dernières exécutions</h3></div></div>'
            . '<div class="health-history-list">';
        if ($page->latestRuns === []) {
            $html .= '<p class="health-muted">Aucune exécution enregistrée pour le moment.</p>';
        }
        foreach ($page->latestRuns as $run) {
            $html .= '<article><strong>' . View::e((string) ($run['module'] ?? ''))
                . '</strong><span>' . View::e((string) ($run['scope'] ?? '')) . ' • '
                . View::e((string) ($run['created_at'] ?? '')) . '</span><em class="health-pill health-pill-'
                . View::e((string) ($run['status'] ?? 'warning')) . '">' . (int) ($run['score'] ?? 0)
                . '%</em></article>';
        }
        $html .= '</div></section>';

        return $html . '<div class="health-modal" data-health-modal hidden>'
            . '<div class="health-modal__overlay" data-health-close-modal></div>'
            . '<article class="health-modal__dialog" role="dialog" aria-modal="true" aria-label="Détails du test">'
            . '<header><h3 data-health-modal-title>Détails</h3><button type="button" data-health-close-modal>×</button></header>'
            . '<pre data-health-modal-body></pre></article></div>'
            . '<script>window.ERP_HEALTH_TESTS=' . json_encode([
                'csrfToken' => $page->csrfToken,
                'endpoints' => [
                    'runAll' => View::url('admin/system-tests/run'),
                    'runModule' => View::url('admin/system-tests/run/'),
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>';
    }

    private static function rightLabel(array $permission): string
    {
        $rights = [];
        foreach (['view' => 'L', 'create' => 'C', 'update' => 'M', 'delete' => 'S'] as $key => $label) {
            if (!empty($permission['can_' . $key])) {
                $rights[] = $label;
            }
        }
        return implode(' ', $rights);
    }

    private static function healthStat(string $label, string $value): string
    {
        return '<article><span>' . View::e($label) . '</span><strong>'
            . View::e($value) . '</strong></article>';
    }
}
