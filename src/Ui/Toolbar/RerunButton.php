<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Toolbar;

use Entreya\Flux\Ui\FluxComponent;
use Entreya\Flux\Ui\FluxRenderer;

class RerunButton extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'    => 'fx-toolbar-rerun-btn',
            'class' => 'btn btn-outline-secondary btn-sm',
        ];
    }

    protected function template(): string
    {
        return '<button id="{id}" class="{class}" onclick="FluxUI.rerun()" disabled><i class="bi bi-arrow-clockwise"></i> <span class="d-none d-sm-inline">Re-run</span></button>';
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('rerunBtn', $this->props['id']);
    }
}
