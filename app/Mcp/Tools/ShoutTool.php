<?php

namespace App\Mcp\Tools;

use Fabricate\NutsAndBolts\Attributes\ToolCall;
use Fabricate\NutsAndBolts\Schema\TextProperty;

#[ToolCall]
class ShoutTool
{
    public function handle(string $input): TextProperty
    {
        return new TextProperty(mb_strtoupper($input).'!');
    }
}
