<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Sidebar;

use Entreya\Flux\Ui\FluxComponent;
use Entreya\Flux\Ui\FluxRenderer;

class Footer extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'           => 'fx-sidebar-footer',
            'class'        => 'p-3 border-top small text-secondary',
            'workflowName' => 'Unknown',
            'trigger'      => 'manual',
            'runner'       => 'Local',
        ];
    }

    protected function template(): string
    {
        return <<<'HTML'
        <div id="{id}" class="{class}">
            <div class="mb-1 text-uppercase font-weight-bold" style="font-size:10px;opacity:.6">System Context</div>
            <div class="d-flex justify-content-between mb-1">
                <span>Workflow</span>
                <span class="text-body fw-medium">{workflowName}</span>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span>Trigger</span>
                <span class="text-body fw-medium capitalize">{trigger}</span>
            </div>
            <div class="d-flex justify-content-between">
                <span>Runner</span>
                <span class="text-body fw-medium">{runner}</span>
            </div>
        </div>
        HTML;
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('sidebarFooter', (string) $this->props['id']);
    }
}
