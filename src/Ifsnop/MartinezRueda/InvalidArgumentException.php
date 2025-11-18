<?php

namespace Ifsnop\MartinezRueda;

class InvalidArgumentException extends \Exception
{
    private string $prettyTrace;

    public function __construct(
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null,
        bool $dumpToErrorLog = true,
        bool $includeArgs = false
    ) {
        parent::__construct($message, $code, $previous);

        // El backtrace de Exception ya está listo tras parent::__construct().
        $trace = $includeArgs
            ? $this->getTrace() // cuidado con datos sensibles
            : debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        $this->prettyTrace = self::formatTrace($trace);

        if ($dumpToErrorLog) {
            error_log($this->composeLogMessage());
        }
    }
/*
    public function getPrettyTrace(): string
    {
        return $this->prettyTrace;
    }
*/
    public function __toString(): string
    {
        return sprintf(
            "%s: %s in %s:%d\nStack trace:\n%s",
            static::class,
            $this->getMessage(),
            $this->getFile(),
            $this->getLine(),
            $this->prettyTrace
        );
    }

    private function composeLogMessage(): string
    {
        return sprintf(
            "[%s] %s at %s:%d\n%s",
            static::class,
            $this->getMessage(),
            $this->getFile(),
            $this->getLine(),
            $this->prettyTrace
        );
    }

    private static function formatTrace(array $trace): string
    {
        $lines = [];
        foreach ($trace as $i => $frame) {
            $file = $frame['file'] ?? '[internal]';
            $line = $frame['line'] ?? 0;
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $func = $frame['function'] ?? '{closure}';
            $lines[] = sprintf("#%d %s(%d): %s%s%s()", $i, $file, $line, $class, $type, $func);
        }
        return implode("\n", $lines);
    }
}
