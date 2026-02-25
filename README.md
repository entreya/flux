# entreya/flux

> Real-time workflow streaming for PHP. Run YAML-defined pipelines and stream live console output to the browser — GitHub Actions style.

![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)

---

## Installation

```bash
composer require entreya/flux
```

**Requires:** PHP 8.1+

If you plan to use `Flux::fromYaml()`, also install a YAML parser. The recommended option is `symfony/yaml`, though the native PHP `yaml` extension works too:

```bash
composer require symfony/yaml
```

`Flux::workflow()` (the fluent PHP API) has no YAML dependency at all.

---

## Quick Start

### 1. From a YAML file

```yaml
# workflows/deploy.yaml
name: Deploy to Production

jobs:
  build:
    name: Build
    steps:
      - name: Install dependencies
        run: composer install --no-dev --optimize-autoloader
      - name: Run tests
        run: vendor/bin/phpunit
      - name: Build assets
        run: npm run build

  deploy:
    name: Deploy
    needs: build
    steps:
      - name: Sync files
        run: rsync -avz dist/ prod:/var/www/app/
      - name: Clear cache
        run: php artisan cache:clear
```

```php
// public/sse.php
require_once __DIR__ . '/../vendor/autoload.php';

Flux::fromYaml(__DIR__ . '/../workflows/deploy.yaml')->stream();
```

### 2. Fluent PHP API

```php
Flux::workflow('Deploy')
    ->job('build', 'Build')
        ->step('Install deps',  'composer install --no-dev')
        ->step('Run tests',     'vendor/bin/phpunit')
        ->step('Build assets',  'npm run build')
    ->job('deploy', 'Deploy')
        ->needs('build')
        ->step('Sync files',    'rsync -avz dist/ prod:/var/www/')
        ->step('Clear cache',   'php artisan cache:clear')
    ->withAuth(fn() => isset($_SESSION['admin']))
    ->stream();
```

---

## ⚡ Architecture: Inline vs. Background

This is the most important decision when integrating Flux.

### Mode 1 — Inline Streaming (default)

```
Browser → SSE connection → PHP process → bash command → output → browser
```

**Use when:** Developer tools, CI visualization, quick scripts (<60s), jobs where losing the browser connection is acceptable.

**Limitation:** The process lifecycle is tied to the HTTP connection. If the browser closes, PHP may kill the process.

```php
// SSE endpoint — runs the job and streams it
Flux::fromYaml('deploy.yaml')->stream();
```

### Mode 2 — Background + File Channel ✅ Recommended for production tasks

```
HTTP request → start worker → return job ID
                   ↓
           Worker runs → writes to /tmp/flux-jobs/{id}.log
                   ↓
Browser opens SSE → tail the log → stream to browser
```

**Use when:** CSV imports, data processing, report generation, any job > 30s, any job that must survive browser closure.

**Key properties:**
- ✅ Process runs independently of the browser
- ✅ Browser can close and reconnect — sees full history
- ✅ Works with any queue system (Laravel Horizon, Beanstalk, etc.)
- ✅ No special server requirements (just files)

#### In your queue worker / background job:

```php
$jobId   = $this->job->id; // From your queue system
$logPath = storage_path('logs/flux/' . $jobId . '.log');

Flux::workflow('Import Customer Data')
    ->job('validate', 'Validate')
        ->step('Check format', 'php artisan import:validate --file=' . $this->file)
    ->job('process', 'Process')
        ->needs('validate')
        ->step('Import rows',  'php artisan import:run --file=' . $this->file)
    ->writeToFile($logPath);
```

#### In your SSE endpoint:

```php
// public/sse.php?job=abc123
$jobId   = preg_replace('/[^a-zA-Z0-9\-_]/', '', $_GET['job'] ?? '');
$logPath = storage_path('logs/flux/' . $jobId . '.log');

Flux::tail($logPath)->stream();
```

#### In your controller:

```php
public function startImport(Request $request): JsonResponse
{
    $jobId = Str::uuid();

    ImportJob::dispatch($request->file('csv'), $jobId);

    return response()->json([
        'job_id'  => $jobId,
        'sse_url' => route('flux.sse', ['job' => $jobId]),
    ]);
}
```

#### In your frontend:

```javascript
const { job_id, sse_url } = await fetch('/import', { method: 'POST', ... }).then(r => r.json());
const es = new EventSource(sse_url);
// es streams output in real-time
```

---

## YAML Workflow Reference

```yaml
name: My Workflow        # Required

env:                     # Optional: global environment variables
  APP_ENV: production

jobs:
  job-id:
    name: Human Name     # Displayed in sidebar
    needs: other-job     # Depend on another job (or array)
    env:                 # Job-scoped env (merged with global)
      DATABASE_URL: sqlite::memory:
    steps:
      - name: Step name
        run: echo "hello"     # Single-line command

      - name: Multi-line script
        run: |                # Bash script block
          set -e
          echo "line 1"
          php artisan migrate
          echo "done"
        env:                  # Step-scoped env
          VERBOSE: "1"
        continue-on-error: true  # Don't stop workflow on failure
```

