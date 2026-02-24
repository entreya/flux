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

`Flux::pipeline()` (the fluent PHP API) has no YAML dependency at all.

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
Flux::pipeline('Deploy')
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

Flux::pipeline('Import Customer Data')
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

## Frontend Integration

The included UI (`public/index.php`) is a self-contained, dependency-free log viewer.  
You can also integrate Flux into your own frontend:

```javascript
const es = new EventSource('/sse.php?workflow=deploy');

es.addEventListener('workflow_start',   e => console.log('Started', JSON.parse(e.data)));
es.addEventListener('job_start',        e => console.log('Job', JSON.parse(e.data)));
es.addEventListener('step_start',       e => console.log('Step', JSON.parse(e.data)));
es.addEventListener('log',              e => {
    const { type, content } = JSON.parse(e.data);
    // type: 'stdout' | 'stderr' | 'cmd'
    // content: HTML string with ANSI colors converted to <span> elements
    appendToConsole(content);
});
es.addEventListener('step_success',     e => console.log('Step done', JSON.parse(e.data).duration + 's'));
es.addEventListener('workflow_complete',() => es.close());
es.addEventListener('workflow_failed',  () => es.close());
```

### SSE Event Reference

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

---

---

## Framework Integration

- [**Yii2 Integration Recipe**](docs/YII2_INTEGRATION.md) — Complete guide for Controller, View, and Background implementation.
- **Laravel / Symfony**: Similar principles apply (use SSE headers and disable buffering).

## License

MIT — [entreya.com](https://entreya.com)
