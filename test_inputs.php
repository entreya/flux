<?php
require __DIR__ . '/vendor/autoload.php';

use Entreya\Flux\Flux;

echo "--- Test 1: Defaults ---\n";
$p1 = Flux::fromYaml(__DIR__ . '/examples/inputs-test.yaml');
foreach ($p1->getJobs()['greet']->getSteps() as $s) echo $s->getCommand() . "\n";

echo "\n--- Test 2: Overrides ---\n";
$p2 = Flux::fromYaml(__DIR__ . '/examples/inputs-test.yaml', [
    'inputs' => ['target' => 'Flux', 'greeting' => 'Hi']
]);
foreach ($p2->getJobs()['greet']->getSteps() as $s) echo $s->getCommand() . "\n";
