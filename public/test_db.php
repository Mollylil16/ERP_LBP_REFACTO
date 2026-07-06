<?php
require_once __DIR__ . '/../bootstrap/app.php';

header('Content-Type: text/plain');

try {
    $db = \App\Models\Database::getConnection();
    echo "Connexion BDD réussie !\n";
    
    // Config chargée
    $config = require __DIR__ . '/../config/database.php';
    echo "Hôte configuré : " . ($config['host'] ?? 'non défini') . "\n";
    echo "Base configurée : " . ($config['dbname'] ?? 'non défini') . "\n";
    echo "Utilisateur configuré : " . ($config['username'] ?? 'non défini') . "\n";
    
    // Requête de test
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();
    echo "Nombre d'utilisateurs en base : " . $count . "\n";
    
    $stmt = $db->query("SELECT id, email, password_hash FROM users WHERE email = 'wilfried.abassi@labelleporte.cloud'");
    $user = $stmt->fetch();
    if ($user) {
        echo "Utilisateur wilfried.abassi trouvé !\n";
        echo "Hash en base : " . $user['password_hash'] . "\n";
        echo "Validation directe de 'lbp2026' : " . (password_verify('lbp2026', $user['password_hash']) ? 'OUI' : 'NON') . "\n";
    } else {
        echo "Utilisateur wilfried.abassi NON trouvé !\n";
    }
} catch (\Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
