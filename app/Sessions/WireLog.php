<?php

namespace App\Sessions;

/** Every message a session sent or received, in order. Screens poll revision() to learn of new ones. */
final class WireLog
{
    /** @var list<WireEntry> */
    private array $entries = [];

    public function out(string $line): void
    {
        $this->entries[] = WireEntry::outgoing($line);
    }

    public function in(object $message): void
    {
        $this->entries[] = WireEntry::incoming($message);
    }

    /** @return list<WireEntry> */
    public function entries(): array
    {
        return $this->entries;
    }

    /** @return list<WireEntry> the server's notifications only */
    public function notifications(): array
    {
        return array_values(array_filter(
            $this->entries,
            fn (WireEntry $entry): bool => $entry->direction === WireEntry::IN && $entry->isNotification(),
        ));
    }

    public function revision(): int
    {
        return count($this->entries);
    }
}
