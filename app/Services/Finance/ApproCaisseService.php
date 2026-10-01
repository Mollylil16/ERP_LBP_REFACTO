<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Helpers\Auth;
use App\Models\Database;
use App\Security\ApproCaisseAcces;
use PDO;

/**
 * Approvisionnement de caisse : l'argent que le siège remet à une agence.
 *
 * La Gestion des Fonds ne connaissait que les décaissements — l'argent qui
 * sort. Rien ne disait ce qui entrait dans un tiroir, si bien qu'une agence
 * approvisionnée comptait le soir plus que le logiciel n'attendait, et
 * s'entendait reprocher un écart qu'elle n'avait pas fait.
 *
 * La caissière principale saisit, le comptable valide, et à partir de sa date
 * d'effet l'appro entre dans l'attendu du point de caisse de l'agence. Tant
 * qu'il n'est pas validé, il ne compte nulle part : un appro annoncé n'est pas
 * un appro remis.
 */
final class ApproCaisseService
{
    /** D'où vient l'argent remis. */
    public const SOURCES = [
        'SIEGE' => 'Siège',
        'BANQUE' => 'Retrait bancaire',
        'AUTRE_AGENCE' => 'Autre agence',
        'AUTRE' => 'Autre',
    ];

    /**
     * Les monnaies d'un tiroir. Paris compte en euros : un appro saisi en
     * francs y serait attendu dans la mauvaise caisse.
     *
     * @var array<string, string>
     */
    public const DEVISES = ['XOF' => 'FCFA', 'EUR' => 'EUR'];

    /** @var array<string, string> */
    public const STATUTS = [
        'en_attente' => 'En attente de validation',
        'validee' => 'Validé',
        'rejetee' => 'Rejeté',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public static function creer(): self
    {
        return new self(Database::getConnection());
    }

    /**
     * L'écran : les appros de la période, les totaux, et les agences.
     *
     * @param array<string, mixed> $requete
     * @return array<string, mixed>
     */
    public function tableau(array $requete): array
    {
        $filtres = $this->filtres($requete);
        $lignes = $this->lister($filtres);

        return [
            'filtres' => $filtres,
            'lignes' => $lignes,
            'totaux' => $this->totaux($lignes),
            'agences' => $this->agences(),
            'peutSaisir' => ApproCaisseAcces::peutSaisir(),
            'peutValider' => ApproCaisseAcces::peutValider(),
        ];
    }

    /**
     * @param array<string, mixed> $requete
     * @return array{du:string, au:string, agence_id:int, statut:string}
     */
    public function filtres(array $requete): array
    {
        $date = static function (mixed $valeur, string $defaut): string {
            $texte = trim((string) ($valeur ?? ''));

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $texte) === 1 ? $texte : $defaut;
        };

        $du = $date($requete['du'] ?? null, date('Y-m-01'));
        $au = $date($requete['au'] ?? null, date('Y-m-d'));

        if ($du > $au) {
            [$du, $au] = [$au, $du];
        }

        $statut = (string) ($requete['statut'] ?? '');

        // Une caissière ne voit que son agence : le filtre ne lui sert pas à
        // regarder ailleurs.
        $agence = max(0, (int) ($requete['agence_id'] ?? 0));
        if (!ApproCaisseAcces::voitToutesLesAgences()) {
            $agence = (int) (Auth::agenceId() ?? 0);
        }

        return [
            'du' => $du,
            'au' => $au,
            'agence_id' => $agence,
            'statut' => isset(self::STATUTS[$statut]) ? $statut : '',
        ];
    }

