<?php

declare(strict_types=1);

namespace Entreya\Flux\Channel;

use Entreya\Flux\Output\AnsiConverter;

/**
 * Redis Streams channel for multi-server / auto-scale deployments.
 *
 * WRITE mode — Worker pushes events via XADD.
 * TAIL  mode — SSE endpoint reads via XRANGE (history) + XREAD BLOCK (live tail).
 *
 * ┌─ Worker (any server) ─────────────────────────────────────────┐
 * │  $ch = new RedisChannel($redis, 'job:abc123', 'write');       │
 * │  $ch->open();                                                  │
 * │  foreach ($executor->execute(...) as $event) {                 │
 * │      $ch->write($event);                                       │
 * │  }                                                             │
 * │  $ch->complete();                                              │
 * └────────────────────────────────────────────────────────────────┘
 *
 * ┌─ SSE Endpoint (any server) ───────────────────────────────────┐
 * │  $ch = new RedisChannel($redis, 'job:abc123', 'tail');        │
 * │  $ch->stream();                                                │
 * └────────────────────────────────────────────────────────────────┘
 *
 * Redis key layout:
 *   flux:stream:{jobId}   — Redis Stream containing events
 *   flux:alive:{jobId}    — TTL key for worker heartbeat
 *   flux:status:{jobId}   — 'running' | 'complete' | 'failed'
 *
 * Requires: ext-redis (phpredis) or a compatible Redis client
 * that supports xAdd(), xRange(), xRead().
 */
class RedisChannel implements ChannelInterface
{
    public const MODE_WRITE = 'write';
    public const MODE_TAIL  = 'tail';

    /** Heartbeat TTL in seconds. Worker renews every HEARTBEAT_INTERVAL. */
    private const HEARTBEAT_TTL      = 15;
    private const HEARTBEAT_INTERVAL = 5;

    /** Max idle seconds before giving up tailing */
    private const MAX_IDLE_SECONDS = 300;

    /** Block timeout for XREAD in milliseconds */
    private const BLOCK_MS = 500;

    /** TTL for stream data after completion (seconds) */
    private const STREAM_EXPIRE = 3600; // 1 hour

    private string $streamKey;
    private string $aliveKey;
    private string $statusKey;
    private AnsiConverter $ansi;
    private float $lastHeartbeat = 0;

    /**
     * @param \Redis $redis     A connected Redis instance
     * @param string $jobId     Unique job identifier
     * @param string $mode      'write' or 'tail'
     */
    public function __construct(
        private readonly \Redis $redis,
        private readonly string $jobId,
        private readonly string $mode = self::MODE_TAIL,
    ) {
        $this->streamKey = 'flux:stream:' . $jobId;
        $this->aliveKey  = 'flux:alive:'  . $jobId;
        $this->statusKey = 'flux:status:' . $jobId;
        $this->ansi      = new AnsiConverter();
    }

    // ── WRITE mode ──────────────────────────────────────────────────────────

    public function open(): void
    {
        if ($this->mode !== self::MODE_WRITE) {
            return;
        }

        // Set initial status
        $this->redis->set($this->statusKey, 'running');

        // Set heartbeat with PID + hostname for diagnostics
        $heartbeatData = json_encode([
            'pid'    => getmypid(),
            'server' => gethostname(),
            'time'   => time(),
        ]);
        $this->redis->setex($this->aliveKey, self::HEARTBEAT_TTL, $heartbeatData);
        $this->lastHeartbeat = microtime(true);
    }

    public function write(array $event): void
    {
        if ($this->mode !== self::MODE_WRITE) {
            return;
        }

        // XADD — append event to stream. '*' = auto-generate ID.
        $this->redis->xAdd($this->streamKey, '*', [
            'payload' => json_encode($event, JSON_UNESCAPED_UNICODE),
        ]);

        // Renew heartbeat periodically (not on every write — reduces Redis calls)
        $now = microtime(true);
        if ($now - $this->lastHeartbeat >= self::HEARTBEAT_INTERVAL) {
            $heartbeatData = json_encode([
                'pid'    => getmypid(),
                'server' => gethostname(),
                'time'   => time(),
            ]);
            $this->redis->setex($this->aliveKey, self::HEARTBEAT_TTL, $heartbeatData);
            $this->lastHeartbeat = $now;
        }
    }

    public function complete(): void
    {
        if ($this->mode !== self::MODE_WRITE) {
            return;
        }

        // Write sentinel event
        $this->redis->xAdd($this->streamKey, '*', [
            'payload' => json_encode([
                'event' => '__complete__',
                'data'  => [],
            ]),
        ]);

        // Update status
        $this->redis->set($this->statusKey, 'complete');

        // Remove heartbeat
        $this->redis->del($this->aliveKey);

        // Auto-expire stream data after 1 hour
        $this->redis->expire($this->streamKey, self::STREAM_EXPIRE);
        $this->redis->expire($this->statusKey, self::STREAM_EXPIRE);
    }

