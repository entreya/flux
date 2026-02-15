<?php

declare(strict_types=1);

namespace Entreya\Flux;

use Entreya\Flux\Executor\CommandRunner;
use Entreya\Flux\Executor\WorkflowExecutor;
use Entreya\Flux\Output\AnsiConverter;
use Entreya\Flux\Output\OutputFormatter;
use Entreya\Flux\Parser\YamlParser;
use Entreya\Flux\Security\AuthManager;
use Entreya\Flux\Security\CommandValidator;
use Entreya\Flux\Security\RateLimiter;
use Entreya\Flux\Theme\ThemeManager;

/**
 * Main entry point for the Flux library.
 */
class Flux
{
    private WorkflowExecutor $executor;
    private OutputFormatter $formatter;
    private ThemeManager $themeManager;
    private AuthManager $authManager;
    private RateLimiter $rateLimiter;
    private array $globalEnv;

    public function __construct(array $config = [])
    {
        // 1. Environment Setup
        $phpDir = dirname(PHP_BINARY);
        $path = getenv('PATH') ?: '/usr/bin:/bin';
        $fullPath = $phpDir . PATH_SEPARATOR . $path;
        
        $baseEnv = [
            'PATH' => $fullPath,
            'PHP_BINARY' => PHP_BINARY,
            'ANSIBLE_FORCE_COLOR' => '1',
            'TERM' => 'xterm-256color', // Help tools detect TTY-like capability
        ];

        // Merge user config env if any
        $configEnv = $config['env'] ?? [];
        $this->globalEnv = array_merge($baseEnv, $configEnv);

        // 2. Dependency Injection Wiring
        $validator = new CommandValidator($config['security'] ?? []);
        $runner = new CommandRunner($validator, $config['timeout'] ?? 300);
        $parser = new YamlParser(); // YamlParser doesn't need env
        
        // Pass globalEnv to Executor?
        $this->executor = new WorkflowExecutor($runner, $parser, $this->globalEnv);
        
        $this->formatter = new OutputFormatter(new AnsiConverter());
        $this->themeManager = new ThemeManager($config['theme']['custom_dir'] ?? null);
        
        $this->authManager = new AuthManager(
            $config['security']['require_auth'] ?? true,
            $config['security']['cookie_name'] ?? 'flux_sess'
        );
        
        $this->rateLimiter = new RateLimiter(
            $config['security']['rate_limit']['max_per_hour'] ?? 1000
        );
    }

    public function getThemeManager(): ThemeManager
    {
        return $this->themeManager;
    }

    public function getAuthManager(): AuthManager
    {
        return $this->authManager;
    }

    /**
     * Run a workflow and stream SSE events.
     * Ends the script execution.
     */
    public function streamWorkflow(string $yamlFile): void
    {
        // Security Checks
        $this->authManager->enforce();
        $this->rateLimiter->check($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        
        // release session lock so other requests (like theme switching) aren't blocked
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (!file_exists($yamlFile)) {
             $this->sendError("Workflow file not found.");
             return;
        }

        // Headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Nginx

        // Execute
        $content = file_get_contents($yamlFile);
        $iterator = $this->executor->execute($content);

        foreach ($iterator as $event) {
            echo $this->formatter->formatLog($event);
            ob_flush();
            flush();
        }
    }

    private function sendError(string $msg): void
    {
        echo $this->formatter->formatEvent('error', ['message' => $msg]);
        ob_flush();
        flush();
    }
}
