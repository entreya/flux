<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

use Entreya\Flux\Ui\Renderer\DetailsStepRenderer;
use Entreya\Flux\Ui\Renderer\StepRendererInterface;

/**
 * Log panel widget — step accordions and log lines container.
 *
 * Layout: {beforeSteps}{steps}{afterSteps}
 *
 * Slots: stepsContainer
 *
 * Options:
 *   - options            (array)           — root <div> attributes
 *   - stepRenderer       (string|object)   — class name or instance implementing StepRendererInterface
 *   - stepOptions        (array)           — default attributes for step containers (passed to JS)
 *   - stepHeaderOptions  (array)           — default attributes for step headers (passed to JS)
 *   - logBodyOptions     (array)           — default attributes for log body (passed to JS)
 *   - beforeSteps        (string)          — HTML inserted before the steps container
 *   - afterSteps         (string)          — HTML inserted after the steps container
 *   - jobHeaderTemplate  (string)          — HTML template for job headers
 *   - pluginOptions      (array)           — JS behavior config
 *   - pluginEvents       (array)           — JS event hooks
 *   - slots              (array)           — per-component render closures
 */
class FluxLogPanel extends FluxWidget
{
    /** @var StepRendererInterface */
    protected StepRendererInterface $stepRenderer;

    protected array $stepOptions       = [];
    protected array $stepHeaderOptions = [];
    protected array $logBodyOptions    = [];
    protected string $jobHeaderTemplate = '';
    protected string $beforeSteps      = '';
    protected string $afterSteps       = '';

    protected function configure(array $config): void
    {
        // Step renderer: accept class string or instance
        $renderer = $config['stepRenderer'] ?? DetailsStepRenderer::class;
        if (is_string($renderer)) {
            $renderer = new $renderer();
        }
        $this->stepRenderer = $renderer;

        $this->stepOptions       = $config['stepOptions'] ?? [];
        $this->stepHeaderOptions = $config['stepHeaderOptions'] ?? [];
        $this->logBodyOptions    = $config['logBodyOptions'] ?? [];
        $this->jobHeaderTemplate = $config['jobHeaderTemplate'] ?? '';
        $this->beforeSteps       = $config['beforeSteps'] ?? '';
        $this->afterSteps        = $config['afterSteps'] ?? '';

        // Register the step template and collapse method with FluxAsset
        FluxAsset::registerTemplate('step', $this->stepRenderer->jsTemplate());

        if ($this->jobHeaderTemplate) {
            FluxAsset::registerTemplate('jobHeader', $this->jobHeaderTemplate);
        }
        FluxAsset::registerPluginOptions('logPanel', [
            'collapseMethod' => $this->stepRenderer->collapseMethod(),
        ]);
    }

