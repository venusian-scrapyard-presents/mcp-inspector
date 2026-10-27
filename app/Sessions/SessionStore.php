<?php

namespace App\Sessions;

use InvalidArgumentException;

/** The open sessions, by name, in the order they were opened. */
final class SessionStore
{
    /** @var array<string, Session> */
    private array $sessions = [];

    public function add(Session $session): void
    {
        if ($this->has($session->name())) {
            throw new InvalidArgumentException("A session named [{$session->name()}] is already open.");
        }

        $this->sessions[$session->name()] = $session;
    }

    /** Put a reopened session where its predecessor was. */
    public function replace(Session $session): void
    {
        $this->sessions[$session->name()] = $session;
    }

    public function has(string $name): bool
    {
        return isset($this->sessions[$name]);
    }

    public function get(string $name): Session
    {
        return $this->sessions[$name] ?? throw new InvalidArgumentException("No session named [{$name}] is open.");
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->sessions);
    }

    /** @return array<string, Session> */
    public function all(): array
    {
        return $this->sessions;
    }

    public function remove(string $name): void
    {
        if ($this->has($name)) {
            $this->sessions[$name]->close();
            unset($this->sessions[$name]);
        }
    }

    public function closeAll(): void
    {
        foreach (array_keys($this->sessions) as $name) {
            $this->remove($name);
        }
    }

    /** A name no open session uses, for the form's default. */
    public function freeName(string $base): string
    {
        $base = $base === '' ? 'session' : $base;

        if (! $this->has($base)) {
            return $base;
        }

        for ($n = 2; ; $n++) {
            if (! $this->has("{$base}-{$n}")) {
                return "{$base}-{$n}";
            }
        }
    }
}
