<?php

namespace App\NutsAndBolts;

use InvalidArgumentException;
use JsonException;

final class Json
{
    public static function pretty(mixed $value): string
    {
        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * JSON typed into a toolkit text view. macOS substitutes curly quotes as you type, so the
     * typographic doubles and singles are read as the straight ones first.
     *
     * @throws InvalidArgumentException naming the field when the text is not JSON
     */
    public static function decode(string $text, string $field = 'Arguments'): mixed
    {
        $text = strtr($text, ['“' => '"', '”' => '"', '„' => '"', '‘' => "'", '’' => "'"]);

        try {
            return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException("[{$field}] is not valid JSON: ".$e->getMessage());
        }
    }

    /**
     * A JSON object typed into a form: empty text is an empty object.
     *
     * @throws InvalidArgumentException when the text is not a JSON object
     */
    public static function object(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        $decoded = self::decode($text);

        if (! is_array($decoded) || array_is_list($decoded) && $decoded !== []) {
            throw new InvalidArgumentException('Arguments must be a JSON object.');
        }

        return $decoded;
    }
}
