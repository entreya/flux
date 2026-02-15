<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Entreya\Flux\Flux;

$config = ['security' => ['require_auth' => false]]; // Minimal config for theme manager
$flux = new Flux($config);

// Handle Theme AJAX request
if (isset($_GET['action']) && $_GET['action'] === 'get_theme_css') {
    $theme = $_GET['theme'] ?? 'dark';
    header('Content-Type: text/css');
    echo $flux->getThemeManager()->getCssVariables($theme);
    exit;
}

// Default View
$theme = $_GET['theme'] ?? 'dark';
$cssVars = $flux->getThemeManager()->getCssVariables($theme);
$initialWorkflow = $_GET['workflow'] ?? 'basic-workflow'; // Default example

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entreya Flux Console</title>
    <link rel="stylesheet" href="assets/css/flux.css">
    <style id="flux-theme-style">
        <?= $cssVars ?>
    </style>
</head>
<body class="flux-body">
    <!-- Header -->
    <header class="flux-header">
        <div class="flux-workflow-title">
            <span id="workflow-status" class="flux-status-badge pending">QUEUED</span>
            <span id="workflow-name">GitHub Actions Style Pipeline</span>
        </div>
        <div class="flux-actions">
            <select id="theme" class="theme-select">
                <option value="dark">Dark</option>
                <option value="light">Light</option>
                <option value="high-contrast">High Contrast</option>
            </select>
            <a href="?workflow=complex-workflow" class="theme-select" style="text-decoration:none">Run Complex Info</a>
        </div>
    </header>

    <!-- Main Layout -->
    <div class="flux-layout">
        <!-- Sidebar -->
        <aside class="flux-sidebar" id="flux-sidebar">
            <!-- Jobs injected here -->
        </aside>

        <!-- Log View -->
        <main class="flux-main">
            <div class="flux-log-header">
                <div class="flux-log-title" id="flux-log-title">Initializing...</div>
                <div class="flux-log-actions">
                    <button onclick="document.getElementById('flux-log-lines').innerHTML = ''">Clear</button>
                    <button>Scroll to Bottom</button>
                </div>
            </div>
            <div class="flux-log-viewport">
                <div id="flux-log-lines"></div>
            </div>
        </main>
    </div>

    <script src="assets/js/flux.js"></script>
    <script>
        const app = new FluxApp({
            sseUrl: 'sse.php?workflow=<?= htmlspecialchars($initialWorkflow) ?>'
        });
    </script>
</body>
</html>
