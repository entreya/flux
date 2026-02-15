<?php

declare(strict_types=1);

namespace Entreya\Flux\Executor;

use Entreya\Flux\Parser\YamlParser;
use Entreya\Flux\Exceptions\FluxException;
use Entreya\Flux\Exceptions\ExecutionException;

class WorkflowExecutor
{
    private CommandRunner $runner;
    private YamlParser $parser;
    private array $globalEnv;

    public function __construct(CommandRunner $runner, YamlParser $parser, array $globalEnv = [])
    {
        $this->runner = $runner;
        $this->parser = $parser;
        $this->globalEnv = $globalEnv;
    }

    /**
     * @param string $yamlContent
     * @return \Generator yields events including logs and status
     */
    public function execute(string $yamlContent): \Generator
    {
        $workflow = $this->parser->parse($yamlContent);
        
        // Flatten structure for easier iteration if needed, but here we process hierarchy
        $name = $workflow['name'] ?? 'Untitled Workflow';
        $jobs = $workflow['jobs'] ?? [];

        // Check if old format (single 'steps' array)
        if (isset($workflow['steps']) && !isset($workflow['jobs'])) {
             // Migrate on fly for fallback? 
             $jobs = ['default' => ['name' => 'Default Job', 'steps' => $workflow['steps']]];
        }

        $totalJobs = count($jobs);
        
        // Send initial metadata
        yield ['event' => 'workflow_start', 'data' => [
            'name' => $name, 
            'job_count' => $totalJobs,
            'jobs' => array_keys($jobs) // Send job IDs to init UI
        ]];

        foreach ($jobs as $jobId => $job) {
            $jobName = $job['name'] ?? $jobId;
            $steps = $job['steps'] ?? [];
            
            // Dependencies check (simulated)
            $needs = $job['needs'] ?? [];
            if (!is_array($needs)) $needs = [$needs];
            
            // In a real runner we would wait for them. Here we execute sequentially so dependencies are implicitly met 
            // IF the YAML is ordered correctly or we order them.
            // For simplicity in this version, we execute in defined order.

            yield ['event' => 'job_start', 'data' => [
                'id' => $jobId, 
                'name' => $jobName,
                'steps_count' => count($steps)
            ]];

            foreach ($steps as $index => $step) {
                $stepName = $step['name'] ?? "Step #".($index+1);
                $command = $step['run'] ?? null;
                
                // Merge Envs: Global -> Job -> Step
                $stepEnv = $step['env'] ?? []; 
                $jobEnv = $job['env'] ?? [];
                
                // Priority: Step > Job > Global
                $finalEnv = array_merge($this->globalEnv, $jobEnv, $stepEnv);
                
                if (!$command) {
                    // Could be a 'uses' action (not supported yet)
                    yield ['event' => 'step_skipped', 'data' => ['job' => $jobId, 'step' => $index, 'reason' => 'No run command']];
                    continue;
                }

                yield ['event' => 'step_start', 'data' => [
                    'job' => $jobId, 
                    'step' => $index, 
                    'name' => $stepName
                ]];

                $startTime = microtime(true);
                try {
                    // Send command alias
                    yield ['event' => 'log', 'data' => [
                         'job' => $jobId,
                         'step' => $index,
                         'type' => 'command', 
                         'content' => "> $command"
                    ]];

                    foreach ($this->runner->execute($command, null, $finalEnv) as $output) {
                         yield ['event' => 'log', 'data' => [
                             'job' => $jobId,
                             'step' => $index,
                             'type' => $output['type'], 
                             'content' => $output['content']
                         ]];
                    }
                    
                    $duration = microtime(true) - $startTime;
                    yield ['event' => 'step_success', 'data' => [
                        'job' => $jobId,
                        'step' => $index, 
                        'duration' => $duration
                    ]];

                } catch (FluxException $e) {
                    yield ['event' => 'step_failure', 'data' => [
                        'job' => $jobId,
                        'step' => $index, 
                        'message' => $e->getMessage(),
                    ]];
                    
                    yield ['event' => 'job_failure', 'data' => ['id' => $jobId]];
                    yield ['event' => 'workflow_failed', 'data' => ['message' => "Job '$jobName' failed."]];
                    return; // Stop workflow
                }
            }
            
            yield ['event' => 'job_success', 'data' => ['id' => $jobId]];
        }

        yield ['event' => 'workflow_complete', 'data' => []];
    }
}
