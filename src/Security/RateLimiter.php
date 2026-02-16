<?php

declare(strict_types=1);

namespace Entreya\Flux\Security;

use Entreya\Flux\Exceptions\SecurityException;

/**
 * Simple sliding-window rate limiter.
 *
 * Uses APCu when available (recommended for production), falls back to
 * temp files for environments without APCu.
 */
class RateLimiter
{
    public function __construct(
        private readonly int    $maxPerHour = 60,
        private readonly string $storageDir = '',
    ) {}

    /**
     * @throws SecurityException
     */
    public function check(string $identifier): void
    {
        if ($this->maxPerHour <= 0) {
            return; // Rate limiting disabled
        }

        $key   = 'flux_rl_' . md5($identifier);
        $count = $this->increment($key);

        if ($count > $this->maxPerHour) {
            throw new SecurityException(
                "Rate limit exceeded. Maximum {$this->maxPerHour} requests per hour."
            );
        }
    }

    private function increment(string $key): int
    {
        // Prefer APCu for atomic operations
        if (function_exists('apcu_inc')) {
            if (!apcu_exists($key)) {
                apcu_store($key, 0, ttl: 3600);
            }
            return (int) apcu_inc($key);
        }

        return $this->fileIncrement($key);
    }

    private function fileIncrement(string $key): int
    {
        $dir  = $this->storageDir ?: sys_get_temp_dir() . '/flux_rate_limits';
        $file = "$dir/$key.json";

        if (!is_dir($dir)) {
            mkdir($dir, 0700, recursive: true);
        }

        $fp = fopen($file, 'c+');
        flock($fp, LOCK_EX);

        $data = json_decode(fread($fp, 512) ?: '{}', true) ?? [];

        // Reset window if older than 1 hour
        if (empty($data) || (time() - ($data['start'] ?? 0)) > 3600) {
            $data = ['start' => time(), 'count' => 0];
        }

        $data['count']++;

        rewind($fp);
        fwrite($fp, json_encode($data));
        ftruncate($fp, ftell($fp));
        flock($fp, LOCK_UN);
        fclose($fp);

        return $data['count'];
    }
}
