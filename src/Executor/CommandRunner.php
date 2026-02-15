<?php

declare(strict_types=1);

namespace Entreya\Flux\Executor;

use Entreya\Flux\Exceptions\ExecutionException;
use Entreya\Flux\Security\CommandValidator;

class CommandRunner
{
    public function __construct(
        private readonly CommandValidator $validator,
        private readonly int $timeout = 300
    ) {}

    /**
     * Executes a command and yields output incrementally.
     * 
     * @param string $command
     * @param string|null $cwd
     * @param array|null $env
     * @return \Generator yields [type, content]
     * @throws ExecutionException
     */
    public function execute(string $command, ?string $cwd = null, ?array $env = null): \Generator
    {
        // 1. Validate
        $this->validator->validate($command);

        // 2. Prepare descriptors
        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        // 3. Execute with proc_open
        $process = proc_open($command, $descriptors, $pipes, $cwd, $env);

        if (!is_resource($process)) {
            throw new ExecutionException("Failed to spawn process for command: $command");
        }

        // Non-blocking streams
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $startTime = microtime(true);

        try {
            while (true) {
                $status = proc_get_status($process);
                
                // Read stdout
                $out = stream_get_contents($pipes[1]);
                if ($out) {
                    yield ['type' => 'stdout', 'content' => $out];
                }

                // Read stderr
                $err = stream_get_contents($pipes[2]);
                if ($err) {
                    yield ['type' => 'stderr', 'content' => $err];
                }

                // Check timeout
                if (microtime(true) - $startTime > $this->timeout) {
                    proc_terminate($process);
                    throw new ExecutionException("Command timed out after {$this->timeout} seconds.");
                }

                if (!$status['running']) {
                    // Final read
                    $out = stream_get_contents($pipes[1]);
                    if ($out) yield ['type' => 'stdout', 'content' => $out];
                    
                    $err = stream_get_contents($pipes[2]);
                    if ($err) yield ['type' => 'stderr', 'content' => $err];
                    
                    $exitCode = $status['exitcode'];
                    if ($exitCode !== 0) {
                         throw new ExecutionException("Command failed with exit code $exitCode", $exitCode);
                    }
                    break;
                }

                usleep(50000); // 50ms buffer
            }
        } finally {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
    }
}
