<?php

declare(strict_types=1);

namespace Entreya\Flux\Channel;

/**
 * Contract for all Flux event channels.
 *
 * A channel is responsible for transporting workflow events from a worker
 * process to an SSE endpoint (and ultimately to the browser).
 *
 * Built-in implementations:
 *   - SseChannel      — inline streaming (same process)
 *   - FileChannel      — local file (JSONL) with tail
 *   - RedisChannel     — Redis Streams (XADD/XREAD BLOCK) for multi-server
 *   - DatabaseChannel  — MySQL/Postgres table with polling for multi-server
 */
interface ChannelInterface
{
    /**
     * Open the channel for writing.
     * Called once before any write() calls.
     */
    public function open(): void;

    /**
     * Write a single workflow event.
     *
     * @param array $event  Shape: ['event' => string, 'data' => array]
     */
    public function write(array $event): void;

    /**
     * Signal that the workflow is complete and close the channel.
     * For SseChannel, this emits `stream_close`.
     * For storage channels, this writes a sentinel/status update.
     */
    public function complete(): void;
}
