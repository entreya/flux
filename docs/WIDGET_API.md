# Flux UI Widget API Reference

Every widget supports three customization levels: **Options**, **Slots**, and **Closures**.

| Level | What you control | How |
|-------|-----------------|-----|
| **Options** | CSS classes, attributes, text, visibility | `searchOptions => ['class' => '...']` |
| **Slots** | One component's HTML | `slots => ['search' => fn(...)]` |
| **Closure** | Full layout arrangement | `render([], fn($t) { ... })` |

---

## Quick Examples

### Options — Change styling
```php
<?= FluxToolbar::widget([
    'headingText'     => 'Grace Marks Evaluation',
    'searchOptions'   => ['class' => 'border-primary bg-dark'],
    'showRerun'       => false,
    'options'         => ['class' => 'sticky-top shadow-sm'],
]) ?>
```

### Slots — Swap one component
```php
<?= FluxToolbar::widget([
    'slots' => [
        // Replace rerun button with a download link
        'btnRerun' => fn($w, $props, $default) =>
            '<a href="/report" class="btn btn-sm btn-primary">Download</a>',

        // Wrap search with a glow effect
        'search' => fn($w, $props, $default) =>
            '<div class="glow">' . $default() . '</div>',

        // Augment heading with a badge
        'heading' => fn($w, $props, $default) =>
            $default() . '<span class="badge text-bg-info ms-2">LIVE</span>',
    ],
]) ?>
```

Every slot closure receives `($widget, $props, $default)`:
- `$widget` — the widget instance
- `$props` — resolved values (merged classes, computed IDs, text)
- `$default` — call `$default()` to get the original HTML

### Closure — Rearrange the layout
```php
<?= FluxToolbar::render(['id' => 'my-tb'], function (FluxToolbar $t) { ?>
    <div class="row align-items-center">
        <div class="col-4"><?= $t->heading() ?></div>
        <div class="col-4"><?= $t->search() ?></div>
        <div class="col-4 text-end">
            <?= $t->btnTimestamps() ?>
            <?= $t->btnExpand() ?>
            <?= $t->btnCollapse() ?>
            <?= $t->btnRerun() ?>
            <?= $t->btnTheme() ?>
        </div>
    </div>
<?php }) ?>
```

> **Tip:** Inside a closure, use `$t->selector('search')` to get the JS-bound element ID if you want to write completely raw HTML while keeping JS bindings.

---

## FluxToolbar

**Root tag:** `<div>` &nbsp;|&nbsp; **Default ID:** `fx-toolbar`

### Config

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `headingOptions` | `array` | `[]` | Heading `<span>` attributes |
| `searchOptions` | `array` | `[]` | Search `<input>` attributes |
| `tsBtnOptions` | `array` | `[]` | Timestamp button attributes |
| `expandBtnOptions` | `array` | `[]` | Expand-all button attributes |
| `collapseBtnOptions` | `array` | `[]` | Collapse-all button attributes |
| `rerunBtnOptions` | `array` | `[]` | Rerun button attributes |
| `themeBtnOptions` | `array` | `[]` | Theme toggle button attributes |
| `showRerun` | `bool` | `true` | Show rerun button |
| `showThemeToggle` | `bool` | `true` | Show theme toggle |
| `showSearch` | `bool` | `true` | Show search input |
| `showTimestamps` | `bool` | `true` | Show timestamp toggle |
| `showExpand` | `bool` | `true` | Show expand/collapse buttons |
| `headingText` | `string` | `'Initializing…'` | Initial heading text |
| `searchPlaceholder` | `string` | `'Search logs…'` | Search placeholder |
| `afterSearch` | `string` | `''` | HTML after search input |

### Slots

