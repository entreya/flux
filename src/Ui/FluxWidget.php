<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

/**
 * Abstract base for all Flux UI widgets.
 *
 * Provides Kartik/GridView-level customization:
 *   - layout        — template string with {placeholders} for named sections
 *   - options       — HTML attributes for the root element
 *   - *Options      — per-sub-element HTML attributes (defined in subclasses)
 *   - pluginOptions — passed to FluxUI.init() as plugin config
 *   - pluginEvents  — JS event hooks: event name → raw JS function string
 *   - beforeContent / afterContent — inject arbitrary HTML
 *
 * Usage:
 *   echo FluxToolbar::widget([
 *       'id'            => 'myToolbar',
 *       'layout'        => '{heading} {search} {buttons}',
 *       'options'       => ['class' => 'bg-dark'],
 *       'searchOptions' => ['class' => 'form-control-lg'],
 *       'pluginOptions' => ['autoCollapse' => false],
 *       'pluginEvents'  => ['workflow_complete' => 'function() { alert("done"); }'],
 *   ]);
 */
abstract class FluxWidget
{
    /** @var string HTML element ID */
    protected string $id;

    /** @var array HTML attributes for the root element */
    protected array $options;

    /** @var string Layout template with {placeholders} */
    protected string $layout;

    /** @var array Plugin options passed to JS */
    protected array $pluginOptions;

    /** @var array<string, string> JS event hooks */
    protected array $pluginEvents;

    /** @var string Arbitrary HTML before widget content */
    protected string $beforeContent;

    /** @var string Arbitrary HTML after widget content */
    protected string $afterContent;

    public function __construct(array $config = [])
    {
        $this->id            = $config['id'] ?? $this->defaultId();
        $this->options       = $config['options'] ?? [];
        $this->layout        = $config['layout'] ?? $this->defaultLayout();
        $this->pluginOptions = $config['pluginOptions'] ?? [];
        $this->pluginEvents  = $config['pluginEvents'] ?? [];
        $this->beforeContent = $config['beforeContent'] ?? '';
        $this->afterContent  = $config['afterContent'] ?? '';

        // Let subclasses pick up their own named config keys
        $this->configure($config);

        // Register selectors with the asset accumulator
        foreach ($this->selectorMap() as $key => $elementId) {
            FluxAsset::register($key, $elementId);
        }

        // Register plugin options under the widget's namespace
        if (!empty($this->pluginOptions)) {
            FluxAsset::registerPluginOptions($this->pluginNamespace(), $this->pluginOptions);
        }

        // Register event hooks
        foreach ($this->pluginEvents as $event => $handler) {
            FluxAsset::registerEvent($event, $handler);
        }
    }

    /**
     * Static factory — matches Yii2 widget() convention.
     */
    public static function widget(array $config = []): string
    {
        $widget = new static($config);
        return $widget->render();
    }

    /**
     * Render the complete widget HTML.
     */
    public function render(): string
    {
        // Lazy CSS registration — only registers when widget is actually rendered
        $this->registerStyleOnce();

        $sections = $this->renderSections();
        $content = $this->renderLayout($sections);

        $before = $this->beforeContent;
        $after  = $this->afterContent;

        return $before . $content . $after;
    }

    // ── Overridable Methods (GridView pattern) ──────────────────────────────

    /**
     * Return the default element ID.
     */
    abstract protected function defaultId(): string;

    /**
     * Return the default layout template string.
     * Subclasses define their own placeholders.
     */
    abstract protected function defaultLayout(): string;

    /**
     * Return an array of {placeholder} => rendered HTML.
     * Each section is a named render method.
     *
     * @return array<string, string>
     */
    abstract protected function renderSections(): array;

    /**
     * Configure subclass-specific properties from the config array.
     * Override this to pick up named *Options and other sub-configs.
     */
    protected function configure(array $config): void
    {
        // Default: do nothing. Subclasses override.
    }

    /**
     * Return component-specific CSS.
     * Override in subclasses to provide widget styles.
     * Return empty string if the widget has no custom CSS.
     */
    protected function styles(): string
    {
        return '';
    }

    /**
     * Return the selector key → element ID mappings this widget registers.
     *
     * @return array<string, string>
     */
    protected function selectorMap(): array
    {
        return [];
    }

    /**
     * Namespace for plugin options. Defaults to the short class name.
     */
    protected function pluginNamespace(): string
    {
        $fqcn = static::class;
        $pos = strrpos($fqcn, '\\');
        return lcfirst($pos !== false ? substr($fqcn, $pos + 1) : $fqcn);
    }

    // ── Rendering Helpers ───────────────────────────────────────────────────

    /**
     * Replace {placeholders} in the layout template with rendered sections.
     */
    protected function renderLayout(array $sections): string
    {
        return str_replace(
            array_keys($sections),
            array_values($sections),
            $this->layout
        );
    }

    /**
     * Register this widget class's CSS lazily (once per class, not per instance).
     * Uses the short class name as the dedup key.
     */
    private function registerStyleOnce(): void
    {
        $key = static::class;
        $css = $this->styles();
        if ($css !== '') {
            FluxAsset::registerCss($key, $css);
        }
    }

    /**
     * Render an HTML attributes string from an array.
     * Merges extra attributes on top.
     */
    protected function renderAttributes(array $attrs): string
    {
        $parts = [];
        foreach ($attrs as $key => $value) {
            if ($value === true) {
                $parts[] = htmlspecialchars($key, ENT_QUOTES);
            } elseif ($value !== false && $value !== null) {
                $parts[] = htmlspecialchars($key, ENT_QUOTES)
                         . '="' . htmlspecialchars((string) $value, ENT_QUOTES) . '"';
            }
        }
        return $parts ? ' ' . implode(' ', $parts) : '';
    }

    /**
     * Merge CSS classes: base + user-provided.
     * Unlike the old version, this does NOT mutate $this->options.
     */
    protected function mergeClass(string $base, array &$opts): string
    {
        $userClass = $opts['class'] ?? '';
        unset($opts['class']);
        return trim($base . ($userClass ? ' ' . $userClass : ''));
    }

    /**
     * Render an opening tag with merged class and extra attributes.
     */
    protected function openTag(string $tag, string $id, string $baseClass, array $opts = []): string
    {
        $class = $this->mergeClass($baseClass, $opts);
        return '<' . $tag
             . ' id="' . htmlspecialchars($id, ENT_QUOTES) . '"'
             . ' class="' . htmlspecialchars($class, ENT_QUOTES) . '"'
             . $this->renderAttributes($opts)
             . '>';
    }
}
