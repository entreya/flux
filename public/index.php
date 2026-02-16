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

    // Handle File Content Request
    if ($_GET['action'] === 'get_workflow_content') {
        $file = $_GET['file'] ?? '';
        
        // Auto-append .yaml if missing and file doesn't exist
        if (!file_exists($file) && file_exists($file . '.yaml')) {
            $file .= '.yaml';
        }

        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (!in_array(strtolower($ext), ['yaml', 'yml'])) {
             http_response_code(403); 
             exit('Invalid file type');
        }
        
        if (file_exists($file)) {
            header('Content-Type: text/plain');
            echo file_get_contents($file);
        } else {
            http_response_code(404); 
            echo 'File not found';
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
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/flux.css">
    <style id="flux-theme-style">
        <?= $cssVars ?>
    </style>
</head>
<body class="flux-body" data-bs-theme="dark"> <!-- Force BS dark mode to match -->
    <!-- Header -->
    <!-- Header -->
    <header class="flux-header">
        <div class="d-flex align-items-center gap-2">
            <!-- Mobile Menu Toggle -->
            <button class="btn btn-sm btn-outline-secondary d-md-none" onclick="app.toggleSidebar()" aria-label="Toggle Navigation">
                <i class="bi bi-list"></i>
            </button>
            <div class="flux-workflow-title">
                <span id="workflow-status" class="flux-status-badge pending">READY</span>
                <span id="workflow-name">Flux Pipeline</span>
            </div>
        </div>
        <div class="flux-actions d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="theme-toggle" onclick="app.toggleTheme()" title="Toggle Theme">
                <i class="bi bi-moon-stars-fill" id="theme-icon"></i>
            </button>
        </div>
    </header>

    <!-- Main Layout -->
    <div class="flux-layout">
        <!-- Sidebar -->
        <aside class="flux-sidebar" id="flux-sidebar">
            <?php if ($initialWorkflow): ?>
                <div class="flux-sidebar-header d-flex justify-content-between align-items-center">
                    <div style="overflow:hidden; flex:1; min-width:0;">
                         <div class="flux-filename" style="margin-bottom:4px;">WORKFLOW FILE</div>
                         <div class="flux-file-link text-truncate" onclick="app.showWorkflowFile('<?= htmlspecialchars($initialWorkflow) ?>')" style="cursor:pointer; font-weight:600; font-size:13px; color:var(--flux-info);">
                            <i class="bi bi-file-earmark-code me-1"></i><?= htmlspecialchars(basename($initialWorkflow)) ?>
                         </div>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary ms-2" onclick="window.location.reload()" title="Rerun Job">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            <?php endif; ?>
            <!-- Jobs injected here -->
        </aside>

        <!-- Log View -->
        <!-- Main Content -->
        <main class="flux-main" id="flux-main-view">
            <?php if ($initialWorkflow): ?>
                <div class="flux-workflow-header">
                    <h2 id="flux-job-title" class="mb-0">Initializing...</h2>
                    <input type="text" id="log-search" class="flux-search-input form-control form-control-sm" style="width:250px;" placeholder="Search logs (Regex)...">
                </div>
                <div id="flux-steps-container" class="flux-steps-container p-3">
                    <!-- Steps injected here as Accordions -->
                </div>
            <?php else: ?>
                <!-- Drop Zone -->
                <div id="flux-dropzone" class="flux-dropzone">
                    <div class="dropzone-content">
                        <h1>🚀 Deploy with Flux</h1>
                        <p>Drag & Drop a <code>.yaml</code> workflow file here to execute.</p>
                        <input type="file" id="file-upload" hidden>
                        <button onclick="document.getElementById('file-upload').click()" class="btn btn-success">Select File</button>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Workflow File Modal -->
    <div class="modal fade" id="fileModal" tabindex="-1">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="background:var(--flux-bg); color:var(--flux-text); border:1px solid var(--flux-border);">
          <div class="modal-header" style="border-bottom:1px solid var(--flux-border);">
            <h5 class="modal-title">Workflow Content</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <pre id="file-content-view" style="margin:0; font-family:var(--flux-font-mono); font-size:12px;"></pre>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/flux.js"></script>
    <script>
        window.app = new FluxApp({
            sseUrl: '<?= $initialWorkflow ? "sse.php?workflow=" . htmlspecialchars($initialWorkflow) : "" ?>'
        });
    </script>
</body>
</html>
