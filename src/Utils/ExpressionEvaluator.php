<?php

declare(strict_types=1);

namespace Entreya\Flux\Utils;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Evaluates conditional expressions for Jobs and Steps.
 * Supports GitHub Actions-like functions: success(), failure(), always(), cancelled().
 */
class ExpressionEvaluator
{
    public function evaluate(string $expression, array $context = []): bool
    {
        // Strip ${{ }} wrapper if present (GitHub Actions style)
        $expression = trim($expression);
        if (str_starts_with($expression, '${{') && str_ends_with($expression, '}}')) {
            $expression = trim(substr($expression, 3, -2));
        }

        $status = $context['status'] ?? 'success';

        return match ($expression) {
            'success()'   => $status === 'success',
            'failure()'   => $status === 'failure',
            'always()'    => true,
            'cancelled()' => $status === 'cancelled',
            default       => false, // Treats evaluation errors/unknowns as false
        };
    }
}
