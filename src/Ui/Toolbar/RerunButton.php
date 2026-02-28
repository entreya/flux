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
            'class' => 'btn btn-primary btn-sm',
            'title' => 'Re-run Workflow',
            'text'  => 'Re-run',
        ];
    }

    protected function template(): string
    {
        return '<button type="button" id="{id}" class="{class}" title="{title}">{text}</button>';
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('rerunBtn', (string) $this->props['id']);
    }
}
