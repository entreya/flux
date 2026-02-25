<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

/**
 * Status badge widget — shows workflow state (Connecting, Running, Completed, Failed).
 *
 * Usage:
 *   echo FluxBadge::widget(['id' => 'myBadge']);
 *   echo FluxBadge::widget(['id' => 'myBadge', 'options' => ['class' => 'ms-auto']]);
 */
class FluxBadge extends FluxWidget
{
    protected function defaultId(): string
    {
        return 'fx-badge';
    }

    protected function selectorMap(): array
    {
        return [
            'badge'     => $this->id,
            'badgeText' => $this->id . '-text',
        ];
    }

    public function render(): string
    {
        $class = $this->mergeClass('badge rounded-pill text-bg-secondary d-inline-flex align-items-center gap-1');

        return '<span id="' . htmlspecialchars($this->id, ENT_QUOTES) . '" '
            . 'class="' . htmlspecialchars($class, ENT_QUOTES) . '" '
            . 'data-status="pending"'
            . $this->renderAttributes() . '>'
            . '<span class="flux-badge-dot"></span>'
            . '<span id="' . htmlspecialchars($this->id . '-text', ENT_QUOTES) . '">Connecting</span>'
            . '</span>';
    }
}
