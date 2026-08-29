<?php
/**
 * Script de reséquençage des numéros de tracking et de facture suite à des suppressions.
 * Ce script comble tous les écarts de séquence (gaps) existants.
 */

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
    die("[-] Erreur de connexion à la base de données : " . $e->getMessage() . "\n");
}

echo "[*] Début du reséquençage des Colis...\n";

// 1. Reséquencer lbp_colis
$stmt = $pdo->query("SELECT id, numero_tracking FROM lbp_colis ORDER BY id ASC");
$colisList = $stmt->fetchAll();

$colisGroups = [];
foreach ($colisList as $colis) {
    $parts = explode('-', $colis['numero_tracking']);
    if (count($parts) >= 2) {
        $seqStr = array_pop($parts);
        $prefix = implode('-', $parts);
        $colisGroups[$prefix][] = [
            'id' => $colis['id'],
            'seq_len' => strlen($seqStr)
        ];
    }
}

$updatedColisCount = 0;
foreach ($colisGroups as $prefix => $items) {
    $seq = 1;
    foreach ($items as $item) {
        $newSeqStr = str_pad((string)$seq, $item['seq_len'], '0', STR_PAD_LEFT);
        $newTracking = $prefix . '-' . $newSeqStr;
        
        $upStmt = $pdo->prepare("UPDATE lbp_colis SET numero_tracking = ? WHERE id = ?");
        $upStmt->execute([$newTracking, $item['id']]);
        $seq++;
        $updatedColisCount++;
    }
}
echo "[+] Reséquençage des Colis terminé. {$updatedColisCount} colis traités.\n";

echo "[*] Début du reséquençage des Factures...\n";

// 2. Reséquencer lbp_factures
$stmtF = $pdo->query("SELECT id, numero_facture FROM lbp_factures ORDER BY id ASC");
$facturesList = $stmtF->fetchAll();

$factureGroups = [];
foreach ($facturesList as $facture) {
    $parts = explode('-', $facture['numero_facture']);
    if (count($parts) >= 2) {
        $seqStr = array_pop($parts);
        $prefix = implode('-', $parts);
        $factureGroups[$prefix][] = [
            'id' => $facture['id'],
            'seq_len' => strlen($seqStr)
        ];
    }
}

$updatedFactureCount = 0;
foreach ($factureGroups as $prefix => $items) {
    $seq = 1;
    foreach ($items as $item) {
        $newSeqStr = str_pad((string)$seq, $item['seq_len'], '0', STR_PAD_LEFT);
        $newFactureNum = $prefix . '-' . $newSeqStr;
        
        $upStmt = $pdo->prepare("UPDATE lbp_factures SET numero_facture = ? WHERE id = ?");
        $upStmt->execute([$newFactureNum, $item['id']]);
        $seq++;
        $updatedFactureCount++;
    }
}
echo "[+] Reséquençage des Factures terminé. {$updatedFactureCount} factures traitées.\n";
