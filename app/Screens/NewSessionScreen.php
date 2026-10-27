<?php

namespace App\Screens;

use App\Actions\Work\Navigate;
use App\GlobalVariables;
use App\Sessions\Session;
use App\Sessions\SessionDefinition;
use InvalidArgumentException;
use Surface\Contracts\Windows\Mail\View\ButtonClicked;
use Surface\Contracts\Windows\Mail\View\SelectionChanged;
use Surface\Contracts\Windows\Mail\View\TextSubmitted;
use Surface\Contracts\Windows\Primitives\TKButton;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKDropdown;
use Surface\Contracts\Windows\Primitives\TKLabel;
use Surface\Contracts\Windows\Primitives\TKRow;
use Surface\Contracts\Windows\Primitives\TKTextArea;
use Surface\Contracts\Windows\Primitives\TKTextInput;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\FontWeight;
use Surface\NutsAndBolts\Color;
use Throwable;
use Voyager\Contracts\Signals\NamedSignal;

/** The form that opens a session: a preset or typed-in transport, and the protocol version to propose. */
final class NewSessionScreen extends Screen
{
    private const string NO_PRESET = '(none)';

    /** @var list<string> */
    private array $presets = [];

    /** @var list<string> newest first */
    private array $versions = [];

    private ?TKDropdown $preset = null;

    private ?TKTextInput $name = null;

    private ?TKDropdown $type = null;

    private ?TKDropdown $version = null;

    private ?TKRow $command_row = null;

    private ?TKRow $args_row = null;

    private ?TKRow $cwd_row = null;

    private ?TKRow $env_row = null;

    private ?TKRow $url_row = null;

    private ?TKRow $headers_row = null;

    private ?TKTextInput $command = null;

    private ?TKTextInput $args = null;

    private ?TKTextInput $cwd = null;

    private ?TKTextArea $env = null;

    private ?TKTextInput $url = null;

    private ?TKTextArea $headers = null;

    private ?TKButton $open = null;

    private ?TKLabel $message = null;

    public function key(): string
    {
        return GlobalVariables::FORM;
    }

    public function index(TKColumn $root): void
    {
        $bag = GlobalVariables::make();
        $this->presets = array_keys((array) config('mcp.mcp_servers', []));
        $this->versions = self::versions();

        $root->label('heading', 'New session')->setFont(new FontSpec(20, FontWeight::BOLD));
        $root->label('about', 'Open a connection to an MCP server. Every open session runs on its own; pick one in the sidebar to work with it.')
            ->setWrap(true)
            ->setTextColor(Color::hex('#8a8a8a'));

        $this->preset = $this->field($root, 'preset', 'Preset')->dropdown('preset', [self::NO_PRESET, ...$this->presets]);
        $this->name = $this->input($this->field($root, 'name', 'Name')->textInput('name', $bag->sessions->freeName('session'), 'A name for the sidebar'));
        $this->type = $this->field($root, 'type', 'Transport')->dropdown('type', SessionDefinition::TYPES);
        $this->version = $this->field($root, 'version', 'Protocol version')->dropdown('version', $this->versions);

        $this->command_row = $this->field($root, 'command', 'Command');
        $this->command = $this->input($this->command_row->textInput('command', '', 'php, npx, an executable on PATH'));
        $this->args_row = $this->field($root, 'args', 'Arguments');
        $this->args = $this->input($this->args_row->textInput('args', '', 'One line; quote an argument with spaces'));
        $this->cwd_row = $this->field($root, 'cwd', 'Working directory');
        $this->cwd = $this->input($this->cwd_row->textInput('cwd', '', 'Leave empty for this app\'s own'));
        $this->env_row = $this->field($root, 'env', 'Environment');
        $this->env = $this->input($this->env_row->textArea('env', ''), 72);

        $this->url_row = $this->field($root, 'url', 'URL');
        $this->url = $this->input($this->url_row->textInput('url', '', 'https://host/mcp'));
        $this->headers_row = $this->field($root, 'headers', 'Headers');
        $this->headers = $this->input($this->headers_row->textArea('headers', ''), 72);

        $actions = $root->row('actions', 8);
        $this->open = $actions->button('open', 'Open session');
        $this->message = $actions->label('message', '');
        $this->message->setTextColor(Color::hex('#c0392b'));
        $this->message->fill(true, false);

        $root->column('spacer')->fill(false, true);

        $this->showTransportFields('stdio');
    }

