<?php

declare(strict_types=1);

namespace Entreya\Flux\Security;

use Entreya\Flux\Exceptions\SecurityException;

/**
 * Validates commands before execution.
 *
 * Key design decisions vs. previous version:
 *  - Multi-line scripts (containing \n) skip the single-command allowlist check
 *    because they are wrapped in a bash script by CommandRunner and can legitimately
 *    contain pipes, redirects, conditionals, etc.
 *  - The blocked-patterns list is applied to the full script text in all cases.
 *  - Single-line commands still go through the allowlist.
 *  - The allowlist is fully configurable and open by default (empty = allow all).
 */
class CommandValidator
{
    private array $allowedCommands;
    private array $blockedPatterns;
    private bool  $useAllowlist;

    public function __construct(array $config = [])
    {
        // If 'allowed_commands' is explicitly set, enforce allowlist; otherwise open.
        $this->useAllowlist    = isset($config['allowed_commands']);
        $this->allowedCommands = $config['allowed_commands'] ?? [];

        $this->blockedPatterns = $config['blocked_patterns'] ?? [
            // Destructive filesystem ops
            '/\brm\s+-[rf]/i',
            '/\bmkfs\b/i',
            '/\bdd\s+if=/i',
            '/\bshred\b/i',

            // Privilege escalation
            '/\bsudo\b/i',
            '/\bsu\s/i',
            '/\bpasswd\b/i',
            '/\bchmod\s+[0-7]*7[0-7]{2}\b/i',

            // System control
            '/\b(shutdown|reboot|halt|poweroff|init\s)\b/i',

            // Command injection via eval/exec (PHP-level)
            '/\beval\s*\(/i',

            // Dangerous redirects into system dirs
            '/>\/etc\//i',
            '/>\/boot\//i',
            '/>\/sys\//i',
        ];
    }

    /**
     * @throws SecurityException
     */
    public function validate(string $command): void
    {
        $command = trim($command);

        if ($command === '') {
            return;
        }

        // 1. Always check blocked patterns (applies to single-line and scripts)
        foreach ($this->blockedPatterns as $pattern) {
            if (preg_match($pattern, $command)) {
                throw new SecurityException(
                    "Security violation: command matches blocked pattern."
                );
            }
        }

        // 2. For multi-line scripts, skip allowlist — they are bash scripts
        //    and can reasonably contain pipes, ifs, loops, etc.
        if (str_contains($command, "\n")) {
            return;
        }

        // 3. For single-line commands, optionally enforce allowlist
        if ($this->useAllowlist && !empty($this->allowedCommands)) {
            // Extract the binary token, handling: quoted paths, paths with spaces, env prefixes.
            // e.g. `php yii foo` → "php", `/usr/bin/php yii` → "php", `"php" yii` → "php"
            preg_match('/^(?:"([^"]+)"|\'([^\']+)\'|(\S+))/', $command, $m);
            $binary   = $m[1] ?? $m[2] ?? $m[3] ?? '';
            $baseName = basename($binary);

            if (!in_array($baseName, $this->allowedCommands, strict: true)) {
                throw new SecurityException(
                    "Security violation: '$baseName' is not in the allowed commands list."
                );
            }
        }
    }
}
