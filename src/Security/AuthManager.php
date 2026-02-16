<?php

declare(strict_types=1);

namespace Entreya\Flux\Security;

use Entreya\Flux\Exceptions\SecurityException;

/**
 * Callback-based authentication guard.
 *
 * Usage:
 *   Flux::fromYaml('deploy.yaml')
 *       ->withAuth(fn() => isset($_SESSION['user']) && $_SESSION['role'] === 'admin')
 *       ->stream();
 */
class AuthManager
{
    /** @var callable */
    private $check;

    public function __construct(callable $check)
    {
        $this->check = $check;
    }

    public function isAuthenticated(): bool
    {
        return (bool) ($this->check)();
    }

    /**
     * @throws SecurityException
     */
    public function enforce(): void
    {
        if (!$this->isAuthenticated()) {
            throw new SecurityException('Unauthorized: authentication check failed.');
        }
    }
}
