<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Renderer;

/**
 * Bootstrap 5 accordion step renderer.
 * Uses Bootstrap's Collapse JS for smooth open/close animations.
 */
final class AccordionStepRenderer implements StepRendererInterface
{
    public function jsTemplate(): string
    {
        return '<div class="accordion-item border-0" id="{id}" data-job="{job}" data-step="{step}" data-status="pending">'
             . '<h2 class="accordion-header">'
             .   '<button class="accordion-button py-2 px-3 small" type="button" '
             .     'data-bs-toggle="collapse" data-bs-target="#{collapse_id}" aria-expanded="true">'
             .     '<span class="flux-step-ico is-pending me-2" id="{icon_id}"></span>'
             .     '{phase}'
             .     '<span class="flux-step-name">{name}</span>'
             .     '<span class="flux-step-dur ms-auto me-2" id="{dur_id}"></span>'
             .   '</button>'
             . '</h2>'
             . '<div id="{collapse_id}" class="accordion-collapse collapse show">'
             .   '<div class="accordion-body p-0 flux-log-body" id="{logs_id}"></div>'
             . '</div>'
             . '</div>';
    }

    public function collapseMethod(): string
    {
        return 'accordion';
    }
}