    /**
     * @param array<string, mixed> $filtres
     * @return array<int, array<string, mixed>>
     */
    public function lister(array $filtres): array
    {
        $conditions = ['a.date_effet BETWEEN :du AND :au'];
        $parametres = ['du' => $filtres['du'], 'au' => $filtres['au']];

        if ((int) $filtres['agence_id'] > 0) {
            $conditions[] = 'a.agence_id = :agence';
            $parametres['agence'] = (int) $filtres['agence_id'];
        }

        if (($filtres['statut'] ?? '') !== '') {
            $conditions[] = 'a.statut = :statut';
            $parametres['statut'] = (string) $filtres['statut'];
        }

        $stmt = $this->pdo->prepare('
            SELECT a.*, s.name AS agence, d.full_name AS demandeur, v.full_name AS validateur
            FROM lbp_appros_caisse a
            LEFT JOIN company_sites s ON s.id = a.agence_id
            LEFT JOIN users d ON d.id = a.demandeur_id
            LEFT JOIN users v ON v.id = a.validateur_id
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY a.date_effet DESC, a.id DESC
            LIMIT 300
        ');
        $stmt->execute($parametres);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function trouver(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT a.*, s.name AS agence
            FROM lbp_appros_caisse a
            LEFT JOIN company_sites s ON s.id = a.agence_id
            WHERE a.id = :id LIMIT 1
        ');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Enregistre un approvisionnement, en attente de validation.
     *
     * @param array<string, mixed> $saisie
     * @return array{0:string, 1:array<int, string>} message, erreurs
     */
    public function enregistrer(array $saisie): array
    {
        if (!ApproCaisseAcces::peutSaisir()) {
            return ['', ["La saisie d'un approvisionnement revient à la caissière principale."]];
        }

        $erreurs = [];
        $agence = (int) ($saisie['agence_id'] ?? 0);
        $montant = (float) str_replace([' ', ','], ['', '.'], (string) ($saisie['montant'] ?? '0'));
        $motif = trim((string) ($saisie['motif'] ?? ''));
        $source = (string) ($saisie['source'] ?? 'SIEGE');
        $dateEffet = trim((string) ($saisie['date_effet'] ?? ''));

        if ($agence <= 0) {
            $erreurs[] = "Indiquez l'agence qui reçoit l'argent.";
        }
        if ($montant <= 0) {
            $erreurs[] = 'Le montant doit être supérieur à zéro.';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEffet) !== 1) {
            $erreurs[] = "La date de remise est obligatoire : c'est le jour où l'argent entre en caisse.";
        }
        if ($motif === '') {
            $erreurs[] = "Dites à quoi sert cet approvisionnement : l'agence le lira le soir, au comptage.";
        }

        if ($erreurs !== []) {
            return ['', $erreurs];
        }

        $devise = (string) ($saisie['devise'] ?? 'XOF');

        $stmt = $this->pdo->prepare("
            INSERT INTO lbp_appros_caisse
                (numero, agence_id, montant, devise, source, motif, date_effet, demandeur_id, statut, created_at)
            VALUES
                (:numero, :agence, :montant, :devise, :source, :motif, :date_effet, :demandeur, 'en_attente', NOW())
        ");
        $stmt->execute([
            'numero' => $this->prochainNumero(),
            'agence' => $agence,
            'montant' => $montant,
            'devise' => isset(self::DEVISES[$devise]) ? $devise : 'XOF',
            'source' => isset(self::SOURCES[$source]) ? $source : 'AUTRE',
            'motif' => $motif,
            'date_effet' => $dateEffet,
            'demandeur' => (int) Auth::id(),
        ]);

        return ['Approvisionnement enregistré. Il attend la validation du comptable.', []];
    }

    /**
     * Le comptable valide : l'argent entre alors dans l'attendu du point de
     * caisse de l'agence, à sa date de remise.
     *
     * @return array{0:string, 1:array<int, string>}
     */
    public function valider(int $id): array
    {
        if (!ApproCaisseAcces::peutValider()) {
            return ['', ['La validation des approvisionnements revient au comptable.']];
        }

        $appro = $this->trouver($id);

        if ($appro === null) {
            return ['', ['Cet approvisionnement est introuvable.']];
        }

        if ((string) $appro['statut'] !== 'en_attente') {
            return ['', ['Cet approvisionnement a déjà été traité.']];
        }

        $stmt = $this->pdo->prepare("
            UPDATE lbp_appros_caisse
               SET statut = 'validee', validateur_id = :validateur, date_validation = NOW(), updated_at = NOW()
             WHERE id = :id AND statut = 'en_attente'
        ");
        $stmt->execute(['validateur' => (int) Auth::id(), 'id' => $id]);

        return [
            'Approvisionnement ' . $appro['numero'] . ' validé. Il entre dans la caisse de '
            . ($appro['agence'] ?: 'l\'agence') . ' au ' . $this->jour((string) $appro['date_effet']) . '.',
            [],
        ];
    }

    /**
     * @return array{0:string, 1:array<int, string>}
     */
    public function rejeter(int $id, string $motif): array
    {
        if (!ApproCaisseAcces::peutValider()) {
            return ['', ['Le rejet des approvisionnements revient au comptable.']];
        }

        $motif = trim($motif);

        if ($motif === '') {
            return ['', ['Un rejet doit dire pourquoi : la caissière principale corrigera sur cette phrase.']];
        }

        $appro = $this->trouver($id);

        if ($appro === null || (string) $appro['statut'] !== 'en_attente') {
            return ['', ['Cet approvisionnement est introuvable ou déjà traité.']];
        }

        $stmt = $this->pdo->prepare("
            UPDATE lbp_appros_caisse
               SET statut = 'rejetee', validateur_id = :validateur, date_validation = NOW(),
                   motif_rejet = :motif, updated_at = NOW()
             WHERE id = :id AND statut = 'en_attente'
        ");
        $stmt->execute(['validateur' => (int) Auth::id(), 'motif' => $motif, 'id' => $id]);

        return ['Approvisionnement ' . $appro['numero'] . ' rejeté.', []];
    }

    /**
     * Ce qu'une agence a reçu un jour donné, et qui doit donc se retrouver dans
     * son tiroir. Seuls les appros validés comptent.
     */
    public static function montantValide(PDO $pdo, int $agenceId, string $jour, string $devise = 'XOF'): float
    {
        try {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(montant), 0)
                FROM lbp_appros_caisse
                WHERE agence_id = :agence AND date_effet = :jour AND statut = 'validee' AND devise = :devise
            ");
            $stmt->execute(['agence' => $agenceId, 'jour' => $jour, 'devise' => $devise]);

            return (float) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            // La table peut manquer sur une base que les migrations n'ont pas
            // encore touchée : un point de caisse ne doit pas tomber pour ça.
            return 0.0;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $lignes
     * @return array<string, mixed>
     */
    public function totaux(array $lignes): array
    {
        $totaux = ['nombre' => count($lignes), 'en_attente' => 0.0, 'valide' => 0.0, 'rejete' => 0.0, 'a_valider' => 0];

        foreach ($lignes as $ligne) {
            $montant = (float) $ligne['montant'];

            match ((string) $ligne['statut']) {
                'validee' => $totaux['valide'] += $montant,
                'rejetee' => $totaux['rejete'] += $montant,
                default => $totaux['en_attente'] += $montant,
            };

            if ((string) $ligne['statut'] === 'en_attente') {
                $totaux['a_valider']++;
            }
        }

        return $totaux;
    }

    /** @return array<int, array<string, mixed>> */
    public function agences(): array
    {
        return $this->pdo
            ->query('SELECT id, name FROM company_sites WHERE is_active = 1 ORDER BY name')
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function prochainNumero(): string
    {
        $prefixe = 'APP-' . date('Ym') . '-';

        $stmt = $this->pdo->prepare('SELECT numero FROM lbp_appros_caisse WHERE numero LIKE :prefixe ORDER BY id DESC LIMIT 1');
        $stmt->execute(['prefixe' => $prefixe . '%']);
        $dernier = (string) ($stmt->fetchColumn() ?: '');

        $rang = $dernier === '' ? 0 : (int) substr($dernier, -3);

        return $prefixe . str_pad((string) ($rang + 1), 3, '0', STR_PAD_LEFT);
    }

    private function jour(string $date): string
    {
        $objet = date_create($date);

        return $objet === false ? $date : $objet->format('d/m/Y');
    }
}
