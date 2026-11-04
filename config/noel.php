<?php

declare(strict_types=1);

/**
 * NOEL, l'assistant interne de La Belle Porte.
 *
 * Aucune clé n'est écrite en dur ici, contrairement a config/infobip.php :
 * ce fichier est suivi par git et part sur GitHub. La clé se pose sur le
 * serveur, en variable d'environnement, et nulle part ailleurs. Sans elle,
 * NOEL reste utilisable — il répond alors sur ce qu'il sait du logiciel, et
 * dit franchement qu'il ne peut pas aller plus loin.
 */
return [
    /*
     * L'interrupteur. Il doit pouvoir s'éteindre en une seconde : le jour où
     * NOEL dit une bêtise devant tout le personnel, on ne cherche pas quel
     * fichier modifier.
     */
    'actif' => filter_var(
        $_SERVER['NOEL_ACTIF'] ?? $_ENV['NOEL_ACTIF'] ?? getenv('NOEL_ACTIF') ?: 'true',
        FILTER_VALIDATE_BOOL
    ),

    'cle_api' => $_SERVER['NOEL_CLE_API'] ?? $_ENV['NOEL_CLE_API'] ?? getenv('NOEL_CLE_API') ?: '',

    'url' => $_SERVER['NOEL_URL'] ?? $_ENV['NOEL_URL'] ?? getenv('NOEL_URL')
        ?: 'https://api.anthropic.com/v1/messages',

    /*
     * Le modele de tous les jours : il repond aux questions du personnel. Le
     * modele leger sert au mot du matin et aux reformulations, ou la finesse
     * compte moins que le prix — ces textes-la sont produits par centaines.
     */
    'modele' => $_SERVER['NOEL_MODELE'] ?? $_ENV['NOEL_MODELE'] ?? getenv('NOEL_MODELE')
        ?: 'claude-sonnet-5-5',

    'modele_leger' => $_SERVER['NOEL_MODELE_LEGER'] ?? $_ENV['NOEL_MODELE_LEGER'] ?? getenv('NOEL_MODELE_LEGER')
        ?: 'claude-haiku-5-5',

    /*
     * Les garde-fous de depense. Chaque message est facture : sans plafond, un
     * seul agent qui s'amuse une apres-midi peut couter le budget du mois.
     * Les compteurs se remettent a zero chaque jour.
     */
    'messages_par_jour_par_agent' => (int) ($_SERVER['NOEL_MAX_AGENT'] ?? $_ENV['NOEL_MAX_AGENT'] ?? getenv('NOEL_MAX_AGENT') ?: 40),
    'messages_par_jour_total' => (int) ($_SERVER['NOEL_MAX_TOTAL'] ?? $_ENV['NOEL_MAX_TOTAL'] ?? getenv('NOEL_MAX_TOTAL') ?: 600),

    /* Au-dela, la reponse est coupee. Une reponse d'aide tient en un ecran. */
    'jetons_reponse' => (int) ($_SERVER['NOEL_JETONS'] ?? $_ENV['NOEL_JETONS'] ?? getenv('NOEL_JETONS') ?: 900),

    /* Delai avant d'abandonner un appel : mieux vaut s'excuser que faire attendre. */
    'delai_secondes' => (int) ($_SERVER['NOEL_DELAI'] ?? $_ENV['NOEL_DELAI'] ?? getenv('NOEL_DELAI') ?: 25),

    /*
     * A qui parviennent les signalements du personnel. L'administrateur les
     * recoit dans son module ; l'adresse sert au doublon par courriel.
     */
    'courriel_admin' => $_SERVER['NOEL_COURRIEL_ADMIN'] ?? $_ENV['NOEL_COURRIEL_ADMIN'] ?? getenv('NOEL_COURRIEL_ADMIN')
        ?: 'brunellomepieu@labelleporte.ci',

    /*
     * L'IA propre a LBP (decide le 04/11/2026) : un modele libre qui tourne
     * sur une machine de l'entreprise, par Ollama, sans API tierce ni
     * facture par question. Aucun texte ne sort de LBP.
     *
     *   NOEL_IA         = ollama | aucun   (aucun par defaut : NOEL garde ses regles)
     *   NOEL_IA_URL     = adresse d'Ollama (http://127.0.0.1:11434 sur la machine elle-meme,
     *                     l'adresse du tunnel securise depuis la production)
     *   NOEL_IA_JETON   = secret partage avec le relais du tunnel (vide en local)
     *   NOEL_IA_MODELE  = modele charge dans Ollama
     *   NOEL_IA_COMPTES = e-mails des personnes qui ont l'IA pendant l'essai,
     *                     separes par des virgules ; vide = tout le monde
     *
     * Si la machine est eteinte ou injoignable, NOEL repond avec ses regles
     * habituelles : rien ne casse.
     */
    'ia' => $_SERVER['NOEL_IA'] ?? $_ENV['NOEL_IA'] ?? getenv('NOEL_IA') ?: 'aucun',
    'ia_url' => $_SERVER['NOEL_IA_URL'] ?? $_ENV['NOEL_IA_URL'] ?? getenv('NOEL_IA_URL') ?: 'http://127.0.0.1:11434',
    'ia_jeton' => $_SERVER['NOEL_IA_JETON'] ?? $_ENV['NOEL_IA_JETON'] ?? getenv('NOEL_IA_JETON') ?: '',
    'ia_modele' => $_SERVER['NOEL_IA_MODELE'] ?? $_ENV['NOEL_IA_MODELE'] ?? getenv('NOEL_IA_MODELE') ?: 'qwen3:4b-instruct',
    'ia_comptes' => $_SERVER['NOEL_IA_COMPTES'] ?? $_ENV['NOEL_IA_COMPTES'] ?? getenv('NOEL_IA_COMPTES') ?: '',
    'ia_delai_secondes' => (int) ($_SERVER['NOEL_IA_DELAI'] ?? $_ENV['NOEL_IA_DELAI'] ?? getenv('NOEL_IA_DELAI') ?: 90),
];
