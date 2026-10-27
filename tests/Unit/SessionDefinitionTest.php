<?php

use App\Sessions\SessionDefinition;

it('splits typed arguments on whitespace and keeps quoted ones together', function () {
    expect(SessionDefinition::splitArgs('rocket mcp:cli self'))->toBe(['rocket', 'mcp:cli', 'self'])
        ->and(SessionDefinition::splitArgs('--name "Angel G" \'two words\' plain'))->toBe(['--name', 'Angel G', 'two words', 'plain'])
        ->and(SessionDefinition::splitArgs('   '))->toBe([])
        ->and(SessionDefinition::splitArgs('"a \"quoted\" word"'))->toBe(['a "quoted" word']);
});

it('reads one pair per line and skips lines without the separator', function () {
    expect(SessionDefinition::parseLines("NEO4J_URI=bolt://localhost:7687\n\nnot a pair\nTOKEN = abc=def ", '='))->toBe([
        'NEO4J_URI' => 'bolt://localhost:7687',
        'TOKEN' => 'abc=def',
    ])
        ->and(SessionDefinition::parseLines("Authorization: Bearer x\nAccept: text/event-stream", ':'))->toBe([
            'Authorization' => 'Bearer x',
            'Accept' => 'text/event-stream',
        ]);
});

it('builds a stdio definition from the form, leaving empty fields out of the entry', function () {
    $definition = SessionDefinition::fromForm([
        'name' => ' self ',
        'type' => 'stdio',
        'version' => '2025-03-26',
        'command' => 'php',
        'args' => 'rocket mcp:cli self',
        'cwd' => '',
        'env' => "APP_ENV=testing\n",
        'url' => 'ignored',
        'headers' => 'ignored: yes',
    ]);

    expect($definition->name)->toBe('self')
        ->and($definition->type)->toBe('stdio')
        ->and($definition->version)->toBe('2025-03-26')
        ->and($definition->toEntry())->toBe(['type' => 'stdio', 'command' => 'php', 'args' => ['rocket', 'mcp:cli', 'self'], 'env' => ['APP_ENV' => 'testing']])
        ->and($definition->describe())->toBe('stdio: php rocket mcp:cli self');
});

it('builds an http definition from the form with its headers', function () {
    $definition = SessionDefinition::fromForm([
        'name' => 'pss',
        'type' => 'http',
        'version' => '2025-03-26',
        'url' => 'https://projectsaturnstudios.com/mcp/operator',
        'headers' => 'Authorization: Bearer token',
    ]);

    expect($definition->toEntry())->toBe(['type' => 'http', 'url' => 'https://projectsaturnstudios.com/mcp/operator', 'headers' => ['Authorization' => 'Bearer token']])
        ->and($definition->describe())->toBe('http: https://projectsaturnstudios.com/mcp/operator');
});

it('names the first field that is wrong', function () {
    expect(fn () => SessionDefinition::fromForm(['name' => '', 'type' => 'stdio', 'version' => 'v']))->toThrow(InvalidArgumentException::class, 'name')
        ->and(fn () => SessionDefinition::fromForm(['name' => 'a', 'type' => 'ftp', 'version' => 'v']))->toThrow(InvalidArgumentException::class, 'transport')
        ->and(fn () => SessionDefinition::fromForm(['name' => 'a', 'type' => 'stdio', 'version' => '']))->toThrow(InvalidArgumentException::class, 'version')
        ->and(fn () => SessionDefinition::fromForm(['name' => 'a', 'type' => 'stdio', 'version' => 'v', 'command' => '']))->toThrow(InvalidArgumentException::class, 'command')
        ->and(fn () => SessionDefinition::fromForm(['name' => 'a', 'type' => 'sse', 'version' => 'v', 'url' => 'localhost:8080']))->toThrow(InvalidArgumentException::class, 'URL');
});

it('reads a preset the way the client manager does: a command with no type is stdio', function () {
    $definition = SessionDefinition::fromEntry('neo4j', ['command' => 'neo4j-mcp', 'env' => ['NEO4J_URI' => 'bolt://x']], '2024-11-05');

    expect($definition->type)->toBe('stdio')
        ->and($definition->toEntry())->toBe(['type' => 'stdio', 'command' => 'neo4j-mcp', 'env' => ['NEO4J_URI' => 'bolt://x']])
        ->and(fn () => SessionDefinition::fromEntry('bad', ['url' => 'https://x'], 'v'))->toThrow(InvalidArgumentException::class, 'transport type');
});

it('round-trips through the form', function () {
    $definition = SessionDefinition::fromEntry('self', ['type' => 'stdio', 'command' => 'php', 'args' => ['rocket', 'two words'], 'cwd' => '/tmp', 'env' => ['A' => '1']], '2025-03-26');

    $form = $definition->toForm();

    expect($form['args'])->toBe('rocket "two words"')
        ->and($form['env'])->toBe('A=1')
        ->and(SessionDefinition::fromForm($form)->toEntry())->toBe($definition->toEntry());
});
