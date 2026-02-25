<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

/**
 * Sidebar widget — job list panel.
 *
 * Layout: {jobList}{footer}
 *
 * Options:
 *   - options         (array)  — root <nav> HTML attributes
 *   - listOptions     (array)  — job list container attributes
 *   - footerOptions   (array)  — footer container attributes
 *   - itemOptions     (array)  — default per-job-item attributes (applied via JS)
 *   - workflowName    (string) — workflow name shown in footer
 *   - trigger         (string) — trigger label (default: 'manual')
 *   - showFooter      (bool)   — show the runner info footer (default: true)
 *   - emptyText       (string) — placeholder text when no jobs
 *   - layout          (string) — template: '{jobList}{footer}'
 *   - beforeContent / afterContent
 */
class FluxSidebar extends FluxWidget
{
    protected array $listOptions   = [];
    protected array $footerOptions = [];
    protected array $itemOptions   = [];
    protected string $workflowName = '';
    protected string $trigger      = 'manual';
    protected bool $showFooter     = true;
    protected string $emptyText    = 'Waiting for workflow…';

    protected function configure(array $config): void
    {
        $this->listOptions   = $config['listOptions'] ?? [];
        $this->footerOptions = $config['footerOptions'] ?? [];
        $this->itemOptions   = $config['itemOptions'] ?? [];
        $this->workflowName  = $config['workflowName'] ?? '';
        $this->trigger       = $config['trigger'] ?? 'manual';
        $this->showFooter    = $config['showFooter'] ?? true;
        $this->emptyText     = $config['emptyText'] ?? 'Waiting for workflow…';
    }

    protected function styles(): string
    {
        return <<<'CSS'
.flux-sidebar-empty{font-size:12px;color:var(--bs-secondary-color);font-style:italic;padding:4px}
.flux-job-icon{width:18px;height:18px;border-radius:50%;display:inline-grid;place-items:center;flex-shrink:0;font-size:10px;font-weight:700;border:1.5px solid var(--flux-muted);color:transparent;transition:all .2s;position:relative}
.flux-job-icon.is-running{border-color:var(--flux-accent);color:var(--flux-accent)}
.flux-job-icon.is-running::after{content:'';position:absolute;inset:-4px;border-radius:50%;border:1.5px solid var(--flux-accent);opacity:0;animation:ring-out 1.5s ease-out infinite}
.flux-job-icon.is-success{background:var(--flux-success);border-color:var(--flux-success);color:#fff}
.flux-job-icon.is-failure{background:var(--flux-danger);border-color:var(--flux-danger);color:#fff}
.flux-job-icon.is-skipped{border-color:var(--flux-muted);color:var(--bs-secondary-color);opacity:.45}
@keyframes ring-out{0%{transform:scale(1);opacity:.5}100%{transform:scale(2);opacity:0}}
CSS;
    }

    protected function defaultId(): string
    {
        return 'fx-sidebar';
    }

    protected function defaultLayout(): string
    {
        return '{jobList}{footer}';
    }

    protected function selectorMap(): array
    {
        return [
            'jobList' => $this->id . '-job-list',
        ];
    }

    protected function renderSections(): array
    {
        return [
            '{jobList}' => $this->renderJobList(),
            '{footer}'  => $this->showFooter ? $this->renderFooter() : '',
        ];
    }

    protected function renderJobList(): string
    {
        $opts = $this->listOptions;
        $class = $this->mergeClass('list-group list-group-flush', $opts);
        $listId = htmlspecialchars($this->id . '-job-list', ENT_QUOTES);
        $empty = htmlspecialchars($this->emptyText, ENT_QUOTES);

        return '<div class="flex-grow-1 overflow-auto p-2">'
             . '<p class="text-uppercase text-body-secondary fw-semibold small mb-2 px-1" style="font-size:11px;letter-spacing:.5px">Jobs</p>'
             . '<div id="' . $listId . '" class="' . htmlspecialchars($class, ENT_QUOTES) . '"'
             . $this->renderAttributes($opts) . '>'
             . '<div class="flux-sidebar-empty text-body-secondary fst-italic small p-2">' . $empty . '</div>'
             . '</div>'
             . '</div>';
    }

    protected function renderFooter(): string
    {
        $opts = $this->footerOptions;
        $class = $this->mergeClass('border-top small p-2', $opts);
        $wf = htmlspecialchars($this->workflowName, ENT_QUOTES);
        $trigger = htmlspecialchars($this->trigger, ENT_QUOTES);

        $html = '<div class="' . htmlspecialchars($class, ENT_QUOTES) . '"'
              . $this->renderAttributes($opts) . '>';

        if ($wf) {
            $html .= '<div class="d-flex justify-content-between px-1 py-1">'
                   . '<span class="text-body-secondary">Workflow</span>'
                   . '<span class="text-body-emphasis font-monospace text-truncate" style="max-width:140px">' . $wf . '</span>'
                   . '</div>';
        }

        $html .= '<div class="d-flex justify-content-between px-1 py-1">'
               . '<span class="text-body-secondary">Trigger</span>'
               . '<span class="text-body-emphasis font-monospace">' . $trigger . '</span>'
               . '</div>';

        $html .= '<div class="d-flex justify-content-between px-1 py-1">'
               . '<span class="text-body-secondary">Runner</span>'
               . '<span class="text-body-emphasis font-monospace">php-' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '</span>'
               . '</div>';

        $html .= '</div>';
        return $html;
    }

    public function render(): string
    {
        $opts = $this->options;
        $class = $this->mergeClass('d-flex flex-column border-end bg-body-tertiary', $opts);

        $inner = $this->beforeContent
               . $this->renderLayout($this->renderSections())
               . $this->afterContent;

        return '<nav id="' . htmlspecialchars($this->id, ENT_QUOTES) . '" '
             . 'class="' . htmlspecialchars($class, ENT_QUOTES) . '"'
             . $this->renderAttributes($opts) . '>'
             . $inner
             . '</nav>';
    }
}
