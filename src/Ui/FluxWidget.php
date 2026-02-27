<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

/**
 * Abstract base for all Flux UI widgets.
 *
 * Three levels of customization:
 *
 *   Options  — CSS class/attribute injection via *Options config keys
 *   Slots    — Per-component render closures via `slots` config key
 *   Closure  — Full layout control via render(config, fn)
 *
 * @see docs/WIDGET_API.md for full reference.
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

    /** @var array<string, \Closure> Per-component render overrides (slots) */
    protected array $slots;

    public function __construct(array $config = [])
    {
        $this->id            = $config['id'] ?? $this->defaultId();
        $this->options       = $config['options'] ?? [];
        $this->layout        = $config['layout'] ?? $this->defaultLayout();
        $this->pluginOptions = $config['pluginOptions'] ?? [];
        $this->pluginEvents  = $config['pluginEvents'] ?? [];
        $this->beforeContent = $config['beforeContent'] ?? '';
        $this->afterContent  = $config['afterContent'] ?? '';
        $this->slots         = $config['slots'] ?? [];

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
        return static::render($config);
    }

    /**
     * Closure-first rendering.
     *
     * Both widget() and render(config, fn) converge here.
     * When no closure is provided, defaultClosure() builds one from renderSections().
     */
    public static function render(array $config = [], ?\Closure $layoutClosure = null): string
    {
        /** @phpstan-ignore-next-line Yii2 factory pattern — all subclasses are concrete */
        $widget = new static($config);
        $widget->registerStyleOnce();

        // If no closure supplied, build the default from renderSections()
        $closure = $layoutClosure ?? $widget->defaultClosure();

        ob_start();
        ob_implicit_flush(false);

        echo $widget->openTarget();
        $closure($widget);
        echo $widget->closeTarget();

        return ob_get_clean();
    }

    // ── Overridable Methods ─────────────────────────────────────────────────

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
     * Required by closure pattern to open the root wrapper tag.
     * Must be a PURE opening tag — no content injection.
     */
    abstract protected function openTarget(): string;

    /**
     * Required by closure pattern to close the root wrapper tag.
     * Must be a PURE closing tag — no content injection.
     */
    abstract protected function closeTarget(): string;

    /**
     * Build the default closure from renderSections().
     *
     * This is the unified "legacy" rendering path — every call to widget()
     * or render() without a custom closure uses this. Subclasses may override
     * to produce widget-specific default layouts (e.g., FluxToolbar's controls
     * wrapper div).
     */
    protected function defaultClosure(): \Closure
    {
        return function (self $w): void {
            echo $w->beforeContent;
            $sections = $w->renderSections();
            echo $w->renderLayout($sections);
            echo $w->afterContent;
        };
    }

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

    // ── Slot Dispatcher ────────────────────────────────────────────────────

    /**
     * Dispatch a named render slot.
     *
     * If the developer provided a closure via the `slots` config key,
     * that closure is called with ($widget, $props, $default).
     * Otherwise, the default renderer runs.
     *
     * @param string   $name    Slot name (e.g., 'search', 'heading', 'btnRerun')
     * @param array    $props   Resolved props — merged classes, computed IDs, etc.
     * @param \Closure $default The default render function — call $default() for original HTML
     */
    protected function slot(string $name, array $props, \Closure $default): string
    {
        if (isset($this->slots[$name])) {
            return ($this->slots[$name])($this, $props, $default);
        }
        return $default();
    }

    // ── Public API for Closure Pattern ───────────────────────────────────────

    /**
     * Retrieve a JS-bound element ID for a specific widget component.
     *
     * Use `$widget->selector('key')` for raw HTML — write your own markup but
     * bind it to the FluxUI JS logic via the correct element ID.
     *
     * e.g., <input id="<?= $t->selector('search') ?>" type="text">
     */
    public function selector(string $key): string
    {
        return $this->selectorMap()[$key] ?? '';
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
     * Uses the FQCN as the dedup key.
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
     * Does NOT mutate $this->options.
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
