<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Sidebar;

use Entreya\Flux\Ui\FluxComponent;

/**
 * Sidebar component — job list navigation panel.
 *
 * Slots: jobList, footer
 */
class Sidebar extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'    => 'fx-sidebar',
            'class' => 'd-flex flex-column border-end bg-body-tertiary',
        ];
    }

    protected function slots(): array
    {
        return [
            'jobList' => JobList::class,
            'footer'  => Footer::class,
        ];
    }

    protected function template(): string
    {
        return '<nav id="{id}" class="{class}">{slot:jobList}{slot:footer}</nav>';
    }

    protected function style(): string
    {
        return <<<'CSS'
        .flux-job-icon{width:18px;height:18px;border-radius:50%;display:inline-grid;place-items:center;flex-shrink:0;font-size:10px;font-weight:700;border:1.5px solid var(--flux-muted);color:transparent;transition:all .2s;position:relative}
        .flux-job-icon.is-running{border-color:var(--flux-accent);color:var(--flux-accent)}
        .flux-job-icon.is-running::after{content:'';position:absolute;inset:-4px;border-radius:50%;border:1.5px solid var(--flux-accent);opacity:0;animation:ring-out 1.5s ease-out infinite}
        .flux-job-icon.is-success{background:var(--flux-success);border-color:var(--flux-success);color:#fff}
        .flux-job-icon.is-failure{background:var(--flux-danger);border-color:var(--flux-danger);color:#fff}
        .flux-job-icon.is-skipped{border-color:var(--flux-muted);color:var(--bs-secondary-color);opacity:.45}
        @keyframes ring-out{0%{transform:scale(1);opacity:.5}100%{transform:scale(2);opacity:0}}
        CSS;
    }

    /**
     * Pass parent props down to child slots.
     */
    protected function childConfig(string $slotName): array
    {
        if ($slotName === 'footer') {
            return [
                'props' => [
                    'workflowName' => $this->props['workflowName'] ?? '',
                    'trigger'      => $this->props['trigger'] ?? 'manual',
                ],
            ];
        }
        return [];
    }
}
