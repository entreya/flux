<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Log;

use Entreya\Flux\Ui\FluxComponent;

/**
 * Empty slot — renders nothing by default.
 * Used as the default for optional slots (beforeSteps, afterSteps).
 */
class EmptySlot extends FluxComponent
{
    protected function defaults(): array
    {
        return ['id' => ''];
    }

    protected function template(): string
    {
        return '';
    }
}
