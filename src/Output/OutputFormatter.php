<?php

declare(strict_types=1);

namespace Entreya\Flux\Output;

class OutputFormatter
{
    private AnsiConverter $ansiConverter;

    public function __construct(AnsiConverter $ansiConverter = null)
    {
        $this->ansiConverter = $ansiConverter ?? new AnsiConverter();
    }

    /**
     * Format a log event for SSE.
     */
    public function formatLog(array $event): string
    {
        // Convert content ANSI -> HTML
        if (isset($event['data']['content'])) {
            $event['data']['html'] = $this->ansiConverter->convert($event['data']['content']);
        }
        return $this->formatEvent($event['event'], $event['data']);
    }

    /**
     * Standard SSE formatting:
     * event: type
     * data: json_payload
     * \n\n
     */
    public function formatEvent(string $type, mixed $data): string
    {
        return sprintf(
            "event: %s\ndata: %s\n\n",
            $type,
            json_encode($data, JSON_THROW_ON_ERROR)
        );
    }
}
