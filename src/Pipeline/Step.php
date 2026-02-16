<?php

declare(strict_types=1);

namespace Entreya\Flux\Pipeline;

class Step
{
    public function __construct(
        private readonly string  $name,
        private readonly ?string $command,
        private readonly array   $env             = [],
        private bool             $continueOnError = false,
        private readonly ?string $workingDir      = null,
    ) {}

    public function setContinueOnError(bool $value): void { $this->continueOnError = $value; }

    public function getName(): string     { return $this->name; }
    public function getCommand(): ?string { return $this->command; }
    public function getEnv(): array       { return $this->env; }
    public function getWorkingDir(): ?string { return $this->workingDir; }
    public function isContinueOnError(): bool { return $this->continueOnError; }
}
