<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Toolbar;

use Entreya\Flux\Ui\FluxComponent;
use Entreya\Flux\Ui\FluxRenderer;

class SearchInput extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'          => 'fx-toolbar-search',
            'class'       => 'form-control form-control-sm font-monospace',
            'placeholder' => 'Search logs…',
        ];
    }

    protected function template(): string
    {
        return <<<'HTML'
        <div class="position-relative">
            <i class="bi bi-search position-absolute top-50 translate-middle-y text-body-secondary" style="left:8px;font-size:11px;pointer-events:none"></i>
            <input id="{id}" type="search" class="{class}" style="width:180px;padding-left:26px;font-size:12px" placeholder="{placeholder}" autocomplete="off">
        </div>
        HTML;
    }

    protected function script(): string
    {
        return 'document.getElementById("{id}").addEventListener("input",FluxUI.debounce(function(e){FluxUI.filter(e.target.value)},200));';
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('search', $this->props['id']);
    }
}
