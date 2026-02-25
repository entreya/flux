<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

/**
 * Toolbar widget — search input, timestamp toggle, expand/collapse buttons.
 *
 * Usage:
 *   echo FluxToolbar::widget(['id' => 'myToolbar']);
 *   echo FluxToolbar::widget(['id' => 'myToolbar', 'options' => ['class' => 'px-3']]);
 */
class FluxToolbar extends FluxWidget
{
    /** @var bool Show the Re-run button */
    protected bool $showRerun = true;

    /** @var bool Show the theme toggle button */
    protected bool $showThemeToggle = true;

    public function __construct(array $config = [])
    {
        $this->showRerun = $config['showRerun'] ?? true;
        $this->showThemeToggle = $config['showThemeToggle'] ?? true;
        parent::__construct($config);
    }

    protected function defaultId(): string
    {
        return 'fx-toolbar';
    }

    protected function selectorMap(): array
    {
        return [
            'search'     => $this->id . '-search',
            'rerunBtn'   => $this->id . '-rerun-btn',
            'themeIcon'  => $this->id . '-theme-icon',
            'tsBtn'      => $this->id . '-ts-btn',
            'jobHeading' => $this->id . '-heading',
        ];
    }

    public function render(): string
    {
        $class = $this->mergeClass('d-flex align-items-center gap-2 px-3 py-2 border-bottom bg-body-tertiary');
        $id = htmlspecialchars($this->id, ENT_QUOTES);

        $html = '<div id="' . $id . '" class="' . htmlspecialchars($class, ENT_QUOTES) . '"'
              . $this->renderAttributes() . '>';

        // Title
        $html .= '<span class="fw-semibold small flex-grow-1 text-truncate" id="' . $id . '-heading">Initializing…</span>';

        // Controls group
        $html .= '<div class="d-flex align-items-center gap-1 flex-shrink-0">';

        // Search
        $html .= '<div class="position-relative">';
        $html .= '<i class="bi bi-search position-absolute top-50 translate-middle-y text-body-secondary" style="left:8px;font-size:11px;pointer-events:none"></i>';
        $html .= '<input id="' . $id . '-search" type="search" class="form-control form-control-sm font-monospace" '
                . 'style="width:180px;padding-left:26px;font-size:12px" placeholder="Search logs…" autocomplete="off">';
        $html .= '</div>';

        // Timestamp toggle
        $html .= '<button class="btn btn-outline-secondary btn-sm" id="' . $id . '-ts-btn" '
                . 'onclick="FluxUI.toggleTimestamps()" title="Toggle timestamps">'
                . '<i class="bi bi-clock"></i></button>';

        // Expand all
        $html .= '<button class="btn btn-outline-secondary btn-sm" onclick="FluxUI.expandAll()" title="Expand all">'
                . '<i class="bi bi-arrows-expand"></i></button>';

        // Collapse all
        $html .= '<button class="btn btn-outline-secondary btn-sm" onclick="FluxUI.collapseAll()" title="Collapse all">'
                . '<i class="bi bi-arrows-collapse"></i></button>';

        // Re-run
        if ($this->showRerun) {
            $html .= '<button class="btn btn-outline-secondary btn-sm" id="' . $id . '-rerun-btn" '
                    . 'onclick="FluxUI.rerun()" disabled>'
                    . '<i class="bi bi-arrow-clockwise"></i>'
                    . ' <span class="d-none d-sm-inline">Re-run</span>'
                    . '</button>';
        }

        // Theme toggle
        if ($this->showThemeToggle) {
            $html .= '<button class="btn btn-outline-secondary btn-sm" onclick="FluxUI.toggleTheme()" title="Toggle theme">'
                    . '<i id="' . $id . '-theme-icon" class="bi bi-moon-stars"></i>'
                    . '</button>';
        }

        $html .= '</div>'; // controls
        $html .= '</div>'; // toolbar

        return $html;
    }
}
