<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Renderer;

/**
 * Contract for step renderers.
 *
 * A step renderer provides the JS HTML template that flux.js uses
 * to dynamically create step elements in the DOM.
 *
 * Built-in implementations:
 *   - DetailsStepRenderer   — <details>/<summary> (default)
 *   - AccordionStepRenderer — Bootstrap 5 accordion
 *
 * Create your own by implementing this interface.
 */
interface StepRendererInterface
{
    /**
     * Return the JS HTML template string with {placeholders}.
     *
     * Available placeholders:
     *   {id}          — unique step element ID
     *   {icon_id}     — status icon element ID
     *   {dur_id}      — duration element ID
     *   {logs_id}     — log body container ID
     *   {collapse_id} — collapse target ID (accordion only)
     *   {name}        — step name (escaped)
     *   {phase}       — phase HTML badge (pre/post/main)
     *   {status}      — initial status class
     */
    public function jsTemplate(): string;

    /**
     * Return the JS expand/collapse method name.
     * 'details' = use .open property, 'accordion' = use bootstrap.Collapse API
     */
    public function collapseMethod(): string;
}
