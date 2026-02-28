<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Badge;

use Entreya\Flux\Ui\FluxComponent;

class Dot extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'class' => 'flux-badge-dot',
        ];
    }

    protected function template(): string
    {
        return '<span class="{class}"></span>';
    }
}
