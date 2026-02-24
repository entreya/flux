<?php

declare(strict_types=1);

namespace Entreya\Flux\Pipeline;

use Entreya\Flux\Channel\FileChannel;
use Entreya\Flux\Channel\SseChannel;
use Entreya\Flux\Executor\CommandRunner;
use Entreya\Flux\Executor\WorkflowExecutor;
use Entreya\Flux\Exceptions\FluxException;
use Entreya\Flux\Security\AuthManager;
use Entreya\Flux\Security\CommandValidator;
use Entreya\Flux\Security\RateLimiter;

/**
 * Fluent pipeline builder.
 *
 *   Flux::pipeline('Deploy')
 *       ->job('build', 'Build')
 *           ->preStep('Validate env',   'php artisan env:check')
 *           ->step('Install',           'composer install --no-dev')
 *           ->step('Run tests',         'vendor/bin/phpunit')
 *           ->postStep('Cleanup temp',  'rm -rf /tmp/build-*')
 *       ->job('deploy', 'Deploy')
 *           ->needs('build')
 *           ->step('Sync',              'rsync -avz dist/ prod:/var/www/')
 *           ->postStep('Notify',        'php artisan notify:deploy')
 *       ->stream();
 */
class Pipeline
{
    private string       $name;
    private array        $jobs      = [];
    private ?Job         $activeJob = null;
    private string       $phase     = 'main'; // 'pre' | 'main' | 'post'
    private array        $globalEnv = [];
    private array        $config    = [];
    private ?AuthManager $auth      = null;
    private ?RateLimiter $rateLimiter = null;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    // ── Static factory ────────────────────────────────────────────────────────

    public static function fromArray(array $data, array $config = []): self
    {
        $pipeline            = new self($data['name'] ?? 'Workflow');
        $pipeline->config    = $config;
        $pipeline->globalEnv = $data['env'] ?? [];
        
        // Process Inputs (Default values + Config overrides)
        $providedInputs = $config['inputs'] ?? [];
        $inputs = [];
        foreach ($data['inputs'] ?? [] as $key => $def) {
            $inputs[$key] = $providedInputs[$key] ?? $def['default'] ?? '';
        }

        $jobs = $data['jobs'] ?? [];

        // Legacy flat format support
        if (empty($jobs) && isset($data['steps'])) {
            $jobs = ['default' => ['name' => 'Run', 'steps' => $data['steps']]];
        }

        foreach ($jobs as $jobId => $jobData) {
            // Check for Matrix Strategy
            $matrix = $jobData['strategy']['matrix'] ?? null;

            if ($matrix && is_array($matrix)) {
                // Expand Matrix
                $combinations = self::expandMatrix($matrix);
                foreach ($combinations as $combo) {
                    $suffix = implode('-', array_values($combo));
                    $newId  = "{$jobId}-{$suffix}";
                    
                    // Context for interpolation
                    $context = [
                        'matrix' => $combo,
                        'inputs' => $inputs,
                        'env'    => $pipeline->globalEnv,
                    ];

                    self::addJobToPipeline($pipeline, $newId, $jobData, $context);
                }
            } else {
                // Single Job
                $context = [
                    'matrix' => [],
                    'inputs' => $inputs,
                    'env'    => $pipeline->globalEnv,
                ];
                self::addJobToPipeline($pipeline, (string)$jobId, $jobData, $context);
            }
        }

        return $pipeline;
    }

    private static function expandMatrix(array $matrix): array
    {
        $keys = array_keys($matrix);
        $combinations = [[]];

        foreach ($keys as $key) {
            $values = $matrix[$key];
            $newCombinations = [];
            foreach ($combinations as $combo) {
                foreach ($values as $value) {
                    $newCombo = $combo;
                    $newCombo[$key] = $value;
                    $newCombinations[] = $newCombo;
                }
            }
            $combinations = $newCombinations;
        }

        return $combinations;
    }

    private static function addJobToPipeline(self $pipeline, string $id, array $data, array $context): void
    {
        // Interpolate Name
        $name = \Entreya\Flux\Utils\VariableInterpolator::interpolate($data['name'] ?? $id, $context);
        
        $job = new Job($id, $name);
        $job->setNeeds((array) ($data['needs'] ?? []));
        $job->setEnv($data['env'] ?? []);
        $job->setIf($data['if'] ?? null);
        
        foreach ($data['pre'] ?? [] as $s) {
            $job->addPreStep(self::makeStep($s, $context));
        }
        foreach ($data['steps'] ?? [] as $s) {
            $job->addStep(self::makeStep($s, $context));
        }
        foreach ($data['post'] ?? [] as $s) {
            $job->addPostStep(self::makeStep($s, $context));
        }

        $pipeline->jobs[$id] = $job;
    }

