<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Entreya\Flux\Flux;

// Configuration (Real app should load this from a file)
$config = [
    'security' => [
        'allowed_commands' => ['composer', 'npm', 'git', 'php', 'echo', 'sleep', 'ls', 'whoami'],
        'require_auth' => false, // Set to true in production
    ],
    'timeout' => 600,
];

try {
    $flux = new Flux($config);
    
    // Get workflow file from query param
    $workflow = $_GET['workflow'] ?? 'default';
    
    // Security: Validate path traversal
    // In a real app, look up mapping from ID to file.
    // For demo, we allow loading from examples/ directory
    $baseDir = realpath(__DIR__ . '/../examples');
    $file = realpath($baseDir . '/' . $workflow . '.yaml');
    
    if (!$file || !str_starts_with($file, $baseDir)) {
        throw new Exception("Invalid workflow file.");
    }

    $flux->streamWorkflow($file);

} catch (Throwable $e) {
    header('Content-Type: text/event-stream');
    echo "event: error\n";
    echo "data: " . json_encode(['message' => $e->getMessage()]) . "\n\n";
}
