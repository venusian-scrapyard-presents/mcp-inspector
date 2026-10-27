<?php

namespace App\Sessions;

use InvalidArgumentException;

/**
 * What a session connects to and how: a name, a transport entry in the shape config/mcp.php
 * uses for mcp_servers, and the protocol version the client proposes.
 */
final readonly class SessionDefinition
{
    public const array TYPES = ['stdio', 'http', 'sse'];

    public function __construct(
        public string $name,
        public string $type,
        public string $version,
        public array $entry,
    ) {}

    /**
     * The new session form's fields. Each transport reads only its own fields.
     *
     * @param array{name?: string, type?: string, version?: string, command?: string, args?: string, cwd?: string, env?: string, url?: string, headers?: string} $form
     *
     * @throws InvalidArgumentException naming the first field that is wrong
     */
    public static function fromForm(array $form): self
    {
        $name = trim((string) ($form['name'] ?? ''));
        $type = (string) ($form['type'] ?? 'stdio');
        $version = trim((string) ($form['version'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('Give the session a name.');
        }

        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("[{$type}] is not a transport: stdio, http or sse.");
        }

        if ($version === '') {
            throw new InvalidArgumentException('Pick a protocol version.');
        }

        if ($type === 'stdio') {
            $command = trim((string) ($form['command'] ?? ''));

            if ($command === '') {
                throw new InvalidArgumentException('A stdio session needs a command.');
            }

            $entry = ['type' => 'stdio', 'command' => $command];
            $args = self::splitArgs((string) ($form['args'] ?? ''));
            $cwd = trim((string) ($form['cwd'] ?? ''));
            $env = self::parseLines((string) ($form['env'] ?? ''), '=');

            if ($args !== []) {
                $entry['args'] = $args;
            }

            if ($cwd !== '') {
                $entry['cwd'] = $cwd;
            }

            if ($env !== []) {
                $entry['env'] = $env;
            }

            return new self($name, $type, $version, $entry);
        }

        $url = trim((string) ($form['url'] ?? ''));

        if (! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new InvalidArgumentException('An http or sse session needs an http(s) URL.');
        }

        $entry = ['type' => $type, 'url' => $url];
        $headers = self::parseLines((string) ($form['headers'] ?? ''), ':');

        if ($headers !== []) {
            $entry['headers'] = $headers;
        }

        return new self($name, $type, $version, $entry);
    }

    /** A config/mcp.php preset: an entry with a command and no type is stdio, as the client manager reads it. */
    public static function fromEntry(string $name, array $entry, string $version): self
    {
        if (! isset($entry['type']) && isset($entry['command'])) {
            $entry['type'] = 'stdio';
        }

        $type = (string) ($entry['type'] ?? '');

        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Preset [{$name}] has no transport type.");
        }

        return new self($name, $type, $version, $entry);
    }

    /** The entry as config/mcp.php would hold it, with the type named. */
    public function toEntry(): array
    {
        return ['type' => $this->type, ...$this->entry];
    }

    public function describe(): string
    {
        if ($this->type === 'stdio') {
            return 'stdio: '.implode(' ', [$this->entry['command'], ...array_map(strval(...), $this->entry['args'] ?? [])]);
        }

        return "{$this->type}: {$this->entry['url']}";
    }

    /** The form's text for this definition's fields, the reverse of fromForm. */
    public function toForm(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'version' => $this->version,
            'command' => (string) ($this->entry['command'] ?? ''),
            'args' => implode(' ', array_map(self::quoteArg(...), $this->entry['args'] ?? [])),
            'cwd' => (string) ($this->entry['cwd'] ?? ''),
            'env' => self::joinLines($this->entry['env'] ?? [], '='),
            'url' => (string) ($this->entry['url'] ?? ''),
            'headers' => self::joinLines($this->entry['headers'] ?? [], ': '),
        ];
    }

    /**
     * Command arguments typed on one line: split on whitespace, with double or single quotes
     * keeping an argument together.
     *
     * @return list<string>
     */
    public static function splitArgs(string $args): array
    {
        preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"|\'([^\']*)\'|(\S+)/', $args, $matches, PREG_SET_ORDER);

        return array_map(
            fn (array $match): string => isset($match[3]) ? $match[3] : (isset($match[2]) && $match[2] !== '' ? $match[2] : stripcslashes($match[1])),
            $matches,
        );
    }

    /**
     * One KEY=VALUE or Header: value per line; blank lines and lines without the separator are skipped.
     *
     * @return array<string, string>
     */
    public static function parseLines(string $text, string $separator): array
    {
        $pairs = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (! str_contains($line, $separator)) {
                continue;
            }

            [$key, $value] = explode($separator, $line, 2);
            $key = trim($key);

            if ($key !== '') {
                $pairs[$key] = trim($value);
            }
        }

        return $pairs;
    }

    private static function joinLines(array $pairs, string $separator): string
    {
        $lines = [];

        foreach ($pairs as $key => $value) {
            $lines[] = $key.$separator.$value;
        }

        return implode("\n", $lines);
    }

    private static function quoteArg(mixed $arg): string
    {
        $arg = (string) $arg;

        return preg_match('/\s/', $arg) ? '"'.addcslashes($arg, '"\\').'"' : $arg;
    }
}
