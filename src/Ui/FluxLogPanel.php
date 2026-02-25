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
 * Options:
 *   - options            (array)           — root <div> attributes
 *   - stepRenderer       (string|object)   — class name or instance implementing StepRendererInterface
 *   - stepOptions        (array)           — default attributes for step containers (passed to JS)
 *   - stepHeaderOptions  (array)           — default attributes for step headers (passed to JS)
 *   - logBodyOptions     (array)           — default attributes for log body (passed to JS)
 *   - beforeSteps        (string)          — HTML inserted before the steps container
 *   - afterSteps         (string)          — HTML inserted after the steps container
 *   - pluginOptions      (array)           — JS behavior config:
 *       - autoCollapse   (bool)  — auto-close steps on success (default: true)
 *       - autoScroll     (bool)  — auto-scroll on new log lines (default: true)
 *   - pluginEvents       (array)           — JS event hooks
 *   - layout             (string)          — template: '{beforeSteps}{steps}{afterSteps}'
 */
class FluxLogPanel extends FluxWidget
{
    /** @var StepRendererInterface */
    protected StepRendererInterface $stepRenderer;

    protected array $stepOptions       = [];
    protected array $stepHeaderOptions = [];
    protected array $logBodyOptions    = [];
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
        $this->beforeSteps       = $config['beforeSteps'] ?? '';
        $this->afterSteps        = $config['afterSteps'] ?? '';

        // Register the step template and collapse method with FluxAsset
        FluxAsset::registerTemplate('step', $this->stepRenderer->jsTemplate());
        FluxAsset::registerPluginOptions('logPanel', [
            'collapseMethod' => $this->stepRenderer->collapseMethod(),
        ]);
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

    protected function renderStepsContainer(): string
    {
        // This is just the container — JS fills it dynamically
        return '';
    }

    public function render(): string
    {
        $opts = $this->options;
        $class = $this->mergeClass('flex-grow-1 overflow-auto', $opts);

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
