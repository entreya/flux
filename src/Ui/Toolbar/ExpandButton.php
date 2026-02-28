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
            'class' => 'btn btn-outline-secondary btn-sm',
            'title' => 'Expand All',
        ];
    }

    protected function template(): string
    {
        return '<button type="button" id="{id}" class="{class}" title="{title}">{title}</button>';
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('expandBtn', (string) $this->props['id']);
    }
}
