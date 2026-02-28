<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Toolbar;

use Entreya\Flux\Ui\FluxComponent;
use Entreya\Flux\Ui\FluxRenderer;

class Heading extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'    => 'fx-toolbar-heading',
            'class' => 'h6 mb-0 text-truncate font-weight-bold',
            'icon'  => 'bi bi-gear-wide-connected',
            'text'  => 'Initializing...',
        ];
    }

    protected function template(): string
    {
        return '<div id="{id}" class="{class}"><i class="{icon} me-1"></i>{text}</div>';
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('jobHeading', (string) $this->props['id']);
    }
}
