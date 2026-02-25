<?php

declare(strict_types=1);

namespace Entreya\Flux\Channel;

use Entreya\Flux\Output\AnsiConverter;

/**
 * File-based channel for background job streaming.
 *
 * WRITE mode  — used by a background worker writing job output.
 * TAIL  mode  — used by an SSE endpoint to stream that output to a browser,
 *               including history replay and live tailing.
 *
 * File format: one JSON-encoded event per line (JSONL / newline-delimited JSON).
 * A sentinel line "__FLUX_COMPLETE__" signals the job finished.
 *
 * ┌─ Worker / Queue Job ──────────────────────────────────────────┐
 * │  $ch = new FileChannel('/tmp/flux-jobs/abc123.log', 'write'); │
 * │  $ch->open();                                                 │
 * │  foreach ($executor->execute(...) as $event) {                │
 * │      $ch->write($event);                                      │
 * │  }                                                            │
 * │  $ch->complete();                                             │
 * └───────────────────────────────────────────────────────────────┘
 *
 * ┌─ SSE Endpoint ────────────────────────────────────────────────┐
 * │  Flux::tail('/tmp/flux-jobs/abc123.log')->stream();           │
 * └───────────────────────────────────────────────────────────────┘
 */
class FileChannel
{
    public const MODE_WRITE = 'write';
    public const MODE_TAIL  = 'tail';

    private const SENTINEL = "__FLUX_COMPLETE__\n";

    /** @var resource|null */
    private $handle = null;

    private AnsiConverter $ansi;

    public function __construct(
        private readonly string $path,
        private readonly string $mode = self::MODE_TAIL,
    ) {
        $this->ansi = new AnsiConverter();
    }

    // -------------------------------------------------------------------------
    // WRITE mode — used by the background worker
    // -------------------------------------------------------------------------

    public function open(): void
    {
        if ($this->mode === self::MODE_WRITE) {
            $dir = dirname($this->path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, recursive: true);
            }
            $this->handle = fopen($this->path, 'w');
            if ($this->handle === false) {
                throw new \RuntimeException("Cannot open log file for writing: {$this->path}");
            }
        }
    }

    public function write(array $event): void
    {
        if ($this->mode !== self::MODE_WRITE || $this->handle === null) {
            return;
        }
        fwrite($this->handle, json_encode($event, JSON_UNESCAPED_UNICODE) . "\n");
        fflush($this->handle);
    }

    /**
     * Write the sentinel and close the file handle.
     */
    public function complete(): void
    {
        if ($this->handle !== null) {
            fwrite($this->handle, self::SENTINEL);
            fflush($this->handle);
            fclose($this->handle);
            $this->handle = null;
        }
    }

    // -------------------------------------------------------------------------
    // TAIL mode — used by the SSE endpoint to stream to a browser
    // -------------------------------------------------------------------------

    // Tail-mode constants
    private const MAX_IDLE_SECONDS   = 300;   // give up after 5 min of no new data
    private const POLL_INTERVAL_USEC = 200_000; // 200 ms between polls

    /**
     * Open the log file as an SSE stream.
     *
     * Replays all events written so far, then tails for new ones until the
     * sentinel is seen (job done) or the client disconnects.
     *
     * Maximum wait: 300 seconds after last new data before giving up.
     */
    public function stream(): void
    {
        // SSE headers
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');
        ini_set('zlib.output_compression', '0');

        // Wait for the file to appear (job may start slightly after request)
        $waited = 0;
        while (!file_exists($this->path) && $waited < 10) {
            sleep(1);
            $waited++;
        }

        if (!file_exists($this->path)) {
            echo "event: error\ndata: " . json_encode(['message' => 'Job not found or did not start.']) . "\n\n";
            flush();
            return;
        }

        $handle      = fopen($this->path, 'r');
        // Track idle time in microseconds for accuracy — fixes the previous bug
        // where counting 200ms ticks as "seconds" meant timeout fired at 60s, not 300s.
        $idleUsec    = 0;
        $maxIdleUsec = self::MAX_IDLE_SECONDS * 1_000_000;

        while (!feof($handle) || $idleUsec < $maxIdleUsec) {
            if (connection_aborted()) {
                break;
            }

            $line = fgets($handle);

            if ($line === false) {
                // No new data yet — sleep and retry
                usleep(self::POLL_INTERVAL_USEC);
                $idleUsec += self::POLL_INTERVAL_USEC;
                clearstatcache(true, $this->path);
                continue;
            }

            $idleUsec = 0; // reset idle counter on any new data
            $line     = rtrim($line);

            if ($line === '' ) {
                continue;
            }

            if ($line === rtrim(self::SENTINEL)) {
                // Job finished — tell browser and stop
                echo "event: stream_close\ndata: {}\n\n";
                flush();
                break;
            }

            $event = @json_decode($line, true);
            if (!is_array($event)) {
                continue;
            }

            $type = $event['event'] ?? 'log';
            $data = $event['data']  ?? [];

            // ANSI → HTML for log lines
            if ($type === 'log' && isset($data['content'])) {
                $data['content'] = $this->ansi->convert($data['content']);
            }

            echo "event: $type\n";
            echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
        }

        fclose($handle);
    }
}
