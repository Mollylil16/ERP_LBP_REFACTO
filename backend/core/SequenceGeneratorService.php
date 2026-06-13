<?php

namespace App\Core;

use App\Models\Database;
use PDO;

class SequenceGeneratorService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /**
     * Génère un numéro séquentiel unique avec verrouillage de table
     */
    public function generateNumber(string $type, string $prefixe, string $format, int $mois, int $annee): string
    {
        try {
            $this->pdo->beginTransaction();

            // Verrouille la ligne pour éviter les accès concurrents
            $stmt = $this->pdo->prepare("
                SELECT numero FROM lbp_numeros_sequences 
                WHERE type = :type AND annee = :annee AND mois = :mois 
                FOR UPDATE
            ");
            $stmt->execute([
                'type' => $type,
                'annee' => $annee,
                'mois' => $mois
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $numero = $row['numero'] + 1;
                $update = $this->pdo->prepare("
                    UPDATE lbp_numeros_sequences 
                    SET numero = :numero, updated_at = NOW() 
                    WHERE type = :type AND annee = :annee AND mois = :mois
                ");
                $update->execute([
                    'numero' => $numero,
                    'type' => $type,
                    'annee' => $annee,
                    'mois' => $mois
                ]);
            } else {
                $numero = 1;
                $insert = $this->pdo->prepare("
                    INSERT INTO lbp_numeros_sequences (type, prefixe, annee, mois, numero) 
                    VALUES (:type, :prefixe, :annee, :mois, :numero)
                ");
                $insert->execute([
                    'type' => $type,
                    'prefixe' => $prefixe,
                    'annee' => $annee,
                    'mois' => $mois,
                    'numero' => $numero
                ]);
            }

            $this->pdo->commit();

            // Remplacer les variables dans le format
            $numeroStr = str_pad((string)$numero, 3, '0', STR_PAD_LEFT);
            $moisStr = str_pad((string)$mois, 2, '0', STR_PAD_LEFT);
            $anneeStr = substr((string)$annee, -2); // Garde uniquement les 2 derniers chiffres

            $result = str_replace(
                ['{PREFIX}', '{MM}', '{YY}', '{NUM}'],
                [$prefixe, $moisStr, $anneeStr, $numeroStr],
                $format
            );

            return $result;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Génère un numéro de dossier/colis (Ex: LB-CI 001)
     */
    public function generateDossier(string $codeAgence): string
    {
        // Ex: $codeAgence = "CI"
        $prefixe = "LB-" . $codeAgence;
        // La séquence de dossier semble ne pas utiliser MMYY d'après "LB-CI 001", ou on peut le garder pour isoler par mois.
        // Faisons une séquence annuelle ou globale. Si on suit le format exact "LB-CI 001", le format est {PREFIX} {NUM}
        // Pour éviter de réinitialiser tous les mois, on passe 0 pour le mois et l'année.
        return $this->generateNumber('DOSSIER', $prefixe, '{PREFIX} {NUM}', 0, 0);
    }

    /**
     * Génère un numéro de fiche recette (Ex: FR0124/001)
     */
    public function generateFicheRecette(): string
    {
        $mois = (int)date('m');
        $annee = (int)date('Y');
        return $this->generateNumber('FICHE_RECETTE', 'FR', '{PREFIX}{MM}{YY}/{NUM}', $mois, $annee);
    }

    /**
     * Génère un numéro de bordereau de versement (Ex: BVI0124/001)
     */
    public function generateBordereauVersement(): string
    {
        $mois = (int)date('m');
        $annee = (int)date('Y');
        return $this->generateNumber('BORDEREAU_VI', 'BVI', '{PREFIX}{MM}{YY}/{NUM}', $mois, $annee);
    }

    /**
     * Génère un numéro de décaissement (Ex: DEC0124/001)
     */
    public function generateDecaissement(): string
    {
        $mois = (int)date('m');
        $annee = (int)date('Y');
        return $this->generateNumber('ORDRE_DEC', 'DEC', '{PREFIX}{MM}{YY}/{NUM}', $mois, $annee);
    }
}
