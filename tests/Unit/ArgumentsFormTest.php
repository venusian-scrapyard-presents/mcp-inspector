<?php

use App\NutsAndBolts\Json;
use App\Screens\ArgumentsForm;

it('turns each field into the argument its schema type asks for', function () {
    expect(ArgumentsForm::coerce('q', 'string', ' hello ', true))->toBe('hello')
        ->and(ArgumentsForm::coerce('n', 'integer', '42', true))->toBe(42)
        ->and(ArgumentsForm::coerce('r', 'number', '2.5', true))->toBe(2.5)
        ->and(ArgumentsForm::coerce('b', 'boolean', true, false))->toBeTrue()
        ->and(ArgumentsForm::coerce('e', 'string', 1, true, ['low', 'high']))->toBe('high')
        ->and(ArgumentsForm::coerce('e', 'integer', 0, true, [10, 20]))->toBe(10)
        ->and(ArgumentsForm::coerce('tags', 'array', '["a", "b"]', true))->toBe(['a', 'b'])
        ->and(ArgumentsForm::coerce('opts', 'object', '{"k": 1}', true))->toEqual((object) ['k' => 1])
        ->and(ArgumentsForm::coerce('opts', 'object', '{}', true))->toEqual(new stdClass());
});

it('leaves an empty optional field out and names an empty required one', function () {
    expect(ArgumentsForm::coerce('q', 'string', '', false))->toBe(ArgumentsForm::OMIT)
        ->and(ArgumentsForm::coerce('n', 'integer', '  ', false))->toBe(ArgumentsForm::OMIT)
        ->and(fn () => ArgumentsForm::coerce('q', 'string', '', true))->toThrow(InvalidArgumentException::class, '[q] is required');
});

it('names the field whose text does not fit its type', function () {
    expect(fn () => ArgumentsForm::coerce('n', 'integer', '4.2', true))->toThrow(InvalidArgumentException::class, '[n] must be an integer')
        ->and(fn () => ArgumentsForm::coerce('r', 'number', 'x', true))->toThrow(InvalidArgumentException::class, '[r] must be a number')
        ->and(fn () => ArgumentsForm::coerce('tags', 'array', '{"a": 1}', true))->toThrow(InvalidArgumentException::class, '[tags] must be a JSON array')
        ->and(fn () => ArgumentsForm::coerce('opts', 'object', '[1]', true))->toThrow(InvalidArgumentException::class, '[opts] must be a JSON object')
        ->and(fn () => ArgumentsForm::coerce('opts', 'object', '{nope', true))->toThrow(InvalidArgumentException::class, '[opts] is not valid JSON');
});

it('reads the curly quotes macOS types into a text view as straight ones', function () {
    expect(Json::decode('{“input”: “shit”}'))->toBe(['input' => 'shit'])
        ->and(Json::object(''))->toBe([])
        ->and(fn () => Json::object('[1, 2]'))->toThrow(InvalidArgumentException::class, 'object');
});
