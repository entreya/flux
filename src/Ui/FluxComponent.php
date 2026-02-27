<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

/**
 * Abstract base for all Flux UI components.
 *
 * Each component is a self-contained unit with:
 *   - defaults()  → props (id, class, placeholder, etc.)
 *   - template()  → HTML with {prop} and {slot:name} tokens
 *   - style()     → CSS scoped to this component
 *   - script()    → JS behavior for this component
 *   - slots()     → named child components
 *
 * Customization levels:
 *   Props    — override props via ['props' => [...]]
 *   Slots    — override child components via ['slots' => [...]]
 *   Content  — replace template entirely via ['content' => ...]
 *   Style    — add/replace CSS via ['style' => ...]
 *   Script   — add/replace JS via ['script' => ...]
 *
 * @see docs/WIDGET_API.md
 */
abstract class FluxComponent
{
    /** @var array<string, mixed> Resolved props (defaults merged with overrides) */
    protected array $props = [];

    /** @var array<string, mixed> Slot overrides from user config */
    protected array $slotOverrides = [];

    /** User-provided content override */
    protected string|\Closure|null $contentOverride = null;

    /** User-provided style override/addition */
    protected ?string $styleOverride = null;

    /** User-provided script override/addition */
    protected ?string $scriptOverride = null;

    // ── Override Points ─────────────────────────────────────────────────────

    /**
     * Default prop values.
     * Every prop is available as {key} in template() and script().
     *
     * @return array<string, mixed>
     */
    abstract protected function defaults(): array;

    /**
     * HTML template with {prop} and {slot:name} tokens.
     * {prop} tokens are replaced with htmlspecialchars'd prop values.
     * {slot:name} tokens are replaced with rendered child component HTML.
     */
    abstract protected function template(): string;

    /**
     * CSS for this component. Registered once per class (deduplicated).
     * Return empty string if no styles needed.
     */
    protected function style(): string
    {
        return '';
    }

    /**
     * JS for this component. Registered per instance.
     * {prop} tokens are interpolated with actual prop values (NOT escaped).
     * Return empty string if no script needed.
     */
    protected function script(): string
    {
        return '';
    }

    /**
     * Named slots with their default component classes.
     * Keys are slot names, values are FluxComponent subclass FQCNs.
     *
     * @return array<string, class-string<FluxComponent>>
     */
    protected function slots(): array
    {
        return [];
    }

    /**
     * Prop names whose values contain raw HTML and should NOT be escaped.
     * Override in subclasses that have HTML-containing props.
     *
     * @return list<string>
     */
    protected function rawProps(): array
    {
        return [];
    }

    // ── Static Factory ──────────────────────────────────────────────────────

    /**
     * Render this component with optional overrides.
     *
     * @param array{
     *   props?:   array<string, mixed>,
     *   slots?:   array<string, mixed>,
     *   content?: string|\Closure,
     *   style?:   string,
     *   script?:  string,
     * } $config
     */
    public static function render(array $config = []): string
    {
        /** @phpstan-ignore new.static */
        $instance = new static();
        $instance->configure($config);

        $resolvedSlots = $instance->resolveSlots();
        $html          = $instance->buildContent($resolvedSlots);
        $instance->registerAssets();

        return $html;
    }

    /** Convenience alias — matches Yii2 widget() convention. */
    public static function widget(array $config = []): string
    {
        return static::render($config);
    }

    // ── Internal Pipeline ───────────────────────────────────────────────────

    /** Merge user config into the component instance. */
    protected function configure(array $config): void
    {
        $this->props           = array_merge($this->defaults(), $config['props'] ?? []);
        $this->slotOverrides   = $config['slots'] ?? [];
        $this->contentOverride = $config['content'] ?? null;
        $this->styleOverride   = $config['style'] ?? null;
        $this->scriptOverride  = $config['script'] ?? null;
    }

