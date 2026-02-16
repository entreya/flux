<?php

declare(strict_types=1);

namespace Entreya\Flux\Executor;

use Entreya\Flux\Exceptions\FluxException;
use Entreya\Flux\Pipeline\Job;
use Entreya\Flux\Pipeline\Step;

/**
 * Executes a pipeline and yields structured events.
 *
 * Phase execution contract (mirrors GitHub Actions):
 *  1. pre  steps  — if one fails (no continue-on-error), skip remaining pre + all main steps
 *  2. main steps  — stop on first failure unless continue-on-error is set
 *  3. post steps  — ALWAYS execute, even when pre or main failed
 *
 * All step events carry a `phase` field: 'pre' | 'main' | 'post'
 */
class WorkflowExecutor
{
    public function __construct(
        private readonly CommandRunner $runner,
        private readonly array         $globalEnv = [],
    ) {}

    /** @return \Generator<array> */
    public function execute(string $name, array $jobs): \Generator
    {
        $completedJobs = [];

        yield $this->event('workflow_start', [
            'name'      => $name,
            'job_count' => count($jobs),
            'job_ids'   => array_keys($jobs),
        ]);

        foreach ($jobs as $jobId => $job) {
            // Dependency check
            foreach ($job->getNeeds() as $needed) {
                if (!in_array($needed, $completedJobs, true)) {
                    yield $this->event('job_skipped', [
                        'id'     => $jobId,
                        'name'   => $job->getName(),
                        'reason' => "Dependency '$needed' did not succeed.",
                    ]);
                    continue 2;
                }
            }

            yield $this->event('job_start', [
                'id'              => $jobId,
                'name'            => $job->getName(),
                'pre_step_count'  => count($job->getPreSteps()),
                'step_count'      => count($job->getSteps()),
                'post_step_count' => count($job->getPostSteps()),
            ]);

            $jobFailed = false;

            // ── Pre steps ─────────────────────────────────────────────────
            foreach ($job->getPreSteps() as $index => $step) {
                $stepKey  = "pre-$index";
                $failed   = false;

                foreach ($this->runStep($jobId, $stepKey, $step, $job, 'pre') as $event) {
                    yield $event;
                    if ($event['event'] === 'step_failure' && !$step->isContinueOnError()) {
                        $failed = true;
                    }
                }

                if ($failed) {
                    $jobFailed = true;
                    break; // Stop remaining pre steps; skip main steps; post will still run
                }
            }

            // ── Main steps (skipped if pre failed) ────────────────────────
            if (!$jobFailed) {
                foreach ($job->getSteps() as $index => $step) {
                    $stepKey = (string) $index;
                    $failed  = false;

                    foreach ($this->runStep($jobId, $stepKey, $step, $job, 'main') as $event) {
                        yield $event;
                        if ($event['event'] === 'step_failure' && !$step->isContinueOnError()) {
                            $failed = true;
                        }
                    }

                    if ($failed) {
                        $jobFailed = true;
                        break;
                    }
                }
            }

            // ── Post steps (ALWAYS run) ────────────────────────────────────
            foreach ($job->getPostSteps() as $index => $step) {
                $stepKey = "post-$index";
                foreach ($this->runStep($jobId, $stepKey, $step, $job, 'post') as $event) {
                    yield $event;
                    // Post step failures are logged but don't change job status
                }
            }

            // ── Job outcome ───────────────────────────────────────────────
            if ($jobFailed) {
                yield $this->event('job_failure', ['id' => $jobId]);
                yield $this->event('workflow_failed', [
                    'message' => "Job '{$job->getName()}' failed.",
                ]);
                return;
            }

            $completedJobs[] = $jobId;
            yield $this->event('job_success', ['id' => $jobId, 'name' => $job->getName()]);
        }

        yield $this->event('workflow_complete', []);
    }

    /** @return \Generator<array> */
    private function runStep(string $jobId, string $stepKey, Step $step, Job $job, string $phase): \Generator
    {
        if ($step->getCommand() === null) {
            yield $this->event('step_skipped', [
                'job'    => $jobId,
                'step'   => $stepKey,
                'phase'  => $phase,
                'name'   => $step->getName(),
                'reason' => 'No run command defined.',
            ]);
            return;
        }

        $env = array_merge(
            $this->buildBaseEnv(),
            $this->globalEnv,
            $job->getEnv(),
            $step->getEnv(),
        );

        yield $this->event('step_start', [
            'job'   => $jobId,
            'step'  => $stepKey,
            'phase' => $phase,
            'name'  => $step->getName(),
        ]);

        // Show the command as the first log line
        yield $this->event('log', [
            'job'     => $jobId,
            'step'    => $stepKey,
            'phase'   => $phase,
            'type'    => 'cmd',
            'content' => $step->getCommand(),
        ]);

        $start = hrtime(true);

        try {
            foreach ($this->runner->execute($step->getCommand(), $step->getWorkingDir(), $env) as $out) {
                yield $this->event('log', [
                    'job'     => $jobId,
                    'step'    => $stepKey,
                    'phase'   => $phase,
                    'type'    => $out['type'],
                    'content' => $out['content'],
                ]);
            }

            yield $this->event('step_success', [
                'job'      => $jobId,
                'step'     => $stepKey,
                'phase'    => $phase,
                'duration' => round((hrtime(true) - $start) / 1e9, 2),
            ]);

        } catch (FluxException $e) {
            yield $this->event('step_failure', [
                'job'     => $jobId,
                'step'    => $stepKey,
                'phase'   => $phase,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function event(string $type, array $data): array
    {
        return ['event' => $type, 'data' => $data, 'ts' => time()];
    }

    private function buildBaseEnv(): array
    {
        $phpDir = dirname(PHP_BINARY);
        $path   = getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';

        return [
            'PATH'                => $phpDir . PATH_SEPARATOR . $path,
            'PHP_BINARY'          => PHP_BINARY,
            'TERM'                => 'xterm-256color',
            'ANSIBLE_FORCE_COLOR' => '1',
            'FORCE_COLOR'         => '1',
        ];
    }
}
