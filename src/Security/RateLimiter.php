<?php

declare(strict_types=1);

namespace Entreya\Flux\Security;

use Entreya\Flux\Exceptions\SecurityException;

class RateLimiter
{
    private int $maxPerHour;
    
    public function __construct(int $maxPerHour = 10)
    {
        $this->maxPerHour = $maxPerHour;
    }

    /**
     * Simple file-based rate limiter per IP.
     * In production use Redis/Memcached.
     */
    public function check(string $ip): void
    {
        $tmpDir = sys_get_temp_dir() . '/flux_limits';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $file = $tmpDir . '/' . md5($ip) . '.json';
        $data = ['count' => 0, 'start_time' => time()];
        
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
        }

        // Reset if hour passed
        if (time() - $data['start_time'] > 3600) {
            $data = ['count' => 0, 'start_time' => time()];
        }

        if ($data['count'] >= $this->maxPerHour) {
            // Throw exception, but we should also ideally let the caller handle it to send a "stop" signal
            throw new SecurityException("Rate limit exceeded. Max {$this->maxPerHour} workflows per hour.");
        }

        $data['count']++;
        file_put_contents($file, json_encode($data));
    }
}
