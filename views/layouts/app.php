<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>/title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <link href="" rel="stylesheet">
</head>

<body class="app-dashboard-body">

    <div class="app-shell">

        <aside class="app-sidebar">
            <a href="" class="app-sidebar-brand">
                <span class="app-sidebar-logo">G</span>
                <span>
                    <strong>Programmation</strong>
                    <small>Espace utilisateur</small>
                </span>
            </a>

            <nav class="app-sidebar-nav">
                <a href="" class="app-nav-link active">
                    Tableau de bord
                </a>
            </nav>
        </aside>

        <div class="app-main">

            <header class="app-topbar">
                <div>
                    <span class="app-topbar-kicker">Plateforme des dev</span>
                    <h1></h1>
                </div>

                <div class="app-profile">
                    <div class="app-profile-avatar">

                    </div>

                    <div class="app-profile-info">
                        <strong></strong>
                        <span>Compte actif</span>
                    </div>

                    <a href="" class="app-logout-link">
                        Déconnexion
                    </a>
                </div>
            </header>

            <main class="app-content">
                <?= $content ?? '' ?>
            </main>

        </div>

    </div>

    <script src=""></script>
</body>

</html>