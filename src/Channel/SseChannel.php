<?php

declare(strict_types=1);

namespace Entreya\Flux\Channel;

use Entreya\Flux\Output\AnsiConverter;

/**
 * Writes workflow events directly to an SSE HTTP response.
 * Used for inline (real-time) streaming where the process is attached to the request.
 */
class SseChannel
{
    private AnsiConverter $ansi;

    public function __construct()
    {
        $this->ansi = new AnsiConverter();
    }

    /**
     * Set SSE response headers and disable output buffering.
     */
    public function open(): void
    {
        // Discard any previous output
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store');
        header('X-Accel-Buffering: no');    // Nginx: disable proxy buffering
        header('Connection: keep-alive');

        // Some frameworks/hosts need this
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
        }
        ini_set('zlib.output_compression', '0');
    }

    /**
     * Emit one event to the browser.
     */
    public function write(array $event): void
    {
        $type = $event['event'];
        $data = $event['data'];

        // Convert ANSI color codes to HTML for log lines
        if ($type === 'log' && isset($data['content'])) {
            $data['content'] = $this->ansi->convert($data['content']);
        }

        echo "event: $type\n";
        echo "data: " . json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n\n";

        // Push to client immediately
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * Send a terminal done event.
     */
    public function close(): void
    {
        echo "event: stream_close\ndata: {}\n\n";
        flush();
    }

    /**
     * Emit a raw error event (for exception handling in SSE endpoints).
     */
    public function error(string $message): void
    {
        echo "event: error\n";
        echo "data: " . json_encode(['message' => $message]) . "\n\n";
        flush();
    }
}
