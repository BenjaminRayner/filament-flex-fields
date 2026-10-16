<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\AddressAutocomplete;

use Bjanczak\FilamentFlexFields\Support\Select\Data\IdentifierList;
use Closure;
use InvalidArgumentException;

/** Manual correction remains structured; changing address text clears stale coordinates. */
trait EditsAddressDetails
{
    protected bool|Closure $addressDetailsEditable = false;

    /** @var list<string>|Closure */
    protected array|Closure $addressEditableFields = ['street', 'city', 'region', 'postcode', 'country'];

    public function editableAddress(bool|Closure $condition = true): static
    {
        $this->addressDetailsEditable = $condition;

        return $this;
    }

    /** @param list<string>|Closure $fields */
    public function editableFields(array|Closure $fields): static
    {
        $this->addressEditableFields = $fields;

        return $this;
    }

    public function isAddressEditable(): bool
    {
        return (bool) $this->evaluate($this->addressDetailsEditable);
    }

    /** @return list<string> */
    public function getEditableAddressFields(): array
    {
        $fields = IdentifierList::normalize($this->evaluate($this->addressEditableFields));
        foreach ($fields as $field) {
            if (! in_array($field, ['street', 'city', 'region', 'postcode', 'country'], true)) {
                throw new InvalidArgumentException('Unsupported editable address field.');
            }
        }

        return $fields;
    }
}
