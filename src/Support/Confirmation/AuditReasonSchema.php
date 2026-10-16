<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Confirmation;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use LogicException;

final class AuditReasonSchema
{
    public const string FIELD = '_fff_confirmation_reason';

    public static function append(Schema $schema): Schema
    {
        $components = $schema->getComponents(withHidden: true);
        foreach ($schema->getFlatComponents(withHidden: true) as $component) {
            if ($component instanceof Field && $component->getName() === self::FIELD) {
                throw new LogicException('The audit reason field name is reserved for FLEX confirmation.');
            }
        }
        $reason = Textarea::make(self::FIELD)
            ->label(fn () => ConfirmationOptions::text(__('filament-flex-fields::confirmation.reason'), 'Reason label'))
            ->required()->rules(['string', 'regex:/\\S/u'])->minLength(3)->maxLength(2000)->rows(3);

        return $schema->components([...$components, $reason]);
    }
}