---

## 🚀 Advanced Features

### 1. Matrix Strategy
Run a job multiple times with different variable combinations (e.g., testing against multiple PHP versions).

```yaml
jobs:
  test:
    name: Test PHP ${{ matrix.php }}
    strategy:
      matrix:
        php: ['8.1', '8.2', '8.3']
        os:  ['ubuntu', 'alpine']
    steps:
      - run: echo "Running on ${{ matrix.os }} with PHP ${{ matrix.php }}"
```

### 2. Inputs & Interpolation
Define variable inputs with default values, overridable at runtime.

```yaml
inputs:
  target:
    default: "staging"

jobs:
  deploy:
    steps:
      - run: ./deploy.sh ${{ inputs.target }}
```

### 3. CLI Ansi Helper
Flux provides a helper to emit ANSI escape codes for formatting CLI output.

```php
use Entreya\Flux\Output\Ansi;

// Colored text
echo Ansi::green('Success!');
echo Ansi::bold(Ansi::red('Error!'));

// Clickable terminal links
echo Ansi::link('https://entreya.com', 'Entreya Website');
```

## Security

### Command Allowlist

```php
Flux::fromYaml('deploy.yaml')
    ->withConfig([
        'security' => [
            'allowed_commands' => ['composer', 'npm', 'php', 'git'],
        ],
    ])
    ->stream();
```

**Note:** Multi-line `run: |` blocks bypass the allowlist by design — they are executed as bash scripts and can legitimately contain pipes, conditionals, etc.

### Authentication

```php
Flux::fromYaml('deploy.yaml')
    ->withAuth(fn() => isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin')
    ->stream();
```

---

## Configuration Reference

```php
Flux::fromYaml('workflow.yaml')
    ->withConfig([
        'timeout'  => 300,              // Max seconds per step
        'security' => [
            'allowed_commands' => [],   // Empty = allow all single-line commands
            'blocked_patterns' => [],   // Regex patterns always checked
        ],
    ])
    ->withAuth(callable $check)         // Return true = authenticated
    ->stream();
```

---

## 🎨 Widget System (Component-wise Rendering)

Flux provides independent PHP widgets inspired by Kartik widgets and Yii2 GridView.
Each component renders its own HTML and auto-registers its JS selectors with `FluxAsset`.

**Key design principles:**
- Each widget is independently renderable — use any combination
- Every sub-element has its own `*Options` array for HTML attribute control
- Layout templates control which sections render and in what order
- Named render methods can be overridden in subclasses
- `beforeContent` / `afterContent` hooks inject arbitrary HTML
- Step renderers are swappable (like GridView columns)

### Minimal Example

```php
<?php
use Entreya\Flux\Ui\{FluxAsset, FluxBadge, FluxToolbar, FluxLogPanel, FluxProgress};
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <?= FluxAsset::css() ?>
</head>
<body>
  <div class="d-flex flex-column vh-100">
    <?= FluxBadge::widget() ?>
    <?= FluxToolbar::widget() ?>
    <?= FluxProgress::widget() ?>
    <?= FluxLogPanel::widget() ?>
  </div>

  <?= FluxAsset::js() ?>
  <?= FluxAsset::init(['sseUrl' => 'sse.php?workflow=deploy']) ?>
</body>
</html>
```

That's it — 5 lines of PHP render a full GitHub Actions-style viewer.

---

### Component Reference

#### FluxBadge — Status indicator

```php
// Default (Bootstrap pill badge)
<?= FluxBadge::widget() ?>

// Custom ID, text, and sub-element options
<?= FluxBadge::widget([
    'id'          => 'myBadge',
    'initialText' => 'Starting…',
    'options'     => ['class' => 'fs-6'],           // root <span> attributes
    'dotOptions'  => ['style' => 'width:10px;height:10px'],  // dot <span>
    'textOptions' => ['class' => 'fw-bold'],                 // text <span>
    'layout'      => '{text}{dot}',                          // swap dot/text order
]) ?>
```

**Selectors registered:** `badge`, `badgeText`
**Layout placeholders:** `{dot}`, `{text}`

---

#### FluxSidebar — Job list panel

```php
// Default
<?= FluxSidebar::widget() ?>

// Fully configured
<?= FluxSidebar::widget([
    'id'           => 'mySidebar',
    'workflowName' => 'deploy-v2',
    'trigger'      => 'webhook',
    'emptyText'    => 'No jobs yet…',
    'showFooter'   => true,
    'options'       => ['style' => 'width:260px'],     // root <nav>
    'listOptions'   => ['class' => 'list-group-flush'], // job list container
    'footerOptions' => ['class' => 'bg-dark'],          // footer container
    'beforeContent' => '<div class="p-2 text-center"><img src="logo.svg" width="40"></div>',
]) ?>
```

