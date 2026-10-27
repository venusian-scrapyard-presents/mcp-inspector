<?php

namespace App\Screens;

use App\Actions\Work\Navigate;
use App\GlobalVariables;
use App\NutsAndBolts\Json;
use App\Sessions\Session;
use App\Sessions\WireEntry;
use Fabricate\Client\ClientStatus;
use Fabricate\NutsAndBolts\Messages\Ping\PingResponse;
use Fabricate\NutsAndBolts\Messages\Prompts\GetPromptResponse;
use Fabricate\NutsAndBolts\Messages\Resources\ReadResourceResponse;
use Fabricate\NutsAndBolts\Messages\Tools\CallToolResponse;
use InvalidArgumentException;
use Surface\Contracts\Windows\Mail\View\ButtonClicked;
use Surface\Contracts\Windows\Mail\View\RowSelected;
use Surface\Contracts\Windows\Primitives\TableColumn;
use Surface\Contracts\Windows\Primitives\TKButton;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKLabel;
use Surface\Contracts\Windows\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Windows\Primitives\TKTable;
use Surface\Contracts\Windows\Primitives\TKTextArea;
use Surface\Contracts\Windows\Primitives\TKTextInput;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\FontWeight;
use Surface\NutsAndBolts\Color;
use Throwable;
use Voyager\Contracts\Signals\NamedSignal;

/** One open session: its tools, resources and prompts to try, and everything that crossed its wire. */
final class SessionScreen extends Screen
{
    private ?TKLabel $status = null;

    private ?TKLabel $version = null;

    private ?TKButton $ping = null;

    private ?TKButton $reconnect = null;

    private ?TKButton $close = null;

    private ?TKTable $tools = null;

    private ?TKTextArea $tool_schema = null;

    private ?ArgumentsForm $tool_form = null;

    private ?TKButton $call = null;

    private ?TKTextArea $tool_result = null;

    private ?TKTable $resources = null;

    private ?TKTable $templates = null;

    private ?TKTextInput $resource_uri = null;

    private ?TKButton $read = null;

    private ?TKTextArea $resource_result = null;

    private ?TKTable $prompts = null;

    private ?ArgumentsForm $prompt_form = null;

    private ?TKButton $get = null;

    private ?TKTextArea $prompt_result = null;

    private ?TKTable $history = null;

    private ?TKTextArea $history_detail = null;

    private ?TKTable $notifications = null;

    private ?TKTextArea $notification_detail = null;

    private ?TKTextArea $server = null;

    /** How much of the wire log each table has shown. */
    private int $history_shown = 0;

    private int $notifications_shown = 0;

    /** What the header and server page drew last, to redraw them only on change. */
    private string $drawn_state = '';

    /** The ready promise the lists were last loaded from, so a reconnect reloads them. */
    private ?Session $listed_for = null;

    public function __construct(private Session $session) {}

    public function key(): string
    {
        return 'session-'.$this->session->name();
    }

    public function session(): Session
    {
        return $this->session;
    }

    public function index(TKColumn $root): void
    {
        $header = $root->row('header', 10);
        $header->label('name', $this->session->name())->setFont(new FontSpec(18, FontWeight::BOLD));
        // The full command or URL sits on the Server page; a long one here pushes the buttons off the window.
        $header->label('transport', $this->session->definition->type)->setTextColor(Color::hex('#8a8a8a'));
        $header->column('spacer')->fill(true, false);
        $this->version = $header->label('version', '');
        $this->status = $header->label('status', '');
        $this->ping = $header->button('ping', 'Ping');
        $this->reconnect = $header->button('reconnect', 'Reconnect');
        $this->close = $header->button('close', 'Close');

        $tabs = $root->tabs('tabs');
        $tabs->fill();

        $this->toolsPage($tabs->addPage('tools', 'Tools'));
        $this->resourcesPage($tabs->addPage('resources', 'Resources'));
        $this->promptsPage($tabs->addPage('prompts', 'Prompts'));
        $this->historyPage($tabs->addPage('history', 'History'));
        $this->notificationsPage($tabs->addPage('notifications', 'Notifications'));
        $this->serverPage($tabs->addPage('server', 'Server'));
    }

