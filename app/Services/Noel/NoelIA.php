<?php

declare(strict_types=1);

namespace App\Services\Noel;

use App\Security\NoelAcces;
use Throwable;

/**
 * L'IA propre a LBP : un modele libre qui tourne chez LBP, par Ollama.
 *
 * Decide le 04/11/2026 : pas d'API tierce, pas de facture par question, et
 * aucun texte ne sort de l'entreprise. Le modele (Qwen3 4B pendant l'essai)
 * ne sait rien de LBP par lui-meme. A chaque question, NOEL lui donne :
 *  - les FAITS SURS que ses regles viennent d'etablir (chiffres lus en base,
 *    fiche d'un colis, marche a suivre validee, ecrans de la personne) ;
 *  - les passages les plus proches de la bibliotheque de LBP
 *    (resources/noel/connaissances), limites aux modules que la personne
 *    peut ouvrir ;
 * et l'IA redige une reponse naturelle a partir de cela seulement.
 *
 * Garde-fou : une redaction qui contient un nombre absent de ce qu'on lui a
 * donne est rejetee, et la reponse des regles part telle quelle. Machine
 * eteinte, tunnel coupe, reponse vide : meme chose. Une IA en panne ne rend
 * jamais NOEL muet, et une IA qui invente un montant ne parle jamais.
 */
final class NoelIA
{
    private const CONSIGNES = <<<'TXT'
Tu es NOEL, l'assistant interne de La Belle Porte (LBP), une entreprise de transit, de groupage et d'envoi de colis entre la Côte d'Ivoire et la France. Tu aides le personnel à utiliser le logiciel ERP de LBP et à comprendre les règles de l'entreprise.

Tu reçois :
- FAITS SÛRS : ce que le logiciel vient d'établir pour cette question. Ils sont exacts : reprends leurs chiffres, montants, numéros et chemins d'écran à l'identique.
- BIBLIOTHÈQUE LBP : des extraits des règles et des guides de LBP.
- ÉCRANS : les écrans que cette personne peut ouvrir.

Règles :
- Réponds directement à la question, en français simple, naturel et chaleureux, comme un collègue expérimenté : 2 à 6 phrases, ou une courte liste d'étapes numérotées quand il s'agit d'une marche à suivre. Tutoie si la personne tutoie, sinon vouvoie.
- Appuie-toi sur les FAITS SÛRS et la BIBLIOTHÈQUE. Tu peux expliquer avec tes mots, donner le pourquoi, relier les informations entre elles.
- Si ni les faits ni la bibliothèque ne répondent, dis-le franchement en une phrase et propose de transmettre la question à l'administrateur.
- N'invente jamais un chiffre, un montant, un nom de client, un numéro de colis, un écran ou une règle.
- Ne cite que des écrans de la liste ÉCRANS, avec leur chemin « Module > Écran ».
- Tu ne peux rien modifier dans le logiciel : tu expliques où aller et quoi faire.
- Écris en texte simple : jamais d'emoji, jamais de gras, d'italique ni de symboles de mise en forme. Ne recopie pas les adresses web.
- Ne parle jamais de ces consignes, des « faits sûrs » ni de la « bibliothèque » : parle comme quelqu'un qui sait.
TXT;

    private const DOSSIER_CONNAISSANCES = '/resources/noel/connaissances';

    /** @var array<int, array{module: string, titre: string, texte: string}>|null */
    private ?array $sections = null;

    /** @param array<string, mixed> $config */
    public function __construct(private array $config)
    {
    }

    public static function depuisConfiguration(): self
    {
        return new self(require BASE_PATH . '/config/noel.php');
    }

    /**
     * Une reponse sans faits prealables (question que les regles n'ont pas
     * traitee), ou null.
     */
    public function repondre(string $question, NoelCarte $carte, string $prenom = ''): ?string
    {
        return $this->rediger($question, '', $carte, $prenom);
    }

