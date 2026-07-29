<?php
/**
 * ============================================================
 *  Script de mise à jour des emails utilisateurs
 *  De @labelleporte.cloud → @labelleporte.ci
 * ============================================================
 * 
 *  INSTRUCTIONS :
 *  1. Uploader ce fichier dans public_html/ sur le serveur
 *  2. Ouvrir https://labelleporte.ci/update_emails.php dans le navigateur
 *  3. Vérifier le résultat affiché
 *  4. SUPPRIMER CE FICHIER immédiatement après exécution
 * 
 * ============================================================
 */

// Bootstrap minimal
define('BASE_PATH', __DIR__);
$config = require BASE_PATH . '/config/database.php';

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $config['host'],
    $config['port'],
    $config['dbname'],
    $config['charset']
);

try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('<h1 style="color:red">Erreur de connexion a la base de donnees</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>');
}

$results = [];

// 1. Mettre à jour les emails : @labelleporte.cloud → @labelleporte.ci

// Table users
$stmt = $pdo->prepare("UPDATE users SET email = REPLACE(email, '@labelleporte.cloud', '@labelleporte.ci'), updated_at = NOW() WHERE email LIKE '%@labelleporte.cloud'");
$stmt->execute();
$results[] = "Table users : " . $stmt->rowCount() . " email(s) mis a jour (cloud -> ci)";

// Table rh_employees
$stmt = $pdo->prepare("UPDATE rh_employees SET email = REPLACE(email, '@labelleporte.cloud', '@labelleporte.ci'), updated_at = NOW() WHERE email LIKE '%@labelleporte.cloud'");
$stmt->execute();
$results[] = "Table rh_employees : " . $stmt->rowCount() . " email(s) mis a jour (cloud -> ci)";

// 2. Vérification : lister tous les utilisateurs après mise à jour
$users = $pdo->query("SELECT id, full_name, email, status, is_admin FROM users ORDER BY full_name")->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mise a jour emails - ERP LBP</title>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #f1f5f9; color: #1e293b; }
        h1 { color: #1d2b57; }
        .result { padding: 12px 16px; margin: 8px 0; border-radius: 10px; background: #dcfce7; border: 1px solid #86efac; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        th { background: #1d2b57; color: #fff; text-align: left; padding: 12px; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; }
        td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-size: 0.9rem; }
        tr:hover { background: #f8fafc; }
        .admin { color: #dc2626; font-weight: 700; }
        .active { color: #16a34a; }
        .inactive { color: #dc2626; }
        .delete-notice { margin-top: 30px; padding: 16px; background: #fef2f2; border: 2px solid #fca5a5; border-radius: 12px; color: #991b1b; font-weight: 600; }
    </style>
</head>
<body>
    <h1>Rapport de mise a jour des emails</h1>
    
    <?php foreach ($results as $r): ?>
        <div class="result"><?= $r ?></div>
    <?php endforeach; ?>

    <h2>Liste complete des utilisateurs (<?= count($users) ?> comptes)</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nom complet</th>
                <th>Email</th>
                <th>Statut</th>
                <th>Admin</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['full_name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td class="<?= $u['status'] === 'active' ? 'active' : 'inactive' ?>"><?= $u['status'] ?></td>
                <td><?= $u['is_admin'] ? '<span class="admin">OUI</span>' : 'Non' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="delete-notice">
        IMPORTANT : Supprimez ce fichier (update_emails.php) immediatement apres verification !
        Ce script ne doit pas rester accessible en ligne.
    </div>
</body>
</html>
