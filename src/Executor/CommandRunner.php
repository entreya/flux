<?php

declare(strict_types=1);

namespace Entreya\Flux\Executor;

use Entreya\Flux\Exceptions\ExecutionException;
use Entreya\Flux\Security\CommandValidator;

/**
 * Executes a shell command and yields output line-by-line in real-time.
 *
 * Key improvements over Gemini version:
 *  - Yields lines one-at-a-time using stream_select() for true real-time output
 *  - Multi-line `run: |` blocks are executed as a shell script via `bash -c`
 *  - Partial lines (no newline yet) are buffered and flushed when process exits
 */
class CommandRunner
{
    public function __construct(
        private readonly CommandValidator $validator,
        private readonly int              $timeout = 300,
    ) {}

    /**
     * Execute a command and yield each line of output.
     *
     * @param string      $command  Raw shell command or multi-line script
     * @param string|null $cwd      Working directory
     * @param array|null  $env      Environment variables
     * @return \Generator<array{type: string, content: string}>
     * @throws ExecutionException
     */
    public function execute(string $command, ?string $cwd = null, ?array $env = null): \Generator
    {
        $this->validator->validate($command);

        // Wrap multi-line scripts so they run as a single bash invocation
        $shellCmd = $this->wrapCommand($command);

        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($shellCmd, $descriptors, $pipes, $cwd, $env);

        if (!is_resource($process)) {
            throw new ExecutionException("Failed to start process for: $command");
        }

        fclose($pipes[0]); // Close stdin immediately

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdoutBuf = '';
        $stderrBuf = '';
        $startTime = microtime(true);

        try {
            while (true) {
                $read   = [$pipes[1], $pipes[2]];
                $write  = null;
                $except = null;

                // Wait up to 100ms for data
                $changed = stream_select($read, $write, $except, 0, 100_000);

                if ($changed === false) {
                    break;
                }

                if ($changed > 0) {
                    foreach ($read as $stream) {
                        $type = ($stream === $pipes[1]) ? 'stdout' : 'stderr';

                        $chunk = fread($stream, 8192);
                        if ($chunk === false || $chunk === '') {
                            continue;
                        }

                        if ($type === 'stdout') {
                            $stdoutBuf .= $chunk;
                            yield from $this->drainLines($stdoutBuf, 'stdout');
                        } else {
                            $stderrBuf .= $chunk;
                            yield from $this->drainLines($stderrBuf, 'stderr');
                        }
                    }
                }

                // Timeout check
                if (microtime(true) - $startTime > $this->timeout) {
                    proc_terminate($process, 9);
                    throw new ExecutionException("Command timed out after {$this->timeout}s.");
                }

                $status = proc_get_status($process);
                if (!$status['running']) {
                    // Final drain after process exits
                    $remaining = stream_get_contents($pipes[1]);
                    if ($remaining) {
                        $stdoutBuf .= $remaining;
                    }
                    $remaining = stream_get_contents($pipes[2]);
                    if ($remaining) {
                        $stderrBuf .= $remaining;
                    }
                    break;
                }
            }

            // Flush any remaining partial lines
            if ($stdoutBuf !== '') {
                yield ['type' => 'stdout', 'content' => $stdoutBuf];
            }
            if ($stderrBuf !== '') {
                yield ['type' => 'stderr', 'content' => $stderrBuf];
            }

            $status   = proc_get_status($process);
            $exitCode = $status['exitcode'];

            if ($exitCode !== 0) {
                throw new ExecutionException(
                    "Command exited with code $exitCode.",
                    $exitCode
                );
            }

        } finally {
            @fclose($pipes[1]);
            @fclose($pipes[2]);
            @proc_close($process);
        }
    }

    /**
     * Extract complete lines from a growing buffer; leave partial last line.
     *
     * @return \Generator<array{type: string, content: string}>
     */
    private function drainLines(string &$buffer, string $type): \Generator
    {
        while (($pos = strpos($buffer, "\n")) !== false) {
            $line   = substr($buffer, 0, $pos);  // excludes the \n
            $buffer = substr($buffer, $pos + 1);

            if ($line !== '') {
                yield ['type' => $type, 'content' => $line];
            }
        }
    }

    /**
     * Wrap a command for execution.
     *
     * Multi-line scripts (containing newlines) are written to a temp file and
     * executed via `bash`, so they can contain pipes, conditionals, etc.
     * Single-line commands are passed directly to `bash -c`.
     */
    private function wrapCommand(string $command): string
    {
        $command = trim($command);

        // Multi-line script
        if (str_contains($command, "\n")) {
            $tmp = tempnam(sys_get_temp_dir(), 'flux_script_') . '.sh';
            file_put_contents($tmp, "#!/usr/bin/env bash\nset -e\n" . $command);
            chmod($tmp, 0700);

            // Register cleanup
            register_shutdown_function(fn() => @unlink($tmp));

            return "bash " . escapeshellarg($tmp);
        }

        // Single-line: pass through bash so we get consistent env handling
        return "bash -c " . escapeshellarg($command);
    }
}
