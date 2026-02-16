<?php

declare(strict_types=1);

namespace Entreya\Flux\Parser;

use Entreya\Flux\Exceptions\ParseException;

/**
 * YAML parser for Flux workflow files.
 *
 * Uses symfony/yaml when installed (recommended). Falls back to PHP's native
 * yaml_parse() extension if available. Throws ParseException otherwise.
 *
 * Install symfony/yaml:
 *   composer require symfony/yaml
 */
class YamlParser
{
    /**
     * Parse a YAML file into a PHP array.
     *
     * @throws ParseException
     */
    public function parseFile(string $path): array
    {
        if (!is_readable($path)) {
            throw new ParseException("Workflow file not found or not readable: $path");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new ParseException("Could not read workflow file (I/O error): $path");
        }

        return $this->parse($contents);
    }

    /**
     * Parse a YAML string into a PHP array.
     *
     * @throws ParseException
     */
    public function parse(string $yaml): array
    {
        // Prefer symfony/yaml — most robust
        if (class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            try {
                $result = \Symfony\Component\Yaml\Yaml::parse($yaml);
                if (!is_array($result)) {
                    throw new ParseException('Workflow YAML must be a mapping, not a scalar.');
                }
                return $result;
            } catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
                throw new ParseException('YAML parse error: ' . $e->getMessage(), 0, $e);
            }
        }

        // Fallback: PHP YAML extension
        if (function_exists('yaml_parse')) {
            $result = yaml_parse($yaml);
            if ($result === false) {
                throw new ParseException('YAML parse error (yaml_parse extension).');
            }
            return (array) $result;
        }

        throw new ParseException(
            'No YAML parser available. Run: composer require symfony/yaml'
        );
    }
}
