<?php
declare(strict_types=1);
namespace Entreya\Flux\Tests;
use Entreya\Flux\Pipeline\Pipeline;
use PHPUnit\Framework\TestCase;

class PipelineTest extends TestCase
{
    public function testFluentApiBuildsJobs(): void
    {
        $pipeline = (new Pipeline('Test'))
            ->job('build', 'Build')
                ->step('Install', 'composer install')
                ->step('Test',    'phpunit')
            ->job('deploy', 'Deploy')
                ->needs('build')
                ->step('Sync', 'rsync -avz . prod:/var/www');

        $jobs = $pipeline->getJobs();
        $this->assertCount(2, $jobs);
        $this->assertArrayHasKey('build', $jobs);
        $this->assertArrayHasKey('deploy', $jobs);
        $this->assertCount(2, $jobs['build']->getSteps());
        $this->assertEquals(['build'], $jobs['deploy']->getNeeds());
    }

    public function testFromArrayParsesYamlStructure(): void
    {
        $data = ['name' => 'My Workflow', 'jobs' => ['lint' => ['name' => 'Lint', 'steps' => [['name' => 'Run', 'run' => 'phpstan']]]]];
        $pipeline = Pipeline::fromArray($data);
        $this->assertEquals('My Workflow', $pipeline->getName());
        $this->assertArrayHasKey('lint', $pipeline->getJobs());
    }

    public function testLegacyStepsFormat(): void
    {
        $data = ['name' => 'Legacy', 'steps' => [['name' => 'Run', 'run' => 'echo hello']]];
        $pipeline = Pipeline::fromArray($data);
        $this->assertArrayHasKey('default', $pipeline->getJobs());
    }
}
