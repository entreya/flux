# Flux API Reference

> Real-time workflow streaming for PHP. Run YAML-defined pipelines and stream live console output to the browser — GitHub Actions style.

**Requires:** PHP 8.0+ &nbsp;|&nbsp; **Optional:** `symfony/yaml`, `ext-redis`

---

## Table of Contents

1. [Core API](#1-core-api)
2. [Pipeline (Fluent API)](#2-pipeline-fluent-api)
3. [Pipeline (YAML)](#3-pipeline-yaml)
4. [Channels](#4-channels)
5. [Executor](#5-executor)
6. [Security](#6-security)
7. [Output & ANSI](#7-output--ansi)
8. [Parser](#8-parser)
9. [UI Components](#9-ui-components)
10. [FluxRenderer](#10-fluxrenderer)
11. [JavaScript API](#11-javascript-api)
12. [Yii2 Integration](#12-yii2-integration)
13. [Custom Components](#13-custom-components)

---

## 1. Core API

**File:** `src/Flux.php`

The `Flux` class is the primary entry point. All three methods return objects that expose `.stream()` for SSE output.

```php
use Entreya\Flux\Flux;
```

### `Flux::workflow(string $name = 'Workflow'): Pipeline`

Start building a workflow with the fluent PHP API. No YAML dependency required.

```php
Flux::workflow('Deploy')
    ->job('build', 'Build')
        ->step('Install', 'composer install')
        ->step('Test', 'vendor/bin/phpunit')
    ->job('deploy', 'Deploy')
        ->needs('build')
        ->step('Sync', 'rsync -avz dist/ prod:/var/www/')
    ->stream();
```

### `Flux::fromYaml(string $path, array $config = []): Pipeline`

Load a YAML workflow file. Requires `symfony/yaml` or `ext-yaml`.

```php
Flux::fromYaml(__DIR__ . '/deploy.yaml', [
    'timeout' => 300,
    'security' => [
        'allowlist' => ['composer', 'npm', 'php'],
    ],
])->stream();
```

### `Flux::tail(string $logPath): FileChannel`

Reconnect a browser to an already-running (or completed) background job by tailing its log file. Used in your SSE endpoint.

```php
// In your SSE endpoint:
Flux::tail('/tmp/flux-jobs/' . $jobId . '.log')->stream();
```

> **Hidden API:** `Flux::tail()` returns a `FileChannel` in `MODE_TAIL`. You can call `->stream()` on it directly — it sets SSE headers, replays history, then tails live appends using `clearstatcache()` + `fread()` in a tight loop with `usleep()`.

---

## 2. Pipeline (Fluent API)

**Files:** `src/Pipeline/Pipeline.php`, `src/Pipeline/Job.php`, `src/Pipeline/Step.php`

### Building a Pipeline

```php
$pipeline = Flux::workflow('CI/CD');
```

### Job Methods

| Method | Signature | Description |
|--------|-----------|-------------|
| `job()` | `->job(string $id, string $name = '')` | Begin a new job. Subsequent `step()` calls attach here. |
| `needs()` | `->needs(string\|array $jobs)` | Dependency — this job waits until listed jobs succeed. |
| `env()` | `->env(array $env)` | Set environment variables for the current job. |
| `continueOnError()` | `->continueOnError(bool $value = true)` | Don't fail the workflow if this job fails. |

### Step Methods

| Method | Signature | Description |
|--------|-----------|-------------|
| `step()` | `->step(string $name, string $command, array $env = [])` | Add a main-phase step. |
| `preStep()` | `->preStep(string $name, string $command, array $env = [])` | **Hidden API.** Runs *before* main steps. If it fails, main steps are skipped (post still runs). |
| `postStep()` | `->postStep(string $name, string $command, array $env = [])` | **Hidden API.** Runs *after* main steps — **always**, even if the job failed. Use for cleanup, notifications, lock release. |

### Execution

| Method | Signature | Description |
|--------|-----------|-------------|
| `stream()` | `->stream()` | Execute and stream via SSE (inline mode). |
| `streamTo()` | `->streamTo(ChannelInterface $channel)` | **Hidden API.** Execute and push events to any channel (Redis, Database, custom). |
| `writeToFile()` | `->writeToFile(string $path)` | **Hidden API.** Execute and write events to a log file (background mode). |

### Configuration

```php
$pipeline->withConfig([
    'timeout'  => 300,            // Per-step timeout in seconds (default: 120)
    'security' => [
        'allowlist' => ['php', 'composer', 'npm'],
        'blocklist' => ['rm -rf', 'DROP TABLE'],
        'maxRate'   => 10,        // Max workflow executions per minute
    ],
]);
```

### Authentication

```php
$pipeline->withAuth(function (): bool {
    return Yii::$app->user->can('runWorkflows');
});
```

> **Hidden API:** `withAuth()` accepts any `callable` that returns `bool`. If it returns `false`, the workflow emits a `workflow_error` SSE event and aborts immediately — no jobs execute.

### Matrix Strategy

```yaml
jobs:
  test:
    strategy:
      matrix:
        php: [8.0, 8.1, 8.2]
        os: [ubuntu, macos]
    steps:
      - name: Test on PHP ${{ matrix.php }}
        run: php-${{ matrix.php }} vendor/bin/phpunit
```

> **Hidden API:** Matrix expansion happens in `Pipeline::expandMatrix()`. It generates the Cartesian product of all matrix dimensions and creates one job per combination, with variables available via `${{ matrix.key }}` interpolation.

---

## 3. Pipeline (YAML)

**File:** `src/Parser/YamlParser.php`

### YAML Structure

```yaml
name: My Workflow

jobs:
  build:
    name: Build Application
    steps:
      - name: Install
        run: composer install
      - name: Test
        run: vendor/bin/phpunit

  deploy:
    name: Deploy
    needs: build
    if: success()
    continue-on-error: true
    env:
      APP_ENV: production
    steps:
      - name: Upload
        run: rsync -avz dist/ prod:/var/www/
    pre:
      - name: Validate
        run: php artisan config:check
    post:
      - name: Notify
        run: curl -X POST https://slack.com/webhook
```

### Conditional Execution

| Expression | Description |
|------------|-------------|
| `success()` | True if all dependency jobs succeeded |
| `failure()` | True if any dependency failed |
| `always()` | Always runs |

> **Hidden API:** The `if:` field is evaluated by `ExpressionEvaluator`, which uses `symfony/expression-language` when available. Without it, a built-in evaluator handles `success()`, `failure()`, and `always()`. You can also use arbitrary expressions like `env.APP_ENV == "production"`.

### Variable Interpolation

```yaml
steps:
  - name: Deploy ${{ env.APP_ENV }}
    run: echo "Deploying to ${{ env.TARGET_HOST }}"
```

> **Hidden API:** `VariableInterpolator` resolves `${{ }}` tokens against a context object containing `env`, `matrix`, `job`, and `steps` data. It supports nested access via dot notation: `${{ steps.build.outcome }}`.

---

## 4. Channels

**File:** `src/Channel/ChannelInterface.php`

All channels implement:

```php
interface ChannelInterface
{
    public function open(): void;
    public function write(array $event): void;
    public function complete(): void;
}
```

### SseChannel (Default)

**File:** `src/Channel/SseChannel.php`

Streams events directly to the browser via Server-Sent Events. This is the default channel used by `Pipeline::stream()`.

```php
// Used automatically — you rarely call this directly
$channel = new SseChannel();
$channel->open();   // Sets Content-Type: text/event-stream, disables buffering
$channel->write(['type' => 'step_start', 'data' => [...]]);
$channel->complete();
```

> **Hidden APIs:**
> - `error(string $message)` — Emits a `stream_error` SSE event and closes the connection.
> - Constructor accepts optional `bool $convertAnsi = true` — when true, ANSI escape sequences in log output are converted to styled HTML via `AnsiConverter`.
> - Automatically disables gzip (`Content-Encoding: none`) and nginx buffering (`X-Accel-Buffering: no`).
> - Uses `JSON_THROW_ON_ERROR` for all JSON encoding.

### FileChannel

**File:** `src/Channel/FileChannel.php`

Writes events to a file (background mode) or tails a file for SSE replay.

```php
// WRITE mode (in a background worker):
$channel = new FileChannel('/tmp/flux-jobs/abc123.log', mode: FileChannel::MODE_WRITE);

// TAIL mode (in your SSE endpoint):
$channel = new FileChannel('/tmp/flux-jobs/abc123.log', mode: FileChannel::MODE_TAIL);
$channel->stream();  // Replays history, then live-tails
```

> **Hidden APIs:**
> - `stream()` — Only available in `MODE_TAIL`. Replays all existing lines, then enters a `usleep()` loop watching for new content via `clearstatcache()` + `fstat()`.
> - Completion sentinel: `complete()` writes `---FLUX-SENTINEL-COMPLETE---` as the last line. `stream()` detects this and emits `stream_close`.
> - Idle timeout: If no new content appears for 30 seconds, `stream()` emits a connection-lost annotation and closes.
> - ANSI conversion: Like `SseChannel`, log output is optionally converted from ANSI to HTML.

### RedisChannel

**File:** `src/Channel/RedisChannel.php`

Redis Streams (XADD/XREAD BLOCK) for multi-server deployments. Zero polling — uses Redis blocking reads.

```php
// WRITE mode (background worker):
$ch = new RedisChannel($redis, 'job:abc123', mode: 'write');

// TAIL mode (SSE endpoint):
$ch = new RedisChannel($redis, 'job:abc123', mode: 'tail');
$ch->stream();
```

**Redis key layout:**

| Key | Purpose |
|-----|---------|
| `flux:events:{jobId}` | Stream of job events (XADD/XRANGE) |
| `flux:alive:{jobId}` | TTL key — worker heartbeat (refreshed every 5s) |
| `flux:status:{jobId}` | `running` → `complete` / `failed` |

> **Hidden APIs:**
> - `fail()` — Marks the job as failed (alternative to `complete()`). Sets status key and adds a sentinel event.
> - `isWorkerAlive()` — Checks the heartbeat TTL key. If expired, the worker crashed.
> - Worker heartbeat: `open()` sets a TTL key that `write()` refreshes. If the worker crashes, `stream()` detects the missing heartbeat and emits a dead-worker error.
> - History replay: `stream()` first does `XRANGE` (instant replay of all past events), then `XREAD BLOCK` (live tail with zero polling).
> - Auto-expire: Stream keys expire after 24h to prevent unbounded growth.

### DatabaseChannel

**File:** `src/Channel/DatabaseChannel.php`

MySQL/Postgres/SQLite channel via PDO. Uses polling (300ms intervals).

```php
// Auto-create tables:
DatabaseChannel::migrate($pdo);

// WRITE mode:
$ch = new DatabaseChannel($pdo, 'abc123', mode: 'write');

// TAIL mode:
$ch = new DatabaseChannel($pdo, 'abc123', mode: 'tail');
$ch->stream();
```

**Schema (auto-migrated):**

```sql
CREATE TABLE flux_jobs (
    job_id VARCHAR(64) PRIMARY KEY,
    status ENUM('running','complete','failed'),
    heartbeat TIMESTAMP,
    created_at TIMESTAMP
);

CREATE TABLE flux_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    job_id VARCHAR(64) NOT NULL,
    seq INT UNSIGNED NOT NULL,
    type VARCHAR(32) NOT NULL,
    data TEXT NOT NULL,
    INDEX idx_job_seq (job_id, seq)
);
```

> **Hidden APIs:**
> - `migrate(PDO $pdo)` — Static method. Creates both tables idempotently (`IF NOT EXISTS`). Safe to call on every request.
> - `cleanup(PDO $pdo, int $maxAgeSeconds = 86400)` — **Static method.** Purges completed/failed jobs older than the given age. Run via cron.
> - `fail()` — Marks the job as failed.
> - `updateHeartbeat()` — Called internally by `write()`. Updates the `heartbeat` column timestamp.
> - `jobExists()` / `getJobInfo()` — Check job status before streaming.

---

## 5. Executor

### WorkflowExecutor

**File:** `src/Executor/WorkflowExecutor.php`

Orchestrates the entire workflow execution lifecycle: iterates jobs in dependency order, executes phases (pre → main → post), handles conditional execution, and emits SSE events.

> **Hidden APIs:**
> - Three-phase execution: Each job runs `pre` steps first, then `main`, then `post`. If a `pre` step fails, all `main` steps are skipped but `post` steps still execute.
> - Dependency resolution: Jobs with `needs` are skipped if any dependency failed (unless `continue-on-error` is set).
> - `hrtime(true)` timestamps: Event durations are measured with nanosecond precision, not `time()`.
> - Environment: Sets `FORCE_COLOR=1`, `PHP_BINARY`, and `PATH` automatically for child processes.

### CommandRunner

**File:** `src/Executor/CommandRunner.php`

Executes individual shell commands with real-time output capture.

> **Hidden APIs:**
> - Multi-line scripts are wrapped in `bash -c '...'` and executed as a single process.
> - Uses `stream_select()` with 200ms timeout for non-blocking stdout/stderr reading.
> - Read buffer is 64KB for high throughput.
> - Returns `exit_code`, `stdout`, `stderr`, and `duration` in the result.
> - Step timeout: Kills the process via `proc_terminate()` if the configured timeout is exceeded.

---

## 6. Security

### CommandValidator

**File:** `src/Security/CommandValidator.php`

Validates commands against allowlists and blocklists before execution.

```php
$validator = new CommandValidator(
    allowlist: ['php', 'composer', 'npm', 'node'],
    blocklist: ['rm -rf /', 'DROP TABLE', '> /dev/sda'],
);

$validator->validate('composer install');    // passes
$validator->validate('rm -rf /');            // throws SecurityException
```

> **Hidden APIs:**
> - Single-line commands are checked against the allowlist (first word must be in the list).
> - Multi-line scripts bypass the allowlist check (treated as bash scripts) but are still checked against the blocklist.
> - Blocklist patterns are regex-based — you can use patterns like `/(rm|del).*-rf/i`.

### RateLimiter

**File:** `src/Security/RateLimiter.php`

Prevents workflow execution abuse.

```php
$limiter = new RateLimiter(maxPerMinute: 10);
$limiter->check('user:42');  // throws SecurityException if rate exceeded
```

> **Hidden APIs:**
> - **APCu-first:** Uses `apcu_inc()` with TTL for atomic increment when `ext-apcu` is available.
> - **File fallback:** Falls back to file-based locking (`flock()`) when APCu is unavailable. Rate data is stored as JSON in `/tmp/flux-rate-{key}.json`.
> - `JSON_THROW_ON_ERROR` for all file I/O.

### AuthManager

**File:** `src/Security/AuthManager.php`

Callback-based authentication gate.

```php
$auth = new AuthManager(function (): bool {
    return isset($_SESSION['user_id']);
});

$auth->check();            // throws SecurityException if not authenticated
$auth->isAuthenticated();  // returns bool (no exception)
```

---

## 7. Output & ANSI

### Ansi

**File:** `src/Output/Ansi.php`

Generates ANSI escape sequences for colored console output.

```php
use Entreya\Flux\Output\Ansi;

echo Ansi::green('✓ Tests passed');
echo Ansi::red('✕ Build failed');
echo Ansi::bold(Ansi::cyan('Running...'));
echo Ansi::link('https://example.com', 'Click here');  // OSC 8 hyperlink
echo Ansi::dim('debug info');
```

| Method | Description |
|--------|-------------|
| `red()`, `green()`, `yellow()`, `blue()`, `magenta()`, `cyan()`, `white()`, `gray()` | 8 standard colors |
| `bold()`, `dim()`, `italic()`, `underline()` | Text styles |
| `bg(string $text, int $r, int $g, int $b)` | True-color background |
| `fg(string $text, int $r, int $g, int $b)` | True-color foreground |
| `color256(string $text, int $code)` | 256-color palette |
| `link(string $url, string $text)` | OSC 8 clickable hyperlink |
| `strip(string $text)` | Remove all ANSI sequences |
| `pad(string $text, int $width)` | Pad to width (ANSI-aware) |
| `isSupported()` | Detect TTY + color support |

> **Hidden APIs:**
> - Respects `NO_COLOR` env var — all methods return unformatted text when set.
> - `isSupported()` checks `TERM`, `COLORTERM`, and `stream_isatty()`.
> - `strip()` removes all escape sequences including OSC 8 links, 256-color, and true-color codes.

### AnsiConverter

**File:** `src/Output/AnsiConverter.php`

Converts ANSI-colored terminal output to styled HTML `<span>` tags. Used internally by all channels to render log output in the browser.

```php
use Entreya\Flux\Output\AnsiConverter;

$html = AnsiConverter::toHtml("\e[32m✓ Pass\e[0m");
// → <span style="color:#00c800">✓ Pass</span>
```

> **Hidden APIs:**
> - **256-color support:** Codes 0-7 (standard), 8-15 (bright), 16-231 (6×6×6 cube), 232-255 (grayscale).
> - **True-color support:** `\e[38;2;R;G;Bm` (foreground) and `\e[48;2;R;G;Bm` (background).
> - **OSC 8 hyperlinks:** `\e]8;;URL\e\\text\e]8;;\e\\` → `<a href="URL" target="_blank">text</a>`.
> - **Carriage return handling:** `\r` overwrites the current line (simulates terminal behavior).
> - **Style stacking:** Nested styles produce proper nested `<span>` tags.
> - **XSS-safe:** All text content is `htmlspecialchars`-escaped before wrapping in spans.

---

## 8. Parser

**File:** `src/Parser/YamlParser.php`

```php
use Entreya\Flux\Parser\YamlParser;

$parser = new YamlParser();
$data = $parser->parseFile('/path/to/workflow.yaml');  // Returns array
$data = $parser->parse($yamlString);                    // Parse a string
```

> **Hidden API:** Resolution order:
> 1. `symfony/yaml` (`Symfony\Component\Yaml\Yaml::parseFile()`) — preferred, more robust
> 2. `ext_yaml` (`yaml_parse_file()`) — C extension fallback
> 3. Throws `ParseException` if neither is available

---

## 9. UI Components

All components extend `FluxComponent` and follow the same lifecycle:

```
defaults() → template() → slots() → style() → script() → registerSelectors()
```

### Render API

```php
Component::render([
    'props'   => [...],           // Override default prop values
    'slots'   => [...],           // Override child components
    'content' => string|Closure,  // Replace template entirely
    'style'   => string,          // Add/replace CSS
    'script'  => string,          // Add/replace JS
]);
```

### Slot Override Types

| Value | Behavior |
|-------|----------|
| `string` | Raw HTML replaces the slot |
| `array` | Config passed to default component: `['props' => [...], 'content' => ...]` |
| `Closure` | Called with the **child component's** resolved props, returns HTML |
| `false` | Not rendered — no HTML, no CSS, no JS |
| `Foo::class` | Renders a different component class |

> **Hidden API:** When a slot is overridden with a `Closure`, the engine instantiates the default child component, resolves its props (defaults + `childConfig`), passes those to the closure, and **still calls `registerSelectors()`** on the child. This means `$props['id']` in the closure gives you the child's ID, and JS selectors remain functional.

### Prop Interpolation

- `{prop}` tokens in `template()` are replaced with `htmlspecialchars`-escaped prop values.
- Props listed in `rawProps()` skip escaping (for HTML-containing props).
- `{slot:name}` tokens are replaced with rendered child HTML.
- In `script()`, `{prop}` tokens are interpolated **without** HTML escaping.

> **Hidden API:** `content` overrides (both string and closure) **still process `{slot:name}` tokens**. The engine calls `buildContent()` which replaces slot tokens after the content is generated. This means you can use `{slot:heading}` inside a content closure and it will be replaced with the rendered heading HTML.

---

### Component Reference

#### Toolbar

**Class:** `Entreya\Flux\Ui\Toolbar\Toolbar`

| Prop | Default | Description |
|------|---------|-------------|
| `id` | `fx-toolbar` | Root element ID |
| `class` | `d-flex align-items-center gap-2 px-3 py-2 border-bottom bg-body-tertiary` | CSS classes |

| Slot | Component | JS Selector | Props |
|------|-----------|-------------|-------|
| `heading` | `Heading` | `jobHeading` | `id`, `class`, `icon`, `text` |
| `search` | `SearchInput` | `search` | `id`, `class`, `icon`, `placeholder` |
| `btnTimestamps` | `TimestampButton` | `tsBtn` | `id`, `class`, `icon`, `title` |
| `btnExpand` | `ExpandButton` | `expandBtn` | `id`, `class`, `icon`, `title` |
| `btnCollapse` | `CollapseButton` | `collapseBtn` | `id`, `class`, `icon`, `title` |
| `btnRerun` | `RerunButton` | `rerunBtn` | `id`, `class`, `icon`, `title`, `text` |
| `btnTheme` | `ThemeButton` | `themeBtn` | `id`, `class`, `icon`, `title` |

> **Hidden API — `icon` prop:** Every toolbar component has a configurable `icon` prop (default: Bootstrap Icons class). Override to use any icon library:
> ```php
> 'btnTimestamps' => ['props' => ['icon' => 'mdi mdi-clock-outline']]
> ```

#### Badge

**Class:** `Entreya\Flux\Ui\Badge`

| Prop | Default |
|------|---------|
| `id` | `fx-badge` |
| `class` | `badge rounded-pill text-bg-secondary d-inline-flex align-items-center gap-1 px-2 py-1` |
| `initialText` | `Connecting` |

| Slot | Component | JS Selector |
|------|-----------|-------------|
| `dot` | `Badge\Dot` | — |
| `text` | `Badge\Text` | `badge`, `badgeText` |

#### Sidebar

**Class:** `Entreya\Flux\Ui\Sidebar\Sidebar`

| Prop | Default |
|------|---------|
| `id` | `fx-sidebar` |
| `class` | `d-flex flex-column border-end bg-body-tertiary` |
| `workflowName` | `''` |
| `trigger` | `manual` |

| Slot | Component | JS Selector |
|------|-----------|-------------|
| `jobList` | `Sidebar\JobList` | `jobList` |
| `footer` | `Sidebar\Footer` | `sidebarFooter` |

> **Hidden API — `childConfig()`:** Sidebar's `childConfig('footer')` automatically passes `workflowName` and `trigger` props down to the Footer child.

#### JobList

**Class:** `Entreya\Flux\Ui\Sidebar\JobList`

| Prop | Default | Description |
|------|---------|-------------|
| `id` | `fx-sidebar-job-list` | Container element for JS to populate |
| `class` | `list-group list-group-flush overflow-auto` | CSS classes |
| `jobItemTemplate` | `''` | **Hidden API.** Custom HTML template for dynamically-created job items |

**Template placeholders** (used by `flux.js` at runtime):

| Token | Value |
|-------|-------|
| `{id}` | Unique element ID |
| `{job}` | Job identifier |
| `{name}` | Job display name |
| `{status}` | `pending\|running\|success\|failure\|skipped` |
| `{icon_id}` | ID for status icon element |
| `{icon_char}` | Status character (✓, ✕, ↻, –) |
| `{meta_id}` | ID for metadata/duration element |

```php
'jobList' => ['props' => [
    'class' => 'list-group',
    'jobItemTemplate' =>
        '<li class="list-group-item d-flex justify-content-between align-items-center"'
        . ' id="{id}" data-flux-job="{job}" data-status="{status}" style="cursor:pointer">'
        . '{name}'
        . '<span class="badge badge-primary badge-pill" id="{meta_id}">…</span>'
        . '</li>',
]]
```

#### Footer

**Class:** `Entreya\Flux\Ui\Sidebar\Footer`

| Prop | Default |
|------|---------|
| `id` | `fx-sidebar-footer` |
| `workflowName` | `Unknown` |
| `trigger` | `manual` |
| `runner` | `Local` |

#### LogPanel

**Class:** `Entreya\Flux\Ui\Log\LogPanel`

| Prop | Default |
|------|---------|
| `id` | `fx-log-panel` |
| `class` | `flex-grow-1 overflow-auto` |

| Slot | Component | JS Selector |
|------|-----------|-------------|
| `beforeSteps` | `EmptySlot` | — |
| `stepsContainer` | `StepsContainer` | `steps` |
| `afterSteps` | `EmptySlot` | — |

> **Hidden API:** `beforeSteps` and `afterSteps` are `EmptySlot` by default (render nothing). Override them to inject alerts, banners, or custom footers:
> ```php
> LogPanel::render([
>     'slots' => [
>         'beforeSteps' => '<div class="alert alert-info m-2">Processing…</div>',
>         'afterSteps' => fn() => CustomFooter::render(),
>     ],
> ])
> ```

#### Progress

**Class:** `Entreya\Flux\Ui\Progress`

| Prop | Default |
|------|---------|
| `id` | `fx-progress` |
| `class` | `progress` |
| `height` | `2px` |
| `barClass` | `progress-bar bg-primary` |

| Slot | Component | JS Selector |
|------|-----------|-------------|
| `bar` | `Progress\Bar` | `progress` |

---

## 10. FluxRenderer

**File:** `src/Ui/FluxRenderer.php`

Static asset collector. Accumulates CSS, JS, selectors, templates, and events from all rendered components. Outputs a single `<style>` + `<script>` block.

### Registration Methods

| Method | Description |
|--------|-------------|
| `registerStyle(string $key, string $css)` | Register CSS (deduplicated by key — component FQCN) |
| `registerScript(string $key, string $js)` | Register JS (per-instance, not deduplicated) |
| `registerSelector(string $key, string $elementId)` | Map a logical name to a DOM ID for `flux.js` |
| `registerTemplate(string $name, string $html)` | Register a JS template (`step`, `jobHeader`, `jobItem`) |
| `registerPluginOptions(string $ns, array $opts)` | Pass plugin config to `flux.js` (`logPanel.collapseMethod`, etc.) |
| `registerEvent(string $event, string $handler)` | Register a JS event hook (raw JS function) |
| `setAssetPath(string $path)` | Base URL for `flux.css` / `flux.js` |

### Output Methods

| Method | Description |
|--------|-------------|
| `css()` | `<link>` tag for `flux.css` |
| `js()` | `<script>` tag for `flux.js` |
| `styles()` | `<style>` block with all collected component CSS |
| `init(array $config = [])` | `<script>` block — component JS + `FluxUI.init({...})` |
| `flush(array $config = [])` | `styles()` + `js()` + `init()` in one call |
| `reset()` | Clear all state (for tests or multi-render pages) |

> **Hidden APIs:**
> - **CSS deduplication:** Styles are keyed by component FQCN. Multiple instances of the same component class only register CSS once.
> - **JS per-instance:** Scripts are keyed by `ClassName:elementId`. Each instance gets its own interpolated copy.
> - **Un-rendered = invisible:** If a component is never rendered, its CSS/JS/selectors are never registered. Only what you use gets output.
> - **Style override keying:** When using `'style' => '...'` in config, the key becomes `ClassName:instanceId` to avoid overwriting the class-level style.

### Getters (for testing / framework integration)

```php
FluxRenderer::getStyles();         // array<string, string>
FluxRenderer::getScripts();        // array<string, string>
FluxRenderer::getSelectors();      // array<string, string>
FluxRenderer::getTemplates();      // array<string, string>
FluxRenderer::getPluginOptions();  // array<string, mixed>
FluxRenderer::getEvents();         // array<string, string>
```

---

## 11. JavaScript API

**File:** `public/assets/js/flux.js`

### Initialization

```javascript
FluxUI.init({
    sseUrl: '/sse.php?workflow=deploy',
    sel: { ... },           // Override element selectors
    templates: { ... },     // Override HTML templates
    plugins: {
        logPanel: {
            collapseMethod: 'details',  // 'details' or 'accordion'
            autoCollapse: true,
            autoScroll: true,
        },
    },
    events: {
        workflow_complete: (data) => { alert('Done!'); },
        job_start: (data) => { console.log('Job started:', data.id); },
    },
});
```

### Selectors

Selectors map logical names to DOM element IDs. PHP components register them automatically via `registerSelector()`. The JS fallback defaults are:

| Key | Default ID | Description |
|-----|------------|-------------|
| `badge` | `fx-badge` | Status badge |
| `badgeText` | `fx-badge-text` | Badge text label |
| `jobList` | `fx-job-list` | Sidebar job list container |
| `jobHeading` | `fx-job-heading` | Active job heading |
| `steps` | `fx-steps` | Log steps container |
| `progress` | `fx-progress` | Progress bar |
| `search` | `fx-search` | Search input |
| `tsBtn` | `fx-toolbar-ts-btn` | Timestamp toggle button |
| `themeBtn` | `fx-toolbar-theme-btn` | Theme toggle button |
| `expandBtn` | `fx-toolbar-expand-btn` | Expand all button |
| `collapseBtn` | `fx-toolbar-collapse-btn` | Collapse all button |
| `rerunBtn` | `fx-toolbar-rerun-btn` | Re-run workflow button |

### Configurable Templates

Three templates are configurable from PHP via `FluxRenderer::registerTemplate()`:

| Template | PHP Key | JS Property | Placeholders |
|----------|---------|-------------|-------------|
| Step | `step` | `cfg.templates.step` | `{id}`, `{job}`, `{step}`, `{name}`, `{phase}`, `{icon_id}`, `{dur_id}`, `{logs_id}` |
| Job Header | `jobHeader` | `cfg.templates.jobHeader` | `{header_id}`, `{icon_id}`, `{prog_id}`, `{dur_id}`, `{name}`, `{job}`, `{total_steps}` |
| Job Item | `jobItem` | `cfg.templates.jobItem` | `{id}`, `{job}`, `{name}`, `{status}`, `{icon_id}`, `{icon_char}`, `{meta_id}` |

### Public Methods

| Method | Description |
|--------|-------------|
| `FluxUI.init(config)` | Initialize and connect SSE |
| `FluxUI.toggleTimestamps()` | Show/hide line timestamps |
| `FluxUI.toggleTheme()` | Toggle dark/light mode (persists to `localStorage`) |
| `FluxUI.expandAll()` | Expand all step details |
| `FluxUI.collapseAll()` | Collapse all step details |
| `FluxUI.rerun(url)` | Close current SSE and reconnect |
| `FluxUI.copyLine(btn)` | Copy a log line to clipboard |

> **Hidden API — Auto-wiring:** `init()` automatically attaches `click` event listeners to all toolbar buttons using the registered selectors. You don't need to add `onclick` attributes manually.

### SSE Event Types

| Event | Data | Description |
|-------|------|-------------|
| `workflow_start` | `{ name, jobs: [{id, name, steps, pre, post}] }` | Pipeline started |
| `job_start` | `{ id, name, pre, steps, post }` | Job began |
| `step_start` | `{ job, key, name, phase }` | Step began executing |
| `step_output` | `{ job, key, line, ts }` | One line of stdout/stderr |
| `step_success` | `{ job, key, duration }` | Step completed |
| `step_failure` | `{ job, key, duration, exit_code }` | Step failed |
| `step_skipped` | `{ job, key, reason }` | Step was skipped |
| `job_success` | `{ id, duration }` | Job completed |
| `job_failure` | `{ id, duration }` | Job failed |
| `job_skipped` | `{ id, reason }` | Job was skipped |
| `workflow_complete` | `{ duration, status }` | Pipeline finished |
| `workflow_error` | `{ error }` | Fatal error (auth, rate limit) |

---

## 12. Yii2 Integration

### Controller

```php
class FluxController extends Controller
{
    public function actionIndex(string $workflow = 'deploy')
    {
        return $this->render('index', [
            'sseUrl' => Url::to(['stream', 'workflow' => $workflow]),
        ]);
    }

    public function actionStream(string $workflow)
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        Flux::fromYaml(
            Yii::getAlias("@app/workflows/{$workflow}.yaml")
        )->stream();
        Yii::$app->end();
    }
}
```

### Background Mode

```php
// In your queue worker / console command:
Flux::fromYaml('import.yaml')
    ->writeToFile("/tmp/flux-jobs/{$jobId}.log");

// In your SSE endpoint:
Flux::tail("/tmp/flux-jobs/{$jobId}.log")->stream();
```

### View (Minimal)

```php
<?php
use Entreya\Flux\Ui\{Badge, Progress, FluxRenderer};
use Entreya\Flux\Ui\Toolbar\Toolbar;
use Entreya\Flux\Ui\Sidebar\Sidebar;
use Entreya\Flux\Ui\Log\LogPanel;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?= FluxRenderer::css() ?>
</head>
<body>
<div class="d-flex flex-column vh-100">
    <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
        <?= Badge::render(['props' => ['initialText' => 'Starting…']]) ?>
    </div>
    <?= Toolbar::render([
        'slots' => [
            'heading' => ['props' => ['text' => 'Grade Pipeline']],
        ],
    ]) ?>
    <?= Progress::render(['props' => ['height' => '3px']]) ?>
    <div class="d-flex flex-grow-1 overflow-hidden">
        <?= Sidebar::render(['props' => ['workflowName' => 'grace-marks']]) ?>
        <?= LogPanel::render() ?>
    </div>
</div>
<?= FluxRenderer::flush(['sseUrl' => $sseUrl]) ?>
</body>
</html>
```

---

## 13. Custom Components

Extend `FluxComponent` to create your own components:

```php
use Entreya\Flux\Ui\FluxComponent;
use Entreya\Flux\Ui\FluxRenderer;

class StatusAlert extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'      => 'my-alert',
            'class'   => 'alert alert-info',
            'icon'    => 'bi bi-info-circle',
            'message' => 'Ready',
        ];
    }

    protected function template(): string
    {
        return '<div id="{id}" class="{class}"><i class="{icon} me-2"></i>{message}</div>';
    }

    protected function style(): string
    {
        return '#my-alert { border-left: 4px solid #0dcaf0; cursor: pointer; }';
    }

    protected function script(): string
    {
        // {id} is interpolated with the actual prop value (NOT HTML-escaped in scripts)
        return 'document.getElementById("{id}").addEventListener("click", function() { this.remove(); });';
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('statusAlert', (string) $this->props['id']);
    }
}
```

### Lifecycle Methods

| Method | Purpose | Required |
|--------|---------|----------|
| `defaults()` | Return default props as `['key' => value]` | Yes |
| `template()` | Return HTML with `{prop}` and `{slot:name}` tokens | Yes |
| `slots()` | Return `['slotName' => ComponentClass::class]` | No |
| `style()` | Return CSS string (deduplicated by class) | No |
| `script()` | Return JS string (`{prop}` interpolated without escaping) | No |
| `rawProps()` | Return prop names that skip HTML escaping | No |
| `registerSelectors()` | Register DOM IDs with `FluxRenderer` for JS discovery | No |
| `childConfig()` | Return config for a child slot (pass parent props down) | No |

---

## Exceptions

```
FluxException (base)
├── SecurityException    — Auth failure, rate limit, command validation
├── ExecutionException   — Process timeout, command failure
└── ParseException       — YAML parse failure, missing dependency
```

---

## Project Structure

```
src/
├── Flux.php                    # Entry point
├── Pipeline/
│   ├── Pipeline.php            # Fluent builder + YAML loader
│   ├── Job.php                 # Job model (steps, deps, env)
│   └── Step.php                # Step model (command, phase, condition)
├── Channel/
│   ├── ChannelInterface.php    # Contract: open/write/complete
│   ├── SseChannel.php          # Direct SSE to browser
│   ├── FileChannel.php         # Write/tail log files
│   ├── RedisChannel.php        # Redis Streams (XADD/XREAD)
│   └── DatabaseChannel.php     # MySQL/Postgres via PDO
├── Executor/
│   ├── WorkflowExecutor.php    # Job orchestration
│   └── CommandRunner.php       # Shell command execution
├── Security/
│   ├── CommandValidator.php    # Allowlist/blocklist
│   ├── RateLimiter.php         # APCu/file rate limiting
│   └── AuthManager.php         # Callback auth gate
├── Output/
│   ├── Ansi.php                # ANSI escape sequences
│   └── AnsiConverter.php       # ANSI → HTML conversion
├── Parser/
│   └── YamlParser.php          # YAML → array
├── Utils/
│   ├── VariableInterpolator.php  # ${{ }} token resolution
│   └── ExpressionEvaluator.php   # if: condition evaluation
├── Exceptions/
│   ├── FluxException.php
│   ├── SecurityException.php
│   ├── ExecutionException.php
│   └── ParseException.php
└── Ui/
    ├── FluxComponent.php       # Abstract base (props, slots, template)
    ├── FluxRenderer.php        # Static asset collector
    ├── Badge.php               # Status badge
    ├── Badge/Dot.php
    ├── Badge/Text.php
    ├── Progress.php            # Progress bar
    ├── Progress/Bar.php
    ├── Sidebar/Sidebar.php     # Job list + footer
    ├── Sidebar/JobList.php
    ├── Sidebar/Footer.php
    ├── Toolbar/Toolbar.php     # Heading + buttons + search
    ├── Toolbar/Heading.php
    ├── Toolbar/SearchInput.php
    ├── Toolbar/TimestampButton.php
    ├── Toolbar/ThemeButton.php
    ├── Toolbar/ExpandButton.php
    ├── Toolbar/CollapseButton.php
    ├── Toolbar/RerunButton.php
    ├── Log/LogPanel.php        # Step container + log lines
    ├── Log/StepsContainer.php
    └── Log/EmptySlot.php
```
