<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Toolbar;

use Entreya\Flux\Ui\FluxComponent;

class CollapseButton extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'    => 'fx-toolbar-collapse-btn',
            'class' => 'btn btn-outline-secondary btn-sm',
            'title' => 'Collapse all',
        ];
    }

    protected function template(): string
    {
        return '<button id="{id}" class="{class}" onclick="FluxUI.collapseAll()" title="{title}"><i class="bi bi-arrows-collapse"></i></button>';
    }
}
