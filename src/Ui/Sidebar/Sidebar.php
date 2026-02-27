<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Sidebar;

use Entreya\Flux\Ui\FluxComponent;
use Entreya\Flux\Ui\FluxRenderer;

/**
 * Sidebar — job list + footer with workflow metadata.
 *
 * Slots: jobList, footer
 */
class Sidebar extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'           => 'fx-sidebar',
            'class'        => 'd-flex flex-column border-end bg-body-tertiary',
            'workflowName' => '',
            'trigger'      => 'manual',
        ];
    }

    protected function slots(): array
    {
        return [
            'jobList' => JobList::class,
            'footer'  => Footer::class,
        ];
    }

    protected function childConfig(string $slotName): array
    {
        return match ($slotName) {
            'footer' => [
                'props' => [
                    'workflowName' => $this->props['workflowName'],
                    'trigger'      => $this->props['trigger'],
                ],
            ],
            default => [],
        };
    }

    protected function template(): string
    {
        return '<nav id="{id}" class="{class}" style="width:260px;min-width:260px">'
             . '{slot:jobList}'
             . '<div class="mt-auto">{slot:footer}</div>'
             . '</nav>';
    }

    protected function style(): string
    {
        return <<<'CSS'
        .flux-job-icon {
            width: 14px; height: 14px; border-radius: 50%;
            display: inline-grid; place-items: center; flex-shrink: 0;
            font-size: 8px; font-weight: 800;
            border: 1.5px solid var(--flux-muted); color: transparent;
        }
        .flux-job-icon.is-running { border-color: var(--flux-accent); color: var(--flux-accent); animation: spin .9s linear infinite; }
        .flux-job-icon.is-success { background: var(--flux-success); border-color: var(--flux-success); color: #fff; }
        .flux-job-icon.is-failure { background: var(--flux-danger); border-color: var(--flux-danger); color: #fff; }
        .flux-job-icon.is-skipped { border-color: var(--flux-muted); opacity: .4; }
        CSS;
    }
}
