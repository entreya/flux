<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Log;

use Entreya\Flux\Ui\FluxComponent;

/**
 * Log panel component — step accordions and log lines container.
 *
 * Follows the standard component lifecycle:
 *   defaults → template → slots → style → registerAssets
 *
 * Slots: beforeSteps, stepsContainer, afterSteps
 *
 * JS templates (step, jobHeader) and plugin options are owned by flux.js.
 * To override them, use FluxRenderer::registerTemplate() directly.
 */
class LogPanel extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'    => 'fx-log-panel',
            'class' => 'flex-grow-1 overflow-auto',
        ];
    }

    protected function slots(): array
    {
        return [
            'beforeSteps'    => EmptySlot::class,
            'stepsContainer' => StepsContainer::class,
            'afterSteps'     => EmptySlot::class,
        ];
    }

    protected function template(): string
    {
        return '<div id="{id}" class="{class}">{slot:beforeSteps}{slot:stepsContainer}{slot:afterSteps}</div>';
    }

    protected function style(): string
    {
        return (string) file_get_contents(__DIR__ . '/log-panel.css');
    }
}
