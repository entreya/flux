<?php

/**
 * Background Task Example
 * ========================
 *
 * This shows how to use Flux for tasks that run independently of the
 * browser connection — CSV imports, data processing, long jobs, etc.
 *
 * Step 1: In your controller/queue worker, start the job and get an ID.
 * Step 2: Return the job ID to the browser.
 * Step 3: Browser opens SSE connection with the job ID and watches in real-time.
 *         If the browser closes and reconnects, it replays all output so far.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Entreya\Flux\Flux;

// ── Step 1: Trigger the background job ───────────────────────────────────────
// In a real app this would be inside a queue job (Horizon, Beanstalk, etc.)
// Here we simulate it inline for the example.

$jobId   = uniqid('import-', more_entropy: true);
$logPath = sys_get_temp_dir() . '/flux-jobs/' . $jobId . '.log';

echo "Starting background job: $jobId\n";
echo "Log path: $logPath\n\n";

// Run the pipeline and write output to a file.
// This call blocks until the pipeline finishes — perfect inside a queue worker.
Flux::pipeline('Import Customer Data')
    ->job('validate', 'Validate CSV')
        ->step('Check file format', 'echo "Validating CSV headers..."')
        ->step('Check row count',   'echo "Found 12,450 rows. OK."')
    ->job('process', 'Process Records')
        ->needs('validate')
        ->step('Insert records', 'echo "Inserting batch 1/25..." && sleep 1 && echo "Done."')
        ->step('Update indexes', 'echo "Rebuilding search index..." && sleep 1 && echo "Done."')
    ->job('notify', 'Notify')
        ->needs('process')
        ->step('Send report', 'echo "Email sent to admin@example.com"')
    ->writeToFile($logPath);

echo "Job complete!\n";
echo "\n";

// ── Step 2: In your controller, return the job ID to the browser ──────────────
// e.g.:
//   return response()->json(['job_id' => $jobId]);
echo "Browser can now reconnect to watch output:\n";
echo "  GET /sse.php?job=$jobId\n\n";

// ── Step 3: Browser SSE endpoint (sse.php?job=<jobId>) ───────────────────────
// Flux::tail($logPath)->stream();
// This is already handled in public/sse.php

echo "To tail the log manually:\n";
echo "  tail -f $logPath\n";
