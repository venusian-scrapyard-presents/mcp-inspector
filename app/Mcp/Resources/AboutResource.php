<?php

namespace App\Mcp\Resources;

use App\Mcp\Servers\SelfServer;
use Fabricate\NutsAndBolts\Attributes\ResourceRead;
use Fabricate\NutsAndBolts\Schema\ResourceContents;

#[ResourceRead]
class AboutResource
{
    public function handle(): ResourceContents
    {
        return new ResourceContents(
            SelfServer::ABOUT_URI,
            'text/plain',
            text: 'MCP Inspector, inspecting itself over stdio.',
        );
    }
}
