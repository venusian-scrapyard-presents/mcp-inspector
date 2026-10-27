<?php

namespace App\Screens;

use App\NutsAndBolts\Json;
use InvalidArgumentException;
use Surface\Contracts\Windows\Primitives\TKCheckbox;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKDropdown;
use Surface\Contracts\Windows\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Windows\Primitives\TKTextArea;
use Surface\Contracts\Windows\Primitives\TKTextInput;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\NutsAndBolts\Color;

/**
 * One toolkit field per argument, built from a tool's input schema or a prompt's argument list:
 * a text field for strings and numbers, a checkbox for booleans, a dropdown for enums, and a
 * JSON box only for arrays and objects. values() reads them back as the typed arguments object.
 */
final class ArgumentsForm
{
    /** @var array<string, array{type: string, required: bool, widget: TKTextInput|TKCheckbox|TKDropdown|TKTextArea, enum: list<mixed>}> */
    private array $fields = [];

    private ?TKColumn $column = null;

    private int $generation = 0;

    public function __construct(private readonly TKPrimitiveGroup $host) {}

    /** A JSON-schema object, as a tool's inputSchema. */
    public function fromSchema(array $schema): void
    {
        $required = array_map(strval(...), (array) ($schema['required'] ?? []));
        $this->rebuild();

        $properties = (array) ($schema['properties'] ?? []);

        if ($properties === []) {
            $this->column->label('none', 'This tool takes no arguments.')->setTextColor(Color::hex('#8a8a8a'));

            return;
        }

        foreach ($properties as $name => $property) {
            $this->field((string) $name, is_array($property) ? $property : [], in_array((string) $name, $required, true));
        }
    }

    /** A prompt's arguments: each is a string, with a name, description and required flag. */
    public function fromPromptArguments(array $arguments): void
    {
        $this->rebuild();
        $arguments = array_values(array_filter($arguments, is_array(...)));

        if ($arguments === []) {
            $this->column->label('none', 'This prompt takes no arguments.')->setTextColor(Color::hex('#8a8a8a'));

            return;
        }

        foreach ($arguments as $argument) {
            $this->field((string) ($argument['name'] ?? ''), ['type' => 'string', 'description' => $argument['description'] ?? null], (bool) ($argument['required'] ?? false));
        }
    }

    /**
     * The arguments object the fields hold. An optional field left empty is left out.
     *
     * @throws InvalidArgumentException naming the first field that is wrong
     */
    public function values(): array
    {
        $values = [];

        foreach ($this->fields as $name => $field) {
            $raw = match (true) {
                $field['widget'] instanceof TKCheckbox => $field['widget']->isChecked(),
                $field['widget'] instanceof TKDropdown => $field['widget']->selected(),
                default => $field['widget']->value(),
            };

            $value = self::coerce($name, $field['type'], $raw, $field['required'], $field['enum']);

            if ($value !== self::OMIT) {
                $values[$name] = $value;
            }
        }

        return $values;
    }

    public const string OMIT = "\0omit";

    /**
     * One raw widget value into the argument the schema asks for: a string stays a string, a
     * number is checked, a dropdown index picks its enum value, a JSON box is decoded.
     *
     * @param list<mixed> $enum
     *
     * @throws InvalidArgumentException
     */
    public static function coerce(string $name, string $type, mixed $raw, bool $required, array $enum = []): mixed
    {
        if ($enum !== []) {
            return $enum[(int) $raw] ?? throw new InvalidArgumentException("[{$name}] has no option {$raw}.");
        }

        if ($type === 'boolean') {
            return (bool) $raw;
        }

        $text = trim((string) $raw);

        if ($text === '') {
            if ($required) {
                throw new InvalidArgumentException("[{$name}] is required.");
            }

            return self::OMIT;
        }

        return match ($type) {
            'integer' => preg_match('/^-?\d+$/', $text) ? (int) $text : throw new InvalidArgumentException("[{$name}] must be an integer."),
            'number' => is_numeric($text) ? $text + 0 : throw new InvalidArgumentException("[{$name}] must be a number."),
            'array' => array_is_list($decoded = Json::decode($text, $name)) ? $decoded : throw new InvalidArgumentException("[{$name}] must be a JSON array."),
            'object' => is_array($decoded = Json::decode($text, $name)) && ! array_is_list($decoded) || $decoded === [] ? (object) $decoded : throw new InvalidArgumentException("[{$name}] must be a JSON object."),
            default => $text,
        };
    }

    private function rebuild(): void
    {
        $this->column?->remove();
        $this->fields = [];
        $this->column = $this->host->column('args-'.(++$this->generation), 6);
    }

    private function field(string $name, array $property, bool $required): void
    {
        $type = $property['type'] ?? (isset($property['enum']) ? 'string' : 'string');
        $type = is_array($type) ? (array_values(array_filter($type, fn ($t) => $t !== 'null'))[0] ?? 'string') : (string) $type;
        $enum = array_values((array) ($property['enum'] ?? []));
        $description = isset($property['description']) ? (string) $property['description'] : '';
        $default = $property['default'] ?? null;

        $row = $this->column->row("field-{$name}", 8);
        $row->label("label-{$name}", $name.($required ? ' *' : ''))->minSize(160, 0)->setFont(new FontSpec(13, monospace: true));

        if ($enum !== []) {
            $index = array_search($default, $enum, true);
            $widget = $row->dropdown("input-{$name}", array_map(fn ($o) => is_scalar($o) ? (string) $o : json_encode($o), $enum), $index === false ? 0 : $index);
        } elseif ($type === 'boolean') {
            $widget = $row->checkbox("input-{$name}", $description, (bool) $default);
        } elseif ($type === 'array' || $type === 'object') {
            $widget = $row->textArea("input-{$name}", is_null($default) ? '' : Json::pretty($default));
            $widget->setFont(new FontSpec(12, monospace: true));
            $widget->minSize(420, 60);
            $widget->fill(true, false);
            $description = trim($description.' (JSON)');
        } else {
            $widget = $row->textInput("input-{$name}", is_null($default) ? '' : (is_scalar($default) ? (string) $default : json_encode($default)), $description !== '' ? $description : $type);
            $widget->minSize(420, 0);
            $widget->fill(true, false);
        }

        if ($description !== '' && ! $widget instanceof TKCheckbox) {
            $row->label("about-{$name}", $description)->setTextColor(Color::hex('#8a8a8a'));
        }

        $this->fields[$name] = ['type' => $type, 'required' => $required, 'widget' => $widget, 'enum' => $enum];
    }
}
