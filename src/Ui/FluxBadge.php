<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

/**
 * Status badge widget.
 *
 * Layout: {dot}{text}
 *
 * Slots: dot, text
 *
 * Options:
 *   - id             (string)  — element ID
 *   - options        (array)   — root element HTML attributes
 *   - dotOptions     (array)   — dot <span> HTML attributes
 *   - textOptions    (array)   — text <span> HTML attributes
 *   - initialText    (string)  — default text (default: 'Connecting')
 *   - slots          (array)   — per-component render closures
 */
class FluxBadge extends FluxWidget
{
    protected array $dotOptions  = [];
    protected array $textOptions = [];
    protected string $initialText = 'Connecting';

    protected function configure(array $config): void
    {
        $this->dotOptions  = $config['dotOptions'] ?? [];
        $this->textOptions = $config['textOptions'] ?? [];
        $this->initialText = $config['initialText'] ?? 'Connecting';
    }

    protected function styles(): string
    {
        return <<<'CSS'
.flux-badge-dot{width:8px;height:8px;border-radius:50%;background:currentColor;flex-shrink:0}
[data-status="running"]{color:var(--flux-accent)!important}
[data-status="running"] .flux-badge-dot{animation:pulse-dot 1.4s ease-in-out infinite}
[data-status="success"] .flux-badge-dot{color:var(--flux-success)}
[data-status="failure"] .flux-badge-dot{color:var(--flux-danger)}
@keyframes pulse-dot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.8)}}
CSS;
    }

    protected function defaultId(): string
    {
        return 'fx-badge';
    }

    protected function defaultLayout(): string
    {
        return '{dot}{text}';
    }

    protected function selectorMap(): array
    {
        return [
            'badge'     => $this->id,
            'badgeText' => $this->id . '-text',
        ];
    }

    protected function renderSections(): array
    {
        return [
            '{dot}'  => $this->renderDot(),
            '{text}' => $this->renderText(),
        ];
    }

    // ── Pure Open/Close Tags ────────────────────────────────────────────────

    protected function openTarget(): string
    {
        $opts  = $this->options;
        $class = $this->mergeClass(
            'badge rounded-pill text-bg-secondary d-inline-flex align-items-center gap-1',
            $opts
        );

        return '<span id="' . htmlspecialchars($this->id, ENT_QUOTES) . '"'
             . ' class="' . htmlspecialchars($class, ENT_QUOTES) . '"'
             . ' data-status="pending"'
             . $this->renderAttributes($opts) . '>';
    }

    protected function closeTarget(): string
    {
        return '</span>';
    }

    // ── Public Closure API ───────────────────────────────────────────────

    public function dot(): string
    {
        return $this->renderDot();
    }

    public function text(): string
    {
        return $this->renderText();
    }

    // ── Internal Render Methods (with Slot Dispatch) ────────────────────────

    protected function renderDot(): string
    {
        $opts  = $this->dotOptions;
        $class = $this->mergeClass('flux-badge-dot', $opts);

        $props = [
            'class' => $class,
            'attrs' => $opts,
        ];

        return $this->slot('dot', $props, function () use ($props) {
            return '<span class="' . htmlspecialchars($props['class'], ENT_QUOTES) . '"'
                 . $this->renderAttributes($props['attrs']) . '></span>';
        });
    }

    protected function renderText(): string
    {
        $opts = $this->textOptions;
        $tId  = $this->id . '-text';

        $props = [
            'id'    => $tId,
            'text'  => $this->initialText,
            'attrs' => $opts,
        ];

        return $this->slot('text', $props, function () use ($props) {
            return '<span id="' . htmlspecialchars($props['id'], ENT_QUOTES) . '"'
                 . $this->renderAttributes($props['attrs']) . '>'
                 . htmlspecialchars($props['text'], ENT_QUOTES)
                 . '</span>';
        });
    }
}
