<?php declare(strict_types=1);

/**
 * Flux UI — public/index.php
 * GitHub Actions-inspired workflow viewer
 */

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
<link rel="stylesheet" href="assets/css/flux.css">
</head>
<body>

<!-- ═══ HEADER ══════════════════════════════════════════════════════════════ -->
<header class="flux-header">

  <!-- Logo -->
  <a href="index.php" class="flux-logo">
    <div class="flux-logo-icon">⚡</div>
    <span>flux</span>
  </a>

  <!-- Breadcrumb -->
  <?php if ($workflow || $jobId): ?>
  <div class="flux-breadcrumb">
    <span class="sep">/</span>
    <span class="crumb"><?= htmlspecialchars($workflow ?? 'job/' . $jobId) ?></span>
  </div>
  <?php endif; ?>

  <!-- Right side controls -->
  <div class="ms-auto d-flex align-items-center gap-2">

    <?php if ($sseUrl): ?>
    <!-- Status badge -->
    <span class="flux-badge" id="fx-badge" data-status="pending">
      <span class="flux-badge-dot"></span>
      <span id="fx-badge-text">Connecting</span>
    </span>

    <!-- Re-run button -->
    <button class="flux-hbtn" id="fx-rerun-btn" onclick="FluxUI.rerun()" disabled>
      <i class="bi bi-arrow-clockwise"></i>
      <span class="d-none d-sm-inline">Re-run</span>
    </button>
    <?php endif; ?>

    <!-- Theme toggle -->
    <button class="flux-hbtn" onclick="FluxUI.toggleTheme()" title="Toggle theme">
      <i id="fx-theme-icon" class="bi bi-moon-stars"></i>
    </button>

  </div>
</header>

<!-- ═══ LAYOUT ══════════════════════════════════════════════════════════════ -->
<div class="flux-layout">

<?php if ($sseUrl): ?>

  <!-- ─── SIDEBAR ──────────────────────────────────────────────────────── -->
  <nav class="flux-sidebar">
    <div class="flux-sidebar-scroll">
      <p class="flux-sidebar-label">Jobs</p>
      <div id="fx-job-list">
        <div class="flux-sidebar-empty">Waiting for workflow…</div>
      </div>
    </div>

    <!-- Run info footer -->
    <div class="flux-run-info">
      <div class="flux-run-info-row">
        <span>Workflow</span>
        <span class="flux-run-info-val"><?= htmlspecialchars($workflow ?? 'job/' . $jobId) ?></span>
      </div>
      <div class="flux-run-info-row">
        <span>Trigger</span>
        <span class="flux-run-info-val">manual</span>
      </div>
      <div class="flux-run-info-row">
        <span>Runner</span>
        <span class="flux-run-info-val">php-<?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?></span>
      </div>
    </div>
  </nav>

  <!-- ─── MAIN ─────────────────────────────────────────────────────────── -->
  <main class="flux-main">

    <!-- Toolbar -->
    <div class="flux-toolbar">
      <span class="flux-toolbar-title" id="fx-job-heading">Initializing…</span>

      <div class="d-flex align-items-center gap-1 flex-shrink-0">
        <!-- Search -->
        <div class="flux-search-wrap">
          <i class="bi bi-search flux-search-icon"></i>
          <input id="fx-search" type="search" class="flux-search" placeholder="Search logs…" autocomplete="off">
        </div>

        <!-- Timestamp toggle -->
        <button class="flux-tbtn" id="fx-ts-btn" onclick="FluxUI.toggleTimestamps()" title="Toggle timestamps">
          <i class="bi bi-clock"></i>
        </button>

        <!-- Expand all -->
        <button class="flux-tbtn" onclick="FluxUI.expandAll()" title="Expand all steps">
          <i class="bi bi-arrows-expand"></i>
        </button>

        <!-- Collapse all -->
        <button class="flux-tbtn" onclick="FluxUI.collapseAll()" title="Collapse all steps">
          <i class="bi bi-arrows-collapse"></i>
        </button>
      </div>
    </div>

    <!-- Progress bar -->
    <div class="flux-progress">
      <div class="flux-progress-fill" id="fx-progress" style="width:0%"></div>
    </div>

    <!-- Steps -->
    <div class="flux-steps" id="fx-steps"></div>

  </main>

<?php else: ?>

  <!-- ─── LANDING PAGE ─────────────────────────────────────────────────── -->
  <div class="flux-landing">
    <div class="flux-landing-inner">

      <!-- Drop zone -->
      <div class="flux-dropzone" id="fx-dropzone">
        <div class="flux-dz-icon">
          <i class="bi bi-file-earmark-code"></i>
        </div>
        <h5 class="flux-dz-title">Drop a workflow file</h5>
        <p class="flux-dz-sub">Drag a <code>.yaml</code> or <code>.yml</code> file here, or click to browse</p>
        <label class="flux-hbtn" style="cursor:pointer;display:inline-flex">
          <i class="bi bi-folder2-open"></i> Choose file
          <input type="file" id="fx-file-input" accept=".yaml,.yml" class="d-none">
        </label>
      </div>

      <!-- Example workflows -->
      <?php if (!empty($exampleFiles)): ?>
      <p class="flux-example-label">Example workflows</p>
      <div>
        <?php foreach ($exampleFiles as $ex): ?>
        <a href="index.php?workflow=<?= urlencode($ex) ?>"
           class="flux-example-link">
          <i class="bi bi-play-circle text-muted" style="font-size:14px"></i>
          <span class="flex-grow-1"><?= htmlspecialchars($ex) ?></span>
          <i class="bi bi-chevron-right text-muted" style="font-size:11px"></i>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </div>
  </div>

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
