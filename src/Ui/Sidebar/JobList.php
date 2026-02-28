<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Sidebar;

use Entreya\Flux\Ui\FluxComponent;
use Entreya\Flux\Ui\FluxRenderer;

/**
 * JobList — sidebar container for dynamically-created job items.
 *
 * Props:
 *   id              — DOM id (default: fx-sidebar-job-list)
 *   class           — CSS classes
 *   jobItemTemplate — custom HTML template for each job item (optional)
 *
 * Template placeholders (used by flux.js):
 *   {id}        — unique element id for the <li>
 *   {job}       — job identifier
 *   {name}      — job display name
 *   {status}    — current status (pending|running|success|failure|skipped)
 *   {icon_id}   — id for the status icon element
 *   {icon_char} — status character (✓, ✕, ↻, –)
 *   {meta_id}   — id for the metadata/badge element
 */
class JobList extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'              => 'fx-sidebar-job-list',
            'class'           => 'list-group list-group-flush overflow-auto',
            'jobItemTemplate' => '',
        ];
    }

    protected function template(): string
    {
        return '<div id="{id}" class="{class}"></div>';
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('jobList', (string) $this->props['id']);

        // Register custom job item template if provided
        $tpl = (string) $this->props['jobItemTemplate'];
        if ($tpl !== '') {
            FluxRenderer::registerTemplate('jobItem', $tpl);
        }
    }

    protected function style(): string
    {
        return <<<'CSS'
        .flux-job-item { padding: 10px 16px; cursor: pointer; border-left: 3px solid transparent; transition: all .1s; }
        .flux-job-item:hover { background: rgba(0,0,0,.03); }
        .flux-job-item.is-active { background: #fff; border-left-color: var(--flux-accent); }
        .flux-job-title { font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        CSS;
    }
}
