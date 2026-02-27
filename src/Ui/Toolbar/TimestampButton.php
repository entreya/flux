<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Toolbar;

use Entreya\Flux\Ui\FluxComponent;
use Entreya\Flux\Ui\FluxRenderer;

class TimestampButton extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'    => 'fx-toolbar-ts-btn',
            'class' => 'btn btn-outline-secondary btn-sm',
            'title' => 'Toggle timestamps',
        ];
    }

    protected function template(): string
    {
        return '<button id="{id}" class="{class}" onclick="FluxUI.toggleTimestamps()" title="{title}"><i class="bi bi-clock"></i></button>';
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('tsBtn', $this->props['id']);
    }
}
