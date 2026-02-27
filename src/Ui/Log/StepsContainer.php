<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Log;

use Entreya\Flux\Ui\FluxComponent;
use Entreya\Flux\Ui\FluxRenderer;

class StepsContainer extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'    => 'fx-steps',
            'class' => '',
        ];
    }

    protected function template(): string
    {
        // JS appends step elements into this container
        return '<div id="{id}" class="{class}"></div>';
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('steps', $this->props['id']);
    }
}
