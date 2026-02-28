<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Badge;

use Entreya\Flux\Ui\FluxComponent;
use Entreya\Flux\Ui\FluxRenderer;

class Text extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'   => 'fx-badge-text',
            'text' => 'Connecting',
        ];
    }

    protected function template(): string
    {
        return '<span id="{id}">{text}</span>';
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('badgeText', (string) $this->props['id']);
    }
}