    /**
     * Resolve every declared slot to an HTML string.
     *
     * Override types:
     *   string       → raw HTML
     *   array        → config passed to the default slot component
     *   Closure      → called with parent props, returns HTML
     *   false        → slot not rendered
     *   class-string → different component class rendered
     *
     * @return array<string, string>
     */
    protected function resolveSlots(): array
    {
        $declared = $this->slots();
        $resolved = [];

        foreach ($declared as $name => $defaultClass) {
            $override = $this->slotOverrides[$name] ?? null;

            // Explicitly disabled — no HTML, no CSS, no JS
            if ($override === false) {
                $resolved[$name] = '';
                continue;
            }

            // No override — render default component
            if ($override === null) {
                $resolved[$name] = $defaultClass::render($this->childConfig($name));
                continue;
            }

            // String: raw HTML or component class name
            if (is_string($override)) {
                $resolved[$name] = is_subclass_of($override, self::class)
                    ? $override::render($this->childConfig($name))
                    : $override;
                continue;
            }

            // Array: config → default component
            if (is_array($override)) {
                $resolved[$name] = $defaultClass::render($override);
                continue;
            }

            // Closure: custom rendering
            if ($override instanceof \Closure) {
                $result = $override($this->props);
                $resolved[$name] = is_string($result) ? $result : '';
                continue;
            }

            $resolved[$name] = '';
        }

        return $resolved;
    }

    /** Build the final HTML content. */
    protected function buildContent(array $resolvedSlots): string
    {
        if ($this->contentOverride !== null) {
            $raw  = ($this->contentOverride instanceof \Closure)
                ? ($this->contentOverride)($this->props)
                : $this->contentOverride;
            $html = (string) $raw;
        } else {
            $html = $this->template();
        }

        // Replace {slot:name} tokens (works for both template and content override)
        foreach ($resolvedSlots as $name => $slotHtml) {
            $html = str_replace("{slot:{$name}}", $slotHtml, $html);
        }

        return $this->interpolateProps($html);
    }

    /** Register this component's style and script with the renderer. */
    protected function registerAssets(): void
    {
        $css = $this->styleOverride ?? $this->style();
        if ($css !== '' && $css !== null) {
            $key = $this->styleOverride !== null
                ? static::class . ':' . ($this->props['id'] ?? 'custom')
                : static::class;
            FluxRenderer::registerStyle($key, $css);
        }

        $js = $this->scriptOverride ?? $this->script();
        if ($js !== '' && $js !== null) {
            $instanceKey = static::class . ':' . ($this->props['id'] ?? spl_object_id($this));
            FluxRenderer::registerScript($instanceKey, $this->interpolateProps($js, escape: false));
        }

        if (isset($this->props['id'])) {
            $this->registerSelectors();
        }
    }

    /**
     * Register element IDs as selectors for FluxUI JS.
     * Override in subclasses that need JS discovery.
     */
    protected function registerSelectors(): void
    {
    }

    // ── Interpolation ───────────────────────────────────────────────────────

    /**
     * Replace {key} tokens with prop values.
     *
     * @param bool $escape Whether to htmlspecialchars the values
     */
    protected function interpolateProps(string $template, bool $escape = true): string
    {
        $rawKeys      = array_flip($this->rawProps());
        $replacements = [];

        foreach ($this->props as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $shouldEscape              = $escape && !isset($rawKeys[$key]);
            $replacements["{$key}"] = $shouldEscape
                ? htmlspecialchars((string) $value, ENT_QUOTES)
                : (string) $value;
        }

        return str_replace(
            array_map(static fn(string $k): string => '{' . $k . '}', array_keys($replacements)),
            array_values($replacements),
            $template,
        );
    }

    /**
     * Build config for a child slot component.
     * Override to pass parent props down to specific children.
     *
     * @return array<string, mixed>
     */
    protected function childConfig(string $slotName): array
    {
        return [];
    }

    // ── Prop Access ─────────────────────────────────────────────────────────

    /** Get a resolved prop value. */
    public function prop(string $key, mixed $default = null): mixed
    {
        return $this->props[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function props(): array
    {
        return $this->props;
    }
}
