<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\DescribePrompt;
use App\Mcp\Resources\AboutResource;
use App\Mcp\Tools\EchoTool;
use App\Mcp\Tools\ShoutTool;
use Fabricate\NutsAndBolts\Attributes\Server;
use Fabricate\NutsAndBolts\PromptArgument;
use Fabricate\NutsAndBolts\PromptDefinition;
use Fabricate\NutsAndBolts\ResourceDefinition;
use Fabricate\NutsAndBolts\Schema\StringProperty;
use Fabricate\NutsAndBolts\ToolDefinition;
use Fabricate\Server\MCPServer;

/**
 * A small server the inspector can open against itself (the "self" preset), so the app proves
 * its own wiring without another server on the machine.
 */
#[Server('self')]
class SelfServer extends MCPServer
{
    public const string ABOUT_URI = 'inspector://about';

    protected array $entity_info = ['name' => 'MCP Inspector', 'version' => '0.10.0'];

    protected array $entity_capabilities = [
        'tools' => ['listChanged' => false],
        'resources' => ['subscribe' => false, 'listChanged' => false],
        'prompts' => ['listChanged' => false],
        'logging' => [],
    ];

    public function tools(): array
    {
        return [
            new ToolDefinition('echo', 'You get out what you put in', ['input' => new StringProperty('What to send back')], ['input'], action: EchoTool::class),
            new ToolDefinition('shout', 'The input, upper-cased', ['input' => new StringProperty('What to shout')], ['input'], action: ShoutTool::class),
        ];
    }

    public function resources(): array
    {
        return [
            new ResourceDefinition(self::ABOUT_URI, 'About', 'What this server is', 'text/plain', action: AboutResource::class),
        ];
    }

    public function prompts(): array
    {
        return [
            new PromptDefinition('describe', 'Ask for a one-line description of a topic', [
                new PromptArgument('topic', 'What to describe', required: true),
            ], action: DescribePrompt::class),
        ];
    }
}
