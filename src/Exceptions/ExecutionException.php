<?php declare(strict_types=1);
namespace Entreya\Flux\Exceptions;
class ExecutionException extends FluxException {
    public function __construct(string $message = '', private readonly int $exitCode = -1, ?\Throwable $previous = null) {
        parent::__construct($message, $exitCode, $previous);
    }
    public function getExitCode(): int { return $this->exitCode; }
}
