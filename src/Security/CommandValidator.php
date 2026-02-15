<?php

declare(strict_types=1);

namespace Entreya\Flux\Security;

use Entreya\Flux\Exceptions\SecurityException;

class CommandValidator
{
    private array $allowedCommands;
    private array $blockedPatterns;

    public function __construct(array $config = [])
    {
        // Default whitelist (Extended based on user request)
        $this->allowedCommands = $config['allowed_commands'] ?? [
            'composer', 'npm', 'yarn', 'git', 'php', 'phpunit', 'node', 'python3', 'pytest', 
            'make', 'echo', 'ls', 'pwd', 'sleep'
        ];

        // Default dangerous patterns (Extended)
        $this->blockedPatterns = $config['blocked_patterns'] ?? [
            '/rm\s+-rf/i',
            '/rm\s+\-/i',
            '/sudo/i',
            '/su\s/i',
            '/passwd/i',
            '/shutdown/i',
            '/reboot/i',
            '/halt/i',
            '/init\s/i',
            '/kill/i',
            '/pkill/i',
            '/;/i',      // Chaining
            '/&&/i',     // Chaining
            '/\|\|/i',   // Chaining
            '/\|/',      // Pipe
            '/`/',       // Backticks
            '/\$\(/',    // Command substitution
            '/>\s*\//',  // Redirect to absolute path
            '/>\s*>/',   // Append redirect
            '/chmod/i',
            '/chown/i',
            '/curl/i',
            '/wget/i',
            '/mkfs/i',
            '/dd/i',
            '/eval/i',
            '/exec/i',
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

        // 1. Check blocked patterns
        foreach ($this->blockedPatterns as $pattern) {
            if (preg_match($pattern, $command)) {
                throw new SecurityException("Security Violation: Command contains restricted pattern: $pattern");
            }
        }

        // 2. Extract base command (first word)
        $parts = explode(' ', $command);
        $baseCommand = $parts[0];
        
        // Handle path-based commands e.g. "./vendor/bin/phpunit" -> "phpunit"
        $baseName = basename($baseCommand);

        // 3. Check whitelist
        if (!in_array($baseName, $this->allowedCommands, true)) {
            throw new SecurityException("Security Violation: Command '$baseName' is not in the allowlist.");
        }
    }

    public function sanitize(string $input): string
    {
        return escapeshellcmd($input);
    }
}
