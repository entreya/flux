<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

/**
 * Progress bar component.
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
        return [
            'bar' => Progress\Bar::class,
        ];
    }

    protected function template(): string
    {
        return '<div class="{class}" style="height:{height}">{slot:bar}</div>';
    }

    protected function childConfig(string $slotName): array
    {
        if ($slotName === 'bar') {
            return [
                'props' => [
                    'id'    => $this->props['id'],
                    'class' => $this->props['barClass'],
                ],
            ];
        }
        return [];
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('progress', $this->props['id']);
    }
}
