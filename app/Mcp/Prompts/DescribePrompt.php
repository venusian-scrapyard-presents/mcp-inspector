<?php

namespace App\Mcp\Prompts;

use Fabricate\NutsAndBolts\Attributes\PromptGet;
use Fabricate\NutsAndBolts\PromptMessage;
use Fabricate\NutsAndBolts\Schema\TextProperty;

#[PromptGet]
class DescribePrompt
{
    public function handle(string $topic): array
    {
        return [
            PromptMessage::user(new TextProperty("Describe {$topic} in one line.")),
        ];
    }
}
