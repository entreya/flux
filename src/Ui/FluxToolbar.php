<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

/**
 * Toolbar widget — search, timestamps, expand/collapse, rerun, theme toggle.
 *
 * Layout: {heading}{controls}
 *
 * Slots: heading, search, btnTimestamps, btnExpand, btnCollapse, btnRerun, btnTheme, controls
 *
 * Options:
 *   - options            (array)  — root <div> attributes
 *   - headingOptions     (array)  — heading <span> attributes
 *   - searchOptions      (array)  — search <input> attributes
 *   - tsBtnOptions       (array)  — timestamp btn attributes
 *   - expandBtnOptions   (array)  — expand all btn attributes
 *   - collapseBtnOptions (array)  — collapse all btn attributes
 *   - rerunBtnOptions    (array)  — rerun btn attributes
 *   - themeBtnOptions    (array)  — theme toggle btn attributes
 *   - showRerun          (bool)   — show rerun button (default: true)
 *   - showThemeToggle    (bool)   — show theme toggle (default: true)
 *   - showSearch         (bool)   — show search input (default: true)
 *   - showTimestamps     (bool)   — show timestamps btn (default: true)
 *   - showExpand         (bool)   — show expand/collapse (default: true)
 *   - headingText        (string) — initial heading text
 *   - searchPlaceholder  (string) — placeholder text
 *   - afterSearch        (string) — HTML injected after the search input
 *   - slots              (array)  — per-component render closures
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

    // ── Default Closure ─────────────────────────────────────────────────────

    protected function defaultClosure(): \Closure
    {
        return function (self $w): void {
            echo $w->beforeContent;
            echo $w->heading();
            echo $w->controls();
            echo $w->afterContent;
        };
    }

    // ── Pure Open/Close Tags ────────────────────────────────────────────────

    protected function openTarget(): string
    {
        return $this->openTag('div', $this->id, 'd-flex align-items-center gap-2 px-3 py-2 border-bottom bg-body-tertiary', $this->options);
    }

    protected function closeTarget(): string
    {
        return '</div>';
    }

    // ── Public Closure API ───────────────────────────────────────────────

    public function heading(): string
    {
        return $this->renderHeading();
    }

    public function search(): string
    {
        return $this->renderSearch();
    }

    public function btnTimestamps(): string
    {
        return $this->renderTsBtn();
    }

    public function btnExpand(): string
    {
        return $this->renderExpandBtn();
    }

    public function btnCollapse(): string
    {
        return $this->renderCollapseBtn();
    }

    public function btnRerun(): string
    {
        return $this->renderRerunBtn();
    }

    public function btnTheme(): string
    {
        return $this->renderThemeBtn();
    }

    public function controls(): string
    {
        return $this->renderControls();
    }

    // ── Internal Render Methods (with Slot Dispatch) ────────────────────────

    protected function renderHeading(): string
    {
        $opts  = $this->headingOptions;
        $class = $this->mergeClass('fw-semibold small flex-grow-1 text-truncate', $opts);
        $hId   = $this->id . '-heading';

        $props = [
            'id'    => $hId,
            'class' => $class,
            'text'  => $this->headingText,
            'attrs' => $opts,
        ];

        return $this->slot('heading', $props, function () use ($props) {
            return '<span class="' . htmlspecialchars($props['class'], ENT_QUOTES) . '"'
                 . ' id="' . htmlspecialchars($props['id'], ENT_QUOTES) . '"'
                 . $this->renderAttributes($props['attrs']) . '>'
                 . htmlspecialchars($props['text'], ENT_QUOTES)
                 . '</span>';
        });
    }

    protected function renderControls(): string
    {
        $props = [
            'showSearch'      => $this->showSearch,
            'showTimestamps'  => $this->showTimestamps,
            'showExpand'      => $this->showExpand,
            'showRerun'       => $this->showRerun,
            'showThemeToggle' => $this->showThemeToggle,
        ];

        return $this->slot('controls', $props, function () {
            $html = '<div class="d-flex align-items-center gap-1 flex-shrink-0">';

            if ($this->showSearch)      $html .= $this->renderSearch();
            if ($this->showTimestamps)  $html .= $this->renderTsBtn();
            if ($this->showExpand)      $html .= $this->renderExpandBtn() . $this->renderCollapseBtn();
            if ($this->showRerun)       $html .= $this->renderRerunBtn();
            if ($this->showThemeToggle) $html .= $this->renderThemeBtn();

            $html .= '</div>';
            return $html;
        });
    }

    protected function renderSearch(): string
    {
        $opts  = $this->searchOptions;
        $class = $this->mergeClass('form-control form-control-sm font-monospace', $opts);
        $sId   = $this->id . '-search';

        // Remove placeholder from opts — we handle it explicitly
        unset($opts['placeholder']);

        $props = [
            'id'          => $sId,
            'class'       => $class,
            'placeholder' => $this->searchPlaceholder,
            'afterSearch' => $this->afterSearch,
            'attrs'       => $opts,
        ];

        return $this->slot('search', $props, function () use ($props) {
            $sId   = htmlspecialchars($props['id'], ENT_QUOTES);
            $class = htmlspecialchars($props['class'], ENT_QUOTES);
            $ph    = htmlspecialchars($props['placeholder'], ENT_QUOTES);

            $html = '<div class="position-relative">'
                  . '<i class="bi bi-search position-absolute top-50 translate-middle-y text-body-secondary" style="left:8px;font-size:11px;pointer-events:none"></i>'
                  . '<input id="' . $sId . '" type="search"'
                  . ' class="' . $class . '"'
                  . ' style="width:180px;padding-left:26px;font-size:12px"'
                  . ' placeholder="' . $ph . '"'
                  . ' autocomplete="off"'
                  . $this->renderAttributes($props['attrs']) . '>'
                  . '</div>';

            if ($props['afterSearch']) {
                $html .= $props['afterSearch'];
            }

            return $html;
        });
    }

    protected function renderTsBtn(): string
    {
        $opts  = $this->tsBtnOptions;
        $class = $this->mergeClass('btn btn-outline-secondary btn-sm', $opts);
        $btnId = $this->id . '-ts-btn';

        $props = [
            'id'    => $btnId,
            'class' => $class,
            'attrs' => $opts,
        ];

        return $this->slot('btnTimestamps', $props, function () use ($props) {
            return '<button class="' . htmlspecialchars($props['class'], ENT_QUOTES) . '"'
                 . ' id="' . htmlspecialchars($props['id'], ENT_QUOTES) . '"'
                 . ' onclick="FluxUI.toggleTimestamps()" title="Toggle timestamps"'
                 . $this->renderAttributes($props['attrs']) . '>'
                 . '<i class="bi bi-clock"></i></button>';
        });
    }

    protected function renderExpandBtn(): string
    {
        $opts  = $this->expandBtnOptions;
        $class = $this->mergeClass('btn btn-outline-secondary btn-sm', $opts);

        $props = [
            'class' => $class,
            'attrs' => $opts,
        ];

        return $this->slot('btnExpand', $props, function () use ($props) {
            return '<button class="' . htmlspecialchars($props['class'], ENT_QUOTES) . '"'
                 . ' onclick="FluxUI.expandAll()" title="Expand all"'
                 . $this->renderAttributes($props['attrs']) . '>'
                 . '<i class="bi bi-arrows-expand"></i></button>';
        });
    }

    protected function renderCollapseBtn(): string
    {
        $opts  = $this->collapseBtnOptions;
        $class = $this->mergeClass('btn btn-outline-secondary btn-sm', $opts);

        $props = [
            'class' => $class,
            'attrs' => $opts,
        ];

        return $this->slot('btnCollapse', $props, function () use ($props) {
            return '<button class="' . htmlspecialchars($props['class'], ENT_QUOTES) . '"'
                 . ' onclick="FluxUI.collapseAll()" title="Collapse all"'
                 . $this->renderAttributes($props['attrs']) . '>'
                 . '<i class="bi bi-arrows-collapse"></i></button>';
        });
    }

    protected function renderRerunBtn(): string
    {
        $opts  = $this->rerunBtnOptions;
        $class = $this->mergeClass('btn btn-outline-secondary btn-sm', $opts);
        $btnId = $this->id . '-rerun-btn';

        $props = [
            'id'    => $btnId,
            'class' => $class,
            'attrs' => $opts,
        ];

        return $this->slot('btnRerun', $props, function () use ($props) {
            return '<button class="' . htmlspecialchars($props['class'], ENT_QUOTES) . '"'
                 . ' id="' . htmlspecialchars($props['id'], ENT_QUOTES) . '"'
                 . ' onclick="FluxUI.rerun()" disabled'
                 . $this->renderAttributes($props['attrs']) . '>'
                 . '<i class="bi bi-arrow-clockwise"></i>'
                 . ' <span class="d-none d-sm-inline">Re-run</span>'
                 . '</button>';
        });
    }

    protected function renderThemeBtn(): string
    {
        $opts    = $this->themeBtnOptions;
        $class   = $this->mergeClass('btn btn-outline-secondary btn-sm', $opts);
        $iconId  = $this->id . '-theme-icon';

        $props = [
            'iconId' => $iconId,
            'class'  => $class,
            'attrs'  => $opts,
        ];

        return $this->slot('btnTheme', $props, function () use ($props) {
            return '<button class="' . htmlspecialchars($props['class'], ENT_QUOTES) . '"'
                 . ' onclick="FluxUI.toggleTheme()" title="Toggle theme"'
                 . $this->renderAttributes($props['attrs']) . '>'
                 . '<i id="' . htmlspecialchars($props['iconId'], ENT_QUOTES) . '" class="bi bi-moon-stars"></i>'
                 . '</button>';
        });
    }
}
