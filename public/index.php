<?php declare(strict_types=1);

/**
 * Flux UI — public/index.php
 * GitHub Actions-inspired workflow viewer — now using the widget system.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Entreya\Flux\Ui\FluxAsset;
use Entreya\Flux\Ui\FluxBadge;
use Entreya\Flux\Ui\FluxSidebar;
use Entreya\Flux\Ui\FluxToolbar;
use Entreya\Flux\Ui\FluxLogPanel;
use Entreya\Flux\Ui\FluxProgress;

$workflow = isset($_GET['workflow']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', $_GET['workflow']) : null;
$jobId    = isset($_GET['job'])      ? preg_replace('/[^a-zA-Z0-9\-_]/', '', $_GET['job'])      : null;

$sseUrl = null;
if ($workflow)  $sseUrl = 'sse.php?workflow=' . urlencode($workflow);
elseif ($jobId) $sseUrl = 'sse.php?job='      . urlencode($jobId);

$examplesDir  = realpath(__DIR__ . '/../examples');
$exampleFiles = [];
if ($examplesDir) {
    foreach (array_merge(glob("$examplesDir/*.yaml"), glob("$examplesDir/*.yml")) as $f) {
        $exampleFiles[] = basename($f, pathinfo($f, PATHINFO_EXTENSION) === 'yaml' ? '.yaml' : '.yml');
    }
}

$pageTitle = 'Flux';
if ($workflow) $pageTitle = htmlspecialchars($workflow) . ' — Flux';
elseif ($jobId) $pageTitle = 'Job — Flux';
?><!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<?= FluxAsset::css() ?>
</head>
<body>

<!-- ═══ HEADER (Bootstrap navbar) ═══════════════════════════════════════════ -->
<nav class="navbar navbar-dark bg-dark border-bottom py-0" style="height:48px">
  <div class="container-fluid px-3 gap-2">

    <!-- Logo -->
    <a href="index.php" class="navbar-brand d-flex align-items-center gap-2 py-0" style="font-size:14px">
      <span class="d-inline-grid place-items-center rounded" style="width:20px;height:20px;background:linear-gradient(135deg,#f7c948,#e08200);font-size:12px;color:#000;font-weight:800">⚡</span>
      <span class="fw-semibold">flux</span>
    </a>

    <?php if ($workflow || $jobId): ?>
    <!-- Breadcrumb -->
    <span class="text-body-secondary small d-flex align-items-center gap-1">
      <span style="opacity:.4">/</span>
      <span class="text-body-emphasis fw-medium text-truncate" style="max-width:220px"><?= htmlspecialchars($workflow ?? 'job/' . $jobId) ?></span>
    </span>
    <?php endif; ?>

    <!-- Right side -->
    <div class="ms-auto d-flex align-items-center gap-2">
      <?php if ($sseUrl): ?>
        <?= FluxBadge::widget(['id' => 'fx-badge']) ?>
      <?php endif; ?>
    </div>

  </div>
</nav>

<!-- ═══ LAYOUT ══════════════════════════════════════════════════════════════ -->
<div class="d-flex" style="height:calc(100vh - 48px);overflow:hidden">

<?php if ($sseUrl): ?>

  <!-- ─── SIDEBAR ──────────────────────────────────────────────────────── -->
  <?= FluxSidebar::widget([
      'id'           => 'fx-sidebar',
      'workflowName' => $workflow ?? 'job/' . $jobId,
      'options'      => ['style' => 'width:260px;min-width:260px'],
  ]) ?>

  <!-- ─── MAIN ─────────────────────────────────────────────────────────── -->
  <main class="flex-grow-1 d-flex flex-column overflow-hidden min-vw-0">

    <!-- Toolbar -->
    <?= FluxToolbar::widget(['id' => 'fx-toolbar']) ?>

    <!-- Progress bar -->
    <?= FluxProgress::widget(['id' => 'fx-progress']) ?>

    <!-- Steps / Logs -->
    <?= FluxLogPanel::widget(['id' => 'fx-steps']) ?>

  </main>

<?php else: ?>

  <!-- ─── LANDING PAGE ─────────────────────────────────────────────────── -->
  <div class="flex-grow-1 d-flex align-items-center justify-content-center p-4">
    <div style="max-width:500px;width:100%">

      <!-- Drop zone -->
      <div class="card border-secondary-subtle text-center p-5 mb-3" id="fx-dropzone" style="cursor:pointer;border-style:dashed">
        <div class="mb-3"><i class="bi bi-file-earmark-code" style="font-size:38px"></i></div>
        <h5 class="fw-semibold">Drop a workflow file</h5>
        <p class="text-body-secondary small">Drag a <code>.yaml</code> or <code>.yml</code> file here, or click to browse</p>
        <label class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" style="cursor:pointer">
          <i class="bi bi-folder2-open"></i> Choose file
          <input type="file" id="fx-file-input" accept=".yaml,.yml" class="d-none">
        </label>
      </div>

      <?php if (!empty($exampleFiles)): ?>
      <p class="text-uppercase text-body-secondary fw-semibold small mb-2" style="font-size:11px;letter-spacing:.5px">Example workflows</p>
      <div class="list-group list-group-flush">
        <?php foreach ($exampleFiles as $ex): ?>
        <a href="index.php?workflow=<?= urlencode($ex) ?>"
           class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2 small">
          <i class="bi bi-play-circle text-body-secondary" style="font-size:14px"></i>
          <span class="flex-grow-1"><?= htmlspecialchars($ex) ?></span>
          <i class="bi bi-chevron-right text-body-secondary" style="font-size:11px"></i>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </div>
  </div>

<?php endif; ?>

</div><!-- /.layout -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?= FluxAsset::js() ?>
<?= FluxAsset::init([
    'sseUrl'    => $sseUrl,
    'uploadUrl' => 'upload.php',
]) ?>
</body>
</html>
