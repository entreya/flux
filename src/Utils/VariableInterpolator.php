<?php

declare(strict_types=1);

namespace Entreya\Flux\Utils;

class VariableInterpolator
{
    /**
     * Interpolate variables in a string.
     * Supports ${{ matrix.key }}, ${{ inputs.key }}, ${{ env.key }}
     */
    public static function interpolate(string $content, array $context): string
    {
        return preg_replace_callback('/\$\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function ($matches) use ($context) {
            $key = $matches[1];
            return self::getValue($key, $context);
        }, $content);
    }

    private static function getValue(string $key, array $context): string
    {
        $parts = explode('.', $key);
        $value = $context;

        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return ''; // Not found or invalid path
            }
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