    public function deliver(NamedSignal $mail): bool
    {
        return match (true) {
            $mail instanceof ButtonClicked && $mail->uuid() === $this->ping?->uuid() => $this->pingServer(),
            $mail instanceof ButtonClicked && $mail->uuid() === $this->reconnect?->uuid() => $this->reconnectSession(),
            $mail instanceof ButtonClicked && $mail->uuid() === $this->close?->uuid() => $this->closeSession(),
            $mail instanceof ButtonClicked && $mail->uuid() === $this->call?->uuid() => $this->callTool(),
            $mail instanceof ButtonClicked && $mail->uuid() === $this->read?->uuid() => $this->readResource(),
            $mail instanceof ButtonClicked && $mail->uuid() === $this->get?->uuid() => $this->getPrompt(),
            $mail instanceof RowSelected && $mail->uuid() === $this->tools?->uuid() => $this->toolPicked($mail->row),
            $mail instanceof RowSelected && $mail->uuid() === $this->prompts?->uuid() => $this->promptPicked($mail->row),
            $mail instanceof RowSelected && $mail->uuid() === $this->resources?->uuid() => $this->resourcePicked($mail->row),
            $mail instanceof RowSelected && $mail->uuid() === $this->templates?->uuid() => $this->templatePicked($mail->row),
            $mail instanceof RowSelected && $mail->uuid() === $this->history?->uuid() => $this->entryPicked($this->session->log->entries(), $mail->row, $this->history_detail),
            $mail instanceof RowSelected && $mail->uuid() === $this->notifications?->uuid() => $this->entryPicked($this->session->log->notifications(), $mail->row, $this->notification_detail),
            default => false,
        };
    }

    /** The Session menu's items, by id. */
    public function menu(string $item): bool
    {
        return match ($item) {
            'ping' => $this->pingServer(),
            'reconnect' => $this->reconnectSession(),
            'close-session' => $this->closeSession(),
            default => false,
        };
    }

    public function tick(): void
    {
        $this->drawHeader();
        $this->drawLists();
        $this->appendEntries($this->session->log->entries(), $this->history, $this->history_shown);
        $this->appendEntries($this->session->log->notifications(), $this->notifications, $this->notifications_shown);
    }

    // ------------------------------------------------------------------ pages

    private function toolsPage(TKColumn $page): void
    {
        $split = $page->row('split', 10);
        $split->fill();

        $left = $split->column('list', 6);
        $left->minSize(300, 0);
        $left->fill(false, true);
        $this->tools = $left->table('tools', [new TableColumn('name', 'Tool'), new TableColumn('description', 'Description')]);
        $this->tools->fill();

        $right = $split->column('detail', 6);
        $right->fill();
        $right->label('args-label', 'Arguments')->setFont(new FontSpec(13, FontWeight::BOLD));
        $this->tool_form = new ArgumentsForm($right->column('form'));
        $right->label('schema-label', 'Input schema')->setTextColor(Color::hex('#8a8a8a'));
        $this->tool_schema = $this->jsonArea($right, 'schema', true, 90);
        $actions = $right->row('actions', 8);
        $this->call = $actions->button('call', 'Call tool');
        $right->label('result-label', 'Result');
        $this->tool_result = $this->jsonArea($right, 'result', true, 120);
        $this->tool_result->fill();
    }

