<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Actions;

use Bjanczak\FilamentFlexFields\Filament\Actions\Concerns\CanRequireHoldConfirm;
use Bjanczak\FilamentFlexFields\Filament\Actions\Concerns\CanRoundAction;
use Bjanczak\FilamentFlexFields\Filament\Actions\Concerns\CanStyleItemCardAction;
use Bjanczak\FilamentFlexFields\Support\Confirmation\AuditReasonSchema;
use Closure;
use Filament\Actions\Action as BaseAction;
use Filament\Schemas\Schema;

class Action extends BaseAction
{
    use CanRequireHoldConfirm;
    use CanRoundAction;
    use CanStyleItemCardAction;

    public function hasLivewireTarget(): bool
    {
        return $this->livewireTarget !== null;
    }

    protected function toButtonHtml(): string
    {
        $html = parent::toButtonHtml();

        if ($this->getConfirmationVariant() === 'hold') {
            return $this->wrapHoldConfirmButtonHtml($html);
        }

        return $html;
    }

    public function getActionFunction(): ?Closure
    {
        return $this->resolveConfirmationCallback(parent::getActionFunction());
    }

    public function callAfter(): mixed
    {
        return $this->confirmationReceipt(parent::callAfter());
    }

    public function callBefore(): mixed
    {
        if ($this->hasHoldConfirm()) {
            $this->getActionFunction();
        }

        return parent::callBefore();
    }

    public function getSchema(Schema $schema): ?Schema
    {
        $configured = parent::getSchema($schema);
        if (! $this->hasHoldConfirm() || ! $this->isHoldConfirmAuditReasonRequired()) {
            return $configured;
        }
        if ($this->isSchemaDisabled()) {
            throw new \LogicException('The required audit reason cannot use a disabled action schema.');
        }

        return AuditReasonSchema::append($configured ?? $schema);
    }
}
