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
            'class' => 'fw-semibold small flex-grow-1 text-truncate',
            'text'  => 'Initializing…',
        ];
    }

    protected function template(): string
    {
        return '<span id="{id}" class="{class}">{text}</span>';
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('jobHeading', $this->props['id']);
    }
}
