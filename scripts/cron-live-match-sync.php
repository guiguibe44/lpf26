<?php

declare(strict_types=1);

/**
 * Point d’entrée pour le cron OVH (tâche planifiée PHP).
 * Le manager attend un chemin du type ./lpf26/scripts/cron-live-match-sync.php
 *
 * Test SSH : php scripts/cron-live-match-sync.php
 */

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Dotenv\Dotenv;

$projectDir = dirname(__DIR__);

if (!is_file($projectDir.'/vendor/autoload.php')) {
    fwrite(STDERR, "Composer non installé (vendor/ manquant).\n");
    exit(1);
}

require $projectDir.'/vendor/autoload.php';

(new Dotenv())->bootEnv($projectDir.'/.env');

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'prod';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '0';

$kernel = new Kernel('prod', false);
$kernel->boot();

$application = new Application($kernel);
$application->setAutoExit(false);

$logDir = $projectDir.'/var/log';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

$logStream = fopen($logDir.'/live-match-sync.log', 'a');
if (false === $logStream) {
    $output = new StreamOutput(fopen('php://stdout', 'w'));
} else {
    fwrite($logStream, sprintf("\n[%s] cron-live-match-sync\n", date('c')));
    $output = new StreamOutput($logStream);
}

$input = new StringInput('app:live-match:sync --no-interaction');

$exitCode = $application->run($input, $output);

if (false !== $logStream) {
    fclose($logStream);
}

exit($exitCode);