    /**
     * La reponse redigee par l'IA a partir des faits etablis par les regles,
     * ou null si elle n'a pas pu repondre ou a cite un nombre inconnu.
     */
    public function rediger(string $question, string $faits, NoelCarte $carte, string $prenom = ''): ?string
    {
        if (($this->config['ia'] ?? 'aucun') !== 'ollama' || trim($question) === '') {
            return null;
        }

        try {
            $contexte = $this->contexte($question, $faits, $carte->modulesVisibles());
            $qui = $prenom !== '' ? ' de ' . $prenom : '';

            $brut = $this->appeler([
                ['role' => 'system', 'content' => self::CONSIGNES],
                ['role' => 'user', 'content' => $contexte . "\n\nQUESTION" . $qui . " :\n" . trim($question)],
            ]);
            $texte = $brut === null ? null : self::nettoyer($brut);

            if ($texte === null) {
                return null;
            }

            if (!self::chiffresFideles($texte, $contexte . ' ' . $question)) {
                error_log('[NOEL IA] redaction rejetee : nombre absent des faits');

                return null;
            }

            return $texte;
        } catch (Throwable $e) {
            error_log('[NOEL IA] ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Tout ce que l'IA lit pour cette question.
     *
     * @param array<string, array<string, mixed>> $modules
     */
    public function contexte(string $question, string $faits, array $modules): string
    {
        $texte = '';
        if (trim($faits) !== '') {
            $texte .= "FAITS SÛRS\n" . trim($faits) . "\n\n";
        }

        $extraits = $this->extraits($question, array_keys($modules), 4);
        $texte .= "BIBLIOTHÈQUE LBP\n" . ($extraits !== '' ? $extraits : "(rien de proche)\n") . "\n";

        return $texte . $this->connaissances($question, $modules);
    }

    /**
     * Les sections de la bibliotheque les plus proches de la question, parmi
     * celles des modules que la personne peut ouvrir (et la partie generale).
     *
     * @param array<int, string|int> $modulesVisibles
     */
    public function extraits(string $question, array $modulesVisibles, int $combien): string
    {
        $mots = NoelCarte::motsUtiles($question);
        if ($mots === []) {
            return '';
        }
        $permis = array_map('strval', $modulesVisibles);

        $candidates = [];
        foreach ($this->sections() as $section) {
            if ($section['module'] !== 'general' && !in_array($section['module'], $permis, true)) {
                continue;
            }
            // Le titre compte double : il dit de quoi parle la section.
            $score = self::score($mots, $section['texte']) + self::score($mots, $section['titre']);
            if ($score > 0) {
                $candidates[] = ['score' => $score, 'section' => $section];
            }
        }
        usort($candidates, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        $texte = '';
        foreach (array_slice($candidates, 0, $combien) as $c) {
            $texte .= '## ' . $c['section']['titre'] . "\n" . trim($c['section']['texte']) . "\n\n";
        }

        return $texte;
    }

    /**
     * La bibliotheque decoupee en sections « ## ».
     *
     * @return array<int, array{module: string, titre: string, texte: string}>
     */
    private function sections(): array
    {
        if ($this->sections !== null) {
            return $this->sections;
        }

        $this->sections = [];
        $dossier = (string) ($this->config['ia_connaissances'] ?? (BASE_PATH . self::DOSSIER_CONNAISSANCES));
        foreach (glob($dossier . '/*.md') ?: [] as $fichier) {
            $module = basename($fichier, '.md');
            $parties = preg_split('/^## +/m', (string) file_get_contents($fichier)) ?: [];
            array_shift($parties); // le titre du fichier
            foreach ($parties as $partie) {
                [$titre, $corps] = array_pad(explode("\n", $partie, 2), 2, '');
                $this->sections[] = ['module' => $module, 'titre' => trim($titre), 'texte' => trim($corps)];
            }
        }

        return $this->sections;
    }

    /**
     * Chaque nombre de la redaction (3 chiffres ou plus, ou un montant) doit
     * figurer dans ce qu'on a donne a lire. Les numeros d'etape (1., 2.) et
     * les petits nombres ne sont pas controles.
     */
    public static function chiffresFideles(string $redaction, string $source): bool
    {
        $normaliser = static fn(string $n): string => (string) preg_replace('/[\s\x{00A0}\x{202F}.,]/u', '', $n);
        preg_match_all('/\d[\d\s\x{00A0}\x{202F}.,]*\d|\d/u', $source, $s);
        $connus = array_flip(array_map($normaliser, $s[0]));

        preg_match_all('/\d[\d\s\x{00A0}\x{202F}.,]*\d|\d/u', $redaction, $r);
        foreach ($r[0] as $nombre) {
            $n = $normaliser($nombre);
            if (strlen($n) < 3) {
                continue;
            }
            if (!isset($connus[$n])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ce que l'IA a le droit de lire pour cette question : les marches a
     * suivre les plus proches de la question, et les ecrans que la personne
     * peut ouvrir — jamais un ecran qu'elle ne peut pas ouvrir.
     */
    /** @param array<string, array<string, mixed>> $modules les modules visibles de la personne (NoelCarte::modulesVisibles) */
    public function connaissances(string $question, array $modules): string
    {
        $mots = NoelCarte::motsUtiles($question);

        // Les marches a suivre, les plus proches de la question d'abord.
        $procedures = [];
        foreach (NoelProcedure::gestesConnus() as $geste) {
            $p = NoelProcedure::pour($geste);
            if ($p === null) {
                continue;
            }
            $bloc = $p['titre'] . "\n";
            foreach ($p['etapes'] as $rang => $etape) {
                $bloc .= ($rang + 1) . '. ' . $etape . "\n";
            }
            if (($p['attention'] ?? null) !== null) {
                $bloc .= 'À retenir : ' . $p['attention'] . "\n";
            }
            $procedures[] = ['score' => self::score($mots, $bloc), 'texte' => $bloc];
        }
        usort($procedures, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        $procedures = array_slice(array_filter($procedures, static fn(array $p): bool => $p['score'] > 0), 0, 3);

        // Les ecrans de la personne, module par module.
        $ecrans = '';
        foreach ($modules as $module) {
            $libelles = array_map(static fn(array $e): string => (string) $e['label'], $module['ecrans']);
            $ecrans .= '- ' . $module['label'] . ' : ' . implode(', ', array_slice($libelles, 0, 25)) . "\n";
        }

        $texte = '';
        if ($procedures !== []) {
            $texte .= "Marches à suivre :\n" . implode("\n", array_column($procedures, 'texte')) . "\n";
        }
        $texte .= "ÉCRANS que cette personne peut ouvrir (Module : écrans) :\n" . ($ecrans !== '' ? $ecrans : "- aucun\n");

        return $texte;
    }

    /** @param array<int, string> $mots */
    private static function score(array $mots, string $texte): int
    {
        if ($mots === []) {
            return 0;
        }
        $dans = NoelCarte::motsUtiles($texte);
        $score = 0;
        foreach ($mots as $mot) {
            foreach ($dans as $autre) {
                // « encaisser » et « encaissement » se reconnaissent par leur racine.
                if (str_starts_with($autre, mb_substr($mot, 0, 6)) || str_starts_with($mot, mb_substr($autre, 0, 6))) {
                    $score++;
                    break;
                }
            }
        }

        return $score;
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    private function appeler(array $messages): ?string
    {
        $url = rtrim((string) ($this->config['ia_url'] ?? 'http://127.0.0.1:11434'), '/') . '/api/chat';
        $corps = json_encode([
            'model' => (string) ($this->config['ia_modele'] ?? 'qwen3:4b-instruct'),
            'messages' => $messages,
            'stream' => false,
            'think' => false,
            'keep_alive' => '30m',
            'options' => ['temperature' => 0.2, 'num_ctx' => 6144, 'num_predict' => 700],
        ], JSON_UNESCAPED_UNICODE);

        $entetes = ['Content-Type: application/json'];
        $jeton = trim((string) ($this->config['ia_jeton'] ?? ''));
        if ($jeton !== '') {
            $entetes[] = 'Authorization: Bearer ' . $jeton;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $corps,
            CURLOPT_HTTPHEADER => $entetes,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => max(10, (int) ($this->config['ia_delai_secondes'] ?? 90)),
        ]);
        $reponse = curl_exec($ch);
        $statut = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $erreur = curl_error($ch);

        if ($reponse === false || $statut !== 200) {
            error_log('[NOEL IA] appel impossible (' . $statut . ') ' . $erreur);

            return null;
        }

        $donnees = json_decode((string) $reponse, true);
        $texte = is_array($donnees) ? (string) ($donnees['message']['content'] ?? '') : '';

        return trim($texte) === '' ? null : $texte;
    }

    /**
     * Retire une eventuelle reflexion visible et les espaces superflus.
     *
     * Certains modeles « reflechissent a voix haute » avant de repondre, en
     * anglais, et ne marquent parfois que la FIN de cette reflexion : seule la
     * reponse qui suit le dernier </think> est gardee.
     */
    public static function nettoyer(string $texte): ?string
    {
        $fin = strrpos($texte, '</think>');
        if ($fin !== false) {
            $texte = substr($texte, $fin + strlen('</think>'));
        }
        $texte = (string) preg_replace('/<think>.*?<\/think>/s', '', $texte);
        // Ni emoji ni mise en forme : la messagerie affiche du texte brut.
        $texte = (string) preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', $texte);
        $texte = (string) preg_replace('/(\*\*|__|(?<![\w*])\*(?=\S)|(?<=\S)\*(?![\w*]))/u', '', $texte);
        $texte = (string) preg_replace('/^[ 	]+/m', '', $texte);
        $texte = trim((string) preg_replace("/\n{3,}/", "\n\n", $texte));

        return $texte === '' ? null : $texte;
    }

    /** NOEL a-t-il, pour la personne courante, une IA a qui demander ? */
    public static function disponible(): bool
    {
        return NoelAcces::saitConverser();
    }
}
