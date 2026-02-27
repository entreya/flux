<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

/**
 * Progress bar widget.
 *
 * Layout: {bar}
 *
 * Slots: bar
 *
 * Options:
 *   - options        (array)  — outer progress container attributes
 *   - barOptions     (array)  — inner progress-bar <div> attributes
 *   - height         (string) — CSS height (default: '2px')
 *   - barClass       (string) — initial bar color class (default: 'bg-primary')
 *   - slots          (array)  — per-component render closures
 */
class FluxProgress extends FluxWidget
{
    protected array $barOptions = [];
    protected string $height    = '2px';
    protected string $barClass  = 'bg-primary';

    protected function configure(array $config): void
    {
        $this->barOptions = $config['barOptions'] ?? [];
        $this->height     = $config['height'] ?? '2px';
        $this->barClass   = $config['barClass'] ?? 'bg-primary';
    }

    protected function defaultId(): string
    {
        return 'fx-progress';
    }

    protected function defaultLayout(): string
    {
        return '{bar}';
    }

    protected function selectorMap(): array
    {
        return [
            'progress' => $this->id,
        ];
    }

    protected function renderSections(): array
    {
        return [
            '{bar}' => $this->renderBar(),
        ];
    }

    // ── Pure Open/Close Tags ────────────────────────────────────────────────

    protected function openTarget(): string
    {
        $opts  = $this->options;
        $class = $this->mergeClass('progress', $opts);
        $h     = htmlspecialchars($this->height, ENT_QUOTES);

        return '<div class="' . htmlspecialchars($class, ENT_QUOTES) . '"'
             . ' style="height:' . $h . '"'
             . $this->renderAttributes($opts) . '>';
    }

    protected function closeTarget(): string
    {
        return '</div>';
    }

    // ── Public Closure API ───────────────────────────────────────────────

    public function bar(): string
    {
        return $this->renderBar();
    }

    // ── Internal Render Methods (with Slot Dispatch) ────────────────────────

    protected function renderBar(): string
    {
        $barOpts = $this->barOptions;
        $barClass = $this->mergeClass('progress-bar ' . $this->barClass, $barOpts);

        $props = [
            'id'    => $this->id,
            'class' => $barClass,
            'attrs' => $barOpts,
        ];

        return $this->slot('bar', $props, function () use ($props) {
            return '<div class="' . htmlspecialchars($props['class'], ENT_QUOTES) . '"'
                 . ' id="' . htmlspecialchars($props['id'], ENT_QUOTES) . '"'
                 . ' style="width:0%;transition:width .5s ease"'
                 . ' role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"'
                 . $this->renderAttributes($props['attrs'])
                 . '></div>';
        });
    }
}
