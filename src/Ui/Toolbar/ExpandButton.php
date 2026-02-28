<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Toolbar;

use Entreya\Flux\Ui\FluxComponent;
use Entreya\Flux\Ui\FluxRenderer;

class ExpandButton extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'    => 'fx-toolbar-expand-btn',
            'class' => 'btn btn-outline-secondary',
            'title' => 'Expand All',
            'icon'  => 'bi bi-chevron-bar-down',
        ];
    }

    protected function template(): string
    {
        return '<button type="button" id="{id}" class="{class}" title="{title}"><i class="{icon}"></i></button>';
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('expandBtn', (string) $this->props['id']);
    }
}