    private function resourcesPage(TKColumn $page): void
    {
        $split = $page->row('split', 10);
        $split->fill();

        $left = $split->column('list', 6);
        $left->minSize(340, 0);
        $left->fill(false, true);
        $left->label('resources-label', 'Resources');
        $this->resources = $left->table('resources', [new TableColumn('uri', 'URI'), new TableColumn('name', 'Name'), new TableColumn('mimeType', 'Type')]);
        $this->resources->fill();
        $left->label('templates-label', 'Templates');
        $this->templates = $left->table('templates', [new TableColumn('uriTemplate', 'URI template'), new TableColumn('name', 'Name')]);
        $this->templates->fill();

        $right = $split->column('detail', 6);
        $right->fill();
        $uri = $right->row('uri-row', 8);
        $uri->label('uri-label', 'URI');
        $this->resource_uri = $uri->textInput('uri', '', 'A listed URI, or a template filled in');
        $this->resource_uri->minSize(360, 0);
        $this->resource_uri->fill(true, false);
        $this->read = $uri->button('read', 'Read');
        $right->label('result-label', 'Contents');
        $this->resource_result = $this->jsonArea($right, 'result', true, 120);
        $this->resource_result->fill();
    }

    private function promptsPage(TKColumn $page): void
    {
        $split = $page->row('split', 10);
        $split->fill();

        $left = $split->column('list', 6);
        $left->minSize(300, 0);
        $left->fill(false, true);
        $this->prompts = $left->table('prompts', [new TableColumn('name', 'Prompt'), new TableColumn('arguments', 'Arguments'), new TableColumn('description', 'Description')]);
        $this->prompts->fill();

        $right = $split->column('detail', 6);
        $right->fill();
        $right->label('args-label', 'Arguments')->setFont(new FontSpec(13, FontWeight::BOLD));
        $this->prompt_form = new ArgumentsForm($right->column('form'));
        $actions = $right->row('actions', 8);
        $this->get = $actions->button('get', 'Get prompt');
        $right->label('result-label', 'Messages');
        $this->prompt_result = $this->jsonArea($right, 'result', true, 120);
        $this->prompt_result->fill();
    }

    private function historyPage(TKColumn $page): void
    {
        $this->history = $page->table('history', self::entryColumns());
        $this->history->fill();
        $this->history->minSize(0, 160);
        $this->history_detail = $this->jsonArea($page, 'detail', true, 160);
        $this->history_detail->fill();
    }

    private function notificationsPage(TKColumn $page): void
    {
        $this->notifications = $page->table('notifications', self::entryColumns());
        $this->notifications->fill();
        $this->notifications->minSize(0, 160);
        $this->notification_detail = $this->jsonArea($page, 'detail', true, 160);
        $this->notification_detail->fill();
    }

    private function serverPage(TKColumn $page): void
    {
        $this->server = $this->jsonArea($page, 'server', true, 200);
        $this->server->fill();
    }

    /** @return list<TableColumn> */
    private static function entryColumns(): array
    {
        return [
            new TableColumn('n', '#'),
            new TableColumn('dir', ''),
            new TableColumn('kind', 'Kind'),
            new TableColumn('method', 'Method'),
            new TableColumn('id', 'Id'),
            new TableColumn('at', 'At'),
        ];
    }

    private function jsonArea(TKPrimitiveGroup $in, string $name, bool $read_only, int $height): TKTextArea
    {
        $area = $in->textArea($name, '');
        $area->setFont(new FontSpec(12, monospace: true));
        $area->setReadOnly($read_only);
        $area->setWrap(false);
        $area->minSize(0, $height);

        return $area;
    }

    // ------------------------------------------------------------------ drawing

