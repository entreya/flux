<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Renderer;

/**
 * Default step renderer using <details>/<summary>.
 * Lightweight, no Bootstrap JS dependency for collapse.
 */
final class DetailsStepRenderer implements StepRendererInterface
{
    public function jsTemplate(): string
    {
        return '<details class="flux-step" id="{id}" data-job="{job}" data-step="{step}" data-status="pending" open>'
             . '<summary class="flux-step-summary">'
             .   '<div class="flux-step-ico is-pending" id="{icon_id}"></div>'
             .   '{phase}'
             .   '<span class="flux-step-name">{name}</span>'
             .   '<span class="flux-step-dur" id="{dur_id}"></span>'
             .   '<i class="bi bi-chevron-right flux-step-chevron"></i>'
             . '</summary>'
             . '<div class="flux-log-body" id="{logs_id}"></div>'
             . '</details>';
    }

    public function collapseMethod(): string
    {
        return 'details';
    }
}
