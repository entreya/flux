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
        // Empty — JS fills it dynamically
        return '';
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('steps', $this->props['id']);
    }
}
