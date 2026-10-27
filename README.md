# MCP Inspector

A desktop inspector for Model Context Protocol servers: open a server over stdio, Streamable HTTP or SSE, pick the protocol version to propose, then list and call its tools, read its resources, get its prompts, and watch every JSON-RPC message cross the wire, in native windows on macOS and Linux.

MCP Inspector is written in PHP on the [Venusian](https://venusian.projectsaturnstudios.com/docs) framework. It draws with AppKit on macOS and GTK 4 on Linux, and speaks MCP through [superconductor/mcp](https://github.com/superconductor-mcp/mcp): the client rides the application's own event loop, so there is no proxy process and no browser between you and the server.

## What's inside

| Page | What you get |
| --- | --- |
| Sidebar | Every open session with its status and negotiated version. Sessions run side by side; pick one to work with it, or open another. |
| New session | A preset from `config/mcp.php`, or a transport typed in: command, arguments, working directory and environment for stdio; URL and headers for http or sse. A picker names the protocol version the client proposes. |
| Tools | The server's tools; pick one to see its input schema and a starting arguments object, edit the JSON, call it, read the result. |
| Resources | Resources and resource templates; pick one or type a URI, read it. |
| Prompts | Prompts with their arguments; fill the JSON, get the messages. |
| History | Every message sent and received, in order, with the full JSON of any row you pick. |
| Notifications | The server's notifications alone: log messages, list changes, progress, resource updates. |
| Server | The negotiated version, status, last error, server info and capabilities. |

The Session menu carries Ping, Reconnect and Close Session for the shown session, and New Session (⌘N / Ctrl+N).

## Protocol versions

Each session chooses the version its client proposes from the versions listed under `servers.supported_mcp_versions` in `config/mcp.php`, newest first. The server answers with that version, or with another it speaks; a session shows the one agreed on. A version outside the list is refused, as the client always does.

## Run from source

You need PHP 8.4 or newer, Composer, and the toolkit extension for your platform: `appkit` on macOS, `gtk` on Linux. Both install with the [Venusian installer](https://github.com/VenusianPHP/installer):

```bash
venusian install:ext
```

Then:

```bash
composer setup
php rocket inspector
```

On Linux, swap `jovian/venusian-appkit` for `jovian/venusian-gtk` in `composer.json` and set `TOOLKIT_BRIDGE=gtk` in `.env` before `composer setup`.

The `self` preset opens the app's own small server (`php rocket mcp:cli self`) over stdio: two tools, a resource and a prompt, enough to see every page work with nothing else installed. The `pss` preset is a Streamable HTTP example that reads its bearer token from `PSS_MCP_TOKEN` in `.env`.

## Presets

Entries under `mcp_servers` in `config/mcp.php` take the shape Claude Desktop uses, so one pastes in as it is:

```php
'neo4j' => [
    'command' => 'neo4j-mcp',
    'env' => ['NEO4J_URI' => 'bolt://localhost:7687'],
],
'remote' => [
    'type' => 'http',
    'url' => 'https://example.com/mcp',
    'headers' => ['Authorization' => 'Bearer …'],
],
```

An entry with a command and no type is stdio. The inspector never connects to a preset on its own; it fills the new session form when you pick it.

## Tests

```bash
composer test
```

The suite drives the session classes on a recording connection with no server behind it: version proposal and restoration, both directions of the wire, notifications, form parsing and the arguments skeleton.

## License

GPL-3.0-only, with the [additional permission](LICENSE-EXCEPTION) to combine with PHP and its extensions.
