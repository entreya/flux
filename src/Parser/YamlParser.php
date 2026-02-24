<?php

declare(strict_types=1);

namespace Entreya\Flux\Parser;

use Entreya\Flux\Exceptions\ParseException;

/**
 * YAML parser for Flux workflow files.
 *
 * Only used when Flux::fromYaml() is called — Flux::pipeline() has no dependency
 * on this class or any YAML library.
 *
 * Parser resolution order:
 *   1. symfony/yaml  (optional, recommended) — composer require symfony/yaml
 *   2. yaml_parse()  (PHP YAML extension)
 *   3. ParseException if neither is available
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
        // Use PHP YAML extension
        if (function_exists('yaml_parse')) {
            $result = yaml_parse($yaml);
            if ($result === false) {
                throw new ParseException('YAML parse error (yaml_parse extension).');
            }
            return (array) $result;
        }

        throw new ParseException(
            'No YAML parser available. Install PECL yaml extension.'
        );
    }
}
