<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

use Entreya\Flux\Ui\Progress\Bar;

/**
 * Progress bar — tracks workflow completion percentage.
 *
 * Slots: bar
 */
class Progress extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'       => 'fx-progress',
            'class'    => 'progress',
            'height'   => '2px',
            'barClass' => 'progress-bar bg-primary',
        ];
    }

    protected function slots(): array
    {
        return ['bar' => Bar::class];
    }

    protected function childConfig(string $slotName): array
    {
        return match ($slotName) {
            'bar' => ['props' => ['class' => $this->props['barClass']]],
            default => [],
        };
    }

    protected function template(): string
    {
        return '<div class="{class}" style="height:{height}">{slot:bar}</div>';
    }
}
