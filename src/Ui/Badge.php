<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

/**
 * Badge component — status indicator pill.
 *
 * Slots: dot, text
 */
class Badge extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'          => 'fx-badge',
            'class'       => 'badge rounded-pill text-bg-secondary d-inline-flex align-items-center gap-1',
            'initialText' => 'Connecting',
        ];
    }

    protected function slots(): array
    {
        return [
            'dot'  => Badge\Dot::class,
            'text' => Badge\Text::class,
        ];
    }

    protected function template(): string
    {
        return '<span id="{id}" class="{class}" data-status="pending">{slot:dot}{slot:text}</span>';
    }

    protected function style(): string
    {
        return <<<'CSS'
        .flux-badge-dot{width:8px;height:8px;border-radius:50%;background:currentColor;flex-shrink:0}
        [data-status="running"]{color:var(--flux-accent)!important}
        [data-status="running"] .flux-badge-dot{animation:pulse-dot 1.4s ease-in-out infinite}
        [data-status="success"] .flux-badge-dot{color:var(--flux-success)}
        [data-status="failure"] .flux-badge-dot{color:var(--flux-danger)}
        @keyframes pulse-dot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.8)}}
        CSS;
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('badge', $this->props['id']);
    }

    protected function childConfig(string $slotName): array
    {
        if ($slotName === 'text') {
            return [
                'props' => ['text' => $this->props['initialText']],
            ];
        }
        return [];
    }
}
