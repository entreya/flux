<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Sidebar;

use Entreya\Flux\Ui\FluxComponent;
use Entreya\Flux\Ui\FluxRenderer;

class JobList extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'        => 'fx-sidebar-job-list',
            'class'     => 'list-group list-group-flush',
            'emptyText' => 'Waiting for workflow…',
        ];
    }

    protected function template(): string
    {
        return <<<'HTML'
        <div class="flex-grow-1 overflow-auto p-2">
            <p class="text-uppercase text-body-secondary fw-semibold small mb-2 px-1" style="font-size:11px;letter-spacing:.5px">Jobs</p>
            <div id="{id}" class="{class}">
                <div class="flux-sidebar-empty text-body-secondary fst-italic small p-2">{emptyText}</div>
            </div>
        </div>
        HTML;
    }

    protected function style(): string
    {
        return '.flux-sidebar-empty{font-size:12px;color:var(--bs-secondary-color);font-style:italic;padding:4px}';
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('jobList', $this->props['id']);
    }
}
