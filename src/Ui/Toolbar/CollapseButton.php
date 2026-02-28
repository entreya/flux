<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Toolbar;

use Entreya\Flux\Ui\FluxComponent;
use Entreya\Flux\Ui\FluxRenderer;

class CollapseButton extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'    => 'fx-toolbar-collapse-btn',
            'class' => 'btn btn-outline-secondary btn-sm',
            'title' => 'Collapse All',
        ];
    }

    protected function template(): string
    {
        return '<button type="button" id="{id}" class="{class}" title="{title}">{title}</button>';
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('collapseBtn', (string) $this->props['id']);
    }
}