    /**
     * Mark the job as failed (call instead of complete() on error).
     */
    public function fail(): void
    {
        if ($this->mode !== self::MODE_WRITE) {
            return;
        }

        $this->redis->xAdd($this->streamKey, '*', [
            'payload' => json_encode([
                'event' => '__failed__',
                'data'  => [],
            ]),
        ]);

        $this->redis->set($this->statusKey, 'failed');
        $this->redis->del($this->aliveKey);
        $this->redis->expire($this->streamKey, self::STREAM_EXPIRE);
        $this->redis->expire($this->statusKey, self::STREAM_EXPIRE);
    }

    // ── TAIL mode ───────────────────────────────────────────────────────────

    /**
     * Open an SSE stream that replays history then tails live events.
     *
     * 1. XRANGE — get all past events (instant history replay)
     * 2. XREAD BLOCK — blocking wait for new events (no polling)
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

        // Check if stream exists (job may not have started yet)
        $waited = 0;
        while (!$this->redis->exists($this->streamKey) && $waited < 10) {
            sleep(1);
            $waited++;
        }

        if (!$this->redis->exists($this->streamKey)) {
            echo "event: error\ndata: " . json_encode(['message' => 'Job not found or did not start.']) . "\n\n";
            flush();
            return;
        }

        // Phase 1: Replay all existing events via XRANGE
        $lastId = '0-0';
        $entries = $this->redis->xRange($this->streamKey, '-', '+');
        if (is_array($entries)) {
            foreach ($entries as $id => $fields) {
                if ($this->emitStreamEntry($id, $fields)) {
                    return; // sentinel found — done
                }
                $lastId = $id;
            }
        }

        // Phase 2: Live tail via XREAD BLOCK
        $idleUsec    = 0;
        $maxIdleUsec = self::MAX_IDLE_SECONDS * 1_000_000;

        while ($idleUsec < $maxIdleUsec) {
            if (connection_aborted()) {
                break;
            }

            // XREAD BLOCK: Redis holds the connection until new data or timeout
            $result = $this->redis->xRead(
                [$this->streamKey => $lastId],
                1,               // count: 1 entry at a time for low latency
                self::BLOCK_MS   // block for 500ms then check liveness
            );

            if (is_array($result) && isset($result[$this->streamKey])) {
                $idleUsec = 0; // reset idle
                foreach ($result[$this->streamKey] as $id => $fields) {
                    if ($this->emitStreamEntry($id, $fields)) {
                        return; // sentinel
                    }
                    $lastId = $id;
                }
            } else {
                // No new data — check if worker is still alive
                $idleUsec += self::BLOCK_MS * 1000; // convert ms → µs

                if (!$this->isWorkerAlive()) {
                    // Get diagnostic info
                    $status = $this->redis->get($this->statusKey);
                    if ($status === 'complete') {
                        echo "event: stream_close\ndata: {}\n\n";
                    } elseif ($status === 'failed') {
                        echo "event: error\ndata: " . json_encode(['message' => 'Workflow failed.']) . "\n\n";
                    } else {
                        echo "event: error\ndata: " . json_encode(['message' => 'Worker stopped responding (heartbeat expired).']) . "\n\n";
                    }
                    flush();
                    return;
                }
            }
        }

        // Timed out
        echo "event: error\ndata: " . json_encode(['message' => 'Stream timed out after ' . self::MAX_IDLE_SECONDS . 's of inactivity.']) . "\n\n";
        flush();
    }

    // ── Internals ───────────────────────────────────────────────────────────

    /**
     * Emit a single stream entry as SSE. Returns true if sentinel found.
     */
    private function emitStreamEntry(string $id, array $fields): bool
    {
        $payload = $fields['payload'] ?? null;
        if ($payload === null) {
            return false;
        }

        $event = @json_decode($payload, true);
        if (!is_array($event)) {
            return false;
        }

        $type = $event['event'] ?? 'log';

        // Sentinel events
        if ($type === '__complete__') {
            echo "event: stream_close\ndata: {}\n\n";
            flush();
            return true;
        }
        if ($type === '__failed__') {
            echo "event: stream_close\ndata: {}\n\n";
            flush();
            return true;
        }

        $data = $event['data'] ?? [];

        // ANSI → HTML for log lines
        if ($type === 'log' && isset($data['content'])) {
            $data['content'] = $this->ansi->convert($data['content']);
        }

        echo "event: $type\n";
        echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();

        return false;
    }

    /**
     * Check if the worker is still alive via heartbeat key.
     */
    private function isWorkerAlive(): bool
    {
        // If status key says complete/failed, worker is done (not dead)
        $status = $this->redis->get($this->statusKey);
        if ($status === 'complete' || $status === 'failed') {
            return false; // Not alive, but not a crash — normal termination
        }

        // Check heartbeat TTL key
        return (bool) $this->redis->exists($this->aliveKey);
    }
}
