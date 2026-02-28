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
            'class'       => 'form-control form-control-sm',
            'placeholder' => 'Search logs...',
            'icon'        => 'bi bi-search',
        ];
    }

    protected function template(): string
    {
        return '<div class="position-relative" style="width:200px">'
             . '<input type="text" id="{id}" class="{class}" placeholder="{placeholder}">'
             . '<i class="{icon} position-absolute text-muted" style="right:10px;top:50%;transform:translateY(-50%);font-size:12px"></i>'
             . '</div>';
    }

    protected function script(): string
    {
        return <<<'JS'
        (function() {
            var input = document.getElementById('{id}');
            if (!input) return;
            input.addEventListener('input', function() {
                var term = this.value.toLowerCase();
                document.querySelectorAll('.flux-log-line').forEach(function(line) {
                    line.style.display = (!term || line.textContent.toLowerCase().includes(term)) ? '' : 'none';
                });
            });
        })();
        JS;
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('search', (string) $this->props['id']);
    }
}
