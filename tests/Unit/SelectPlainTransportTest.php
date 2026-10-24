<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;

it('keeps stock text transport equivalent to the extensible renderer including disabled callbacks and grouped identities', function (): void {
    $options = [0 => 'Zero', '01' => 'Leading zero', 'group' => ['nested' => [7 => 'Seven', 'dup' => 'First']],
        'other' => ['dup' => 'Second', 'html' => '<b>Text, not rich HTML</b>'], 'empty' => []];
    $fastCalls = [];
    $fullCalls = [];
    $fast = SelectField::make('value')->disableOptionWhen(function ($value, $label) use (&$fastCalls): bool {
        $fastCalls[] = [$value, $label];

        return $value === 7;
    });
    $full = (new class('value') extends SelectField {})->disableOptionWhen(function ($value, $label) use (&$fullCalls): bool {
        $fullCalls[] = [$value, $label];

        return $value === 7;
    });
    expect($fast->transformRichOptionsForJs($options))->toBe($full->transformRichOptionsForJs($options))
        ->and($fastCalls)->toBe($fullCalls)->toHaveCount(6);
});

it('retains custom label output and invalid option-tree rejection', function (): void {
    $field = SelectField::make('value')->optionView(fn (array $option, string $layout): string => '<i>'.$layout.':'.$option['label'].'</i>');
    $result = $field->transformRichOptionsForJs(['a' => 'Alpha']);
    expect($result[0]['label'])->toContain('list:Alpha')
        ->and($result[0]['triggerLabel'])->toContain('trigger:Alpha');
    $plain = SelectField::make('value');
    expect(fn () => $plain->transformRichOptionsForJs(['a' => null]))->toThrow(InvalidArgumentException::class);
    $deep = ['a' => 'Alpha'];
    for ($depth = 0; $depth < 34; $depth++) {
        $deep = ['group' => $deep];
    }
    expect(fn () => $plain->transformRichOptionsForJs($deep))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $plain->transformRichOptionsForJs(array_fill(0, 100001, 'Text')))->toThrow(InvalidArgumentException::class);
});
