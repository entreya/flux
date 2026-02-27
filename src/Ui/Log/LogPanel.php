<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Log;

use Entreya\Flux\Ui\FluxComponent;
use Entreya\Flux\Ui\FluxRenderer;

/**
 * Log panel component — step accordions and log lines container.
 *
 * Slots: beforeSteps, stepsContainer, afterSteps
 * Props: jobHeaderTemplate (raw HTML for JS-rendered job headers)
 */
class LogPanel extends FluxComponent
{
    /** Step template using native <details>/<summary>. No Bootstrap JS needed. */
    private const STEP_TEMPLATE =
        '<details class="flux-step" id="{id}" data-job="{job}" data-step="{step}" data-status="pending" open>'
        . '<summary class="flux-step-summary">'
        .   '<div class="flux-step-ico is-pending" id="{icon_id}"></div>'
        .   '{phase}'
        .   '<span class="flux-step-name">{name}</span>'
        .   '<span class="flux-step-dur" id="{dur_id}"></span>'
        .   '<i class="bi bi-chevron-right flux-step-chevron"></i>'
        . '</summary>'
        . '<div class="flux-log-body" id="{logs_id}"></div>'
        . '</details>';

    protected function defaults(): array
    {
        return [
            'id'                => 'fx-log-panel',
            'class'             => 'flex-grow-1 overflow-auto',
            'jobHeaderTemplate' => '',
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

    /** Register JS templates and config via the standard asset lifecycle. */
    protected function registerAssets(): void
    {
        parent::registerAssets();

        FluxRenderer::registerTemplate('step', self::STEP_TEMPLATE);
        FluxRenderer::registerPluginOptions('logPanel', [
            'collapseMethod' => 'details',
        ]);

        $jobHeader = $this->props['jobHeaderTemplate'] ?? '';
        if ($jobHeader !== '') {
            FluxRenderer::registerTemplate('jobHeader', $jobHeader);
        }
    }

    protected function style(): string
    {
        // CSS lives in log-panel.css — loaded once, PHPStorm-friendly
        return (string) file_get_contents(__DIR__ . '/log-panel.css');
    }
}
