# Flux UI Component API Reference

Every component is a self-contained unit with **props**, **template**, **style**, **script**, and **slots**.

```
Component::render([
    'props'   => [...],   // override defaults
    'slots'   => [...],   // override child components
    'content' => ...,     // replace template entirely
    'style'   => '...',   // add/replace CSS
    'script'  => '...',   // add/replace JS
])
```

---

## Quick Examples

### Override props
```php
<?= Toolbar::render([
    'props' => ['class' => 'sticky-top shadow-sm'],
    'slots' => [
        'heading' => ['props' => ['text' => 'Grace Marks Evaluation']],
        'search'  => ['props' => ['placeholder' => 'Filter grades…']],
    ],
]) ?>
```

### Replace a slot with raw HTML
```php
<?= Toolbar::render([
    'slots' => [
        'btnRerun' => '<a href="/report" class="btn btn-sm btn-primary">Download</a>',
    ],
]) ?>
```

### Disable a slot
```php
<?= Toolbar::render([
    'slots' => [
        'btnTheme' => false,    // Not rendered, no CSS/JS registered
        'btnRerun' => false,
    ],
]) ?>
```

### Nest components inside a slot
```php
<?= Toolbar::render([
    'slots' => [
        'heading' => fn() =>
            Badge::render(['props' => ['initialText' => 'LIVE']])
            . Heading::render(['props' => ['text' => 'Grace Marks']]),
    ],
]) ?>
```

### Replace template entirely
```php
<?= Heading::render([
    'content' => '<h1 id="{id}" class="display-6">{text}</h1>',
]) ?>
```

### Add custom style and script
```php
<?= SearchInput::render([
    'props'  => ['id' => 'my-search'],
    'style'  => '.my-glow { box-shadow: 0 0 10px gold; }',
    'script' => 'console.log("{id} is ready");',
]) ?>
```

---

## Slot Override Types

| Value | Behavior |
|-------|----------|
| `string` | Raw HTML replaces the slot |
| `array` | Config passed to the default component (`['props' => [...]]`) |
| `Closure` | Called with parent props, returns HTML |
| `false` | Not rendered — no HTML, no CSS, no JS registered |
| `Foo::class` | Renders a different component class |

---

## Toolbar (`Entreya\Flux\Ui\Toolbar\Toolbar`)

**Root:** `<div>` &nbsp;|&nbsp; **Default ID:** `fx-toolbar`

### Props

| Prop | Default | Description |
|------|---------|-------------|
| `id` | `fx-toolbar` | Root element ID |
| `class` | `d-flex align-items-center gap-2 px-3 py-2 border-bottom bg-body-tertiary` | Root CSS |

### Slots

| Slot | Component | Selectors |
|------|-----------|-----------|
| `heading` | `Heading` | `jobHeading` |
| `search` | `SearchInput` | `search` |
| `btnTimestamps` | `TimestampButton` | `tsBtn` |
| `btnExpand` | `ExpandButton` | — |
| `btnCollapse` | `CollapseButton` | — |
| `btnRerun` | `RerunButton` | `rerunBtn` |
| `btnTheme` | `ThemeButton` | `themeIcon` |

### Sub-Component Props

**Heading** — `id`, `class`, `text` (default: `'Initializing…'`)
**SearchInput** — `id`, `class`, `placeholder` (default: `'Search logs…'`)
**RerunButton** — `id`, `class`
**ThemeButton** — `id`, `class`, `icon_id`
**TimestampButton** — `id`, `class`, `title`
**ExpandButton** — `id`, `class`, `title`
**CollapseButton** — `id`, `class`, `title`

---

## Badge (`Entreya\Flux\Ui\Badge`)

**Root:** `<span>` &nbsp;|&nbsp; **Default ID:** `fx-badge`

### Props

| Prop | Default | Description |
|------|---------|-------------|
| `id` | `fx-badge` | Root element ID |
| `class` | `badge rounded-pill text-bg-secondary...` | Root CSS |
| `initialText` | `'Connecting'` | Default display text |

### Slots

| Slot | Component | Selectors |
|------|-----------|-----------|
| `dot` | `Badge\Dot` | — |
| `text` | `Badge\Text` | `badge`, `badgeText` |

### Example

```php
<?= Badge::render([
    'props' => ['initialText' => 'Starting…'],
    'slots' => [
        'dot' => '<i class="bi bi-circle-fill text-success me-1"></i>',
    ],
]) ?>
```

---

## Sidebar (`Entreya\Flux\Ui\Sidebar\Sidebar`)

**Root:** `<nav>` &nbsp;|&nbsp; **Default ID:** `fx-sidebar`

### Props

| Prop | Default | Description |
|------|---------|-------------|
| `id` | `fx-sidebar` | Root element ID |
| `class` | `d-flex flex-column border-end bg-body-tertiary` | Root CSS |
| `workflowName` | `''` | Passed to Footer |
| `trigger` | `'manual'` | Passed to Footer |