    public function deliver(NamedSignal $mail): bool
    {
        return match (true) {
            $mail instanceof SelectionChanged && $mail->uuid() === $this->preset?->uuid() => $this->loadPreset($mail->option),
            $mail instanceof SelectionChanged && $mail->uuid() === $this->type?->uuid() => $this->showTransportFields((string) $mail->option),
            $mail instanceof ButtonClicked && $mail->uuid() === $this->open?->uuid(),
            $mail instanceof TextSubmitted && in_array($mail->uuid(), $this->submitters(), true) => $this->openSession(),
            default => false,
        };
    }

    /** The versions config/mcp.php lists, newest first; the first is the default proposal. */
    public static function versions(): array
    {
        $versions = array_values(array_map(strval(...), (array) config('mcp.servers.supported_mcp_versions', ['2024-11-05'])));
        rsort($versions, SORT_STRING);

        return $versions;
    }

    /** @return list<string> */
    private function submitters(): array
    {
        return array_values(array_filter([$this->name?->uuid(), $this->command?->uuid(), $this->args?->uuid(), $this->cwd?->uuid(), $this->url?->uuid()]));
    }

    private function field(TKColumn $root, string $name, string $label): TKRow
    {
        $row = $root->row("{$name}-row", 8);
        $row->label("{$name}-label", $label)->minSize(140, 0);

        return $row;
    }

    /**
     * In a row an AppKit field measures as zero width without a floor: give it one and the row's spare width.
     *
     * @template T of TKTextInput|TKTextArea
     * @param T $field
     * @return T
     */
    private function input(TKTextInput|TKTextArea $field, int $height = 0): TKTextInput|TKTextArea
    {
        $field->minSize(420, $height);
        $field->fill(true, false);

        return $field;
    }

    private function showTransportFields(string $type): bool
    {
        $stdio = $type === 'stdio';

        foreach ([$this->command_row, $this->args_row, $this->cwd_row, $this->env_row] as $row) {
            $row?->setVisible($stdio);
        }

        foreach ([$this->url_row, $this->headers_row] as $row) {
            $row?->setVisible(! $stdio);
        }

        return true;
    }

    private function loadPreset(?string $option): bool
    {
        if (is_null($option) || $option === self::NO_PRESET) {
            return true;
        }

        $entry = (array) (config('mcp.mcp_servers', [])[$option] ?? []);

        try {
            $definition = SessionDefinition::fromEntry($option, $entry, $this->versions[0]);
        } catch (InvalidArgumentException $e) {
            $this->message?->setText($e->getMessage());

            return true;
        }

        $this->fill($definition->toForm());
        $this->name?->setValue(GlobalVariables::make()->sessions->freeName($option));

        return true;
    }

    private function fill(array $form): void
    {
        $this->command?->setValue($form['command']);
        $this->args?->setValue($form['args']);
        $this->cwd?->setValue($form['cwd']);
        $this->env?->setValue($form['env']);
        $this->url?->setValue($form['url']);
        $this->headers?->setValue($form['headers']);

        $type = array_search($form['type'], SessionDefinition::TYPES, true);
        $this->type?->select($type === false ? 0 : $type);
        $this->showTransportFields($form['type']);
    }

    private function form(): array
    {
        return [
            'name' => $this->name?->value() ?? '',
            'type' => $this->type?->selectedOption() ?? 'stdio',
            'version' => $this->version?->selectedOption() ?? '',
            'command' => $this->command?->value() ?? '',
            'args' => $this->args?->value() ?? '',
            'cwd' => $this->cwd?->value() ?? '',
            'env' => $this->env?->value() ?? '',
            'url' => $this->url?->value() ?? '',
            'headers' => $this->headers?->value() ?? '',
        ];
    }

    private function openSession(): bool
    {
        $bag = GlobalVariables::make();

        try {
            $definition = SessionDefinition::fromForm($this->form());

            if ($bag->sessions->has($definition->name)) {
                throw new InvalidArgumentException("A session named [{$definition->name}] is already open.");
            }

            $session = Session::open($definition, $bag->event_loop);
        } catch (InvalidArgumentException $e) {
            $this->message?->setText($e->getMessage());

            return true;
        } catch (Throwable $e) {
            $this->message?->setText('Could not open the session: '.$e->getMessage());

            return true;
        }

        $this->message?->setText('');
        $bag->sessions->add($session);
        $bag->screens[$session->name()] = new SessionScreen($session);
        $bag->info("Session [{$session->name()}] opened: {$definition->describe()}");
        $this->name?->setValue($bag->sessions->freeName('session'));

        Navigate::to($session->name(), $bag);

        return true;
    }
}
