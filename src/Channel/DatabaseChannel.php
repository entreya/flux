<?php

declare(strict_types=1);

namespace Entreya\Flux\Channel;

use Entreya\Flux\Output\AnsiConverter;

/**
 * Database channel for multi-server / auto-scale deployments.
 *
 * Uses two tables:
 *   flux_jobs   — job status + heartbeat
 *   flux_events — sequential event log
 *
 * WRITE mode — Worker INSERTs events and updates heartbeat.
 * TAIL  mode — SSE endpoint SELECTs events with polling.
 *
 * ┌─ Worker (any server) ─────────────────────────────────────────┐
 * │  $ch = new DatabaseChannel($pdo, 'abc123', 'write');          │
 * │  $ch->open();                                                  │
 * │  foreach ($executor->execute(...) as $event) {                 │
 * │      $ch->write($event);                                       │
 * │  }                                                             │
 * │  $ch->complete();                                              │
 * └────────────────────────────────────────────────────────────────┘
 *
 * ┌─ SSE Endpoint (any server) ───────────────────────────────────┐
 * │  $ch = new DatabaseChannel($pdo, 'abc123', 'tail');           │
 * │  $ch->stream();                                                │
 * └────────────────────────────────────────────────────────────────┘
 *
 * Schema (run DatabaseChannel::migrate($pdo) to create tables):
 *
 *   CREATE TABLE flux_jobs (
 *       job_id       VARCHAR(64) PRIMARY KEY,
 *       status       VARCHAR(16) NOT NULL DEFAULT 'running',
 *       pid          INT UNSIGNED NULL,
 *       server       VARCHAR(128) NULL,
 *       heartbeat_at INT UNSIGNED NOT NULL,
 *       created_at   INT UNSIGNED NOT NULL
 *   );
 *
 *   CREATE TABLE flux_events (
 *       id       BIGINT AUTO_INCREMENT PRIMARY KEY,
 *       job_id   VARCHAR(64) NOT NULL,
 *       seq      INT UNSIGNED NOT NULL,
 *       type     VARCHAR(32) NOT NULL,
 *       data     TEXT NOT NULL,
 *       INDEX idx_job_seq (job_id, seq)
 *   );
 */
class DatabaseChannel implements ChannelInterface
{
    public const MODE_WRITE = 'write';
    public const MODE_TAIL  = 'tail';

    /** Heartbeat update interval in seconds */
    private const HEARTBEAT_INTERVAL = 5;

    /** Max seconds since last heartbeat before worker is considered dead */
    private const HEARTBEAT_STALE = 30;

    /** Max idle seconds for SSE tail before giving up */
    private const MAX_IDLE_SECONDS = 300;

    /** Poll interval in microseconds for tail mode */
    private const POLL_INTERVAL_USEC = 300_000; // 300ms

    /** Batch size for SELECT queries in tail mode */
    private const BATCH_SIZE = 100;

    private int $seq = 0;
    private float $lastHeartbeat = 0;
    private AnsiConverter $ansi;

    /**
     * @param \PDO   $pdo     A connected PDO instance (MySQL, Postgres, SQLite)
     * @param string $jobId   Unique job identifier
     * @param string $mode    'write' or 'tail'
     */
    public function __construct(
        private readonly \PDO   $pdo,
        private readonly string $jobId,
        private readonly string $mode = self::MODE_TAIL,
    ) {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->ansi = new AnsiConverter();
    }

    // ── Schema migration ────────────────────────────────────────────────────