**Selectors registered:** `jobList`
**Layout placeholders:** `{jobList}`, `{footer}`

---

#### FluxToolbar — Search, timestamps, expand/collapse, rerun, theme

```php
// Default
<?= FluxToolbar::widget() ?>

// Customized — hide search, larger heading, custom button styling
<?= FluxToolbar::widget([
    'id'              => 'myToolbar',
    'headingText'     => 'Grace Marks Evaluation',
    'showSearch'      => true,
    'showTimestamps'  => false,
    'showExpand'      => true,
    'showRerun'       => false,           // hide rerun for one-shot jobs
    'showThemeToggle' => true,
    'searchPlaceholder' => 'Filter logs…',
    'layout'          => '{heading}{controls}',  // default
    'options'           => ['class' => 'sticky-top'],
    'headingOptions'    => ['class' => 'fs-5 fw-bold'],
    'searchOptions'     => ['class' => 'form-control-lg'],
    'rerunBtnOptions'   => ['class' => 'btn btn-success btn-sm'],
    'themeBtnOptions'   => [],
    'afterSearch'       => '<span class="badge text-bg-info ms-1">beta</span>',
]) ?>
```

**Selectors registered:** `search`, `rerunBtn`, `themeIcon`, `tsBtn`, `jobHeading`
**Layout placeholders:** `{heading}`, `{controls}`
**Overridable render methods:** `renderHeading()`, `renderControls()`, `renderSearch()`, `renderTsBtn()`, `renderExpandBtn()`, `renderCollapseBtn()`, `renderRerunBtn()`, `renderThemeBtn()`

---

#### FluxLogPanel — Step accordions + log lines

```php
// Default (uses <details>/<summary>)
<?= FluxLogPanel::widget() ?>

// Bootstrap 5 accordion renderer
use Entreya\Flux\Ui\Renderer\AccordionStepRenderer;

<?= FluxLogPanel::widget([
    'id'           => 'graceLogs',
    'stepRenderer' => AccordionStepRenderer::class,
    'beforeSteps'  => '<div class="alert alert-info m-2">Processing student records…</div>',
    'afterSteps'   => '<div class="text-center p-3 text-muted">End of output</div>',
    'options'      => ['class' => 'bg-body'],
    'pluginOptions' => [
        'autoCollapse' => false,    // keep all steps open
        'autoScroll'   => true,     // scroll to new log lines
    ],
    'pluginEvents' => [
        'workflow_complete' => 'function() { location.reload(); }',
        'step_failure'      => 'function(d) { alert("Step " + d.step + " failed!"); }',
    ],
]) ?>
```

**Selectors registered:** `steps`
**Layout placeholders:** `{beforeSteps}`, `{steps}`, `{afterSteps}`
**Step renderers:**

| Renderer | HTML output | JS dependency |
|---|---|---|
| `DetailsStepRenderer` (default) | `<details>/<summary>` | None |
| `AccordionStepRenderer` | Bootstrap accordion | Bootstrap JS |
| Custom (implement `StepRendererInterface`) | Your markup | Your choice |

**Creating a custom step renderer:**

```php
use Entreya\Flux\Ui\Renderer\StepRendererInterface;

class CardStepRenderer implements StepRendererInterface {
    public function jsTemplate(): string {
        return '<div class="card mb-2" id="{id}" data-status="pending">'
             . '  <div class="card-header">{phase}<strong>{name}</strong></div>'
             . '  <div class="card-body p-0 flux-log-body" id="{logs_id}"></div>'
             . '</div>';
    }
    public function collapseMethod(): string { return 'details'; }
}

// Use it:
FluxLogPanel::widget(['stepRenderer' => CardStepRenderer::class]);
```

---

#### FluxProgress — Workflow progress bar

```php
// Default (thin primary bar)
<?= FluxProgress::widget() ?>

// Thick green bar
<?= FluxProgress::widget([
    'id'         => 'myProgress',
    'height'     => '6px',
    'barClass'   => 'bg-success',
    'options'    => ['class' => 'rounded-0'],     // outer container
    'barOptions' => ['class' => 'progress-bar-striped progress-bar-animated'],
]) ?>
```

**Selectors registered:** `progress`
**Layout placeholders:** `{bar}`

---

#### FluxAsset — Configuration accumulator

