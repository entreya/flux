<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Progress;

use Entreya\Flux\Ui\FluxComponent;

class Bar extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'    => 'fx-progress-bar',
            'class' => 'progress-bar bg-primary',
        ];
    }

    protected function template(): string
    {
        return '<div id="{id}" class="{class}" style="width:0%;transition:width .5s ease" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>';
    }
}
