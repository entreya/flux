<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

/**
 * Log panel widget — the main area where step accordions and log lines appear.
 *
 * This is the container that JS populates dynamically with step details.
 * Can be placed anywhere independently.
 *
 * Usage:
 *   echo FluxLogPanel::widget(['id' => 'myLogs']);
 *   echo FluxLogPanel::widget(['id' => 'myLogs', 'options' => ['class' => 'flex-grow-1']]);
 */
class FluxLogPanel extends FluxWidget
{
    protected function defaultId(): string
    {
        return 'fx-steps';
    }

    protected function selectorMap(): array
    {
        return [
            'steps' => $this->id,
        ];
    }

    public function render(): string
    {
        $class = $this->mergeClass('flex-grow-1 overflow-auto');

        return '<div id="' . htmlspecialchars($this->id, ENT_QUOTES) . '" '
             . 'class="' . htmlspecialchars($class, ENT_QUOTES) . '"'
             . $this->renderAttributes()
             . '></div>';
    }
}
