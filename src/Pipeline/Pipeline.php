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

        $jobs = $data['jobs'] ?? [];

        // Support legacy flat `steps:` format
        if (empty($jobs) && isset($data['steps'])) {
            $jobs = ['default' => ['name' => 'Run', 'steps' => $data['steps']]];
        }

        foreach ($jobs as $jobId => $jobData) {
            $job = new Job((string) $jobId, $jobData['name'] ?? (string) $jobId);
            $job->setNeeds((array) ($jobData['needs'] ?? []));
            $job->setEnv($jobData['env'] ?? []);

            foreach ($jobData['pre'] ?? [] as $s) {
                $job->addPreStep(self::makeStep($s));
            }
            foreach ($jobData['steps'] ?? [] as $s) {
                $job->addStep(self::makeStep($s));
            }
            foreach ($jobData['post'] ?? [] as $s) {
                $job->addPostStep(self::makeStep($s));
            }

            $pipeline->jobs[$jobId] = $job;
        }

        return $pipeline;
    }

    private static function makeStep(array $s): Step
    {
        return new Step(
            name:            $s['name'] ?? 'Step',
            command:         $s['run']  ?? null,
            env:             $s['env']  ?? [],
            continueOnError: (bool) ($s['continue-on-error'] ?? false),
            workingDir:      $s['working-directory'] ?? null,
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
        return $this;
    }

    // ── Terminal methods ──────────────────────────────────────────────────────

    public function stream(): void
    {
        $this->commitActiveJob();
        $this->auth?->enforce();

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
    public function getJobs(): array  { return $this->jobs;  }
}
