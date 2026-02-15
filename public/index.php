<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Entreya\Flux\Flux;

$config = ['security' => ['require_auth' => false]]; // Minimal config for theme manager
$flux = new Flux($config);

// Handle Theme AJAX request
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'get_theme_css') {
        $theme = $_GET['theme'] ?? 'dark';
        header('Content-Type: text/css');
        echo $flux->getThemeManager()->getCssVariables($theme);
        exit;
    }
    
    // Handle File Upload
    if ($_GET['action'] === 'upload') {
        header('Content-Type: application/json');
        
        if (!isset($_FILES['workflow_file']) || $_FILES['workflow_file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'File upload failed']);
            exit;
        }
        
        $ext = pathinfo($_FILES['workflow_file']['name'], PATHINFO_EXTENSION);
        if (!in_array(strtolower($ext), ['yaml', 'yml'])) {
             http_response_code(400);
             echo json_encode(['error' => 'Invalid file type. Only .yaml allowed.']);
             exit;
        }

        $tmpPath = sys_get_temp_dir() . '/flux_upl_' . uniqid() . '.yaml';
        if (move_uploaded_file($_FILES['workflow_file']['tmp_name'], $tmpPath)) {
            echo json_encode(['file' => $tmpPath]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save file']);
        }
        exit;
    }
}

// Default View
$theme = $_GET['theme'] ?? 'dark';
$cssVars = $flux->getThemeManager()->getCssVariables($theme);
$initialWorkflow = $_GET['workflow'] ?? null; 
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
            <span id="workflow-status" class="flux-status-badge pending">READY</span>
            <span id="workflow-name">Flux Pipeline</span>
        </div>
        <div class="flux-actions">
            <select id="theme" class="theme-select">
                <option value="dark">Dark</option>
                <option value="light">Light</option>
                <option value="high-contrast">High Contrast</option>
            </select>
        </div>
    </header>

    <!-- Main Layout -->
    <div class="flux-layout">
        <!-- Sidebar -->
        <aside class="flux-sidebar" id="flux-sidebar">
            <!-- Jobs injected here -->
        </aside>

        <!-- Log View -->
        <main class="flux-main" id="flux-main-view">
            <?php if ($initialWorkflow): ?>
                <div class="flux-log-header">
                    <div class="flux-log-title" id="flux-log-title">Initializing...</div>
                    <div class="flux-log-actions">
                        <button onclick="document.getElementById('flux-log-lines').innerHTML = ''">Clear</button>
                        <button id="btn-scroll-bottom">Scroll to Bottom</button>
                    </div>
                </div>
                <div class="flux-log-viewport">
                    <div id="flux-log-lines"></div>
                </div>
            <?php else: ?>
                <!-- Drop Zone -->
                <div id="flux-dropzone" class="flux-dropzone">
                    <div class="dropzone-content">
                        <h1>🚀 Deploy with Flux</h1>
                        <p>Drag & Drop a <code>.yaml</code> workflow file here to execute.</p>
                        <input type="file" id="file-upload" hidden>
                        <button onclick="document.getElementById('file-upload').click()">Select File</button>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="assets/js/flux.js"></script>
    <script>
        const app = new FluxApp({
            sseUrl: '<?= $initialWorkflow ? "sse.php?workflow=" . htmlspecialchars($initialWorkflow) : "" ?>'
        });
    </script>
</body>
</html>
