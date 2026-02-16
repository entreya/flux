<?php

declare(strict_types=1);

namespace Entreya\Flux\Pipeline;

class Job
{
    private array $preSteps  = [];
    private array $steps     = [];
    private array $postSteps = [];
    private array $needs     = [];
    private array $env       = [];
    private ?string $if      = null;

    public function __construct(
        private readonly string $id,
        private readonly string $name,
    ) {}

    public function addPreStep(Step $step): void  { $this->preSteps[]  = $step; }
    public function addStep(Step $step): void     { $this->steps[]     = $step; }
    public function addPostStep(Step $step): void { $this->postSteps[] = $step; }

    public function lastStep(): ?Step
    {
        return $this->steps[array_key_last($this->steps)] ?? null;
    }

    public function setNeeds(array $needs): void { $this->needs = $needs; }
    public function setEnv(array $env): void     { $this->env   = $env;   }
    public function setIf(?string $if): void     { $this->if    = $if;    }

    public function getId(): string       { return $this->id;        }
    public function getName(): string     { return $this->name;      }
    public function getPreSteps(): array  { return $this->preSteps;  }
    public function getSteps(): array     { return $this->steps;     }
    public function getPostSteps(): array { return $this->postSteps; }
    public function getNeeds(): array     { return $this->needs;     }
    public function getEnv(): array       { return $this->env;       }
    public function getIf(): ?string      { return $this->if;        }
}
