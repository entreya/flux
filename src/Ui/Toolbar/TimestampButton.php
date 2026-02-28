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
            'title' => 'Timestamps',
        ];
    }

    protected function template(): string
    {
        return '<button type="button" id="{id}" class="{class}" title="{title}">{title}</button>';
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('tsBtn', (string) $this->props['id']);
    }
}
