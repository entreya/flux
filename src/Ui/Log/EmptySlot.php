<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Log;

use Entreya\Flux\Ui\FluxComponent;

class EmptySlot extends FluxComponent
{
    protected function defaults(): array
    {
        return [];
    }

    protected function template(): string
    {
        return '';
    }
}
