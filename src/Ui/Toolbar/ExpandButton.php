<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Toolbar;

use Entreya\Flux\Ui\FluxComponent;

class ExpandButton extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'    => 'fx-toolbar-expand-btn',
            'class' => 'btn btn-outline-secondary btn-sm',
            'title' => 'Expand all',
        ];
    }

    protected function template(): string
    {
        return '<button id="{id}" class="{class}" onclick="FluxUI.expandAll()" title="{title}"><i class="bi bi-arrows-expand"></i></button>';
    }
}
