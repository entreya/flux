<?php

declare(strict_types=1);

namespace Entreya\Flux\Parser;

use Entreya\Flux\Exceptions\ParseException;

/**
 * A native PHP YAML parser.
 * Supports:
 * - Scalars (strings, numbers, booleans)
 * - Lists (sequences)
 * - Maps (mappings)
 * - Comments
 * - Nested structures
 */
class YamlParser
{
    /**
     * Parse a YAML file into a PHP array.
     *
     * @param string $path
     * @return array<mixed>
     * @throws ParseException
     */
    public function parseFile(string $path): array
    {
        if (!file_exists($path)) {
            throw new ParseException("File not found: $path");
        }
        return $this->parse(file_get_contents($path));
    }

    /**
     * Parse a YAML string into a PHP array.
     *
     * @param string $input
     * @return array<mixed>
     * @throws ParseException
     */
    public function parse(string $input): array
    {
        $lines = explode("\n", $input);
        $data = [];
        $path = [&$data];
        $indents = [-1];
        
        $buffer = [];
        $multilineMode = false;
        $multilineIndent = 0;
        $multilineKey = null; // Key to assign buffer to
        $multilineParent = null; // Reference to parent array to assign to

        foreach ($lines as $lineNum => $line) {
            // Check for indent
            $trimmed = trim($line);
            $indent = strlen($line) - strlen(ltrim($line));

            // Multiline Handling
            if ($multilineMode) {
                if ($trimmed === '') {
                    $buffer[] = ''; 
                    continue;
                }
                
                if ($indent >= $multilineIndent) {
                     // Keep relative indentation? Simple parser: just trim and add
                     // For real YAML, we should keep relative indent. 
                     // Let's just consume the line content.
                     $buffer[] = $trimmed; // trimming for simplicity in this MVP
                     continue;
                } else {
                    // End of multiline
                    $text = implode("\n", $buffer);
                    if ($multilineParent !== null && $multilineKey !== null) {
                       $multilineParent[$multilineKey] = $text;
                    }
                    $multilineMode = false;
                    $buffer = [];
                    // Fall through to normal processing for this line
                }
            }

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            
            // Adjust nesting
            while ($indent <= end($indents) && count($indents) > 1) {
                array_pop($indents);
                array_pop($path);
            }
            
            $parent = &$path[count($path) - 1];
            $content = ltrim($line);
            
            // List item
            if (str_starts_with($content, '- ')) {
                 $content = substr($content, 2);
                 $trimmed = trim($content);
                 $isListItem = true;
            } else {
                 $isListItem = false;
            }
            
            // Key: Value
            if (preg_match('/^((?:["\'].*?["\'])|[^:]+):(?:\s+(.*))?$/', $content, $matches)) {
                $key = $this->parseScalar($matches[1]);
                $valStr = isset($matches[2]) ? trim($matches[2]) : '';
                
                 // Check for multiline indicator
                if ($valStr === '|' || $valStr === '>') {
                    $multilineMode = true;
                    // Next lines must be indented more than current
                    $multilineIndent = $indent + 1; 
                    $multilineKey = $key;
                    
                    if ($isListItem) {
                        $newMap = [];
                        $parent[] = &$newMap;
                        // We need to keep reference to this new map for multiline assignment
                        // And also push it to path for potential nested keys?
                        // Actually, if it's " - run: |", the list item is just this map.
                        // We set multilineParent to this new map.
                        unset($newMap); // break ref
                        $lastIdx = array_key_last($parent);
                        $multilineParent = &$parent[$lastIdx];
                        
                        // Also Add to path in case of mixed content?
                        $path[] = &$parent[$lastIdx];
                        $indents[] = $indent;
                    } else {
                         // Map entry "run: |"
                         $multilineParent = &$parent;
                    }
                    continue;
                }
                
                if ($isListItem) {
                    $newMap = [];
                    if ($valStr === '') {
                        $newMap[$key] = [];
                        $parent[] = $newMap;
                        $lastIdx = array_key_last($parent);
                        $path[] = &$parent[$lastIdx][$key]; // Point to inner array
                        $indents[] = $indent + 2; // Arbitrary deeper indent expectation
                        
                        // Also need to track the map itself? No, standard YAML structure
                        // - job:
                        //   name: foo
                    } else {
                        $newMap[$key] = $this->parseScalar($valStr);
                        $parent[] = $newMap;
                        $lastIdx = array_key_last($parent);
                        $path[] = &$parent[$lastIdx]; // Point to Map
                        $indents[] = $indent;
                    }
                } else {
                    if ($valStr === '') {
                        $parent[$key] = [];
                        $path[] = &$parent[$key];
                        $indents[] = $indent;
                    } else {
                        $parent[$key] = $this->parseScalar($valStr);
                    }
                }
                
            } elseif ($isListItem) {
                // Scalar list item
                 $parent[] = $this->parseScalar($trimmed);
            }
        }
        
        // Final flush if EOF while in multiline
        if ($multilineMode && !empty($buffer)) {
             $text = implode("\n", $buffer);
             if ($multilineParent !== null && $multilineKey !== null) {
                $multilineParent[$multilineKey] = $text;
             }
        }

        return $data;
    }

    private function parseScalar(string $val): mixed
    {
        $val = trim($val);
        
        // Boolean
        if (strtolower($val) === 'true') return true;
        if (strtolower($val) === 'false') return false;
        
        // Null
        if (strtolower($val) === 'null' || $val === '~') return null;
        
        // Numbers
        if (is_numeric($val)) {
            return $val + 0;
        }
        
        // Double Quotes (support escapes)
        if (str_starts_with($val, '"') && str_ends_with($val, '"')) {
            // stripcslashes handles standard C-style escapes like \n, \t, \033
            return stripcslashes(substr($val, 1, -1));
        }

        // Single Quotes (literal, mostly)
        if (str_starts_with($val, "'") && str_ends_with($val, "'")) {
            return substr($val, 1, -1);
        }
        
        return $val;
    }
}
