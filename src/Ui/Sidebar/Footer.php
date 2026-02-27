<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Sidebar;

use Entreya\Flux\Ui\FluxComponent;

class Footer extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'           => 'fx-sidebar-footer',
            'class'        => 'border-top small p-2',
            'workflowName' => '',
            'trigger'      => 'manual',
            'phpVersion'   => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
        ];
    }

    protected function template(): string
    {
        $html = '<div id="{id}" class="{class}">';

        // Workflow row only if name is set — handled in buildContent override
        $html .= '{slot:workflowRow}';
        $html .= '<div class="d-flex justify-content-between px-1 py-1">'
               . '<span class="text-body-secondary">Trigger</span>'
               . '<span class="text-body-emphasis font-monospace">{trigger}</span>'
               . '</div>';
        $html .= '<div class="d-flex justify-content-between px-1 py-1">'
               . '<span class="text-body-secondary">Runner</span>'
               . '<span class="text-body-emphasis font-monospace">php-{phpVersion}</span>'
               . '</div>';
        $html .= '</div>';

        return $html;
    }

    protected function resolveSlots(): array
    {
        $wf = $this->props['workflowName'] ?? '';
        $row = '';
        if ($wf !== '') {
            $row = '<div class="d-flex justify-content-between px-1 py-1">'
                 . '<span class="text-body-secondary">Workflow</span>'
                 . '<span class="text-body-emphasis font-monospace text-truncate" style="max-width:140px">'
                 . htmlspecialchars($wf, ENT_QUOTES) . '</span>'
                 . '</div>';
        }

        return ['workflowRow' => $row];
    }
}