| Slot | Props | Description |
|------|-------|-------------|
| `heading` | `id`, `class`, `text`, `attrs` | Job heading |
| `search` | `id`, `class`, `placeholder`, `afterSearch`, `attrs` | Search input with icon |
| `btnTimestamps` | `id`, `class`, `attrs` | Timestamp toggle |
| `btnExpand` | `class`, `attrs` | Expand-all |
| `btnCollapse` | `class`, `attrs` | Collapse-all |
| `btnRerun` | `id`, `class`, `attrs` | Rerun button |
| `btnTheme` | `iconId`, `class`, `attrs` | Theme toggle |
| `controls` | `showSearch`, `showTimestamps`, `showExpand`, `showRerun`, `showThemeToggle` | All controls wrapper |

### Closure Methods

`$t->heading()` · `$t->search()` · `$t->btnTimestamps()` · `$t->btnExpand()` · `$t->btnCollapse()` · `$t->btnRerun()` · `$t->btnTheme()` · `$t->controls()`

### Selectors

`search` · `rerunBtn` · `themeIcon` · `tsBtn` · `jobHeading`

---

## FluxBadge

**Root tag:** `<span>` &nbsp;|&nbsp; **Default ID:** `fx-badge`

### Config

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `dotOptions` | `array` | `[]` | Dot `<span>` attributes |
| `textOptions` | `array` | `[]` | Text `<span>` attributes |
| `initialText` | `string` | `'Connecting'` | Default text |

### Slots

| Slot | Props | Description |
|------|-------|-------------|
| `dot` | `class`, `attrs` | Pulsing status dot |
| `text` | `id`, `text`, `attrs` | Status text |

### Closure Methods

`$b->dot()` · `$b->text()`

### Selectors

`badge` · `badgeText`

### Example

```php
// Replace the pulsing dot with an icon
<?= FluxBadge::widget([
    'initialText' => 'Starting…',
    'slots' => [
        'dot' => fn($w, $p, $d) => '<i class="bi bi-circle-fill text-success me-1"></i>',
    ],
]) ?>
```

---

## FluxSidebar

**Root tag:** `<nav>` &nbsp;|&nbsp; **Default ID:** `fx-sidebar`

### Config

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `listOptions` | `array` | `[]` | Job list container attributes |
| `footerOptions` | `array` | `[]` | Footer container attributes |
| `itemOptions` | `array` | `[]` | Per-job-item attributes (JS) |
| `workflowName` | `string` | `''` | Workflow name in footer |
| `trigger` | `string` | `'manual'` | Trigger label |
| `showFooter` | `bool` | `true` | Show runner info footer |
| `emptyText` | `string` | `'Waiting for workflow…'` | Empty-state text |

### Slots

| Slot | Props | Description |
|------|-------|-------------|
| `jobList` | `id`, `class`, `emptyText`, `attrs` | Job list container |
| `footer` | `class`, `workflowName`, `trigger`, `phpVersion`, `attrs` | Runner metadata |

### Closure Methods

`$s->jobList()` · `$s->footer()`

### Selectors

`jobList`

### Example

```php
<?= FluxSidebar::render([
    'workflowName' => 'Grace Marks',
    'trigger'      => 'cron',
], function (FluxSidebar $s) { ?>
    <div class="card h-100 border-0">
        <div class="card-body p-0"><?= $s->jobList() ?></div>
        <div class="card-footer"><?= $s->footer() ?></div>
    </div>
<?php }) ?>
```

---

## FluxLogPanel

**Root tag:** `<div>` &nbsp;|&nbsp; **Default ID:** `fx-steps`

### Config

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `stepRenderer` | `string\|object` | `DetailsStepRenderer::class` | Step renderer class/instance |
| `stepOptions` | `array` | `[]` | Step container attributes (JS) |
| `stepHeaderOptions` | `array` | `[]` | Step header attributes (JS) |
| `logBodyOptions` | `array` | `[]` | Log body attributes (JS) |
| `beforeSteps` | `string` | `''` | HTML before steps |
| `afterSteps` | `string` | `''` | HTML after steps |
| `jobHeaderTemplate` | `string` | `''` | Custom job header HTML template |

**Job header template tokens:** `{header_id}`, `{icon_id}`, `{prog_id}`, `{dur_id}`, `{name}`, `{job}`, `{total_steps}`

