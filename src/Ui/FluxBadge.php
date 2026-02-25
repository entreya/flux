<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

/**
 * Status badge widget.
 *
 * Layout: {dot}{text}
 *
 * Options:
 *   - id             (string)  — element ID
 *   - options        (array)   — root element HTML attributes
 *   - dotOptions     (array)   — dot <span> HTML attributes
 *   - textOptions    (array)   — text <span> HTML attributes
 *   - initialText    (string)  — default text (default: 'Connecting')
 *   - layout         (string)  — template: '{dot}{text}'
 *   - beforeContent  (string)  — arbitrary HTML before badge
 *   - afterContent   (string)  — arbitrary HTML after badge
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

    protected function renderDot(): string
    {
        $opts = $this->dotOptions;
        $class = $this->mergeClass('flux-badge-dot', $opts);
        return '<span class="' . htmlspecialchars($class, ENT_QUOTES) . '"'
             . $this->renderAttributes($opts) . '></span>';
    }

    protected function renderText(): string
    {
        $opts = $this->textOptions;
        $id = htmlspecialchars($this->id . '-text', ENT_QUOTES);
        return '<span id="' . $id . '"'
             . $this->renderAttributes($opts) . '>'
             . htmlspecialchars($this->initialText, ENT_QUOTES)
             . '</span>';
    }

    public function render(): string
    {
        $opts = $this->options;
        $class = $this->mergeClass(
            'badge rounded-pill text-bg-secondary d-inline-flex align-items-center gap-1',
            $opts
        );

        $inner = $this->beforeContent
               . $this->renderLayout($this->renderSections())
               . $this->afterContent;

        return '<span id="' . htmlspecialchars($this->id, ENT_QUOTES) . '" '
             . 'class="' . htmlspecialchars($class, ENT_QUOTES) . '" '
             . 'data-status="pending"'
             . $this->renderAttributes($opts) . '>'
             . $inner
             . '</span>';
    }
}
