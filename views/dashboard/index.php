<?php

/** @var array $user */

ob_start();
?>

<section class="dashboard-hero portal-hero">
    <div>
        <p class="eyebrow">Dashboard opérationnel</p>
        <h2>Bonjour <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>, bienvenue dans le tableau de bord.</h2>
        <p class="dashboard-subtitle">Cette page reste dédiée aux indicateurs métier. L’accueil après connexion est désormais séparé dans le portail de sélection.</p>
    </div>
    <div class="hero-chip">Dashboard • Indicateurs</div>
</section>

<section class="dashboard-grid portal-stats">
    <article class="stat-card accent-blue">
        <span>Opérations</span>
        <strong>24</strong>
        <small>Flux actifs aujourd’hui</small>
    </article>
    <article class="stat-card accent-gold">
        <span>Documents</span>
        <strong>11</strong>
        <small>À valider ou signer</small>
    </article>
    <article class="stat-card accent-green">
        <span>Équipes</span>
        <strong>7</strong>
        <small>Utilisateurs connectés</small>
    </article>
    <article class="stat-card accent-rose">
        <span>Conformité</span>
        <strong>96%</strong>
        <small>Niveau de conformité</small>
    </article>
</section>

<section class="portal-grid">
    <article class="portal-card module-transit">
        <span class="portal-icon">TR</span>
        <h3>Transit</h3>
        <p>Suivi des mouvements, destinations et planification des chargements.</p>
        <a href="/dashboard" class="portal-action">Ouvrir le module</a>
    </article>
    <article class="portal-card module-docs">
        <span class="portal-icon">DOC</span>
        <h3>Documents</h3>
        <p>Gestion des bordereaux, pièces justificatives et validation documentaire.</p>
        <a href="/dashboard" class="portal-action">Ouvrir le module</a>
    </article>
    <article class="portal-card module-stock">
        <span class="portal-icon">ST</span>
        <h3>Stocks</h3>
        <p>Visualisation des marchandises, mouvements et niveaux de stock.</p>
        <a href="/dashboard" class="portal-action">Ouvrir le module</a>
    </article>
    <article class="portal-card module-admin">
        <span class="portal-icon">AD</span>
        <h3>Administration</h3>
        <p>Paramètres utilisateurs, sécurité et gestion des équipes.</p>
        <a href="/logout" class="portal-action">Gérer l’accès</a>
    </article>
</section>

<section class="dashboard-panels portal-panels">
    <article class="panel-card panel-wide">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Vue d’ensemble</p>
                <h3>Portail de sélection</h3>
            </div>
            <span class="badge badge-live">Nouveau</span>
        </div>
        <p>Cette page sert de portail central pour accéder aux modules ERP futurs : transit, documents, logistique, reporting et administration.</p>
        <ul class="check-list">
            <li>Interface adaptée à l’ajout de modules</li>
            <li>Navigation claire et moderne</li>
            <li>Base prête pour Odoo / ERP multi-apps</li>
        </ul>
    </article>

    <article class="panel-card">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Actions rapides</p>
                <h3>Raccourcis</h3>
            </div>
        </div>
        <div class="mini-stack">
            <a href="<?= \App\Helpers\View::url('selection_portail') ?>" class="mini-link">Retour au portail</a>
            <a href="/logout" class="mini-link muted">Déconnexion</a>
        </div>
    </article>
</section>

<?php
$content = ob_get_clean();

require BASE_PATH . '/views/layouts/app.php';
