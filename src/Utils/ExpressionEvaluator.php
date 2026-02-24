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
    private ExpressionLanguage $expressionLanguage;

    public function __construct()
    {
        $this->expressionLanguage = new ExpressionLanguage();
        $this->registerFunctions();
    }

    public function evaluate(string $expression, array $context = []): bool
    {
        // Strip ${{ }} wrapper if present (GitHub Actions style)
        $expression = trim($expression);
        if (str_starts_with($expression, '${{') && str_ends_with($expression, '}}')) {
            $expression = trim(substr($expression, 3, -2));
        }

        try {
            return (bool) $this->expressionLanguage->evaluate($expression, $context);
        } catch (\Throwable $e) {
            // Treat evaluation errors as false (or throw? GitHub fails the workflow)
            // For now, let's return false to skip safely, but maybe logging would be good.
            return false;
        }
    }

    private function registerFunctions(): void
    {
        // success() - Returns true if the current step/job status is 'success'.
        // Fix: previously relied on a positional $status argument that was never
        // passed from the call site, so it always defaulted to 'success'. Now reads
        // status from the $arguments context array passed to evaluate().
        $this->expressionLanguage->register('success', fn() => '(status == "success")',
            fn(array $arguments) => ($arguments['status'] ?? 'success') === 'success'
        );

        // failure() - Returns true if the current step/job status is 'failure'.
        $this->expressionLanguage->register('failure', fn() => '(status == "failure")',
            fn(array $arguments) => ($arguments['status'] ?? 'success') === 'failure'
        );

        // always() - Always runs regardless of prior status.
        $this->expressionLanguage->register('always', fn() => 'true',
            fn(array $arguments) => true
        );

        // cancelled() - Returns true if the workflow was cancelled.
        $this->expressionLanguage->register('cancelled', fn() => '(status == "cancelled")',
            fn(array $arguments) => ($arguments['status'] ?? 'success') === 'cancelled'
        );
    }
}
