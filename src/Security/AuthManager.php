<?php

declare(strict_types=1);

namespace Entreya\Flux\Security;

use Entreya\Flux\Exceptions\SecurityException;

class AuthManager
{
    private bool $requireAuth;
    private ?string $cookieName;

    public function __construct(bool $requireAuth = true, string $cookieName = 'flux_sess')
    {
        $this->requireAuth = $requireAuth;
        $this->cookieName = $cookieName;
    }

    public function isAuthenticated(): bool
    {
        if (!$this->requireAuth) {
            return true;
        }

        // Implementation of actual auth check needs integration with user system.
        // For this library, we likely rely on a callback or simple session check.
        // Or we check if a specific Closure passed in returns true.
        
        // Mock implementation: check if session has 'user_id'
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return !empty($_SESSION['flux_user']);
    }

    /**
     * @throws SecurityException
     */
    public function enforce(): void
    {
        if (!$this->isAuthenticated()) {
            throw new SecurityException("Unauthorized access.");
        }
    }

    // Login for demo purposes
    public function login(string $username, string $password): bool
    {
        // In real app, check DB.
        // Demo hardcoded admin/secret
        if ($username === 'admin' && $password === 'secret') {
            $_SESSION['flux_user'] = $username;
            return true;
        }
        return false;
    }
}
