<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

/**
 * Toolbar widget — search, timestamps, expand/collapse, rerun, theme toggle.
 *
 * Layout: {heading}{controls}
 *
 * Options:
 *   - options          (array)  — root <div> attributes
 *   - headingOptions   (array)  — heading <span> attributes
 *   - searchOptions    (array)  — search <input> attributes
 *   - tsBtnOptions     (array)  — timestamp btn attributes
 *   - expandBtnOptions (array)  — expand all btn attributes
 *   - collapseBtnOptions (array) — collapse all btn attributes
 *   - rerunBtnOptions  (array)  — rerun btn attributes
 *   - themeBtnOptions  (array)  — theme toggle btn attributes
 *   - showRerun        (bool)   — show rerun button (default: true)
 *   - showThemeToggle  (bool)   — show theme toggle (default: true)
 *   - showSearch       (bool)   — show search input (default: true)
 *   - showTimestamps   (bool)   — show timestamps btn (default: true)
 *   - showExpand       (bool)   — show expand/collapse (default: true)
 *   - headingText      (string) — initial heading text
 *   - searchPlaceholder (string) — placeholder text
 *   - layout           (string) — template: '{heading}{controls}'
 *   - afterSearch      (string) — HTML injected after the search input
 */
class FluxToolbar extends FluxWidget
{
    protected array $headingOptions     = [];
    protected array $searchOptions      = [];
    protected array $tsBtnOptions       = [];
    protected array $expandBtnOptions   = [];
    protected array $collapseBtnOptions = [];
    protected array $rerunBtnOptions    = [];
    protected array $themeBtnOptions    = [];
    protected bool $showRerun           = true;
    protected bool $showThemeToggle     = true;
    protected bool $showSearch          = true;
    protected bool $showTimestamps      = true;
    protected bool $showExpand          = true;
    protected string $headingText       = 'Initializing…';
    protected string $searchPlaceholder = 'Search logs…';
    protected string $afterSearch       = '';

    protected function configure(array $config): void
    {
        $this->headingOptions     = $config['headingOptions'] ?? [];
        $this->searchOptions      = $config['searchOptions'] ?? [];
        $this->tsBtnOptions       = $config['tsBtnOptions'] ?? [];
        $this->expandBtnOptions   = $config['expandBtnOptions'] ?? [];
        $this->collapseBtnOptions = $config['collapseBtnOptions'] ?? [];
        $this->rerunBtnOptions    = $config['rerunBtnOptions'] ?? [];
        $this->themeBtnOptions    = $config['themeBtnOptions'] ?? [];
        $this->showRerun          = $config['showRerun'] ?? true;
        $this->showThemeToggle    = $config['showThemeToggle'] ?? true;
        $this->showSearch         = $config['showSearch'] ?? true;
        $this->showTimestamps     = $config['showTimestamps'] ?? true;
        $this->showExpand         = $config['showExpand'] ?? true;
        $this->headingText        = $config['headingText'] ?? 'Initializing…';
        $this->searchPlaceholder  = $config['searchPlaceholder'] ?? 'Search logs…';
        $this->afterSearch        = $config['afterSearch'] ?? '';
    }

    protected function defaultId(): string
    {
        return 'fx-toolbar';
    }

