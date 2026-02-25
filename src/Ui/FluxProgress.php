<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

/**
 * Progress bar widget — thin bar showing workflow completion.
 *
 * Uses Bootstrap 5 progress component.
 *
 * Usage:
 *   echo FluxProgress::widget(['id' => 'myProgress']);
 *   echo FluxProgress::widget(['id' => 'myProgress', 'height' => '3px']);
 */
class FluxProgress extends FluxWidget
{
    /** @var string CSS height for the progress bar */
    protected string $height = '2px';

    public function __construct(array $config = [])
    {
        $this->height = $config['height'] ?? '2px';
        parent::__construct($config);
    }

    protected function defaultId(): string
    {
        return 'fx-progress';
    }

    protected function selectorMap(): array
    {
        return [
            'progress' => $this->id,
        ];
    }

    public function render(): string
    {
        $class = $this->mergeClass('');
        $h = htmlspecialchars($this->height, ENT_QUOTES);

        return '<div class="progress' . ($class ? ' ' . htmlspecialchars($class, ENT_QUOTES) : '') . '" '
             . 'role="progressbar" style="height:' . $h . '"'
             . $this->renderAttributes() . '>'
             . '<div class="progress-bar bg-primary" id="' . htmlspecialchars($this->id, ENT_QUOTES) . '" '
             . 'style="width:0%;transition:width .5s ease" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">'
             . '</div></div>';
    }
}
