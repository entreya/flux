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
            'class' => 'btn btn-primary d-flex align-items-center gap-2',
            'title' => 'Re-run Workflow',
            'icon'  => 'bi bi-play-fill',
            'text'  => 'Re-run',
        ];
    }

    protected function template(): string
    {
        return '<button type="button" id="{id}" class="{class}" title="{title}"><i class="{icon}"></i><span>{text}</span></button>';
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('rerunBtn', (string) $this->props['id']);
    }
}
