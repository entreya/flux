<?php

require __DIR__ . '/vendor/autoload.php';

use Entreya\Flux\Parser\YamlParser;

$parser = new YamlParser();

echo "Parsing basic workflow...\n";
try {
    $data = $parser->parseFile(__DIR__ . '/examples/basic-workflow.yaml');
    print_r($data);
    echo "Successfully parsed.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
