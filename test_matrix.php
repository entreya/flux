<?php
require __DIR__ . '/vendor/autoload.php';

use Entreya\Flux\Flux;

$pipeline = Flux::fromYaml(__DIR__ . '/examples/matrix-test.yaml');
$jobs = $pipeline->getJobs();

echo "Total Jobs: " . count($jobs) . "\n\n";

foreach ($jobs as $id => $job) {
    echo "Job ID: $id\n";
    echo "Name:   " . $job->getName() . "\n";
    foreach ($job->getSteps() as $step) {
        echo "Command: " . $step->getCommand() . "\n";
    }
    echo "-------------------\n";
}