    private function drawHeader(): void
    {
        $client = $this->session->client;
        $status = $client->status();
        $state = implode('|', [$status->value, $this->session->version(), (string) $client->lastError(), Json::pretty($client->serverInfo())]);

        if ($state === $this->drawn_state) {
            return;
        }

        $this->drawn_state = $state;
        $this->version?->setText($this->session->version());
        $this->status?->setText(ucfirst($status->value));
        $this->status?->setTextColor(match ($status) {
            ClientStatus::Connected => Color::hex('#27ae60'),
            ClientStatus::Error => Color::hex('#c0392b'),
            default => Color::hex('#8a8a8a'),
        });

        $this->server?->setValue(Json::pretty([
            'session' => $this->session->name(),
            'transport' => $this->session->definition->describe(),
            'entry' => $this->session->definition->toEntry(),
            'proposedVersion' => $client->proposedVersion(),
            'protocolVersion' => $client->protocolVersion(),
            'status' => $status->value,
            'lastError' => $client->lastError(),
            'serverInfo' => $client->serverInfo(),
            'capabilities' => $client->serverCapabilities(),
        ]));
    }

    /** Fill the tool, resource and prompt tables once the client is ready, and again after a reconnect. */
    private function drawLists(): void
    {
        $client = $this->session->client;

        if ($this->listed_for === $this->session || ! $client->ready()->fulfilled()) {
            return;
        }

        $this->listed_for = $this->session;

        $this->tools?->setRows(array_map(fn (array $tool): array => [
            'name' => (string) ($tool['name'] ?? ''),
            'description' => (string) ($tool['description'] ?? ''),
        ], $client->tools()));

        $this->resources?->setRows(array_map(fn (array $resource): array => [
            'uri' => (string) ($resource['uri'] ?? ''),
            'name' => (string) ($resource['name'] ?? ''),
            'mimeType' => (string) ($resource['mimeType'] ?? ''),
        ], $client->resources()));

        $this->templates?->setRows(array_map(fn (array $template): array => [
            'uriTemplate' => (string) ($template['uriTemplate'] ?? ''),
            'name' => (string) ($template['name'] ?? ''),
        ], $client->resourceTemplates()));

        $this->prompts?->setRows(array_map(fn (array $prompt): array => [
            'name' => (string) ($prompt['name'] ?? ''),
            'arguments' => implode(', ', array_map(
                fn (array $argument): string => ($argument['name'] ?? '').(($argument['required'] ?? false) ? '*' : ''),
                array_filter((array) ($prompt['arguments'] ?? []), is_array(...)),
            )),
            'description' => (string) ($prompt['description'] ?? ''),
        ], $client->prompts()));
    }

    /** @param list<WireEntry> $entries */
    private function appendEntries(array $entries, ?TKTable $table, int &$shown): void
    {
        if (is_null($table)) {
            return;
        }

        for ($i = $shown; $i < count($entries); $i++) {
            $table->appendRow($entries[$i]->row($i + 1));
        }

        $shown = count($entries);
    }

    // ------------------------------------------------------------------ actions

    private function toolPicked(?int $row): bool
    {
        $tool = $this->session->client->tools()[$row ?? -1] ?? null;

        if (is_null($tool)) {
            return true;
        }

        $schema = (array) ($tool['inputSchema'] ?? ['type' => 'object']);
        $this->tool_schema?->setValue(Json::pretty($schema));
        $this->tool_form?->fromSchema($schema);
        $this->tool_result?->setValue('');

        return true;
    }

    private function promptPicked(?int $row): bool
    {
        $prompt = $this->session->client->prompts()[$row ?? -1] ?? null;

        if (! is_null($prompt)) {
            $this->prompt_form?->fromPromptArguments((array) ($prompt['arguments'] ?? []));
            $this->prompt_result?->setValue('');
        }

        return true;
    }

    private function resourcePicked(?int $row): bool
    {
        $resource = $this->session->client->resources()[$row ?? -1] ?? null;

        if (! is_null($resource)) {
            $this->resource_uri?->setValue((string) ($resource['uri'] ?? ''));
        }

        return true;
    }

    private function templatePicked(?int $row): bool
    {
        $template = $this->session->client->resourceTemplates()[$row ?? -1] ?? null;

        if (! is_null($template)) {
            $this->resource_uri?->setValue((string) ($template['uriTemplate'] ?? ''));
        }

        return true;
    }

