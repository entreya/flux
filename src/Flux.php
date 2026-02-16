<?php

declare(strict_types=1);

namespace Entreya\Flux;

use Entreya\Flux\Channel\FileChannel;
use Entreya\Flux\Pipeline\Pipeline;
use Entreya\Flux\Parser\YamlParser;

/**
 * Flux — Real-time workflow streaming library.
 *
 * Inline mode (process tied to HTTP request):
 *   Flux::pipeline('Deploy')->job('build')->step('Install', 'composer install')->stream();
 *   Flux::fromYaml(__DIR__ . '/deploy.yaml')->stream();
 *
 * Background mode (process independent of HTTP request):
 *   // In your queue worker / background job:
 *   Flux::fromYaml('import.yaml')->writeToFile('/tmp/flux-jobs/' . $jobId . '.log');
 *
 *   // In your SSE endpoint:
 *   Flux::tail('/tmp/flux-jobs/' . $jobId . '.log')->stream();
 */
final class Flux
{
    /**
     * Start building a workflow pipeline with a fluent API.
     */
    public static function pipeline(string $name = 'Workflow'): Pipeline
    {
        return new Pipeline($name);
    }

    /**
     * Load and parse a YAML workflow file into a Pipeline.
     *
     * @param string $path    Absolute path to the .yaml workflow file
     * @param array  $config  Optional runtime config (timeout, security, env)
     */
    public static function fromYaml(string $path, array $config = []): Pipeline
    {
        $parser = new YamlParser();
        $data   = $parser->parseFile($path);
        return Pipeline::fromArray($data, $config);
    }

    /**
     * Tail a log file and stream its contents via SSE.
     *
     * Use this in your SSE endpoint to reconnect a browser to an already-running
     * (or completed) background job.
     *
     * @param string $logPath Path to the .log file written by ->writeToFile()
     */
    public static function tail(string $logPath): FileChannel
    {
        return new FileChannel($logPath, mode: FileChannel::MODE_TAIL);
    }
}
