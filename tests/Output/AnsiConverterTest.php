<?php
declare(strict_types=1);

namespace Entreya\Flux\Tests\Output;

use Entreya\Flux\Output\AnsiConverter;
use PHPUnit\Framework\TestCase;

class AnsiConverterTest extends TestCase
{
    public function testConvertLink(): void
    {
        $converter = new AnsiConverter();
        $ansi = "\e]8;;https://entreya.com\e\\Entreya Website\e]8;;\e\\";
        $html = $converter->convert($ansi);
        
        $this->assertEquals('<a href="https://entreya.com" target="_blank" rel="noopener noreferrer" class="flux-ansi-link">Entreya Website</a>', $html);
    }
}
