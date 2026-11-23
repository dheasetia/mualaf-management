<?php

declare(strict_types=1);

namespace App\Support\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

/**
 * 13 §13.5: application logs carry no PII. Masks email addresses, phone
 * numbers and 10-digit Saudi identity numbers in messages and context
 * before they are written. Used as a `tap` on the log channels.
 */
final class RedactPersonalData
{
    private const array PATTERNS = [
        '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/' => '[email]',
        '/(?<!\d)[12]\d{9}(?!\d)/' => '[identity]',
        '/(?<![\w\d])(?:\+|00)?\d[\d\s-]{7,14}\d(?![\w\d])/' => '[phone]',
    ];

    public function __invoke(Logger $logger): void
    {
        $handler = $logger->getLogger();

        if (method_exists($handler, 'pushProcessor')) {
            $handler->pushProcessor(fn (LogRecord $record): LogRecord => $record->with(
                message: self::redact($record->message),
                context: self::redactArray($record->context),
            ));
        }
    }

    public static function redact(string $text): string
    {
        $redacted = preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $text);

        return is_string($redacted) ? $redacted : $text;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private static function redactArray(array $values): array
    {
        return array_map(fn (mixed $value): mixed => match (true) {
            is_string($value) => self::redact($value),
            is_array($value) => self::redactArray($value),
            default => $value,
        }, $values);
    }
}