    /** @param list<WireEntry> $entries */
    private function entryPicked(array $entries, ?int $row, ?TKTextArea $detail): bool
    {
        $entry = $entries[$row ?? -1] ?? null;
        $detail?->setValue($entry?->json ?? '');

        return true;
    }

    private function callTool(): bool
    {
        $row = $this->tools?->selectedRow();
        $tool = $this->session->client->tools()[$row ?? -1] ?? null;

        if (is_null($tool)) {
            $this->tool_result?->setValue('Pick a tool first.');

            return true;
        }

        try {
            $arguments = $this->tool_form?->values() ?? [];
        } catch (InvalidArgumentException $e) {
            $this->tool_result?->setValue($e->getMessage());

            return true;
        }

        $this->tool_result?->setValue("Calling {$tool['name']}…");
        $this->session->client->callTool((string) $tool['name'], $arguments)
            ->then(fn (CallToolResponse $response) => $this->tool_result?->setValue(Json::pretty([
                'isError' => $response->is_error,
                'content' => $response->content,
            ])))
            ->error(fn (Throwable $e) => $this->tool_result?->setValue(self::failure($e)));

        return true;
    }

    private function readResource(): bool
    {
        $uri = trim($this->resource_uri?->value() ?? '');

        if ($uri === '') {
            $this->resource_result?->setValue('Type or pick a URI first.');

            return true;
        }

        $this->resource_result?->setValue("Reading {$uri}…");
        $this->session->client->readResource($uri)
            ->then(fn (ReadResourceResponse $response) => $this->resource_result?->setValue(Json::pretty($response->contents)))
            ->error(fn (Throwable $e) => $this->resource_result?->setValue(self::failure($e)));

        return true;
    }

    private function getPrompt(): bool
    {
        $row = $this->prompts?->selectedRow();
        $prompt = $this->session->client->prompts()[$row ?? -1] ?? null;

        if (is_null($prompt)) {
            $this->prompt_result?->setValue('Pick a prompt first.');

            return true;
        }

        try {
            $arguments = $this->prompt_form?->values() ?? [];
        } catch (InvalidArgumentException $e) {
            $this->prompt_result?->setValue($e->getMessage());

            return true;
        }

        $this->prompt_result?->setValue("Getting {$prompt['name']}…");
        $this->session->client->getPrompt((string) $prompt['name'], $arguments)
            ->then(fn (GetPromptResponse $response) => $this->prompt_result?->setValue(Json::pretty([
                'description' => $response->description,
                'messages' => $response->messages,
            ])))
            ->error(fn (Throwable $e) => $this->prompt_result?->setValue(self::failure($e)));

        return true;
    }

    private function pingServer(): bool
    {
        $bag = GlobalVariables::make();
        $sent = microtime(true);
        $name = $this->session->name();

        $this->session->client->ping()
            ->then(fn (PingResponse $response) => $bag->status_label?->setText(sprintf('%s: pong in %.1f ms', $name, (microtime(true) - $sent) * 1000)))
            ->error(fn (Throwable $e) => $bag->status_label?->setText("{$name}: ping failed, ".self::failure($e)));

        return true;
    }

    private function reconnectSession(): bool
    {
        $bag = GlobalVariables::make();
        $this->session = $this->session->reopen($bag->event_loop);
        $bag->sessions->replace($this->session);
        $bag->status_label?->setText("{$this->session->name()}: reconnecting");

        return true;
    }

    private function closeSession(): bool
    {
        $bag = GlobalVariables::make();
        $name = $this->session->name();

        $bag->sessions->remove($name);
        unset($bag->screens[$name]);
        $bag->status_label?->setText("{$name}: closed");

        if ($bag->current === $name) {
            $bag->current = null;
            Navigate::to(array_key_last($bag->screens) ?? GlobalVariables::FORM, $bag);
        }

        $this->unmount();

        return true;
    }

    private static function failure(Throwable $e): string
    {
        return $e->getMessage();
    }
}