    private static function makeStep(array $s, array $context = []): Step
    {
        $command = $s['run'] ?? null;
        if ($command && !empty($context)) {
            $command = \Entreya\Flux\Utils\VariableInterpolator::interpolate($command, $context);
        }

        return new Step(
            name:            $s['name'] ?? 'Step',
            command:         $command,
            env:             $s['env']  ?? [],
            continueOnError: (bool) ($s['continue-on-error'] ?? false),
            workingDir:      $s['working-directory'] ?? null,
            if:              $s['if'] ?? null,
        );
    }

    // ── Fluent builder ────────────────────────────────────────────────────────

    /** Begin a new job. Subsequent step calls attach to this job. */
    public function job(string $id, string $name = ''): self
    {
        $this->commitActiveJob();
        $this->activeJob = new Job($id, $name !== '' ? $name : $id);
        $this->phase     = 'main';
        return $this;
    }

    /** Add a step to the current job (default main phase). */
    public function step(string $name, string $command, array $env = []): self
    {
        $this->ensureJob()->addStep(new Step($name, $command, $env));
        $this->phase = 'main';
        return $this;
    }

    /**
     * Add a pre-job step.
     * Runs before main steps. If it fails (no continueOnError), main steps
     * are skipped but post steps still execute.
     */
    public function preStep(string $name, string $command, array $env = []): self
    {
        $this->ensureJob()->addPreStep(new Step($name, $command, $env));
        $this->phase = 'pre';
        return $this;
    }

    /**
     * Add a post-job step.
     * ALWAYS runs after main steps — even if the job failed.
     * Perfect for cleanup, notifications, releasing locks.
     */
    public function postStep(string $name, string $command, array $env = []): self
    {
        $this->ensureJob()->addPostStep(new Step($name, $command, $env));
        $this->phase = 'post';
        return $this;
    }

    public function env(array $env): self
    {
        if ($this->activeJob !== null) {
            $this->activeJob->setEnv(array_merge($this->activeJob->getEnv(), $env));
        } else {
            $this->globalEnv = array_merge($this->globalEnv, $env);
        }
        return $this;
    }

    public function needs(string|array $jobs): self
    {
        $this->ensureJob()->setNeeds((array) $jobs);
        return $this;
    }

    public function continueOnError(bool $value = true): self
    {
        $this->activeJob?->lastStep()?->setContinueOnError($value);
        return $this;
    }

    public function withAuth(callable $check): self
    {
        $this->auth = new AuthManager($check);
        return $this;
    }

    public function withConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);

        // Wire up RateLimiter if rate_limit config is provided.
        // Previously RateLimiter existed but was never instantiated anywhere.
        if (isset($config['rate_limit'])) {
            $this->rateLimiter = new RateLimiter(
                maxPerHour: (int) ($config['rate_limit']['max_per_hour'] ?? 60),
                storageDir: (string) ($config['rate_limit']['storage_dir'] ?? ''),
            );
        }

        return $this;
    }

    // ── Terminal methods ──────────────────────────────────────────────────────

    public function stream(): void
    {
        $this->commitActiveJob();
        $this->auth?->enforce();
        $this->rateLimiter?->check($_SERVER['REMOTE_ADDR'] ?? 'cli');

        $channel  = new SseChannel();
        $executor = $this->buildExecutor();

        $channel->open();

        foreach ($executor->execute($this->name, $this->jobs) as $event) {
            $channel->write($event);
        }

        $channel->close();
    }

    public function writeToFile(string $path): void
    {
        $this->commitActiveJob();
        $this->rateLimiter?->check('background');

        $channel  = new FileChannel($path, mode: FileChannel::MODE_WRITE);
        $executor = $this->buildExecutor();

        $channel->open();

        foreach ($executor->execute($this->name, $this->jobs) as $event) {
            $channel->write($event);
        }

        $channel->complete();
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function ensureJob(): Job
    {
        if ($this->activeJob === null) {
            $this->job('default');
        }
        return $this->activeJob;
    }

    private function commitActiveJob(): void
    {
        if ($this->activeJob !== null) {
            $this->jobs[$this->activeJob->getId()] = $this->activeJob;
            $this->activeJob = null;
        }
    }

    private function buildExecutor(): WorkflowExecutor
    {
        $validator = new CommandValidator($this->config['security'] ?? []);
        $runner    = new CommandRunner($validator, $this->config['timeout'] ?? 300);
        return new WorkflowExecutor($runner, $this->globalEnv);
    }

    public function getName(): string { return $this->name; }
    public function getJobs(): array
    {
        $jobs = $this->jobs;
        if ($this->activeJob !== null) {
            $jobs[$this->activeJob->getId()] = $this->activeJob;
        }
        return $jobs;
    }
}
