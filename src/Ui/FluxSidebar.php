<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

/**
 * Sidebar widget — job list panel.
 *
 * Usage:
 *   echo FluxSidebar::widget(['id' => 'mySidebar']);
 *   echo FluxSidebar::widget(['id' => 'mySidebar', 'options' => ['style' => 'width:280px']]);
 */
class FluxSidebar extends FluxWidget
{
    /** @var string Workflow name shown in footer */
    protected string $workflowName = '';

    /** @var string Trigger label */
    protected string $trigger = 'manual';

    public function __construct(array $config = [])
    {
        $this->workflowName = $config['workflowName'] ?? '';
        $this->trigger = $config['trigger'] ?? 'manual';
        parent::__construct($config);
    }

    protected function defaultId(): string
    {
        return 'fx-sidebar';
    }

    protected function selectorMap(): array
    {
        return [
            'jobList' => $this->id . '-job-list',
        ];
    }

    public function render(): string
    {
        $class = $this->mergeClass('d-flex flex-column border-end bg-body-tertiary');
        $listId = htmlspecialchars($this->id . '-job-list', ENT_QUOTES);
        $wfName = htmlspecialchars($this->workflowName, ENT_QUOTES);
        $trigger = htmlspecialchars($this->trigger, ENT_QUOTES);

        $html = '<nav id="' . htmlspecialchars($this->id, ENT_QUOTES) . '" '
              . 'class="' . htmlspecialchars($class, ENT_QUOTES) . '"'
              . $this->renderAttributes() . '>';

        // Scrollable job list
        $html .= '<div class="flex-grow-1 overflow-auto p-2">';
        $html .= '<p class="text-uppercase text-body-secondary fw-semibold small mb-2 px-1" style="font-size:11px;letter-spacing:.5px">Jobs</p>';
        $html .= '<div id="' . $listId . '" class="list-group list-group-flush">';
        $html .= '<div class="flux-sidebar-empty text-body-secondary fst-italic small p-2">Waiting for workflow…</div>';
        $html .= '</div>';
        $html .= '</div>';

        // Footer
        if ($wfName || $trigger) {
            $html .= '<div class="border-top small p-2">';
            if ($wfName) {
                $html .= '<div class="d-flex justify-content-between px-1 py-1">';
                $html .= '<span class="text-body-secondary">Workflow</span>';
                $html .= '<span class="text-body-emphasis font-monospace text-truncate" style="max-width:140px">' . $wfName . '</span>';
                $html .= '</div>';
            }
            $html .= '<div class="d-flex justify-content-between px-1 py-1">';
            $html .= '<span class="text-body-secondary">Trigger</span>';
            $html .= '<span class="text-body-emphasis font-monospace">' . $trigger . '</span>';
            $html .= '</div>';
            $html .= '<div class="d-flex justify-content-between px-1 py-1">';
            $html .= '<span class="text-body-secondary">Runner</span>';
            $html .= '<span class="text-body-emphasis font-monospace">php-' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '</span>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</nav>';
        return $html;
    }
}