    /**
     * Create tables if they don't exist. Safe to call multiple times.
     */
    public static function migrate(\PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS flux_jobs (
                job_id       VARCHAR(64) PRIMARY KEY,
                status       VARCHAR(16) NOT NULL DEFAULT 'running',
                pid          INT UNSIGNED NULL,
                server       VARCHAR(128) NULL,
                heartbeat_at INT UNSIGNED NOT NULL,
                created_at   INT UNSIGNED NOT NULL
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS flux_events (
                id       BIGINT AUTO_INCREMENT PRIMARY KEY,
                job_id   VARCHAR(64) NOT NULL,
                seq      INT UNSIGNED NOT NULL,
                type     VARCHAR(32) NOT NULL,
                data     TEXT NOT NULL,
                INDEX idx_job_seq (job_id, seq)
            )
        ");
    }

    // ── WRITE mode ──────────────────────────────────────────────────────────

    public function open(): void
    {
        if ($this->mode !== self::MODE_WRITE) {
            return;
        }

        $now = time();

        $stmt = $this->pdo->prepare("
            INSERT INTO flux_jobs (job_id, status, pid, server, heartbeat_at, created_at)
            VALUES (:job_id, 'running', :pid, :server, :hb, :ca)
            ON DUPLICATE KEY UPDATE
                status = 'running', pid = :pid2, server = :server2, heartbeat_at = :hb2
        ");
        $stmt->execute([
            ':job_id'  => $this->jobId,
            ':pid'     => getmypid(),
            ':server'  => gethostname(),
            ':hb'      => $now,
            ':ca'      => $now,
            ':pid2'    => getmypid(),
            ':server2' => gethostname(),
            ':hb2'     => $now,
        ]);

        $this->lastHeartbeat = microtime(true);
        $this->seq = 0;
    }

    public function write(array $event): void
    {
        if ($this->mode !== self::MODE_WRITE) {
            return;
        }

        $this->seq++;

        $stmt = $this->pdo->prepare("
            INSERT INTO flux_events (job_id, seq, type, data)
            VALUES (:job_id, :seq, :type, :data)
        ");
        $stmt->execute([
            ':job_id' => $this->jobId,
            ':seq'    => $this->seq,
            ':type'   => $event['event'] ?? 'log',
            ':data'   => json_encode($event['data'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);

        // Periodic heartbeat update
        $now = microtime(true);
        if ($now - $this->lastHeartbeat >= self::HEARTBEAT_INTERVAL) {
            $this->updateHeartbeat();
            $this->lastHeartbeat = $now;
        }
    }

    public function complete(): void
    {
        if ($this->mode !== self::MODE_WRITE) {
            return;
        }

        $stmt = $this->pdo->prepare("
            UPDATE flux_jobs SET status = 'complete', heartbeat_at = :hb WHERE job_id = :job_id
        ");
        $stmt->execute([':hb' => time(), ':job_id' => $this->jobId]);
    }

    /**
     * Mark the job as failed.
     */
    public function fail(): void
    {
        if ($this->mode !== self::MODE_WRITE) {
            return;
        }

        $stmt = $this->pdo->prepare("
            UPDATE flux_jobs SET status = 'failed', heartbeat_at = :hb WHERE job_id = :job_id
        ");
        $stmt->execute([':hb' => time(), ':job_id' => $this->jobId]);
    }

    private function updateHeartbeat(): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE flux_jobs SET heartbeat_at = :hb WHERE job_id = :job_id
        ");
        $stmt->execute([':hb' => time(), ':job_id' => $this->jobId]);
    }

    // ── TAIL mode ───────────────────────────────────────────────────────────

    /**
     * Open an SSE stream that replays history then polls for new events.
     *
     * 1. SELECT all existing events (instant history replay)
     * 2. Poll loop: SELECT WHERE seq > last_seq every 300ms
     * 3. Check heartbeat — if worker is dead, emit error and close
     */
    public function stream(): void
    {
        // SSE headers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');
        ini_set('zlib.output_compression', '0');

        // Wait for job to appear (may start slightly after SSE request)
        $waited = 0;
        while (!$this->jobExists() && $waited < 10) {
            sleep(1);
            $waited++;
        }

        if (!$this->jobExists()) {
            echo "event: error\ndata: " . json_encode(['message' => 'Job not found or did not start.']) . "\n\n";
            flush();
            return;
        }

        // Phase 1: Replay all existing events
        $lastSeq = 0;
        $events = $this->fetchEvents($lastSeq);
        foreach ($events as $row) {
            $this->emitRow($row);
            $lastSeq = (int) $row['seq'];
        }

        // Phase 2: Poll for new events
        $idleUsec    = 0;
        $maxIdleUsec = self::MAX_IDLE_SECONDS * 1_000_000;

        while ($idleUsec < $maxIdleUsec) {
            if (connection_aborted()) {
                break;
            }

            $events = $this->fetchEvents($lastSeq);

            if (!empty($events)) {
                $idleUsec = 0;
                foreach ($events as $row) {
                    $this->emitRow($row);
                    $lastSeq = (int) $row['seq'];
                }
            } else {
                $idleUsec += self::POLL_INTERVAL_USEC;

                // Check worker liveness
                $jobInfo = $this->getJobInfo();
                if ($jobInfo) {
                    if ($jobInfo['status'] === 'complete') {
                        echo "event: stream_close\ndata: {}\n\n";
                        flush();
                        return;
                    }
                    if ($jobInfo['status'] === 'failed') {
                        echo "event: error\ndata: " . json_encode(['message' => 'Workflow failed.']) . "\n\n";
                        echo "event: stream_close\ndata: {}\n\n";
                        flush();
                        return;
                    }

                    // Check heartbeat staleness
                    $hbAge = time() - (int) $jobInfo['heartbeat_at'];
                    if ($hbAge > self::HEARTBEAT_STALE) {
                        $server = $jobInfo['server'] ?? 'unknown';
                        $pid    = $jobInfo['pid'] ?? '?';
                        echo "event: error\ndata: " . json_encode([
                            'message' => "Worker on {$server} (PID {$pid}) stopped responding ({$hbAge}s since last heartbeat).",
                        ]) . "\n\n";
                        flush();
                        return;
                    }
                }

                usleep(self::POLL_INTERVAL_USEC);
            }
        }

        echo "event: error\ndata: " . json_encode(['message' => 'Stream timed out after ' . self::MAX_IDLE_SECONDS . 's of inactivity.']) . "\n\n";
        flush();
    }

    // ── Internals ───────────────────────────────────────────────────────────

    private function jobExists(): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM flux_jobs WHERE job_id = :job_id LIMIT 1");
        $stmt->execute([':job_id' => $this->jobId]);
        return (bool) $stmt->fetch();
    }

    private function getJobInfo(): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT status, pid, server, heartbeat_at FROM flux_jobs WHERE job_id = :job_id
        ");
        $stmt->execute([':job_id' => $this->jobId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Fetch events after a given sequence number.
     * @return array<array{seq: int, type: string, data: string}>
     */
    private function fetchEvents(int $afterSeq): array
    {
        $stmt = $this->pdo->prepare("
            SELECT seq, type, data
            FROM flux_events
            WHERE job_id = :job_id AND seq > :seq
            ORDER BY seq ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':job_id', $this->jobId, \PDO::PARAM_STR);
        $stmt->bindValue(':seq', $afterSeq, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', self::BATCH_SIZE, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function emitRow(array $row): void
    {
        $type = $row['type'];
        $data = @json_decode($row['data'], true);

        if (!is_array($data)) {
            $data = [];
        }

        // ANSI → HTML for log lines
        if ($type === 'log' && isset($data['content'])) {
            $data['content'] = $this->ansi->convert($data['content']);
        }

        echo "event: $type\n";
        echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }

    // ── Cleanup utility ─────────────────────────────────────────────────────

    /**
     * Purge completed/failed jobs older than the given age.
     *
     * @param int $maxAgeSeconds  Max age in seconds (default: 86400 = 24h)
     */
    public static function cleanup(\PDO $pdo, int $maxAgeSeconds = 86400): int
    {
        $cutoff = time() - $maxAgeSeconds;

        // Find stale job IDs
        $stmt = $pdo->prepare("
            SELECT job_id FROM flux_jobs
            WHERE status IN ('complete', 'failed')
              AND heartbeat_at < :cutoff
        ");
        $stmt->execute([':cutoff' => $cutoff]);
        $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($ids)) {
            return 0;
        }

        // Delete events
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM flux_events WHERE job_id IN ($placeholders)")->execute($ids);
        $pdo->prepare("DELETE FROM flux_jobs WHERE job_id IN ($placeholders)")->execute($ids);

        return count($ids);
    }
}