```php
// 1. Set base path (if Flux assets are in a non-default location)
FluxAsset::setAssetPath('/vendor/entreya/flux/public/assets');

// 2. Render CSS/JS tags
<?= FluxAsset::css() ?>   <!-- in <head> -->
<?= FluxAsset::js() ?>    <!-- before init -->

// 3. Render init script (MUST be after all widget calls)
<?= FluxAsset::init([
    'sseUrl'    => '/sse.php?workflow=deploy',
    'uploadUrl' => '/upload.php',
]) ?>

// 4. Reset for multi-render (e.g. in tests or multiple viewers on one page)
FluxAsset::reset();
```

---

### Subclassing Widgets

Every widget is designed for inheritance. Override any named render method:

```php
class GraceMarksToolbar extends FluxToolbar {
    // Replace the rerun button with a "Download Report" button
    protected function renderRerunBtn(): string {
        return '<a href="/report.php" class="btn btn-outline-primary btn-sm">'
             . '<i class="bi bi-download"></i> Report'
             . '</a>';
    }
    
    // Add a student count badge after the heading
    protected function renderHeading(): string {
        return parent::renderHeading()
             . '<span class="badge text-bg-info ms-2" id="student-count">0 students</span>';
    }
}

// Usage — all other FluxToolbar options still work:
<?= GraceMarksToolbar::widget(['showSearch' => false]) ?>
```

---

## 🔌 Channel System

Channels transport events from worker to browser. All implement `ChannelInterface`.

| Channel | Use case | Latency | Multi-server |
|---|---|---|---|
| `SseChannel` | Inline streaming (same request) | 0ms | N/A |
| `FileChannel` | Background job, single server | ~200ms | ❌ |
| `DatabaseChannel` | Background job, per-university DB | ~300ms | ✅ |
| `RedisChannel` | Background job, real-time | ~1ms | ✅ |

### FileChannel (default)

```php
// Worker
Flux::workflow('Import')->job('run')->step('Go', 'php import.php')->writeToFile('/tmp/flux/abc.log');

// SSE endpoint
Flux::tail('/tmp/flux/abc.log')->stream();
```

### DatabaseChannel (recommended for multi-DB / university deployments)

```php
use Entreya\Flux\Channel\DatabaseChannel;

// One-time migration (idempotent, safe to call every time)
DatabaseChannel::migrate($pdo);

// Worker (queue job / console command)
$ch = new DatabaseChannel($pdo, $jobId, 'write');
$ch->open();
foreach ($executor->execute($workflow, $jobs) as $event) {
    $ch->write($event);
}
$ch->complete();  // or $ch->fail() on error

// SSE endpoint (any server connected to the same university DB)
$ch = new DatabaseChannel($pdo, $jobId, 'tail');
$ch->stream();  // replays history, then polls for new events

// Cleanup old jobs (call from a cron)
DatabaseChannel::cleanup($pdo, maxAgeSeconds: 86400);  // purge jobs > 24h old
```

### RedisChannel (for real-time multi-server)

```php
use Entreya\Flux\Channel\RedisChannel;

$redis = new \Redis();
$redis->connect('127.0.0.1');

// Worker
$ch = new RedisChannel($redis, $jobId, 'write');
$ch->open();
foreach ($executor->execute($workflow, $jobs) as $event) {
    $ch->write($event);
}
$ch->complete();  // or $ch->fail()

// SSE endpoint (any server with Redis access)
$ch = new RedisChannel($redis, $jobId, 'tail');
$ch->stream();  // XRANGE replay + XREAD BLOCK live tail
```

---

## SSE Event Reference

| Event              | Data fields                              |
|--------------------|------------------------------------------|
| `workflow_start`   | `name`, `job_count`, `job_ids`           |
| `job_start`        | `id`, `name`, `step_count`               |
| `step_start`       | `job`, `step`, `name`                    |
| `log`              | `job`, `step`, `type`, `content`         |
| `step_success`     | `job`, `step`, `duration`                |
| `step_failure`     | `job`, `step`, `message`                 |
| `job_success`      | `id`                                     |
| `job_failure`      | `id`                                     |
| `workflow_complete`| _(empty)_                                |
| `workflow_failed`  | `message`                                |
| `stream_close`     | _(empty)_                                |

### Raw JS Integration (without widgets)

```javascript
const es = new EventSource('/sse.php?workflow=deploy');

es.addEventListener('workflow_start', e => console.log('Started', JSON.parse(e.data)));
es.addEventListener('log', e => {
    const { type, content } = JSON.parse(e.data);
    appendToConsole(content);  // content has ANSI → HTML already applied
});
es.addEventListener('workflow_complete', () => es.close());
```

---

## Framework Integration

- [**Yii2 Integration Recipe**](docs/YII2_INTEGRATION.md) — Complete guide for Controller, View, and Background implementation.
- **Laravel / Symfony**: Similar principles apply — use SSE headers and disable output buffering.

## License

MIT — [entreya.com](https://entreya.com)

