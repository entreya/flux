<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Entreya\Flux\Flux;

/**
 * ─────────────────────────────────────────────────────────────────────────────
 * Fluent API Examples
 * ─────────────────────────────────────────────────────────────────────────────
 */

// ── Example 1: Simple inline stream (from SSE endpoint) ──────────────────────
//
// Flux::pipeline('Deploy to Production')
//     ->job('build', 'Build')
//         ->step('Install deps', 'composer install --no-dev --optimize-autoloader')
//         ->step('Run tests',    'vendor/bin/phpunit --testdox')
//         ->step('Build assets', 'npm run build')
//     ->job('deploy', 'Deploy')
//         ->needs('build')
//         ->step('Sync files',   'rsync -avz --delete dist/ prod:/var/www/app/')
//         ->step('Restart FPM',  'systemctl reload php8.3-fpm')
//         ->step('Clear cache',  'php artisan cache:clear')
//     ->withAuth(fn() => isset($_SESSION['user']))
//     ->stream();   // <-- SSE headers + streaming starts here


// ── Example 2: Load from YAML ─────────────────────────────────────────────────
//
// Flux::fromYaml(__DIR__ . '/../examples/basic-workflow.yaml')
//     ->withConfig(['timeout' => 300])
//     ->stream();


// ── Example 3: Locked-down security ──────────────────────────────────────────
//
// Flux::fromYaml('deploy.yaml')
//     ->withConfig([
//         'timeout' => 120,
//         'security' => [
//             'allowed_commands' => ['composer', 'npm', 'php', 'git', 'rsync'],
//         ],
//     ])
//     ->withAuth(fn() => $_SERVER['HTTP_X_API_KEY'] === getenv('FLUX_API_KEY'))
//     ->stream();


// ── Example 4: Background job (from queue worker) ─────────────────────────────
//
// $jobId   = uniqid('import-');
// $logPath = '/var/log/flux-jobs/' . $jobId . '.log';
//
// Flux::pipeline('Import Orders')
//     ->job('process')
//         ->step('Import CSV',    'php artisan orders:import /tmp/orders.csv')
//         ->step('Send report',   'php artisan report:email')
//     ->writeToFile($logPath);
//
// // Return $jobId to browser so it can open:
// // GET /sse.php?job=<jobId>


// ── Example 5: Reconnect to background job (SSE endpoint) ────────────────────
//
// $jobId   = $_GET['job'] ?? '';
// $logPath = '/var/log/flux-jobs/' . $jobId . '.log';
//
// Flux::tail($logPath)->stream();


// ── Example 6: Step with continue-on-error ───────────────────────────────────
//
// Flux::pipeline('Linting')
//     ->job('lint')
//         ->step('PHP CS Fixer', 'vendor/bin/php-cs-fixer fix --dry-run')
//         ->continueOnError()         // Don't stop if CS fixer finds issues
//         ->step('PHPStan',     'vendor/bin/phpstan analyse')
//     ->stream();


// ── Example 7: Per-step environment ──────────────────────────────────────────
//
// Flux::pipeline('Multi-env Deploy')
//     ->env(['DEPLOY_USER' => 'deployer'])   // Global
//     ->job('staging', 'Deploy Staging')
//         ->env(['APP_URL' => 'https://staging.example.com'])
//         ->step('Deploy', 'cap staging deploy')
//     ->job('production', 'Deploy Production')
//         ->needs('staging')
//         ->env(['APP_URL' => 'https://example.com'])
//         ->step('Deploy', 'cap production deploy')
//     ->stream();

echo "This file contains usage examples — see comments above.\n";
