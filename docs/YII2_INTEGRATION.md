# Flux Integration with Yii2

This guide details how to integrate **Entreya Flux** into a **Yii2** application to parse, execute, and stream workflows in real-time.

## 1. Installation

```bash
composer require entreya/flux
```

## 2. Controller Setup

Create a controller to handle workflow streaming.

### `controllers/FluxController.php`

```php
<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use Entreya\Flux\Flux;

class FluxController extends Controller
{
    /**
     * Render the Flux UI.
     */
    public function actionIndex(string $workflow = 'deploy')
    {
        return $this->render('index', [
            'workflow' => $workflow,
            // Pass the SSE URL to the view
            'sseUrl'   => \yii\helpers\Url::to(['stream', 'workflow' => $workflow]),
        ]);
    }

    /**
     * Stream the workflow logs via Server-Sent Events (SSE).
     */
    public function actionStream(string $workflow)
    {
        // 1. Prepare for SSE (Disable Buffering)
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', 1);
        }
        ini_set('output_buffering', '0');
        ini_set('zlib.output_compression', '0');
        
        while (ob_get_level() > 0) ob_end_clean();

        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no'); // Nginx

        // 2. Resolve Workflow File
        $baseDir = Yii::getAlias('@app/workflows');
        $path    = "$baseDir/$workflow.yaml";

        if (!file_exists($path)) {
            echo "event: error\ndata: {\"message\": \"Workflow file not found: $workflow\"}\n\n";
            exit;
        }

        // 3. Execute and Stream
        try {
            Flux::fromYaml($path, [
                'env' => [
                    'APP_ENV' => YII_ENV,
                    'DB_DSN'  => Yii::$app->db->dsn,
                ]
            ])->stream();
        } catch (\Throwable $e) {
            echo "event: error\ndata: " . json_encode(['message' => $e->getMessage()]) . "\n\n";
        }

        exit;
    }
}
```

## 3. View Setup (The Interface)

Flux requires a specific HTML structure to render the steps and logs correctly.

### `views/flux/index.php`

```php
<?php
/* @var $this yii\web\View */
/* @var $workflow string */
/* @var $sseUrl string */

$this->title = "Flux: $workflow";

// 1. Register Assets (Copy flux.css/js to your web/assets folder or Use CDN)
// You can find these files in vendor/entreya/flux/public/assets/
$this->registerCssFile('/assets/css/flux.css');
$this->registerJsFile('/assets/js/flux.js', ['position' => \yii\web\View::POS_END]);
?>

<div class="flux-layout" style="height: 85vh; border: 1px solid var(--bs-border-color); border-radius: 6px; display: flex; flex-direction: column;">

    <!-- Toolbar -->
    <div class="flux-header border-bottom d-flex align-items-center px-3 py-2 gap-3 bg-body-tertiary">
        <strong class="me-auto"><?= htmlspecialchars($workflow) ?></strong>
        
        <span class="run-badge" id="fx-status-badge" data-status="pending">
            <span class="run-dot"></span>
            <span class="flux-run-text">Connecting</span>
        </span>

        <button class="btn btn-sm btn-outline-secondary" id="fx-rerun-btn" onclick="FluxUI.rerun()" disabled>
            <i class="bi bi-arrow-clockwise"></i> Rerun
        </button>
    </div>

    <div class="d-flex flex-grow-1 overflow-hidden">
        
        <!-- Sidebar: Jobs -->
        <nav class="flux-sidebar border-end d-flex flex-column p-0" id="fx-sidebar" style="width: 280px; background: var(--bs-body-bg);">
            <div class="p-3">
                <p class="flux-section-label mb-2 text-uppercase small fw-bold text-muted">Jobs</p>
                <div id="fx-job-list" class="d-flex flex-column gap-1">
                    <div class="flux-sidebar-empty text-muted small fst-italic">Waiting for workflow…</div>
                </div>
            </div>
        </nav>

        <!-- Main: Logs -->
        <main class="flux-main d-flex flex-column flex-grow-1 overflow-hidden bg-body">
            <!-- Job Heading & Search -->
            <div class="flux-toolbar border-bottom d-flex align-items-center gap-3 px-3 py-2">
                <span class="fw-semibold small flex-grow-1 text-truncate" id="fx-job-heading">Initializing…</span>
                <input id="fx-search" type="search" class="form-control form-control-sm" placeholder="Filter logs…" style="width: 200px;">
            </div>

            <!-- Steps Container -->
            <div class="steps-wrap overflow-auto flex-grow-1 p-2" id="fx-steps"></div>
        </main>

    </div>
</div>

<!-- Initialize FluxUI -->
<?php $this->registerJs("
    FluxUI.init({
        sseUrl: " . json_encode($sseUrl) . "
    });
", \yii\web\View::POS_END); ?>
```

## 4. Background Jobs (Recommended for Production)

For heavy tasks, do not run them inside the web request (Apache/Nginx timeout). Instead, use a **Queue** and the **FileChannel** mode.

1.  **Queue Job**: Runs `Flux::fromYaml(...)->writeToFile($logPath)`.
2.  **Controller**: Returns the `$jobId`.
3.  **FluxController**: Added `actionTail($job)`:

```php
    public function actionTail(string $job)
    {
        $logPath = Yii::getAlias("@runtime/logs/flux/$job.log");
        // ... (headers setup) ...
        Flux::tail($logPath)->stream();
    }
```

4.  **Frontend**: Initialize with `sseUrl: '/flux/tail?job=' + jobId`.