    protected function styles(): string
    {
        return <<<'CSS'
/* Step accordion */
.flux-step{border:none;background:transparent;margin:0 0 2px;border-radius:6px;overflow:hidden}
.flux-step-summary{display:flex;align-items:center;gap:8px;padding:6px 10px 6px 12px;cursor:pointer;user-select:none;list-style:none;min-height:36px;border-radius:6px;transition:background .1s}
.flux-step-summary::-webkit-details-marker{display:none}
.flux-step-summary:hover{background:var(--bs-secondary-bg)}
.flux-step[open]>.flux-step-summary{background:var(--bs-secondary-bg);border-radius:6px 6px 0 0}
/* Step icon */
.flux-step-ico{width:17px;height:17px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;font-size:9px;font-weight:800;border:1.5px solid var(--flux-muted);color:transparent;transition:all .2s}
.flux-step-ico.is-pending{border-color:var(--flux-muted)}
.flux-step-ico.is-running{border-color:var(--flux-accent);color:var(--flux-accent);animation:spin .9s linear infinite}
.flux-step-ico.is-success{background:var(--flux-success);border-color:var(--flux-success);color:#fff}
.flux-step-ico.is-failure{background:var(--flux-danger);border-color:var(--flux-danger);color:#fff}
.flux-step-ico.is-skipped{border-color:var(--flux-muted);opacity:.4}
.flux-step-name{flex:1;font-size:13px;font-weight:500;color:var(--bs-body-color);min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.flux-step-dur{font-size:11.5px;font-family:var(--font-mono);color:var(--bs-secondary-color);flex-shrink:0;margin-left:auto}
.flux-step-chevron{color:var(--bs-secondary-color);font-size:10px;transition:transform .2s;flex-shrink:0;margin-left:4px}
.flux-step[open] .flux-step-chevron{transform:rotate(90deg)}
/* Log body */
.flux-log-body{background:var(--flux-log-bg);border-top:1px solid var(--bs-border-color);border-radius:0 0 6px 6px;overflow:hidden;font-family:var(--font-mono)}
.flux-log-body:empty{display:none}
/* Log lines */
.flux-log-line{display:flex;align-items:flex-start;min-height:22px;line-height:22px;font-size:12.5px;position:relative;transition:background .05s}
.flux-log-line:hover{background:rgba(127,127,127,.04)}
.flux-log-line.is-hidden{display:none!important}
.flux-log-line.is-match{background:rgba(210,153,34,.07)!important}
.flux-log-line.is-match::before{content:'';position:absolute;left:0;top:0;bottom:0;width:2px;background:var(--flux-warning)}
.flux-lineno{flex-shrink:0;min-width:50px;padding:0 14px 0 8px;text-align:right;color:var(--flux-lineno-color);user-select:none;background:var(--flux-log-gutter);border-right:1px solid var(--bs-border-color);font-size:11.5px;transition:color .1s}
.flux-log-line:hover .flux-lineno{color:var(--bs-secondary-color)}
.flux-log-ts{flex-shrink:0;width:84px;padding:0 8px;color:var(--flux-lineno-color);user-select:none;font-size:11px;display:none}
.show-ts .flux-log-ts{display:block}
.flux-log-content{flex:1;padding:0 16px;white-space:pre-wrap;word-break:break-all;color:var(--bs-body-color)}
.flux-log-line[data-type="stderr"] .flux-log-content{color:var(--flux-warning)}
.flux-log-line[data-type="cmd"] .flux-log-content{color:var(--flux-cmd-color);font-style:italic}
/* Copy button */
.flux-log-copy{display:none;position:absolute;right:8px;top:50%;transform:translateY(-50%);background:var(--bs-secondary-bg);border:1px solid var(--bs-border-color);border-radius:4px;padding:0 6px;font-size:10.5px;color:var(--bs-secondary-color);cursor:pointer;line-height:18px;font-family:var(--font-ui);transition:color .1s;z-index:1}
.flux-log-copy:hover{color:var(--bs-body-color)}
.flux-log-line:hover .flux-log-copy{display:block}
@keyframes spin{to{transform:rotate(360deg)}}
CSS;
    }

    protected function defaultId(): string
    {
        return 'fx-steps';
    }

    protected function defaultLayout(): string
    {
        return '{beforeSteps}{steps}{afterSteps}';
    }

    protected function selectorMap(): array
    {
        return [
            'steps' => $this->id,
        ];
    }

    protected function renderSections(): array
    {
        return [
            '{beforeSteps}' => $this->beforeSteps,
            '{steps}'       => $this->renderStepsContainer(),
            '{afterSteps}'  => $this->afterSteps,
        ];
    }

    // ── Default Closure ─────────────────────────────────────────────────────

    protected function defaultClosure(): \Closure
    {
        return function (self $w): void {
            echo $w->beforeContent;
            echo $w->beforeSteps;
            echo $w->stepsContainer();
            echo $w->afterSteps;
            echo $w->afterContent;
        };
    }

    // ── Pure Open/Close Tags ────────────────────────────────────────────────

    protected function openTarget(): string
    {
        return $this->openTag('div', $this->id, 'flex-grow-1 overflow-auto', $this->options);
    }

    protected function closeTarget(): string
    {
        return '</div>';
    }

    // ── Public Closure API ───────────────────────────────────────────────

    public function stepsContainer(): string
    {
        return $this->renderStepsContainer();
    }

    // ── Internal Render Methods (with Slot Dispatch) ────────────────────────

    protected function renderStepsContainer(): string
    {
        $props = [
            'id' => $this->id,
        ];

        return $this->slot('stepsContainer', $props, function () {
            // This is just the container — JS fills it dynamically
            return '';
        });
    }
}
