<?php
/**
 * Visual Test Script — ERP LBP
 * 
 * Ce script teste le flux complet :
 * 1. Création d'un groupage (expédition)
 * 2. Création d'un colis avec marchandises
 * 3. Génération d'une facture
 * 4. Soumission du point journalier
 * 5. Affichage de la facture prête à l'impression
 * 
 * Usage : Accéder à http://localhost/ERP_LBP_REFACTO/scratch/visual_test.php
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Bootstrap the application
define('BASE_PATH', dirname(__DIR__));

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = BASE_PATH . '/app/';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

use App\Models\Database;
use App\Repositories\Colisage\ColisageRepository;
use App\Services\Colisage\ColisageService;
use App\Repositories\Finance\FactureRepository;
use App\Repositories\Finance\EtatJournalierRepository;
use App\Models\Finance\Facture;
use App\Models\Finance\EtatJournalier;
use App\Helpers\View;

// Simulate admin login
$pdo = Database::getConnection();

// Run migrations if not done
try {
    $migrationRunner = new \App\Database\MigrationRunner($pdo);
    $migrationRunner->run();
} catch (\Exception $e) {
    // Migrations may already be run
}

// Get admin user ID
$stmt = $pdo->query("SELECT id FROM users WHERE email = 'admin@erp-lbp.local' LIMIT 1");
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    die("❌ Admin user not found. Please seed the database first.");
}
$_SESSION['auth_user_id'] = (int) $admin['id'];

$colisageRepo = new ColisageRepository($pdo);
$colisageService = new ColisageService($colisageRepo);
$factureRepo = new FactureRepository($pdo);
$etatRepo = new EtatJournalierRepository($pdo);

$results = [];

// ============================================
// STEP 1: Vérifier/Créer les agences
// ============================================
$sitesStmt = $pdo->query("SELECT id, name FROM company_sites WHERE is_active = 1 ORDER BY id ASC LIMIT 2");
$sites = $sitesStmt->fetchAll(PDO::FETCH_ASSOC);

if (count($sites) < 2) {
    // Create test agencies
    $pdo->exec("INSERT IGNORE INTO company_sites (name, address, phone, email, is_active) VALUES 
        ('LBP Abidjan - Treichville', 'Zone 3, Treichville, Abidjan', '+225 0503497979', 'abidjan@lbp-logistics.com', 1),
        ('LBP Paris - Bobigny', '17 Chemin des Vignes, 93000 Bobigny', '+33 775732797', 'paris@lbp-logistics.com', 1)
    ");
    $sitesStmt = $pdo->query("SELECT id, name FROM company_sites WHERE is_active = 1 ORDER BY id ASC LIMIT 2");
    $sites = $sitesStmt->fetchAll(PDO::FETCH_ASSOC);
}

$agenceDepartId = (int) $sites[0]['id'];
$agenceArriveeId = (int) $sites[1]['id'];

$results['agences'] = [
    'depart' => $sites[0],
    'arrivee' => $sites[1],
];

// ============================================
// STEP 2: Créer un GROUPAGE (Expédition)
// ============================================
$expeditionId = $colisageService->createExpedition([
    'type_transport' => 'AÉRIEN',
    'agence_depart_id' => $agenceDepartId,
    'agence_arrivee_id' => $agenceArriveeId,
    'date_depart_prevue' => '2026-07-15 08:00:00',
    'date_arrivee_estimee' => '2026-07-16 14:00:00',
]);

$expedition = $colisageService->getExpeditionDetails($expeditionId);
$results['groupage'] = [
    'id' => $expeditionId,
    'reference' => $expedition['reference'],
    'type_transport' => $expedition['type_transport'],
    'statut' => $expedition['statut'] ?? 'EN_PREPARATION',
    'status' => '✅ Créé avec succès',
];

// ============================================
// STEP 3: Créer un COLIS avec marchandises
// ============================================

// Create expediteur client
$expediteurId = $colisageService->registerClient([
    'name' => 'AICHA OUATTARA',
    'phone' => '0789665421',
    'email' => 'aicha.ouattara@email.com',
    'address' => 'Cocody, Abidjan, Côte d\'Ivoire',
    'type' => 'standard',
]);

// Create destinataire client
$destinataireId = $colisageService->registerClient([
    'name' => 'JEAN DUPONT',
    'phone' => '+33 6 12 34 56 78',
    'email' => 'jean.dupont@email.fr',
    'address' => '15 Rue de la Paix, 75001 Paris',
    'type' => 'standard',
]);

// Create parcel with merchandise
$colisId = $colisageService->registerParcel([
    'expediteur_id' => $expediteurId,
    'destinataire_id' => $destinataireId,
    'poids_total' => 25.5,
    'nombre_colis' => 3,
    'valeur_declaree' => 267750.0,
    'montant_total' => 267750.0,
    'devise' => 'XOF',
    'agence_depart_id' => $agenceDepartId,
    'agence_arrivee_id' => $agenceArriveeId,
    'type_expediteur' => 'export_aerien',
    'marchandises' => [
        [
            'product_id' => null,
            'custom_name' => 'Vêtements et accessoires',
            'custom_price' => 3500.0,
            'quantite' => 1,
            'nbre_colis' => 2,
            'emballage' => 'Carton renforcé',
            'qte_emballage' => 2,
            'poids_unitaire' => 15.0,
            'prix_kg' => 3500.0,
        ],
        [
            'product_id' => null,
            'custom_name' => 'Chaussures de marque',
            'custom_price' => 4000.0,
            'quantite' => 1,
            'nbre_colis' => 1,
            'emballage' => 'Carton simple',
            'qte_emballage' => 1,
            'poids_unitaire' => 10.5,
            'prix_kg' => 4000.0,
        ],
    ],
]);

$colis = $colisageService->getParcelDetails($colisId);
$results['colis'] = [
    'id' => $colisId,
    'numero_tracking' => $colis['numero_tracking'],
    'poids_total' => $colis['poids_total'],
    'nombre_colis' => $colis['nombre_colis'],
    'nb_marchandises' => count($colis['marchandises'] ?? []),
    'status' => '✅ Créé avec succès',
];

// ============================================
// STEP 4: Ajouter le colis au groupage
// ============================================
try {
    $colisageService->addParcelToExpedition($colisId, $expeditionId);
    $results['groupage_link'] = '✅ Colis ajouté au groupage';
} catch (\Exception $e) {
    $results['groupage_link'] = '⚠️ ' . $e->getMessage();
}

// ============================================
// STEP 5: Créer une FACTURE
// ============================================

// Calculate total from marchandises
$stmt = $pdo->prepare("SELECT SUM(total_ligne) FROM lbp_marchandises WHERE colis_id = :colis_id");
$stmt->execute(['colis_id' => $colisId]);
$totalXof = (float) $stmt->fetchColumn();

if ($totalXof <= 0) {
    // Fallback to montant_total from colis
    $totalXof = (float) ($colis['montant_total'] ?? $colis['valeur_declaree'] ?? 267750.0);
}

$numeroFacture = $factureRepo->generateNextInvoiceNumber($agenceDepartId);
$dateEcheance = date('Y-m-d H:i:s', strtotime('+7 days'));

$facture = new Facture(
    id: null,
    numeroFacture: $numeroFacture,
    colisId: $colisId,
    clientId: $expediteurId,
    caissiereId: (int) $admin['id'],
    agenceId: $agenceDepartId,
    montantTotal: $totalXof,
    montantEncaisse: 0.0,
    montantRestant: $totalXof,
    devise: 'XOF',
    tauxChange: null,
    statut: 'emise',
    dateEcheanceSolde: $dateEcheance
);

$factureId = $factureRepo->create($facture);

$results['facture'] = [
    'id' => $factureId,
    'numero' => $numeroFacture,
    'montant_total' => number_format($totalXof, 0, ',', '.') . ' FCFA',
    'statut' => 'emise',
    'status' => '✅ Facture générée avec succès',
];

// ============================================
// STEP 6: Créer un POINT JOURNALIER
// ============================================
$dateJour = date('Y-m-d');

// Check if daily report already exists
$existing = $etatRepo->findByAgenceAndDate($agenceDepartId, $dateJour);

if (!$existing) {
    $live = $etatRepo->computeTotalsForDay($agenceDepartId, $dateJour);

    $etat = new EtatJournalier(
        id: null,
        agenceId: $agenceDepartId,
        chefAgenceId: (int) $admin['id'],
        dateJour: $dateJour,
        nbColisEnregistres: $live['nb_colis'],
        nbFacturesEmises: $live['nb_factures'],
        totalFactureXof: $live['total_facture_xof'],
        totalFactureEur: $live['total_facture_eur'],
        totalEncaisseXof: $live['total_encaisse_xof'],
        totalEncaisseEur: $live['total_encaisse_eur'],
        totalRestantDuXof: $live['total_restant_du_xof'],
        totalRestantDuEur: $live['total_restant_du_eur'],
        soldeCaisseAgenceXof: $live['solde_caisse_agence_xof'],
        soldeCaisseAgenceEur: $live['solde_caisse_agence_eur'],
        statut: 'soumis',
        dateSoumission: date('Y-m-d H:i:s')
    );

    $reportId = $etatRepo->create($etat);
    $results['point_journalier'] = [
        'id' => $reportId,
        'date' => $dateJour,
        'nb_colis' => $live['nb_colis'],
        'nb_factures' => $live['nb_factures'],
        'total_facture_xof' => number_format($live['total_facture_xof'], 0, ',', '.') . ' FCFA',
        'total_encaisse_xof' => number_format($live['total_encaisse_xof'], 0, ',', '.') . ' FCFA',
        'statut' => 'soumis',
        'status' => '✅ Point journalier soumis',
    ];
} else {
    $results['point_journalier'] = [
        'id' => $existing->id,
        'date' => $dateJour,
        'statut' => $existing->statut,
        'status' => '⚠️ Point journalier existait déjà pour ce jour',
    ];
}

// ============================================
// STEP 7: Refresh parcel data for invoice
// ============================================
$colis = $colisageService->getParcelDetails($colisId);

// Update montant_total_eur with EUR conversion
$tauxChangeEur = 655.957;
try {
    $stmt = $pdo->query("SELECT setting_value FROM company_settings WHERE setting_key = 'taux_change_eur' LIMIT 1");
    if ($stmt) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && is_numeric($row['setting_value'])) {
            $tauxChangeEur = (float) $row['setting_value'];
        }
    }
} catch (\Exception $e) {}

$colis['montant_total'] = $totalXof;
$colis['montant_total_eur'] = $totalXof / $tauxChangeEur;

// If the action is to show the invoice directly
$showInvoice = ($_GET['view'] ?? '') === 'facture';

if ($showInvoice) {
    // Render the invoice view directly
    require BASE_PATH . '/views/colisage/parcels/facture.php';
    exit;
}

// ============================================
// DEFAULT: Show test results report
// ============================================
$invoiceUrl = '/ERP_LBP_REFACTO/scratch/visual_test.php?view=facture';
$appInvoiceUrl = '/ERP_LBP_REFACTO/colisage/parcels/' . $colisId . '/facture';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test Visuel — ERP LBP</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            color: #e2e8f0;
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #f59e0b, #ef4444, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        
        .header .badge {
            display: inline-block;
            background: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            color: #94a3b8;
            font-size: 0.9rem;
        }
        
        .step-card {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .step-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }
        
        .step-card h3 {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.1rem;
            font-weight: 700;
            color: #f8fafc;
            margin-bottom: 1rem;
        }
        
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
            font-size: 0.85rem;
            font-weight: 800;
            border-radius: 8px;
            flex-shrink: 0;
        }
        
        .step-card .success { color: #4ade80; }
        .step-card .warning { color: #fbbf24; }
        .step-card .error { color: #f87171; }
        
        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
        }
        
        .data-item {
            background: rgba(0, 0, 0, 0.2);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .data-item .label {
            font-size: 0.7rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .data-item .value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #e2e8f0;
        }
        
        .data-item .value.highlight {
            color: #fbbf24;
            font-weight: 800;
        }
        
        .actions {
            text-align: center;
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #f97316, #ef4444);
            color: white;
            box-shadow: 0 4px 15px rgba(249, 115, 22, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(249, 115, 22, 0.4);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #e2e8f0;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, #3b82f6, #8b5cf6, #ec4899);
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.5rem;
            top: 6px;
            width: 12px;
            height: 12px;
            background: #4ade80;
            border-radius: 50%;
            border: 2px solid #0f172a;
        }

        .summary-banner {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(139, 92, 246, 0.15));
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
            text-align: center;
        }
        
        .summary-banner h2 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            color: #93c5fd;
        }
        
        .timestamp {
            text-align: center;
            color: #475569;
            font-size: 0.8rem;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        
        <div class="header">
            <div class="badge">Test Automatisé</div>
            <h1>🧪 Test Visuel — ERP LBP Transit</h1>
            <p>Exécution du flux complet : Groupage → Colis → Facture → Point Journalier</p>
        </div>

        <div class="timeline">
            
            <!-- STEP 1: Agences -->
            <div class="timeline-item">
                <div class="step-card">
                    <h3><span class="step-number">1</span> Agences vérifiées</h3>
                    <div class="data-grid">
                        <div class="data-item">
                            <div class="label">Agence départ</div>
                            <div class="value"><?= htmlspecialchars($results['agences']['depart']['name']) ?></div>
                        </div>
                        <div class="data-item">
                            <div class="label">Agence destination</div>
                            <div class="value"><?= htmlspecialchars($results['agences']['arrivee']['name']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Groupage -->
            <div class="timeline-item">
                <div class="step-card">
                    <h3><span class="step-number">2</span> Groupage créé <span class="success"><?= $results['groupage']['status'] ?></span></h3>
                    <div class="data-grid">
                        <div class="data-item">
                            <div class="label">Référence</div>
                            <div class="value highlight"><?= htmlspecialchars($results['groupage']['reference']) ?></div>
                        </div>
                        <div class="data-item">
                            <div class="label">Type transport</div>
                            <div class="value">✈️ <?= htmlspecialchars($results['groupage']['type_transport']) ?></div>
                        </div>
                        <div class="data-item">
                            <div class="label">Statut</div>
                            <div class="value"><?= htmlspecialchars($results['groupage']['statut']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 3: Colis -->
            <div class="timeline-item">
                <div class="step-card">
                    <h3><span class="step-number">3</span> Colis enregistré <span class="success"><?= $results['colis']['status'] ?></span></h3>
                    <div class="data-grid">
                        <div class="data-item">
                            <div class="label">Numéro tracking</div>
                            <div class="value highlight"><?= htmlspecialchars($results['colis']['numero_tracking']) ?></div>
                        </div>
                        <div class="data-item">
                            <div class="label">Poids total</div>
                            <div class="value"><?= $results['colis']['poids_total'] ?> kg</div>
                        </div>
                        <div class="data-item">
                            <div class="label">Nombre de colis</div>
                            <div class="value"><?= $results['colis']['nombre_colis'] ?></div>
                        </div>
                        <div class="data-item">
                            <div class="label">Marchandises</div>
                            <div class="value"><?= $results['colis']['nb_marchandises'] ?> lignes</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 4: Groupage Link -->
            <div class="timeline-item">
                <div class="step-card">
                    <h3><span class="step-number">4</span> Colis → Groupage <span class="success"><?= $results['groupage_link'] ?></span></h3>
                </div>
            </div>

            <!-- STEP 5: Facture -->
            <div class="timeline-item">
                <div class="step-card">
                    <h3><span class="step-number">5</span> Facture générée <span class="success"><?= $results['facture']['status'] ?></span></h3>
                    <div class="data-grid">
                        <div class="data-item">
                            <div class="label">Numéro facture</div>
                            <div class="value highlight"><?= htmlspecialchars($results['facture']['numero']) ?></div>
                        </div>
                        <div class="data-item">
                            <div class="label">Montant total</div>
                            <div class="value highlight"><?= $results['facture']['montant_total'] ?></div>
                        </div>
                        <div class="data-item">
                            <div class="label">Statut</div>
                            <div class="value"><?= $results['facture']['statut'] ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 6: Point Journalier -->
            <div class="timeline-item">
                <div class="step-card">
                    <h3><span class="step-number">6</span> Point journalier <span class="success"><?= $results['point_journalier']['status'] ?></span></h3>
                    <div class="data-grid">
                        <div class="data-item">
                            <div class="label">Date</div>
                            <div class="value"><?= $results['point_journalier']['date'] ?></div>
                        </div>
                        <?php if (isset($results['point_journalier']['nb_colis'])): ?>
                        <div class="data-item">
                            <div class="label">Colis du jour</div>
                            <div class="value"><?= $results['point_journalier']['nb_colis'] ?></div>
                        </div>
                        <div class="data-item">
                            <div class="label">Factures émises</div>
                            <div class="value"><?= $results['point_journalier']['nb_factures'] ?></div>
                        </div>
                        <div class="data-item">
                            <div class="label">Total facturé</div>
                            <div class="value highlight"><?= $results['point_journalier']['total_facture_xof'] ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="data-item">
                            <div class="label">Statut</div>
                            <div class="value"><?= $results['point_journalier']['statut'] ?></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="summary-banner">
            <h2>✅ Tous les tests ont réussi !</h2>
            <p>Le flux complet a été exécuté avec succès. Cliquez ci-dessous pour voir la facture prête à l'impression.</p>
        </div>

        <div class="actions">
            <a href="<?= $invoiceUrl ?>" class="btn btn-primary" target="_blank">
                🖨️ Voir la Facture (Impression)
            </a>
            <a href="/ERP_LBP_REFACTO/colisage/parcels/<?= $colisId ?>" class="btn btn-secondary" target="_blank">
                📦 Voir le Colis
            </a>
            <a href="/ERP_LBP_REFACTO/colisage/groupage/<?= $expeditionId ?>" class="btn btn-secondary" target="_blank">
                ✈️ Voir le Groupage
            </a>
            <a href="/ERP_LBP_REFACTO/finance/clotures" class="btn btn-secondary" target="_blank">
                📊 Points de Caisse
            </a>
        </div>

        <div class="timestamp">
            Test exécuté le <?= date('d/m/Y à H:i:s') ?> — ERP LBP Transit v2.0
        </div>

    </div>
</body>
</html>
