<?php

declare(strict_types=1);

namespace App\Repositories\Colisage;

use PDO;

/**
 * Le journal des décisions prises sur les demandes de fournitures.
 *
 * Contrairement aux demandes de fonds, les fournitures n'ont pas de table
 * d'historique : chaque décision s'inscrit en colonne sur la demande
 * elle-même — qui a approuvé et quand, qui a confirmé et quand, quand la
 * livraison a eu lieu. Le journal se reconstitue donc à partir de ces quatre
 * moments, plutôt que d'ajouter une table et de perdre tout le passé.
 *
 * Le filtrage se fait en PHP et non en SQL : une demande porte jusqu'à quatre
 * décisions, et les filtrer en base obligerait à quatre requêtes réunies dont
 * chacune supposerait des colonnes qui n'existent pas partout.
 */
final class FournituresHistoriqueRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string, mixed> $filtres
     * @return array<int, array<string, mixed>>
     */
    public function journal(array $filtres = []): array
    {
        $evenements = [];

        foreach ($this->demandes() as $demande) {
            foreach ($this->evenementsDe($demande) as $evenement) {
                $evenements[] = $evenement;
            }
        }

        $evenements = array_values(array_filter(
            $evenements,
            fn (array $e): bool => $this->retenu($e, $filtres)
        ));

        usort($evenements, static fn (array $a, array $b): int => strcmp((string) $b['quand'], (string) $a['quand']));

        return array_slice($evenements, 0, 2000);
    }

    /**
     * Les personnes qui ont deja tranche : de quoi remplir le filtre « par qui ».
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function decideurs(): array
    {
        $noms = [];

        foreach ($this->demandes() as $demande) {
            foreach (['valideur_nom', 'confirmateur_nom'] as $champ) {
                $nom = trim((string) ($demande[$champ] ?? ''));
                if ($nom !== '') {
                    $noms[$nom] = ['id' => 0, 'name' => $nom];
                }
            }
        }

        ksort($noms);

        return array_values($noms);
    }

    /** @return array<int, array<string, mixed>> */
    private function demandes(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    f.id, f.status, f.items_requested, f.quantite, f.prix_unitaire, f.montant,
                    f.rejection_reason, f.created_at, f.validated_at, f.confirmed_at, f.delivered_at,
                    s.name AS agence_nom,
                    dem.full_name AS demandeur_nom,
                    val.full_name AS valideur_nom,
                    con.full_name AS confirmateur_nom
                FROM lbp_demandes_fournitures f
                LEFT JOIN company_sites s ON s.id = f.agency_id
                LEFT JOIN users dem ON dem.id = f.requested_by
                LEFT JOIN users val ON val.id = f.validated_by
                LEFT JOIN users con ON con.id = f.confirmed_by
                ORDER BY f.id DESC
                LIMIT 1000
            ");

            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Throwable $e) {
            error_log('[FournituresHistoriqueRepository] journal : ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Les décisions lisibles sur une demande.
     *
     * Un rejet et une approbation s'inscrivent dans la même colonne de date :
     * c'est le statut final qui dit laquelle des deux a eu lieu.
     *
     * @param array<string, mixed> $d
     * @return array<int, array<string, mixed>>
     */
    private function evenementsDe(array $d): array
    {
        $commun = [
            'demande_id' => (int) $d['id'],
            'objet' => (string) ($d['items_requested'] ?? ''),
            'quantite' => $d['quantite'] ?? null,
            'montant' => $d['montant'] ?? null,
            'agence_nom' => (string) ($d['agence_nom'] ?? ''),
            'demandeur_nom' => (string) ($d['demandeur_nom'] ?? ''),
            'statut_actuel' => (string) ($d['status'] ?? ''),
        ];

        $evenements = [];

        if (!empty($d['created_at'])) {
            $evenements[] = $commun + [
                'action' => 'DEMANDE',
                'quand' => (string) $d['created_at'],
                'par' => $commun['demandeur_nom'],
                'motif' => '',
            ];
        }

        $rejetee = (string) ($d['status'] ?? '') === 'REJETEE';

        if (!empty($d['validated_at'])) {
            $evenements[] = $commun + [
                'action' => $rejetee ? 'REJET' : 'APPROBATION',
                'quand' => (string) $d['validated_at'],
                'par' => (string) ($d['valideur_nom'] ?? ''),
                'motif' => $rejetee ? (string) ($d['rejection_reason'] ?? '') : '',
            ];
        }

        if (!empty($d['confirmed_at'])) {
            $evenements[] = $commun + [
                'action' => 'CONFIRMATION',
                'quand' => (string) $d['confirmed_at'],
                'par' => (string) ($d['confirmateur_nom'] ?? ''),
                'motif' => '',
            ];
        }

        if (!empty($d['delivered_at'])) {
            // La livraison ne porte pas de nom : c'est l'agence qui la déclare
            // à réception, et la colonne n'enregistre pas qui a cliqué.
            $evenements[] = $commun + [
                'action' => 'LIVRAISON',
                'quand' => (string) $d['delivered_at'],
                'par' => '',
                'motif' => '',
            ];
        }

        return $evenements;
    }

    /**
     * @param array<string, mixed> $e
     * @param array<string, mixed> $f
     */
    private function retenu(array $e, array $f): bool
    {
        $jour = substr((string) $e['quand'], 0, 10);

        $du = trim((string) ($f['du'] ?? ''));
        if ($du !== '' && $jour < $du) {
            return false;
        }

        $au = trim((string) ($f['au'] ?? ''));
        if ($au !== '' && $jour > $au) {
            return false;
        }

        $action = strtoupper(trim((string) ($f['action'] ?? '')));
        if ($action !== '' && (string) $e['action'] !== $action) {
            return false;
        }

        $agence = trim((string) ($f['agence'] ?? ''));
        if ($agence !== '' && (string) $e['agence_nom'] !== $agence) {
            return false;
        }

        $par = trim((string) ($f['par'] ?? ''));
        if ($par !== '' && (string) $e['par'] !== $par) {
            return false;
        }

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $foin = mb_strtolower($e['objet'] . ' ' . $e['motif'] . ' ' . $e['demandeur_nom'] . ' ' . $e['par']);
            if (!str_contains($foin, mb_strtolower($q))) {
                return false;
            }
        }

        return true;
    }
}
