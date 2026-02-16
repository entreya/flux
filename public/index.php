<?php declare(strict_types=1);

/**
 * Flux UI — public/index.php
 *
 * Bootstrap 5 (CDN) — swap to Tailwind by replacing CDN links and class names.
 */

$workflow = isset($_GET['workflow']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', $_GET['workflow']) : null;
$jobId    = isset($_GET['job'])      ? preg_replace('/[^a-zA-Z0-9\-_]/', '', $_GET['job'])      : null;

$sseUrl = null;
if ($workflow)      $sseUrl = 'sse.php?workflow=' . urlencode($workflow);
elseif ($jobId)     $sseUrl = 'sse.php?job='      . urlencode($jobId);

// Discover example workflows for the landing page
$examplesDir  = realpath(__DIR__ . '/../examples');
$exampleFiles = [];
if ($examplesDir) {
    foreach (glob("$examplesDir/*.yaml") as $f) {
        $exampleFiles[] = basename($f, '.yaml');
    }
    foreach (glob("$examplesDir/*.yml") as $f) {
        $exampleFiles[] = basename($f, '.yml');
    }
}

$pageTitle = 'Flux';
if ($workflow) $pageTitle = htmlspecialchars($workflow) . ' · Flux';
elseif ($jobId) $pageTitle = 'Job · Flux';
?><!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?></title>
<!-- Bootstrap 5.3 — swap CDN link to use Tailwind/other framework -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/flux.css">
</head>
<body>

<!-- ═══ HEADER ══════════════════════════════════════════════════════════════ -->
<header class="flux-header border-bottom d-flex align-items-center px-3 gap-3">
  <a href="index.php" class="flux-logo d-flex align-items-center gap-2 text-decoration-none">
    <i class="bi bi-lightning-charge-fill text-warning"></i>
    <span class="fw-semibold">flux</span>
  </a>

  <?php if ($workflow || $jobId): ?>
  <div class="d-flex align-items-center gap-2 text-muted small">
    <span>/</span>
    <span class="fw-medium text-body"><?= htmlspecialchars($workflow ?? 'job/' . $jobId) ?></span>
  </div>
  <?php endif; ?>

  <div class="ms-auto d-flex align-items-center gap-2">
    <?php if ($sseUrl): ?>
    <span class="run-badge" id="fx-status-badge" data-status="pending">
      <span class="run-dot"></span>
      <span class="flux-run-text">Connecting</span>
    </span>
    <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1"
            id="fx-rerun-btn" onclick="FluxUI.rerun()" disabled title="Re-run workflow">
      <i class="bi bi-arrow-clockwise"></i>
      <span class="d-none d-sm-inline">Re-run</span>
    </button>
    <?php endif; ?>
    <button class="btn btn-sm btn-outline-secondary" onclick="FluxUI.toggleTheme()" title="Toggle theme">
      <i id="fx-theme-icon" class="bi bi-moon-stars"></i>
    </button>
  </div>
</header>

<!-- ═══ LAYOUT ══════════════════════════════════════════════════════════════ -->
<div class="flux-layout">

<?php if ($sseUrl): ?>

  <!-- ─── SIDEBAR ──────────────────────────────────── -->
  <nav class="flux-sidebar border-end d-flex flex-column" id="fx-sidebar">
    <div class="px-3 pt-3 pb-1">
      <p class="flux-section-label mb-2">Jobs</p>
      <div id="fx-job-list">
        <div class="flux-sidebar-empty text-muted small fst-italic">Waiting for workflow…</div>
      </div>
    </div>

    <hr class="mx-3 my-2">

    <div class="px-3 pb-3">
      <p class="flux-section-label mb-2">Run info</p>
      <div class="small text-muted d-flex flex-column gap-1">
        <div class="d-flex justify-content-between">
          <span>Workflow</span>
          <span class="text-body fw-medium text-truncate ms-2" style="max-width:120px">
            <?= htmlspecialchars($workflow ?? 'job/' . $jobId) ?>
          </span>
        </div>
        <div class="d-flex justify-content-between">
          <span>Trigger</span>
          <span class="text-body">manual</span>
        </div>
        <div class="d-flex justify-content-between">
          <span>Runner</span>
          <span class="text-body">php-<?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?></span>
        </div>
      </div>
    </div>
  </nav>

  <!-- ─── MAIN ──────────────────────────────────────── -->
  <main class="flux-main d-flex flex-column overflow-hidden flex-grow-1">

    <div class="flux-toolbar border-bottom d-flex align-items-center gap-3 px-3">
      <span class="fw-semibold small flex-grow-1 text-truncate" id="fx-job-heading">Initializing…</span>
      <div class="d-flex align-items-center gap-2 flex-shrink-0">
        <div class="search-wrap">
          <i class="bi bi-search search-icon"></i>
          <input id="fx-search" type="search" class="form-control form-control-sm flux-search"
                 placeholder="Filter logs…" autocomplete="off">
        </div>
        <button class="btn btn-sm btn-outline-secondary" onclick="FluxUI.expandAll()" title="Expand all">
          <i class="bi bi-arrows-expand"></i>
        </button>
      </div>
    </div>

    <div class="steps-wrap overflow-auto flex-grow-1 p-2" id="fx-steps"></div>

  </main>

<?php else: ?>

  <!-- ─── LANDING / DROP ZONE ──────────────────────── -->
  <main class="d-flex align-items-center justify-content-center flex-grow-1"
        style="background:var(--bs-body-bg)">
    <div style="max-width:520px;width:100%;padding:24px">

      <!-- Drop zone -->
      <div class="flux-dropzone text-center p-5 mb-4" id="fx-dropzone">
        <div class="mb-3" style="font-size:40px;color:var(--bs-secondary-color)">
          <i class="bi bi-file-earmark-code"></i>
        </div>
        <h5 class="fw-semibold mb-1">Drop a workflow file</h5>
        <p class="text-muted small mb-4">Drag a <code>.yaml</code> file here, or click to browse</p>
        <label class="btn btn-sm btn-primary mb-0">
          Choose file
          <input type="file" id="fx-file-input" accept=".yaml,.yml" class="d-none">
        </label>
      </div>

      <?php if (!empty($exampleFiles)): ?>
      <!-- Example workflows -->
      <div>
        <p class="flux-section-label mb-2">Example workflows</p>
        <div class="d-flex flex-column gap-1">
          <?php foreach ($exampleFiles as $ex): ?>
          <a href="index.php?workflow=<?= urlencode($ex) ?>"
             class="d-flex align-items-center gap-2 px-3 py-2 rounded text-decoration-none small
                    border flux-example-link">
            <i class="bi bi-file-earmark-play text-muted"></i>
            <span class="flex-grow-1 fw-medium"><?= htmlspecialchars($ex) ?></span>
            <i class="bi bi-chevron-right text-muted" style="font-size:11px"></i>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </main>

<?php endif; ?>

</div><!-- /.flux-layout -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/flux.js"></script>
<script>
  FluxUI.init({
    sseUrl:    <?= $sseUrl ? json_encode($sseUrl) : 'null' ?>,
    uploadUrl: 'upload.php'
  });
</script>
</body>
</html>
