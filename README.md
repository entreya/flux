# Entreya Flux

Flux is a secure, native PHP library for executing YAML-defined workflows with real-time web output, designed for internal tools and CI/CD dashboards.

## Features
- **Native YAML Parser**: Zero-dependency parsing of standard YAML.
- **Real-time Output**: Streams command output via Server-Sent Events (SSE).
- **GitHub Actions Style UI**: Beautiful, collapsible, themable interface.
- **Strict Security**: Whitelisting, blocking of dangerous patterns, and rate limiting.
- **Theming**: Built-in Dark, Light, and High Contrast themes.

## Installation

```bash
composer require entreya/flux
```

## Security Warning ⚠️

**CRITICAL**: This library executes shell commands on your server.

1.  **Authorization**: NEVER expose the Flux interface to the public internet without strict authentication (e.g., VPN, Basic Auth, or OAuth).
2.  **User Input**: DO NOT allow untrusted users to upload or modify YAML files.
3.  **Command Whitelist**: Configure the `allowed_commands` list strictly in your production config.
4.  **Chaining**: By default, command chaining (`&&`, `||`, `;`) contains security risks and is blocked.

## Usage

### 1. Define a Workflow (YAML)
Create a file named `deploy.yaml`:

```yaml
name: Deploy App
steps:
  - name: Check PHP
    run: php -v
  - name: Install Deps
    run: composer install
```

### 2. Setup Public Interface
Create `public/index.php` and `public/sse.php`.

**public/sse.php**:
```php
<?php
require 'vendor/autoload.php';

use Entreya\Flux\Flux;

$config = [
    'security' => [
        'allowed_commands' => ['php', 'composer', 'echo'],
        'require_auth' => true
    ]
];

$flux = new Flux($config);
// In production, validate $_GET['workflow'] against a map of allowed files!
$flux->streamWorkflow('path/to/workflow.yaml');
```

**public/index.php**:
See the provided example in `public/index.php`.

### 3. Start Server
```bash
php -S localhost:8000 -t public
```
Visit `http://localhost:8000` to view the console.

## Configuration

| Option | Description | Default |
|--------|-------------|---------|
| `security.allowed_commands` | List of allowed binaries | `['composer', 'npm', ...]` |
| `security.blocked_patterns` | Regex list of blocked strings | `['/rm -rf/', ...]` |
| `security.rate_limit.max_per_hour` | Max runs per IP/Hour | `10` |
| `timeout` | Command timeout in seconds | `300` |

## Theming

Custom themes can be defined in JSON files in a custom directory passed to `ThemeManager`.

**Structure:**
```json
{
    "flux-bg": "#000000",
    "flux-accent": "#ff0000"
}
```

## License
MIT
