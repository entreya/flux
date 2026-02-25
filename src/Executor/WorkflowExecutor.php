<?php

declare(strict_types=1);

namespace Entreya\Flux\Executor;

use Entreya\Flux\Exceptions\FluxException;
use Entreya\Flux\Pipeline\Job;
use Entreya\Flux\Pipeline\Step;
use Entreya\Flux\Utils\ExpressionEvaluator;

/**
 * Executes a pipeline and yields structured events.
 *
 * Phase execution contract (mirrors GitHub Actions):
 *  1. pre  steps  — if one fails (no continue-on-error), skip remaining pre + all main steps
 *  2. main steps  — stop on first failure unless continue-on-error is set
 *  3. post steps  — ALWAYS execute, even when pre or main failed
 *
 * Job isolation fix: a failing job no longer stops unrelated jobs.
 * Only jobs whose `needs` includes a failed job ID are skipped.
 * workflow_failed is emitted once at the end, after all jobs have run.
 *
 * All step events carry a `phase` field: 'pre' | 'main' | 'post'
 */
class WorkflowExecutor
{
    private ExpressionEvaluator $evaluator;
    private ?array $cachedBaseEnv = null;

    public function __construct(
        private readonly CommandRunner $runner,
        private readonly array         $globalEnv = [],
        ?ExpressionEvaluator           $evaluator = null,
    ) {
        $this->evaluator = $evaluator ?? new ExpressionEvaluator();
    }

    /** @return \Generator<array> */
    public function execute(string $name, array $jobs): \Generator
    {
        $completedJobs  = [];  // job IDs that succeeded
        $failedJobs     = [];  // job IDs that failed
        $workflowFailed = false;

        yield $this->event('workflow_start', [
            'name'      => $name,
            'job_count' => count($jobs),
            'job_ids'   => array_keys($jobs),
        ]);

        foreach ($jobs as $jobId => $job) {
            // ── Dependency check ────────────────────────────────────────────
            // Only skip this job if one of its explicitly-declared needs failed.
            // Unrelated failures do NOT propagate (GitHub Actions behaviour).
            $unmetNeeds = [];
            foreach ($job->getNeeds() as $needed) {
                if (!in_array($needed, $completedJobs, true)) {
                    $unmetNeeds[] = $needed;
                }
            }

            if (!empty($unmetNeeds)) {
                yield $this->event('job_skipped', [
                    'id'     => $jobId,
                    'name'   => $job->getName(),
                    'reason' => "Required job(s) did not succeed: " . implode(', ', $unmetNeeds),
                ]);
                continue;
            }

            // ── Conditional check ────────────────────────────────────────────
            if ($job->getIf()) {
                if (!$this->evaluator->evaluate($job->getIf())) {
                    yield $this->event('job_skipped', [
                        'id'     => $jobId,
                        'name'   => $job->getName(),
                        'reason' => "Condition evaluated to false: {$job->getIf()}",
                    ]);
                    continue;
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

            // ── Pre steps ────────────────────────────────────────────────────
            foreach ($job->getPreSteps() as $index => $step) {
                $stepKey = "pre-$index";
                $failed  = false;

                foreach ($this->runStep($jobId, $stepKey, $step, $job, 'pre', $jobFailed) as $event) {
                    yield $event;
                    if ($event['event'] === 'step_failure' && !$step->isContinueOnError()) {
                        $failed = true;
                    }
                }

                if ($failed) {
                    $jobFailed = true;
                    break; // Stop remaining pre steps; skip main steps; post still runs
                }
            }

            // ── Main steps (skipped if pre failed) ───────────────────────────
            if (!$jobFailed) {
                foreach ($job->getSteps() as $index => $step) {
                    $stepKey = (string) $index;
                    $failed  = false;

                    foreach ($this->runStep($jobId, $stepKey, $step, $job, 'main', $jobFailed) as $event) {
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

            // ── Post steps (ALWAYS run, even after failures) ─────────────────
            foreach ($job->getPostSteps() as $index => $step) {
                $stepKey = "post-$index";
                foreach ($this->runStep($jobId, $stepKey, $step, $job, 'post', $jobFailed) as $event) {
                    yield $event;
                    // Post-step failures are logged but do not change the job's outcome
                }
            }

            // ── Job outcome ──────────────────────────────────────────────────
            if ($jobFailed) {
                $failedJobs[]   = $jobId;
                $workflowFailed = true;
                yield $this->event('job_failure', ['id' => $jobId, 'name' => $job->getName()]);
                // DO NOT return — continue to the next job so independent jobs still run
            } else {
                $completedJobs[] = $jobId;
                yield $this->event('job_success', ['id' => $jobId, 'name' => $job->getName()]);
            }
        }

        // ── Workflow outcome ─────────────────────────────────────────────────
        // Emitted once, after ALL jobs (including their post-steps) have completed.
        // Previously this was emitted mid-pipeline after the first failure, before
        // post-steps had a chance to run (e.g. notifications, cache flushes).
        if ($workflowFailed) {
            yield $this->event('workflow_failed', [
                'message'     => count($failedJobs) . ' job(s) failed.',
                'failed_jobs' => $failedJobs,
            ]);
        } else {
            yield $this->event('workflow_complete', []);
        }
    }

    /** @return \Generator<array> */
    private function runStep(
        string $jobId,
        string $stepKey,
        Step   $step,
        Job    $job,
        string $phase,
        bool   $jobFailed = false,  // actual job status for conditional expressions
    ): \Generator {
        if ($step->getIf()) {
            // Provide the real job status so success() / failure() expressions work correctly.
            $context = ['status' => $jobFailed ? 'failure' : 'success'];

            if (!$this->evaluator->evaluate($step->getIf(), $context)) {
                yield $this->event('step_skipped', [
                    'job'    => $jobId,
                    'step'   => $stepKey,
                    'phase'  => $phase,
                    'name'   => $step->getName(),
                    'reason' => "Condition evaluated to false: {$step->getIf()}",
                ]);
                return;
            }
        }

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
        return ['event' => $type, 'data' => $data, 'ts' => hrtime(true) / 1e9];
    }

    private function buildBaseEnv(): array
    {
        if ($this->cachedBaseEnv !== null) {
            return $this->cachedBaseEnv;
        }

        $phpDir = dirname(PHP_BINARY);
        $path   = getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';

        $this->cachedBaseEnv = [
            'PATH'                => $phpDir . PATH_SEPARATOR . $path,
            'PHP_BINARY'          => PHP_BINARY,
            'TERM'                => 'xterm-256color',
            'ANSIBLE_FORCE_COLOR' => '1',
            'FORCE_COLOR'         => '1',
        ];

        return $this->cachedBaseEnv;
    }
}
