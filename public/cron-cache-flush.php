<?php

declare(strict_types=1);

/**
 * Vidage du cache Symfony sans passer par le routeur (mutualisé OVH + déploiement FTP).
 *
 * Après un push FTP, var/ n’est pas synchronisé : les nouvelles routes (ex. /admin/checklist-compet)
 * et les templates Twig compilés restent obsolètes → 404, icônes manquantes.
 *
 * Appeler une fois après déploiement (puis optionnellement après chaque livraison) :
 *   GET https://votre-domaine.fr/cron-cache-flush.php?token=VOTRE_CRON_SECRET
 *
 * Même secret que les URLs /cron/* (variable CRON_SECRET dans .env.local en prod).
 */

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';

header('Content-Type: application/json; charset=UTF-8');

if (!is_file($root.'/.env')) {
    http_response_code(503);
    echo json_encode(['error' => 'Fichier .env introuvable à la racine du projet.'], JSON_UNESCAPED_UNICODE);
    exit;
}

(new Dotenv())->loadEnv($root.'/.env');

$expected = trim((string) ($_ENV['CRON_SECRET'] ?? ''));
$provided = (string) ($_GET['token'] ?? '');

if ('' === $expected) {
    http_response_code(503);
    echo json_encode(
        ['error' => 'CRON_SECRET absent ou vide. Définir CRON_SECRET dans .env.local sur le serveur.'],
        JSON_UNESCAPED_UNICODE,
    );
    exit;
}

if (!hash_equals($expected, $provided)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token invalide.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $cacheDir = $root.'/var/cache';
    $filesystem = new Filesystem();
    if ($filesystem->exists($cacheDir)) {
        $filesystem->remove($cacheDir);
    }

    echo json_encode(
        [
            'ok' => true,
            'cleared' => $cacheDir,
            'hint' => 'Rechargez le site ; le premier hit peut prendre quelques secondes (recompilation du cache).',
        ],
        JSON_UNESCAPED_UNICODE,
    );
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