    protected function defaultLayout(): string
    {
        return '{heading}{controls}';
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

    protected function renderSections(): array
    {
        return [
            '{heading}'  => $this->renderHeading(),
            '{controls}' => $this->renderControls(),
        ];
    }

    // ── Named Render Methods (override any of these in subclass) ────────────

    protected function renderHeading(): string
    {
        $opts = $this->headingOptions;
        $class = $this->mergeClass('fw-semibold small flex-grow-1 text-truncate', $opts);
        $hId = htmlspecialchars($this->id . '-heading', ENT_QUOTES);
        return '<span class="' . htmlspecialchars($class, ENT_QUOTES) . '" id="' . $hId . '"'
             . $this->renderAttributes($opts) . '>'
             . htmlspecialchars($this->headingText, ENT_QUOTES)
             . '</span>';
    }

    protected function renderControls(): string
    {
        $html = '<div class="d-flex align-items-center gap-1 flex-shrink-0">';

        if ($this->showSearch)     $html .= $this->renderSearch();
        if ($this->showTimestamps) $html .= $this->renderTsBtn();
        if ($this->showExpand)     $html .= $this->renderExpandBtn() . $this->renderCollapseBtn();
        if ($this->showRerun)      $html .= $this->renderRerunBtn();
        if ($this->showThemeToggle) $html .= $this->renderThemeBtn();

        $html .= '</div>';
        return $html;
    }

    protected function renderSearch(): string
    {
        $opts = $this->searchOptions;
        $class = $this->mergeClass('form-control form-control-sm font-monospace', $opts);
        $sId = htmlspecialchars($this->id . '-search', ENT_QUOTES);
        $placeholder = htmlspecialchars($this->searchPlaceholder, ENT_QUOTES);

        // Remove placeholder from opts if it was passed — we handle it explicitly
        unset($opts['placeholder']);

        $html = '<div class="position-relative">'
              . '<i class="bi bi-search position-absolute top-50 translate-middle-y text-body-secondary" style="left:8px;font-size:11px;pointer-events:none"></i>'
              . '<input id="' . $sId . '" type="search"'
              . ' class="' . htmlspecialchars($class, ENT_QUOTES) . '"'
              . ' style="width:180px;padding-left:26px;font-size:12px"'
              . ' placeholder="' . $placeholder . '"'
              . ' autocomplete="off"'
              . $this->renderAttributes($opts) . '>'
              . '</div>';

        if ($this->afterSearch) {
            $html .= $this->afterSearch;
        }

        return $html;
    }

    protected function renderTsBtn(): string
    {
        $opts = $this->tsBtnOptions;
        $class = $this->mergeClass('btn btn-outline-secondary btn-sm', $opts);
        return '<button class="' . htmlspecialchars($class, ENT_QUOTES) . '"'
             . ' id="' . htmlspecialchars($this->id . '-ts-btn', ENT_QUOTES) . '"'
             . ' onclick="FluxUI.toggleTimestamps()" title="Toggle timestamps"'
             . $this->renderAttributes($opts) . '>'
             . '<i class="bi bi-clock"></i></button>';
    }

    protected function renderExpandBtn(): string
    {
        $opts = $this->expandBtnOptions;
        $class = $this->mergeClass('btn btn-outline-secondary btn-sm', $opts);
        return '<button class="' . htmlspecialchars($class, ENT_QUOTES) . '"'
             . ' onclick="FluxUI.expandAll()" title="Expand all"'
             . $this->renderAttributes($opts) . '>'
             . '<i class="bi bi-arrows-expand"></i></button>';
    }

    protected function renderCollapseBtn(): string
    {
        $opts = $this->collapseBtnOptions;
        $class = $this->mergeClass('btn btn-outline-secondary btn-sm', $opts);
        return '<button class="' . htmlspecialchars($class, ENT_QUOTES) . '"'
             . ' onclick="FluxUI.collapseAll()" title="Collapse all"'
             . $this->renderAttributes($opts) . '>'
             . '<i class="bi bi-arrows-collapse"></i></button>';
    }

    protected function renderRerunBtn(): string
    {
        $opts = $this->rerunBtnOptions;
        $class = $this->mergeClass('btn btn-outline-secondary btn-sm', $opts);
        return '<button class="' . htmlspecialchars($class, ENT_QUOTES) . '"'
             . ' id="' . htmlspecialchars($this->id . '-rerun-btn', ENT_QUOTES) . '"'
             . ' onclick="FluxUI.rerun()" disabled'
             . $this->renderAttributes($opts) . '>'
             . '<i class="bi bi-arrow-clockwise"></i>'
             . ' <span class="d-none d-sm-inline">Re-run</span>'
             . '</button>';
    }

    protected function renderThemeBtn(): string
    {
        $opts = $this->themeBtnOptions;
        $class = $this->mergeClass('btn btn-outline-secondary btn-sm', $opts);
        return '<button class="' . htmlspecialchars($class, ENT_QUOTES) . '"'
             . ' onclick="FluxUI.toggleTheme()" title="Toggle theme"'
             . $this->renderAttributes($opts) . '>'
             . '<i id="' . htmlspecialchars($this->id . '-theme-icon', ENT_QUOTES) . '" class="bi bi-moon-stars"></i>'
             . '</button>';
    }

    public function render(): string
    {
        $opts = $this->options;
        $class = $this->mergeClass('d-flex align-items-center gap-2 px-3 py-2 border-bottom bg-body-tertiary', $opts);

        $inner = $this->beforeContent
               . $this->renderLayout($this->renderSections())
               . $this->afterContent;

        return '<div id="' . htmlspecialchars($this->id, ENT_QUOTES) . '"'
             . ' class="' . htmlspecialchars($class, ENT_QUOTES) . '"'
             . $this->renderAttributes($opts) . '>'
             . $inner
             . '</div>';
    }
}
