<?php

/**
 * SSE Endpoint — public/sse.php
 *
 *  GET /sse.php?workflow=basic-workflow   — run a named workflow from examples/
 *  GET /sse.php?workflow=<upload-token>   — run an uploaded YAML file
 *  GET /sse.php?job=abc123               — tail a background job log
 */

declare(strict_types=1);

// ── SSE headers FIRST — before anything that could output ─────────────────
while (ob_get_level() > 0) ob_end_clean();

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store');
header('X-Accel-Buffering: no');    // Nginx: disable proxy buffering
header('Connection: keep-alive');

ini_set('display_errors', '0');     // Never let PHP dump HTML into the stream
ini_set('zlib.output_compression', '0');
set_time_limit(0);

function sseError(string $message): never
{
    echo "event: error\ndata: " . json_encode(['message' => $message]) . "\n\n";
    flush();
    exit;
}

// ── Autoload ───────────────────────────────────────────────────────────────
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    sseError('vendor/autoload.php not found — run: composer install');
}
require_once __DIR__ . '/../vendor/autoload.php';

use Entreya\Flux\Flux;
use Entreya\Flux\Security\RateLimiter;

// ── Config ─────────────────────────────────────────────────────────────────
$workflowsDir   = realpath(__DIR__ . '/../examples');
$uploadsDir     = sys_get_temp_dir() . '/flux-uploads';
$jobsDir        = sys_get_temp_dir() . '/flux-jobs';

$pipelineConfig = [
    'timeout'  => 600,
    'security' => [],
];

// Rate limiting — set to 0 to disable (recommended during development)
// In production behind auth, set to e.g. 120
$rateLimit = (int) ($_SERVER['FLUX_RATE_LIMIT'] ?? 0);
if ($rateLimit > 0) {
    try {
        (new RateLimiter($rateLimit))->check($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    } catch (\Throwable $e) {
        sseError($e->getMessage());
    }
}

// ── Mode: tail background job ──────────────────────────────────────────────
if (isset($_GET['job'])) {
    $jobId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $_GET['job'] ?? '');
    if (!$jobId) sseError('Invalid job ID.');

    try {
        Flux::tail("$jobsDir/$jobId.log")->stream();
    } catch (\Throwable $e) {
        sseError($e->getMessage());
    }
    exit;
}

// ── Mode: inline workflow ──────────────────────────────────────────────────
if (isset($_GET['workflow'])) {
    $name = preg_replace('/[^a-zA-Z0-9\-_]/', '', $_GET['workflow'] ?? '');
    if (!$name) sseError('Invalid workflow name.');

    $yamlPath = null;

    // 1. Check upload token map (drag-and-drop uploaded files)
    $mapFile = "$uploadsDir/$name.map";
    if (file_exists($mapFile)) {
        $uploaded = trim(file_get_contents($mapFile));
        if ($uploaded && file_exists($uploaded)) {
            $yamlPath = $uploaded;
        }
    }

    // 2. Check examples/ directory (named workflows)
    if (!$yamlPath && $workflowsDir) {
        foreach (["$workflowsDir/$name.yaml", "$workflowsDir/$name.yml"] as $candidate) {
            $resolved = realpath($candidate);
            if ($resolved && str_starts_with($resolved, $workflowsDir)) {
                $yamlPath = $resolved;
                break;
            }
        }
    }

    if (!$yamlPath) {
        sseError("Workflow '$name' not found in examples/ and no uploaded file with that token.");
    }

    try {
        Flux::fromYaml($yamlPath, $pipelineConfig)->stream();
    } catch (\Throwable $e) {
        sseError($e->getMessage());
    }
    exit;
}

sseError('Provide ?workflow=name or ?job=id');