### Slots

| Slot | Component | Selectors |
|------|-----------|-----------|
| `jobList` | `Sidebar\JobList` | `jobList` |
| `footer` | `Sidebar\Footer` | — |

### Sub-Component Props

**JobList** — `id`, `class`, `emptyText` (default: `'Waiting for workflow…'`)
**Footer** — `id`, `class`, `workflowName`, `trigger`, `phpVersion`

### Example

```php
<?= Sidebar::render([
    'props' => ['workflowName' => 'grace-marks', 'trigger' => 'webhook'],
    'slots' => ['footer' => false],  // hide footer
]) ?>
```

---

## LogPanel (`Entreya\Flux\Ui\Log\LogPanel`)

**Root:** `<div>` &nbsp;|&nbsp; **Default ID:** `fx-log-panel`

### Props

| Prop | Default | Description |
|------|---------|-------------|
| `id` | `fx-log-panel` | Root element ID |
| `class` | `flex-grow-1 overflow-auto` | Root CSS |
| `jobHeaderTemplate` | `''` | Custom job header HTML (raw, not escaped) |
| `beforeSteps` | `''` | Raw HTML before steps (not escaped) |
| `afterSteps` | `''` | Raw HTML after steps (not escaped) |

### Slots

| Slot | Component | Selectors |
|------|-----------|-----------|
| `stepsContainer` | `Log\StepsContainer` | `steps` |

### Job Header Template Tokens

`{header_id}` · `{icon_id}` · `{prog_id}` · `{dur_id}` · `{name}` · `{job}` · `{total_steps}`

### Example

```php
<?= LogPanel::render([
    'props' => [
        'stepRenderer' => AccordionStepRenderer::class,
        'beforeSteps'  => '<div class="alert alert-info m-2">Processing…</div>',
    ],
]) ?>
```

---

## Progress (`Entreya\Flux\Ui\Progress`)

**Root:** `<div>` &nbsp;|&nbsp; **Default ID:** `fx-progress`

### Props

| Prop | Default | Description |
|------|---------|-------------|
| `id` | `fx-progress` | Root element ID |
| `class` | `progress` | Root CSS |
| `height` | `'2px'` | Bar height |
| `barClass` | `'progress-bar bg-primary'` | Progress bar CSS |

### Slots

| Slot | Component | Selectors |
|------|-----------|-----------|
| `bar` | `Progress\Bar` | `progress` |

### Example

```php
<?= Progress::render([
    'props' => ['height' => '6px', 'barClass' => 'progress-bar bg-success progress-bar-striped'],
]) ?>
```

---

## FluxRenderer

Static asset collector. Not a component — accumulates CSS/JS from all rendered components.

| Method | Description |
|--------|-------------|
| `FluxRenderer::setAssetPath(string)` | Base path for flux.css/js |
| `FluxRenderer::css()` | `<link>` tag for flux.css |
| `FluxRenderer::js()` | `<script>` tag for flux.js |
| `FluxRenderer::styles()` | `<style>` block — all collected CSS |
| `FluxRenderer::init(array)` | `<script>` — component JS + `FluxUI.init()` |
| `FluxRenderer::flush(array)` | `styles()` + `js()` + `init()` in one call |
| `FluxRenderer::reset()` | Clear state (tests) |

**CSS** is deduped by component class (rendered once even with multiple instances).
**JS** is per instance (each gets its own `{id}` interpolated).
**If a component isn't rendered, its CSS/JS never appears.**

---

## Complete Page

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
    <meta charset="UTF-8">
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
                'search'  => ['props' => ['placeholder' => 'Filter students…']],
            ],
        ]) ?>
        <?= Progress::render(['props' => ['height' => '3px', 'barClass' => 'progress-bar bg-success']]) ?>

        <div class="d-flex flex-grow-1 overflow-hidden">
            <?= Sidebar::render(['props' => ['workflowName' => 'grace-marks']]) ?>
            <?= LogPanel::render() ?>
        </div>
    </div>

    <?= FluxRenderer::flush(['sseUrl' => '/sse.php?workflow=grace-marks']) ?>
</body>
</html>
```

---

## Creating Custom Components

```php
use Entreya\Flux\Ui\FluxComponent;

class CustomAlert extends FluxComponent
{
    protected function defaults(): array
    {
        return ['id' => 'my-alert', 'class' => 'alert alert-info', 'message' => 'Hello'];
    }

    protected function template(): string
    {
        return '<div id="{id}" class="{class}">{message}</div>';
    }

    protected function style(): string
    {
        return '#my-alert { border-left: 4px solid #0dcaf0; }';
    }

    protected function script(): string
    {
        return 'document.getElementById("{id}").addEventListener("click", function() { this.remove(); });';
    }
}

// Use it
<?= CustomAlert::render(['props' => ['message' => 'Evaluation complete!']]) ?>
```

Style and script are automatically collected by `FluxRenderer` and output via `flush()`.