### Slots

| Slot | Props | Description |
|------|-------|-------------|
| `stepsContainer` | `id` | Container that JS fills with steps |

### Closure Methods

`$lp->stepsContainer()`

### Selectors

`steps`

### Example

```php
<?= FluxLogPanel::widget([
    'stepRenderer'      => AccordionStepRenderer::class,
    'beforeSteps'       => '<div class="alert alert-info m-2">Processing…</div>',
    'jobHeaderTemplate' => '<div id="{header_id}" class="bg-primary text-white px-3 py-2">'
                         . '<span class="fw-semibold">{name}</span>'
                         . '<small id="{prog_id}">0/{total_steps}</small>'
                         . '</div>',
]) ?>
```

---

## FluxProgress

**Root tag:** `<div>` &nbsp;|&nbsp; **Default ID:** `fx-progress`

### Config

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `barOptions` | `array` | `[]` | Inner progress bar attributes |
| `height` | `string` | `'2px'` | Bar height |
| `barClass` | `string` | `'bg-primary'` | Bootstrap color class |

### Slots

| Slot | Props | Description |
|------|-------|-------------|
| `bar` | `id`, `class`, `attrs` | The progress bar div |

### Closure Methods

`$p->bar()`

### Selectors

`progress`

### Example

```php
<?= FluxProgress::widget([
    'height'     => '6px',
    'barClass'   => 'bg-success',
    'barOptions' => ['class' => 'progress-bar-striped progress-bar-animated'],
]) ?>
```

---

## FluxAsset

Static registry — not a widget. Accumulates config from all widgets and renders the JS bootstrap.

| Method | Description |
|--------|-------------|
| `FluxAsset::setAssetPath(string)` | Set base path for flux.css/js |
| `FluxAsset::css()` | Render `<link>` for flux.css |
| `FluxAsset::js()` | Render `<script>` for flux.js |
| `FluxAsset::styles()` | Render `<style>` with all widget CSS |
| `FluxAsset::init(array $config)` | Render `<script>` bootstrap with selectors, templates, events |
| `FluxAsset::reset()` | Clear state (useful in tests) |

### Complete Page

```php
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <?= FluxAsset::css() ?>
</head>
<body>
    <?= FluxBadge::widget(['initialText' => 'Starting…']) ?>
    <?= FluxToolbar::widget(['headingText' => 'Grade Pipeline']) ?>
    <?= FluxProgress::widget(['height' => '3px']) ?>

    <div class="d-flex flex-grow-1 overflow-hidden">
        <?= FluxSidebar::widget(['workflowName' => 'grace-marks']) ?>
        <?= FluxLogPanel::widget() ?>
    </div>

    <?= FluxAsset::styles() ?>
    <?= FluxAsset::js() ?>
    <?= FluxAsset::init(['sseUrl' => '/sse.php?workflow=grace-marks']) ?>
</body>
</html>
```

---

## Step Renderers

| Renderer | HTML | Collapse Method |
|----------|------|-----------------|
| `DetailsStepRenderer` | `<details>/<summary>` | `details` |
| `AccordionStepRenderer` | Bootstrap accordion | `accordion` |

**Custom renderer:** Implement `StepRendererInterface` — define `jsTemplate(): string` and `collapseMethod(): string`.

```php
<?= FluxLogPanel::widget(['stepRenderer' => MyCustomRenderer::class]) ?>
```

---

## Universal Config

Every widget accepts these:

| Key | Type | Description |
|-----|------|-------------|
| `id` | `string` | Root element ID |
| `options` | `array` | Root element HTML attributes |
| `layout` | `string` | Template with `{placeholders}` (ignored with closure) |
| `pluginOptions` | `array` | JS config passed to `FluxUI.init()` |
| `pluginEvents` | `array` | JS event hooks |
| `beforeContent` | `string` | HTML before widget content |
| `afterContent` | `string` | HTML after widget content |
| `slots` | `array` | Per-component render closures |
