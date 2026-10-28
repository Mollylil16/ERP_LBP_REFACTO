<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * Une base SQLite en mémoire avec les tables du pointage des colis, pour
 * faire tourner les vraies requêtes de PointageColisRepository.
 *
 * NOW() et CURDATE() sont fournis par PHP et suivent une horloge réglable :
 * le délai des 24 heures et le résumé du soir se testent sans attendre.
 */
final class BaseColisSqlite
{
    public string $maintenant = '2026-10-23 10:00:00';

    public PDO $pdo;

    private int $suivant = 1;

    public function __construct()
    {
        /*
         * PDO et non \Pdo\Sqlite : cette classe n'existe qu'a partir de
         * PHP 8.4, et la production tourne en 8.1.34. Le harnais tombait donc
         * avec « Class Pdo\Sqlite not found » et emportait les vingt-neuf
         * tests de la reception avec lui.
         */
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->sqliteCreateFunction('NOW', fn (): string => $this->maintenant, 0);
        $pdo->sqliteCreateFunction('CURDATE', fn (): string => substr($this->maintenant, 0, 10), 0);

        $pdo->exec("CREATE TABLE company_sites (id INTEGER PRIMARY KEY, name TEXT NOT NULL, is_active INTEGER NOT NULL DEFAULT 1,
            fuseau_horaire TEXT NOT NULL DEFAULT 'Africa/Abidjan', updated_at TEXT NULL)");
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, full_name TEXT)');
        $pdo->exec('CREATE TABLE lbp_clients (id INTEGER PRIMARY KEY, name TEXT, phone TEXT)');
        $pdo->exec("CREATE TABLE lbp_expeditions (id INTEGER PRIMARY KEY AUTOINCREMENT, reference TEXT NOT NULL UNIQUE, type_transport TEXT,
            agence_depart_id INTEGER, agence_arrivee_id INTEGER, date_depart_prevue TEXT, statut TEXT NOT NULL DEFAULT 'BROUILLON',
            created_at TEXT NOT NULL, updated_at TEXT NULL, date_depart_effective TEXT NULL, parti_par_id INTEGER NULL,
            date_premiere_reception TEXT NULL, est_reprise INTEGER NOT NULL DEFAULT 0)");
        $pdo->exec("CREATE TABLE lbp_colis (id INTEGER PRIMARY KEY AUTOINCREMENT, numero_tracking TEXT NOT NULL UNIQUE,
            expediteur_id INTEGER, destinataire_id INTEGER, poids_total REAL NOT NULL DEFAULT 0, nombre_colis INTEGER NOT NULL DEFAULT 1,
            agence_depart_id INTEGER, agence_arrivee_id INTEGER, statut TEXT NOT NULL DEFAULT 'enregistre', expedition_id INTEGER NULL,
            created_at TEXT NOT NULL, updated_at TEXT NULL, date_arrivee_agence TEXT NULL, statut_depart TEXT NULL, date_statut_depart TEXT NULL,
            date_reception TEXT NULL, recu_par_id INTEGER NULL, reception_hors_liste INTEGER NOT NULL DEFAULT 0,
            etat_reception TEXT NULL, commentaire_reception TEXT NULL, pointe_le TEXT NULL, pointe_par_id INTEGER NULL)");
        $pdo->exec('CREATE TABLE lbp_colis_pointages (id INTEGER PRIMARY KEY AUTOINCREMENT, colis_id INTEGER NOT NULL, expedition_id INTEGER NULL,
            agence_id INTEGER NULL, action TEXT NOT NULL, source TEXT NOT NULL, user_id INTEGER NULL, created_at TEXT NOT NULL,
            etat_avant TEXT NULL, etat_apres TEXT NULL, commentaire TEXT NULL)');
        $pdo->exec('CREATE TABLE lbp_colis_resumes_reception (id INTEGER PRIMARY KEY AUTOINCREMENT, expedition_id INTEGER NOT NULL, jour TEXT NOT NULL,
            conversation_id INTEGER NULL, message_id INTEGER NULL, created_at TEXT NOT NULL, UNIQUE (expedition_id, jour))');

        $pdo->exec("INSERT INTO company_sites (id, name, fuseau_horaire) VALUES
            (3403, 'Agence Abobo Dokui', 'Africa/Abidjan'), (3400, 'Paris Bobigny', 'Europe/Paris'), (3401, 'Agence Sénégal', 'Africa/Dakar')");
        $pdo->exec("INSERT INTO users (id, full_name) VALUES (10, 'Agent Paris'), (11, 'Agent Dokui')");
        $pdo->exec("INSERT INTO lbp_clients (id, name, phone) VALUES (1, 'Client Abidjan', '0700000000'), (2, 'Client Paris', '0600000000')");

        $this->pdo = $pdo;
    }

    /**
     * Un départ parti au moment présent, avec $nombre colis.
     *
     * @return array{id:int, colis:array<int, int>}
     */
    public function depart(int $nombre, int $depart = 3403, int $arrivee = 3400): array
    {
        $reference = 'DEP-TEST-' . $this->suivant++;
        $this->pdo->prepare("INSERT INTO lbp_expeditions (reference, type_transport, agence_depart_id, agence_arrivee_id, statut, created_at, date_depart_effective)
            VALUES (?, 'AÉRIEN', ?, ?, 'EN_TRANSIT', ?, ?)")->execute([$reference, $depart, $arrivee, $this->maintenant, $this->maintenant]);
        $id = (int) $this->pdo->lastInsertId();

        $colis = [];
        $insertion = $this->pdo->prepare("INSERT INTO lbp_colis (numero_tracking, expediteur_id, destinataire_id, agence_depart_id, agence_arrivee_id,
            statut, expedition_id, created_at) VALUES (?, 1, 2, ?, ?, 'en_transit', ?, ?)");
        for ($i = 1; $i <= $nombre; $i++) {
            $insertion->execute([$reference . '-C' . $i, $depart, $arrivee, $id, $this->maintenant]);
            $colis[] = (int) $this->pdo->lastInsertId();
        }

        return ['id' => $id, 'colis' => $colis];
    }

    /** @return array<string, mixed> */
    public function colis(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lbp_colis WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function journal(int $colisId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lbp_colis_pointages WHERE colis_id = ? ORDER BY id');
        $stmt->execute([$colisId]);

        return $stmt->fetchAll();
    }
}
